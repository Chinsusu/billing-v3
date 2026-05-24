package provisioning

import (
	"context"
	"errors"
	"time"
)

type OnceExecutor interface {
	ProcessOnce(context.Context) (bool, error)
}

type RecoverFunc func(context.Context) error
type SleepFunc func(context.Context, time.Duration) error

type Daemon struct {
	executor     OnceExecutor
	recover      RecoverFunc
	pollInterval time.Duration
	sleep        SleepFunc
}

func NewDaemon(executor OnceExecutor, recover RecoverFunc, pollInterval time.Duration) Daemon {
	if recover == nil {
		recover = func(context.Context) error {
			return nil
		}
	}
	if pollInterval <= 0 {
		pollInterval = time.Second
	}

	return Daemon{
		executor:     executor,
		recover:      recover,
		pollInterval: pollInterval,
		sleep:        sleepContext,
	}
}

func (d Daemon) Run(ctx context.Context) error {
	for {
		if err := d.Step(ctx); err != nil {
			if errors.Is(err, context.Canceled) {
				return nil
			}

			return err
		}
	}
}

func (d Daemon) RunCycles(ctx context.Context, cycles int) error {
	for i := 0; i < cycles; i++ {
		if err := d.Step(ctx); err != nil {
			return err
		}
	}

	return nil
}

func (d Daemon) Step(ctx context.Context) error {
	if err := ctx.Err(); err != nil {
		return err
	}
	if err := d.recover(ctx); err != nil {
		return err
	}

	processed, err := d.executor.ProcessOnce(ctx)
	if err != nil {
		return err
	}
	if processed {
		return nil
	}

	return d.sleep(ctx, d.pollInterval)
}

func sleepContext(ctx context.Context, duration time.Duration) error {
	timer := time.NewTimer(duration)
	defer timer.Stop()

	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-timer.C:
		return nil
	}
}
