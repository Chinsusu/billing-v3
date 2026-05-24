# Sprint 5 Service Renewal Expiry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add wallet-backed service renewals and explicit expiry automation for active services.

**Architecture:** Laravel remains the service lifecycle control plane. A focused `ServiceRenewalService` owns renewal pricing, wallet debit, idempotency, and expiry extension inside one transaction. A CLI command expires overdue active services; no Go worker changes are needed in Sprint 5.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit feature tests, existing wallet ledger and service models.

---

### Task 1: S5 Documentation

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-5-service-renewal-expiry-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-5-service-renewal-expiry.md`

- [x] Save the approved S5 design.
- [x] Save this implementation plan.
- [x] Scan spec and plan for placeholders and contradictions.
- [x] Commit documentation before code.

### Task 2: Laravel RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceRenewalLifecycleTest.php`

- [x] Add a test for customer renewal extending expiry and debiting wallet.

Expected test shape:

```php
public function test_customer_can_renew_active_service_from_wallet(): void
{
    $customer = $this->customerUser();
    Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
    $service = $this->serviceFor($customer, [
        'status' => 'active',
        'expires_at' => now()->addDays(10),
        'meta' => ['duration_days' => 30],
    ]);

    $this->actingAs($customer)
        ->from("/services/{$service->id}")
        ->post("/services/{$service->id}/renew")
        ->assertRedirect("/services/{$service->id}");

    $service->refresh();
    $this->assertSame('active', $service->status);
    $this->assertSame(101000, Wallet::firstOrFail()->balance_amount);
    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $customer->id,
        'direction' => 'debit',
        'amount' => 99000,
        'source_type' => 'service_renewal',
        'source_id' => $service->id,
    ]);
    $this->assertCount(1, $service->meta['renewals']);
}
```

- [x] Add a test that renewal uses current product price when linked product exists.
- [x] Add a test that renewal falls back to order item snapshot price when the product link is missing.
- [x] Add a test that insufficient wallet balance leaves wallet and expiry unchanged.
- [x] Add a test that another customer cannot renew the service.
- [x] Add a test that expired services cannot be renewed.
- [x] Add a test that `services:expire` marks overdue active services expired and leaves future active services unchanged.
- [x] Run the new test file on the dev server and verify it fails on missing route, service, and command.

Run:

```bash
docker run --rm --entrypoint sh -v /opt/billing/apps/backend-laravel:/app -w /app composer:2 -lc 'composer install --no-interaction --prefer-dist >/tmp/composer-install.log && php artisan test tests/Feature/ServiceRenewalLifecycleTest.php'
```

Expected RED result: tests fail because `POST /services/{service}/renew` and `services:expire` do not exist.

### Task 3: Renewal Implementation

**Files:**
- Create: `apps/backend-laravel/app/Services/Services/ServiceRenewalService.php`
- Create: `apps/backend-laravel/app/Http/Controllers/ServiceRenewalController.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [x] Implement `ServiceRenewalService`.

Core implementation:

```php
namespace App\Services\Services;

use App\Models\Service;
use App\Models\User;
use App\Services\Finance\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRenewalService
{
    public function __construct(private readonly WalletService $walletService) {}

    public function renew(Service $service, User $user): Service
    {
        return DB::transaction(function () use ($service, $user): Service {
            $lockedService = Service::with(['product', 'orderItem'])
                ->whereKey($service->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedService->user_id !== $user->id) {
                abort(404);
            }

            if ($lockedService->status !== 'active') {
                throw ValidationException::withMessages(['service' => 'Only active services can be renewed.']);
            }

            if ($lockedService->expires_at === null) {
                throw ValidationException::withMessages(['service' => 'Service expiry is missing.']);
            }

            $durationDays = (int) ($lockedService->meta['duration_days'] ?? $lockedService->orderItem?->duration_days ?? 0);
            if ($durationDays <= 0) {
                throw ValidationException::withMessages(['service' => 'Service duration is missing.']);
            }

            $amount = (int) ($lockedService->product?->price_amount ?? $lockedService->orderItem?->unit_amount ?? 0);
            $currency = (string) ($lockedService->product?->currency ?? $lockedService->orderItem?->currency ?? 'VND');
            if ($amount <= 0) {
                throw ValidationException::withMessages(['service' => 'Service renewal price is missing.']);
            }

            $oldExpiresAt = $lockedService->expires_at->copy();
            $base = $oldExpiresAt->greaterThan(now()) ? $oldExpiresAt : now();
            $newExpiresAt = $base->copy()->addDays($durationDays);
            $wallet = $this->walletService->walletFor($user, $currency);

            $this->walletService->debit(
                $wallet,
                $amount,
                $currency,
                'service_renewal',
                $lockedService->id,
                "service-renewal:{$lockedService->id}:{$oldExpiresAt->toISOString()}",
                "Renew {$lockedService->product_name}",
                ['old_expires_at' => $oldExpiresAt->toISOString(), 'new_expires_at' => $newExpiresAt->toISOString()]
            );

            $meta = $lockedService->meta ?? [];
            $meta['renewals'] = $meta['renewals'] ?? [];
            $meta['renewals'][] = [
                'renewed_at' => Carbon::now()->toISOString(),
                'old_expires_at' => $oldExpiresAt->toISOString(),
                'new_expires_at' => $newExpiresAt->toISOString(),
                'amount' => $amount,
                'currency' => strtoupper($currency),
            ];

            $lockedService->forceFill(['expires_at' => $newExpiresAt, 'meta' => $meta])->save();

            return $lockedService->refresh();
        });
    }
}
```

- [x] Implement `ServiceRenewalController`.

```php
namespace App\Http\Controllers;

