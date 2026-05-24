package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"os"
	"time"

	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/config"
	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/provisioning"
	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/provisioningstore"
	_ "github.com/lib/pq"
)

func main() {
	once := flag.Bool("once", true, "process one provisioning job and exit")
	flag.Parse()

	cfg := config.Load()
	db, err := sql.Open("postgres", cfg.DatabaseURL)
	if err != nil {
		fmt.Fprintf(os.Stderr, "open database: %v\n", err)
		os.Exit(1)
	}
	defer db.Close()

	executor := provisioning.NewExecutor(provisioningstore.NewStore(db), provisioning.Processor{})
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
