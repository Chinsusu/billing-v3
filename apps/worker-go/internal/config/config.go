package config

import "os"

type Config struct {
	DatabaseURL string
	RabbitMQURL string
	LogLevel    string
}

func Load() Config {
	return Config{
		DatabaseURL: envOrDefault("DATABASE_URL", "postgres://billing:billing_secret@localhost:5432/billing_v3?sslmode=disable"),
		RabbitMQURL: envOrDefault("RABBITMQ_URL", "amqp://billing:billing_secret@localhost:5672/"),
		LogLevel:    envOrDefault("LOG_LEVEL", "info"),
	}
}

func envOrDefault(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}
	return value
}
