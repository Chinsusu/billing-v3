package config

import "testing"

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
}

func TestLoadUsesEnvironmentOverrides(t *testing.T) {
	t.Setenv("DATABASE_URL", "postgres://example")
	t.Setenv("RABBITMQ_URL", "amqp://example")
	t.Setenv("LOG_LEVEL", "debug")

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
}
