package provisioning

import (
	"context"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestInternalExecutorProcessorSendsTokenAndMapsSuccess(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("expected POST, got %s", r.Method)
		}
		if r.URL.Path != "/internal/provisioning/jobs/job-1/execute" {
			t.Fatalf("unexpected path %s", r.URL.Path)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer internal-token" {
			t.Fatalf("unexpected authorization header %q", got)
		}

		w.Header().Set("Content-Type", "application/json")
		fmt.Fprint(w, `{"status":"processed","external_id":"provider-service-123"}`)
	}))
	defer server.Close()
	processor := NewInternalExecutorProcessor(server.URL, "internal-token", time.Second)

	result, err := processor.Process(context.Background(), Job{ID: "job-1"})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if result.Status != "processed" {
		t.Fatalf("unexpected status %q", result.Status)
	}
	if result.ExternalID != "provider-service-123" {
		t.Fatalf("unexpected external id %q", result.ExternalID)
	}
}

func TestInternalExecutorProcessorReturnsErrorOnNonSuccessStatus(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		http.Error(w, `{"message":"provider timeout"}`, http.StatusUnprocessableEntity)
	}))
	defer server.Close()
	processor := NewInternalExecutorProcessor(server.URL, "internal-token", time.Second)

	_, err := processor.Process(context.Background(), Job{ID: "job-1"})

	if err == nil {
		t.Fatal("expected executor HTTP error")
	}
	if !strings.Contains(err.Error(), "HTTP 422") {
		t.Fatalf("unexpected error %q", err.Error())
	}
}

func TestInternalExecutorProcessorReturnsErrorOnInvalidJSON(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		fmt.Fprint(w, `not-json`)
	}))
	defer server.Close()
	processor := NewInternalExecutorProcessor(server.URL, "internal-token", time.Second)

	_, err := processor.Process(context.Background(), Job{ID: "job-1"})

	if err == nil {
		t.Fatal("expected decode error")
	}
	if !strings.Contains(err.Error(), "decode executor response") {
		t.Fatalf("unexpected error %q", err.Error())
	}
}
