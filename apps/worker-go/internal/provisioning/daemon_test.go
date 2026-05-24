package provisioning

import (
	"context"
	"errors"
	"reflect"
	"testing"
	"time"
)

func TestDaemonRunsRecoveryBeforeEachProcessAttempt(t *testing.T) {
	calls := []string{}
	executor := &fakeOnceExecutor{
		results: []processResult{
			{processed: true},
			{processed: false},
		},
		calls: &calls,
	}
	daemon := NewDaemon(executor, func(context.Context) error {
		calls = append(calls, "recover")

		return nil
	}, time.Second)
	daemon.sleep = func(context.Context, time.Duration) error {
		calls = append(calls, "sleep")

		return nil
	}

	err := daemon.RunCycles(context.Background(), 2)

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	want := []string{"recover", "process", "recover", "process", "sleep"}
	if !reflect.DeepEqual(calls, want) {
		t.Fatalf("unexpected call order: got %v want %v", calls, want)
	}
}

func TestDaemonDoesNotSleepWhenJobWasProcessed(t *testing.T) {
	executor := &fakeOnceExecutor{
		results: []processResult{{processed: true}, {processed: true}},
	}
	daemon := NewDaemon(executor, func(context.Context) error {
		return nil
	}, 10*time.Second)
	sleepCalls := 0
	daemon.sleep = func(context.Context, time.Duration) error {
		sleepCalls++

		return nil
	}

	err := daemon.RunCycles(context.Background(), 2)

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if sleepCalls != 0 {
		t.Fatalf("expected no sleep calls, got %d", sleepCalls)
	}
}

func TestDaemonSleepsWhenIdle(t *testing.T) {
	executor := &fakeOnceExecutor{
		results: []processResult{{processed: false}},
	}
	daemon := NewDaemon(executor, func(context.Context) error {
		return nil
	}, 10*time.Second)
	var sleptFor time.Duration
	daemon.sleep = func(_ context.Context, duration time.Duration) error {
		sleptFor = duration

		return nil
	}

	err := daemon.RunCycles(context.Background(), 1)

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if sleptFor != 10*time.Second {
		t.Fatalf("expected sleep duration 10s, got %s", sleptFor)
	}
}

func TestDaemonReturnsRecoveryErrors(t *testing.T) {
	executor := &fakeOnceExecutor{results: []processResult{{processed: true}}}
	daemon := NewDaemon(executor, func(context.Context) error {
		return errors.New("database unavailable")
	}, time.Second)

	err := daemon.RunCycles(context.Background(), 1)

	if err == nil {
		t.Fatal("expected recovery error")
	}
}

type processResult struct {
	processed bool
	err       error
}

type fakeOnceExecutor struct {
	results []processResult
	calls   *[]string
}

func (f *fakeOnceExecutor) ProcessOnce(context.Context) (bool, error) {
	if f.calls != nil {
		*f.calls = append(*f.calls, "process")
	}
	if len(f.results) == 0 {
		return false, nil
	}

	result := f.results[0]
	f.results = f.results[1:]

	return result.processed, result.err
}