use App\Exceptions\InsufficientWalletBalance;
use App\Models\Service;
use App\Services\Services\ServiceRenewalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceRenewalController extends Controller
{
    public function __invoke(Request $request, Service $service, ServiceRenewalService $renewalService): RedirectResponse
    {
        try {
            $renewalService->renew($service, $request->user());
        } catch (InsufficientWalletBalance $exception) {
            return redirect("/services/{$service->id}")->withErrors(['wallet' => $exception->getMessage()]);
        }

        return redirect("/services/{$service->id}")->with('status', 'Service renewed.');
    }
}
```

- [x] Add the authenticated route.

```php
Route::post('/services/{service}/renew', ServiceRenewalController::class)->name('services.renew');
```

- [x] Run `php artisan test tests/Feature/ServiceRenewalLifecycleTest.php` and fix only renewal-related failures.

### Task 4: Expiry Command

**Files:**
- Create: `apps/backend-laravel/app/Console/Commands/ExpireServicesCommand.php`
- Modify if needed: `apps/backend-laravel/bootstrap/app.php`

- [x] Implement `services:expire`.

Core implementation:

```php
namespace App\Console\Commands;

use App\Models\Service;
use Illuminate\Console\Command;

class ExpireServicesCommand extends Command
{
    protected $signature = 'services:expire';
    protected $description = 'Mark overdue active services as expired.';

    public function handle(): int
    {
        $count = 0;

        Service::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($services) use (&$count): void {
                foreach ($services as $service) {
                    $meta = $service->meta ?? [];
                    $meta['expired_at'] = now()->toISOString();

                    $service->forceFill(['status' => 'expired', 'meta' => $meta])->save();
                    $count++;
                }
            });

        $this->info("Expired {$count} services.");

        return self::SUCCESS;
    }
}
```

- [x] Run the command test and verify overdue services expire while future services stay active.

### Task 5: Blade And Documentation

**Files:**
- Modify: `apps/backend-laravel/resources/views/services/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/index.blade.php`
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-5-service-renewal-expiry.md`

- [x] Add a renew form to customer service detail for active services.

Expected Blade fragment:

```blade
@if ($service->status === 'active')
    <form method="POST" action="/services/{{ $service->id }}/renew" style="margin-top:16px">
        @csrf
        <button type="submit">Renew</button>
    </form>
@endif
```

- [x] Ensure admin service list already displays `status` and `expires_at`; add no extra UI unless tests require it.
- [x] Document S5 routes and command in `README.md`.
- [x] Mark the plan checklist complete after verification.

### Task 6: Final Verification And Publish

**Files:** All S5 files.

- [x] Run Laravel Pint and full tests on the dev server.

```bash
docker run --rm --entrypoint sh -v /opt/billing/apps/backend-laravel:/app -w /app composer:2 -lc 'composer install --no-interaction --prefer-dist >/tmp/composer-install.log && ./vendor/bin/pint --test && php artisan test'
```

- [x] Run Go checks to verify no unrelated worker regression.

```bash
docker run --rm -v /opt/billing/apps/worker-go:/app -w /app golang:1.26.3 gofmt -l .
docker run --rm -v /opt/billing/apps/worker-go:/app -w /app golang:1.26.3 go vet ./...
docker run --rm -v /opt/billing/apps/worker-go:/app -w /app golang:1.26.3 go test ./...
```

- [x] Run Docker compose config and secret scan.
- [x] Commit implementation.
- [x] Push `feature/sprint-5-service-renewal-expiry`.
- [ ] Open PR to `develop`.
- [ ] Wait for GitHub CI.
- [ ] Merge after CI passes.
- [ ] Update `/opt/billing` on the dev server, run migrations, seed, and recreate backend container.
