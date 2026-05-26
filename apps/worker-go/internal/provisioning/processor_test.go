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
		fmt.Fprint(w, `{"status":"processed","external_id":"provider-service-123","config":{"ip":"203.0.113.10","region":"sgp1"},"ordered_at":"2026-05-24T09:00:00Z","expires_at":"2026-06-24T09:00:00Z"}`)
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
	if result.Config["ip"] != "203.0.113.10" || result.Config["region"] != "sgp1" {
		t.Fatalf("unexpected config %#v", result.Config)
	}
	if result.OrderedAt == nil || result.OrderedAt.Format(time.RFC3339) != "2026-05-24T09:00:00Z" {
		t.Fatalf("unexpected ordered_at %#v", result.OrderedAt)
	}
	if result.ExpiresAt == nil || result.ExpiresAt.Format(time.RFC3339) != "2026-06-24T09:00:00Z" {
		t.Fatalf("unexpected expires_at %#v", result.ExpiresAt)
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

func TestInternalExecutorProcessorMapsEmptyArrayConfigToEmptyObject(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		fmt.Fprint(w, `{"status":"processed","external_id":"provider-service-123","config":[]}`)
	}))
	defer server.Close()
	processor := NewInternalExecutorProcessor(server.URL, "internal-token", time.Second)

	result, err := processor.Process(context.Background(), Job{ID: "job-1"})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if len(result.Config) != 0 {
		t.Fatalf("expected empty config, got %#v", result.Config)
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
