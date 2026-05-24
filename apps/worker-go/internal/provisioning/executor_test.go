package provisioning

import (
	"context"
	"errors"
	"testing"
)

func TestExecutorProcessesClaimedJob(t *testing.T) {
	store := &fakeStore{
		job: Job{
			ID:          "job-1",
			Type:        "provision_service",
			OrderID:     "order-1",
			ServiceID:   "service-1",
			ProductCode: "proxy-vn-30d",
			ProductType: "proxy",
			Action:      "provision",
		},
		ok: true,
	}
	executor := NewExecutor(store, Processor{})

	processed, err := executor.ProcessOnce(context.Background())

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if !processed {
		t.Fatal("expected job to be processed")
	}
	if store.succeededExternalID != "sandbox-proxy-service-1" {
		t.Fatalf("unexpected external id %q", store.succeededExternalID)
	}
	if store.failedError != "" {
		t.Fatalf("unexpected failure %q", store.failedError)
	}
}

func TestExecutorMarksJobFailedWhenProcessorFails(t *testing.T) {
	store := &fakeStore{
		job: Job{
			ID:        "job-1",
			Type:      "provision_service",
			OrderID:   "order-1",
			ServiceID: "service-1",
			Action:    "provision",
		},
		ok: true,
	}
	executor := NewExecutor(store, Processor{})

	processed, err := executor.ProcessOnce(context.Background())

	if err == nil {
		t.Fatal("expected processor error")
	}
	if !processed {
		t.Fatal("expected failed job to count as processed attempt")
	}
	if store.failedError == "" {
		t.Fatal("expected failure to be recorded")
	}
}

func TestExecutorReturnsFalseWhenNoJobClaimed(t *testing.T) {
	store := &fakeStore{ok: false}
	executor := NewExecutor(store, Processor{})

	processed, err := executor.ProcessOnce(context.Background())

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if processed {
		t.Fatal("expected no processed job")
	}
}

type fakeStore struct {
	job                 Job
	ok                  bool
	claimErr            error
	succeededExternalID string
	failedError         string
}

func (f *fakeStore) ClaimNext(context.Context) (Job, bool, error) {
	if f.claimErr != nil {
		return Job{}, false, f.claimErr
	}

	return f.job, f.ok, nil
}

func (f *fakeStore) MarkProcessed(_ context.Context, job Job, result Result) error {
	if job.ID == "" {
		return errors.New("missing job")
	}

	f.succeededExternalID = result.ExternalID

	return nil
}

func (f *fakeStore) MarkFailed(_ context.Context, job Job, err error) error {
	if job.ID == "" {
		return errors.New("missing job")
	}

	f.failedError = err.Error()

	return nil
}
