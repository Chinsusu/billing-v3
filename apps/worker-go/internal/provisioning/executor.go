package provisioning

import (
	"context"
	"fmt"
)

type Store interface {
	ClaimNext(context.Context) (Job, bool, error)
	MarkProcessed(context.Context, Job, Result) error
	MarkFailed(context.Context, Job, error) error
}

type Executor struct {
	store     Store
	processor Processor
}

func NewExecutor(store Store, processor Processor) Executor {
	return Executor{store: store, processor: processor}
}

func (e Executor) ProcessOnce(ctx context.Context) (bool, error) {
	job, ok, err := e.store.ClaimNext(ctx)
	if err != nil {
		return false, err
	}
	if !ok {
		return false, nil
	}

	result, err := e.processor.Process(job)
	if err != nil {
		if markErr := e.store.MarkFailed(ctx, job, err); markErr != nil {
			return true, fmt.Errorf("process job: %w; mark failed: %v", err, markErr)
		}

		return true, err
	}

	if err := e.store.MarkProcessed(ctx, job, result); err != nil {
		return true, err
	}

	return true, nil
}
