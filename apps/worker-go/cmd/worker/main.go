package main

import (
	"fmt"
	"os"

	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/config"
)

func main() {
	cfg := config.Load()
	fmt.Fprintf(os.Stdout, "billing worker ready log_level=%s\n", cfg.LogLevel)
}
