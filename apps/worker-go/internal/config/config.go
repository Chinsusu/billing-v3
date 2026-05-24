package config

import (
	"os"
	"strconv"
	"time"
)

const (
	defaultWorkerPollInterval       = 5 * time.Second
	defaultProvisioningStuckAfter   = 5 * time.Minute
	defaultProvisioningMaxAttempts  = 3
	defaultProvisioningRetryBackoff = time.Minute
)

type Config struct {
	DatabaseURL              string
	RabbitMQURL              string
	LogLevel                 string
	WorkerPollInterval       time.Duration
	ProvisioningStuckAfter   time.Duration
	ProvisioningMaxAttempts  int
	ProvisioningRetryBackoff time.Duration
}

func Load() Config {
	return Config{
		DatabaseURL:              envOrDefault("DATABASE_URL", "postgres://billing:billing_secret@localhost:5432/billing_v3?sslmode=disable"),
		RabbitMQURL:              envOrDefault("RABBITMQ_URL", "amqp://billing:billing_secret@localhost:5672/"),
		LogLevel:                 envOrDefault("LOG_LEVEL", "info"),
		WorkerPollInterval:       durationEnvOrDefault("WORKER_POLL_INTERVAL", defaultWorkerPollInterval),
		ProvisioningStuckAfter:   durationEnvOrDefault("PROVISIONING_STUCK_AFTER", defaultProvisioningStuckAfter),
		ProvisioningMaxAttempts:  intEnvOrDefault("PROVISIONING_MAX_ATTEMPTS", defaultProvisioningMaxAttempts),
		ProvisioningRetryBackoff: durationEnvOrDefault("PROVISIONING_RETRY_BACKOFF", defaultProvisioningRetryBackoff),
	}
}

func envOrDefault(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}
	return value
}

func durationEnvOrDefault(key string, fallback time.Duration) time.Duration {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	duration, err := time.ParseDuration(value)
	if err != nil || duration <= 0 {
		return fallback
	}

	return duration
}

func intEnvOrDefault(key string, fallback int) int {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	parsed, err := strconv.Atoi(value)
	if err != nil || parsed <= 0 {
		return fallback
	}

	return parsed
}
