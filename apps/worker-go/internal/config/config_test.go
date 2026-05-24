package config

import (
	"testing"
	"time"
)

func TestLoadUsesDefaults(t *testing.T) {
	t.Setenv("DATABASE_URL", "")
	t.Setenv("RABBITMQ_URL", "")
	t.Setenv("LOG_LEVEL", "")

	cfg := Load()

	if cfg.DatabaseURL == "" {
		t.Fatal("expected default database url")
	}
	if cfg.RabbitMQURL == "" {
		t.Fatal("expected default rabbitmq url")
	}
	if cfg.LogLevel != "info" {
		t.Fatalf("expected info log level, got %q", cfg.LogLevel)
	}
	if cfg.WorkerPollInterval != 5*time.Second {
		t.Fatalf("expected default worker poll interval, got %s", cfg.WorkerPollInterval)
	}
	if cfg.ProvisioningStuckAfter != 5*time.Minute {
		t.Fatalf("expected default provisioning stuck duration, got %s", cfg.ProvisioningStuckAfter)
	}
	if cfg.ProvisioningMaxAttempts != 3 {
		t.Fatalf("expected default max attempts 3, got %d", cfg.ProvisioningMaxAttempts)
	}
	if cfg.ProvisioningRetryBackoff != time.Minute {
		t.Fatalf("expected default retry backoff, got %s", cfg.ProvisioningRetryBackoff)
	}
}

func TestLoadUsesEnvironmentOverrides(t *testing.T) {
	t.Setenv("DATABASE_URL", "postgres://example")
	t.Setenv("RABBITMQ_URL", "amqp://example")
	t.Setenv("LOG_LEVEL", "debug")
	t.Setenv("WORKER_POLL_INTERVAL", "250ms")
	t.Setenv("PROVISIONING_STUCK_AFTER", "2m")
	t.Setenv("PROVISIONING_MAX_ATTEMPTS", "5")
	t.Setenv("PROVISIONING_RETRY_BACKOFF", "15s")

	cfg := Load()

	if cfg.DatabaseURL != "postgres://example" {
		t.Fatalf("unexpected database url %q", cfg.DatabaseURL)
	}
	if cfg.RabbitMQURL != "amqp://example" {
		t.Fatalf("unexpected rabbitmq url %q", cfg.RabbitMQURL)
	}
	if cfg.LogLevel != "debug" {
		t.Fatalf("unexpected log level %q", cfg.LogLevel)
	}
	if cfg.WorkerPollInterval != 250*time.Millisecond {
		t.Fatalf("unexpected worker poll interval %s", cfg.WorkerPollInterval)
	}
	if cfg.ProvisioningStuckAfter != 2*time.Minute {
		t.Fatalf("unexpected stuck duration %s", cfg.ProvisioningStuckAfter)
	}
	if cfg.ProvisioningMaxAttempts != 5 {
		t.Fatalf("unexpected max attempts %d", cfg.ProvisioningMaxAttempts)
	}
	if cfg.ProvisioningRetryBackoff != 15*time.Second {
		t.Fatalf("unexpected retry backoff %s", cfg.ProvisioningRetryBackoff)
	}
}

func TestLoadFallsBackWhenRuntimeConfigIsInvalid(t *testing.T) {
	t.Setenv("WORKER_POLL_INTERVAL", "soon")
	t.Setenv("PROVISIONING_STUCK_AFTER", "later")
	t.Setenv("PROVISIONING_MAX_ATTEMPTS", "0")
	t.Setenv("PROVISIONING_RETRY_BACKOFF", "-5s")

	cfg := Load()

	if cfg.WorkerPollInterval != 5*time.Second {
		t.Fatalf("expected fallback worker poll interval, got %s", cfg.WorkerPollInterval)
	}
	if cfg.ProvisioningStuckAfter != 5*time.Minute {
		t.Fatalf("expected fallback stuck duration, got %s", cfg.ProvisioningStuckAfter)
	}
	if cfg.ProvisioningMaxAttempts != 3 {
		t.Fatalf("expected fallback max attempts, got %d", cfg.ProvisioningMaxAttempts)
	}
	if cfg.ProvisioningRetryBackoff != time.Minute {
		t.Fatalf("expected fallback retry backoff, got %s", cfg.ProvisioningRetryBackoff)
	}
}
