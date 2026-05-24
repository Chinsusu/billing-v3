# Sprint 13 Provider Action Queue Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a durable Laravel provider action queue with retry, stuck recovery, admin visibility, and async suspend/cancel/sync processing.

**Architecture:** Laravel owns provider action jobs because encrypted provider secrets stay in Laravel. `ProviderActionJobDispatcher` creates idempotent jobs, `ProviderActionJobProcessor` claims and executes jobs through the existing `ProviderServiceActionService`, and admin controllers expose list/retry/enqueue actions. Renew remains synchronous and outside the queue.

**Tech Stack:** Laravel 13, PostgreSQL migrations, Eloquent models, Laravel console commands, Blade admin views, Laravel HTTP client fakes, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/plans/2026-05-24-sprint-13-provider-action-queue-recovery.md`

- [x] Save this S13 implementation plan.
- [x] Scan the plan for placeholders, contradictions, and vague scope.
- [x] Commit the plan before code changes.

### Task 2: RED Tests For Provider Action Job Schema And Dispatcher

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProviderActionJobQueueTest.php`

- [x] Add test that dispatching `suspend` creates a `provider_action_jobs` row with service, user, provider account, action, status `pending`, attempts `0`, max attempts `3`, idempotency key, and payload context.
- [x] Add test that dispatching the same idempotency key twice returns one job.
- [x] Run targeted Laravel test on `/opt/billing` and verify failure is missing model/table/dispatcher.
- [x] Commit RED queue schema tests.

### Task 3: Implement Provider Action Job Schema And Dispatcher

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_120000_create_provider_action_jobs_table.php`
- Create: `apps/backend-laravel/app/Models/ProviderActionJob.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderActionJobDispatcher.php`
- Modify: `apps/backend-laravel/app/Models/Service.php`
- Modify: `apps/backend-laravel/app/Models/ProvisioningProviderAccount.php`

- [x] Add `provider_action_jobs` migration with UUID primary key, service/user/provider account foreign keys, action/status/attempts/max attempts/idempotency/payload/available/processed/last error columns and indexes.
- [x] Add `ProviderActionJob` model with fillable fields, casts, and relationships.
- [x] Add relationships from service and provider account to action jobs.
- [x] Implement dispatcher with idempotent `enqueue`.
- [x] Run queue schema tests until green.
- [x] Commit provider action job schema slice.

### Task 4: RED Tests For Provider Action Worker Processing

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ProviderActionJobQueueTest.php`

- [ ] Add test that `provider-actions:work --once` processes a pending suspend job, calls provider suspend, marks service `expired`, and marks job `processed`.
- [ ] Add test that a failed provider action requeues the job with attempts incremented, status `pending`, and a future `available_at`.
- [ ] Add test that a failed provider action at max attempts marks the job `failed`.
- [ ] Add test that a pending sync job updates local service status and expiry from provider.
- [ ] Add test that a pending cancel job marks the service `cancelled`.
- [ ] Run targeted test and verify failure is missing processor/command behavior.
- [ ] Commit RED worker processing tests.

### Task 5: Implement Provider Action Processor And Work Command

**Files:**
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderActionJobProcessor.php`
- Create: `apps/backend-laravel/app/Console/Commands/WorkProviderActionJobsCommand.php`

- [ ] Implement processor claim-next behavior with row locking, status `processing`, and attempts increment.
- [ ] Execute job through `ProviderServiceActionService`.
- [ ] Apply success effects: suspend => expired, cancel => cancelled, sync => provider status/expiry.
- [ ] Mark successful jobs processed.
- [ ] Requeue failed jobs with 60-second backoff when attempts remain.
- [ ] Mark failed jobs failed when attempts reach max attempts.
- [ ] Add `provider-actions:work {--once} {--limit=50}` command.
- [ ] Run provider action worker tests until green.
- [ ] Commit provider action worker slice.

### Task 6: RED Tests For Expiry Enqueue And Admin Actions

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ServiceRenewalLifecycleTest.php`
- Create: `apps/backend-laravel/tests/Feature/AdminProviderActionJobTest.php`

- [ ] Update expiry test to expect `services:expire` enqueues provider suspend and leaves provider-backed service active until worker processes it.
- [ ] Add admin test for posting `/admin/services/{service}/sync-provider` creating a sync action job instead of calling provider immediately.
- [ ] Add admin test for posting `/admin/services/{service}/cancel-provider` creating a cancel action job.
- [ ] Add admin test that `/admin/provider-action-jobs` lists jobs.
- [ ] Add admin test that retrying a failed action job requeues it.
- [ ] Run targeted tests and verify failure is missing enqueue/retry/list behavior.
- [ ] Commit RED admin/expiry tests.

### Task 7: Implement Expiry Enqueue And Admin Actions

**Files:**
- Modify: `apps/backend-laravel/app/Console/Commands/ExpireServicesCommand.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceProviderSyncController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceProviderCancelController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProviderActionJobController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProviderActionJobRetryController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/provider-action-jobs/index.blade.php`

- [ ] Change `services:expire` to enqueue suspend jobs for provider-backed services with suspend path.
- [ ] Keep local-only expiry for services without suspend action path.
- [ ] Change admin sync route to enqueue sync jobs.
- [ ] Add admin cancel route to enqueue cancel jobs.
- [ ] Add provider action job index and retry route.
- [ ] Add provider action job link/actions to admin views.
- [ ] Run expiry and admin provider action tests until green.
- [ ] Commit admin enqueue/retry slice.

### Task 8: RED Tests And Implementation For Stuck Recovery

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ProviderActionJobQueueTest.php`
- Create: `apps/backend-laravel/app/Console/Commands/RecoverStuckProviderActionJobsCommand.php`

- [ ] Add test that stale processing jobs below max attempts are requeued by `provider-actions:recover-stuck`.
- [ ] Add test that stale processing jobs at max attempts are marked failed.
- [ ] Implement recovery command with 5-minute default threshold and output counts.
- [ ] Run recovery tests until green.
- [ ] Commit stuck recovery slice.

### Task 9: Full Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-13-provider-action-queue-recovery.md`

- [ ] Update README with S13 provider action queue commands and admin routes.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Recreate backend and run migrations.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing` using containerized `go fmt`, `go vet`, and `go test`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Run `php artisan provider-actions:work --once` against an empty/due queue.
- [ ] Run `php artisan runtime:smoke-provisioning --timeout=30`.
- [ ] Mark plan verification complete, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch.
- [ ] Pull `develop` on `/opt/billing`, recreate backend and worker, run smoke, and verify `/products`, `/admin/services`, `/admin/provider-action-jobs`, backend health, and worker state.
