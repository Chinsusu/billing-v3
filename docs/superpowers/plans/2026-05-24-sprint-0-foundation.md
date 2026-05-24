# Sprint 0 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild Billing v3 from a clean `/opt/billing` as a working monorepo foundation with Laravel backend, Go worker, Docker dev infra, and GitHub CI.

**Architecture:** The repo is a monorepo with `apps/backend-laravel` as the control-plane skeleton and `apps/worker-go` as the data-plane worker skeleton. Docker Compose provides local dev dependencies only: PostgreSQL, RabbitMQ, Redis, and Mailpit. CI validates backend install/tests, Go formatting/vet/tests, compose config, and a baseline secret scan.

**Tech Stack:** Laravel latest via Composer Docker image, PHP 8.4 in CI, Go 1.26 in worker/CI, Docker Compose v2, PostgreSQL 16, RabbitMQ 4-management, Redis 7, Mailpit, GitHub Actions.

---

### Task 1: Clean Server Runtime

**Files:** None.

- [ ] Remove old Billing containers, volumes, and networks.
- [ ] Confirm `/opt/billing` is empty.
- [ ] Confirm dev ports `5432`, `5672`, `6379`, `8025`, `15672`, `8080` are not bound by old services.

### Task 2: Scaffold Repository Layout

**Files:**
- Create: `/opt/billing/apps/backend-laravel`
- Create: `/opt/billing/apps/worker-go`
- Create: `/opt/billing/infra/docker-compose.dev.yml`
- Create: `/opt/billing/.github/workflows/ci.yml`
- Create: `/opt/billing/README.md`
- Create: `/opt/billing/Makefile`
- Create: `/opt/billing/.env.example`
- Create: `/opt/billing/.gitignore`

- [ ] Initialize an empty git repository on branch `main`.
- [ ] Create Laravel backend skeleton with `composer create-project` from a Docker Composer image.
- [ ] Create Go worker module skeleton with minimal config and tests.
- [ ] Add root docs and developer commands.

### Task 3: Docker Dev Infra

**Files:**
- Create: `/opt/billing/infra/docker-compose.dev.yml`

- [ ] Define `postgres`, `rabbitmq`, `redis`, and `mailpit` services with stable local ports.
- [ ] Use named volumes prefixed with `billing_v3_`.
- [ ] Add health checks for Postgres and RabbitMQ.
- [ ] Run `docker compose -f infra/docker-compose.dev.yml config`.

### Task 4: Backend Baseline Verification

**Files:**
- Modify: `/opt/billing/apps/backend-laravel/.env.example`
- Modify: `/opt/billing/apps/backend-laravel/phpunit.xml` if required.

- [ ] Run `composer install` inside Docker.
- [ ] Run `php artisan test` inside Docker.
- [ ] Keep backend to framework skeleton behavior only for Sprint 0.

### Task 5: Worker Baseline Verification

**Files:**
- Create: `/opt/billing/apps/worker-go/cmd/worker/main.go`
- Create: `/opt/billing/apps/worker-go/internal/config/config.go`
- Create: `/opt/billing/apps/worker-go/internal/config/config_test.go`

- [ ] Implement env config parsing for `DATABASE_URL`, `RABBITMQ_URL`, and `LOG_LEVEL`.
- [ ] Test defaults and env override behavior.
- [ ] Run `gofmt`, `go vet ./...`, and `go test ./...` inside Docker.

### Task 6: CI/CD Baseline

**Files:**
- Create: `/opt/billing/.github/workflows/ci.yml`

- [ ] Add GitHub Actions workflow for pushes and PRs to `main` and `develop`.
- [ ] Backend job uses PHP 8.4 and runs Composer install plus Laravel tests.
- [ ] Worker job uses Go 1.26 and runs gofmt/vet/test.
- [ ] Infra job validates Docker Compose config.
- [ ] Secret scan job excludes documented placeholders in `.env.example`.

### Task 7: Commit And Publish

**Files:** All Sprint 0 files.

- [ ] Commit with `chore(repo): rebuild sprint 0 foundation`.
- [ ] Fetch the server commit into the local authenticated repo.
- [ ] Force-reset GitHub `main` to the new foundation because the user requested a clean rebuild.
- [ ] Create/update `develop` to match `main`.
- [ ] Re-check GitHub Actions status.