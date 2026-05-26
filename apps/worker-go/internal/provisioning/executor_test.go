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
	processor := &fakeProcessor{result: Result{Status: "processed", ExternalID: "provider-service-123"}}
	executor := NewExecutor(store, processor)

	processed, err := executor.ProcessOnce(context.Background())

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if !processed {
		t.Fatal("expected job to be processed")
	}
	if processor.processedJobID != "job-1" {
		t.Fatalf("unexpected processed job %q", processor.processedJobID)
	}
	if store.succeededExternalID != "provider-service-123" {
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
	executor := NewExecutor(store, &fakeProcessor{err: errors.New("provider timeout")})

	processed, err := executor.ProcessOnce(context.Background())

	if err != nil {
		t.Fatalf("expected processor error to be handled, got %v", err)
	}
	if !processed {
		t.Fatal("expected failed job to count as processed attempt")
	}
	if store.failedError == "" {
		t.Fatal("expected failure to be recorded")
	}
}

func TestExecutorReturnsErrorWhenFailureStateCannotBeRecorded(t *testing.T) {
	store := &fakeStore{
		job: Job{
			ID:        "job-1",
			Type:      "provision_service",
			OrderID:   "order-1",
			ServiceID: "service-1",
			Action:    "provision",
		},
		ok:            true,
		markFailedErr: errors.New("database unavailable"),
	}
	executor := NewExecutor(store, &fakeProcessor{err: errors.New("provider timeout")})

	processed, err := executor.ProcessOnce(context.Background())

	if err == nil {
		t.Fatal("expected failure state error")
	}
	if !processed {
		t.Fatal("expected attempted job to count as processed")
	}
}

func TestExecutorReturnsFalseWhenNoJobClaimed(t *testing.T) {
	store := &fakeStore{ok: false}
	executor := NewExecutor(store, &fakeProcessor{})

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
	markFailedErr       error
	succeededExternalID string
	failedError         string
}

type fakeProcessor struct {
	result         Result
	err            error
	processedJobID string
}

func (f *fakeProcessor) Process(_ context.Context, job Job) (Result, error) {
	f.processedJobID = job.ID
	if f.err != nil {
		return Result{}, f.err
	}

	return f.result, nil
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
	if f.markFailedErr != nil {
		return f.markFailedErr
	}

	f.failedError = err.Error()

	return nil
}
