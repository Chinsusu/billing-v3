.PHONY: up down ps logs backend-test worker-test infra-check ci

COMPOSE=docker compose -f infra/docker-compose.dev.yml

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f --tail=150

backend-test:
	docker run --rm -v "$$(pwd)/apps/backend-laravel:/app" -w /app composer:2 composer install --no-interaction --prefer-dist
	docker run --rm -v "$$(pwd)/apps/backend-laravel:/app" -w /app composer:2 php artisan test

worker-test:
	docker run --rm -v "$$(pwd)/apps/worker-go:/app" -w /app golang:1.26.3 gofmt -w .
	docker run --rm -v "$$(pwd)/apps/worker-go:/app" -w /app golang:1.26.3 go vet ./...
	docker run --rm -v "$$(pwd)/apps/worker-go:/app" -w /app golang:1.26.3 go test ./...

infra-check:
	$(COMPOSE) config

ci: infra-check backend-test worker-test
