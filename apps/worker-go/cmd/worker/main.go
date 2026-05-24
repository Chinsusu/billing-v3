package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/config"
	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/provisioning"
	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/provisioningstore"
	_ "github.com/lib/pq"
)

func main() {
	once := flag.Bool("once", true, "process one provisioning job and exit")
	daemonMode := flag.Bool("daemon", false, "run provisioning worker continuously")
	flag.Parse()

	cfg := config.Load()
	db, err := sql.Open("postgres", cfg.DatabaseURL)
	if err != nil {
		fmt.Fprintf(os.Stderr, "open database: %v\n", err)
		os.Exit(1)
	}
	defer db.Close()

	store := provisioningstore.NewStoreWithPolicy(db, provisioningstore.RetryPolicy{
		MaxAttempts:  cfg.ProvisioningMaxAttempts,
		RetryBackoff: cfg.ProvisioningRetryBackoff,
	})
	executor := provisioning.NewExecutor(store, provisioning.Processor{})
	if *daemonMode {
		ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
		defer stop()

		daemon := provisioning.NewDaemon(executor, func(ctx context.Context) error {
			result, err := store.RecoverStuck(ctx, cfg.ProvisioningStuckAfter)
			if err != nil {
				return err
			}
			if result.Requeued > 0 || result.Failed > 0 {
				fmt.Fprintf(os.Stdout, "billing worker recovered provisioning_jobs requeued=%d failed=%d\n", result.Requeued, result.Failed)
			}

			return nil
		}, cfg.WorkerPollInterval)

		fmt.Fprintf(os.Stdout, "billing worker ready log_level=%s mode=daemon poll_interval=%s stuck_after=%s max_attempts=%d retry_backoff=%s\n", cfg.LogLevel, cfg.WorkerPollInterval, cfg.ProvisioningStuckAfter, cfg.ProvisioningMaxAttempts, cfg.ProvisioningRetryBackoff)
		if err := daemon.Run(ctx); err != nil {
			fmt.Fprintf(os.Stderr, "run provisioning daemon: %v\n", err)
			os.Exit(1)
		}

		return
	}

	for {
		processed, err := executor.ProcessOnce(context.Background())
		if err != nil {
			fmt.Fprintf(os.Stderr, "process provisioning job: %v\n", err)
			os.Exit(1)
		}

		fmt.Fprintf(os.Stdout, "billing worker ready log_level=%s mode=provisioning-sandbox processed=%t\n", cfg.LogLevel, processed)
		if *once || !processed {
			return
		}

		time.Sleep(time.Second)
	}
}
