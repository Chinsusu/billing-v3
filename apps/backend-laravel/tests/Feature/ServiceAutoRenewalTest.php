<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Services\ServiceAutoRenewalProcessor;
use App\Services\Services\ServiceRenewalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ServiceAutoRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_enable_and_disable_auto_renewal_for_own_active_service(): void
    {
        $customer = $this->customerUser('auto-toggle@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => false,
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/auto-renew", ['enabled' => '1'])
            ->assertRedirect("/services/{$service->id}");

        $this->assertTrue($service->refresh()->auto_renew_enabled);
        $this->actingAs($customer)
            ->get("/services/{$service->id}")
            ->assertOk()
            ->assertSee('Auto-renew')
            ->assertSee('Enabled');

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/auto-renew", ['enabled' => '0'])
            ->assertRedirect("/services/{$service->id}");

        $this->assertFalse($service->refresh()->auto_renew_enabled);
    }

    public function test_customer_cannot_toggle_another_users_service(): void
    {
        $owner = $this->customerUser('auto-owner@example.test');
        $other = $this->customerUser('auto-other@example.test');
        $service = $this->serviceFor($owner, [
            'status' => 'active',
            'auto_renew_enabled' => false,
        ]);

        $this->actingAs($other)
            ->post("/services/{$service->id}/auto-renew", ['enabled' => '1'])
            ->assertNotFound();

        $this->assertFalse($service->refresh()->auto_renew_enabled);
    }

    public function test_auto_renew_command_renews_due_enabled_services_once(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-success@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $due = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(12),
        ]);
        $notEnabled = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => false,
            'expires_at' => $now->copy()->addHours(12),
        ], ['code' => 'auto-not-enabled']);
        $future = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addDays(3),
        ], ['code' => 'auto-future']);

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=1 succeeded=1 failed=0 skipped=0.')
            ->assertExitCode(0);

        $this->assertTrue($due->refresh()->expires_at->isSameSecond($now->copy()->addDays(30)->addHours(12)));
        $this->assertTrue($notEnabled->refresh()->expires_at->isSameSecond($now->copy()->addHours(12)));
        $this->assertTrue($future->refresh()->expires_at->isSameSecond($now->copy()->addDays(3)));
        $this->assertSame(151000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'service_renewal',
            'source_id' => $due->id,
            'amount' => 99000,
        ]);
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $due->id,
            'user_id' => $customer->id,
            'status' => 'succeeded',
            'attempts' => 1,
            'amount' => 99000,
            'currency' => 'VND',
        ]);
        $this->assertDatabaseMissing('service_auto_renewal_attempts', [
            'service_id' => $notEnabled->id,
        ]);

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=0 succeeded=0 failed=0 skipped=0.')
            ->assertExitCode(0);
        $this->assertSame(1, DB::table('service_auto_renewal_attempts')->where('service_id', $due->id)->count());
    }

    public function test_failed_auto_renewal_records_retry_and_does_not_duplicate_notification_before_retry_time(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-failed@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(6),
        ]);
        $oldExpiresAt = $service->expires_at->copy();

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=1 succeeded=0 failed=1 skipped=0.')
            ->assertExitCode(0);

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($oldExpiresAt));
        $this->assertSame(1000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $service->id,
            'status' => 'failed',
            'attempts' => 1,
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
        ]);
        $attempt = DB::table('service_auto_renewal_attempts')->where('service_id', $service->id)->first();
        $this->assertSame($now->copy()->addHour()->toDateTimeString(), Carbon::parse($attempt->next_attempt_at)->toDateTimeString());
        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'service_auto_renew_failed',
            'recipient_email' => 'auto-failed@example.test',
            'source_type' => 'service',
            'source_id' => $service->id,
            'status' => 'pending',
        ]);

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=0 succeeded=0 failed=0 skipped=1.')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('notification_events')->where('type', 'service_auto_renew_failed')->count());
        $this->assertSame(1, DB::table('service_auto_renewal_attempts')->where('service_id', $service->id)->value('attempts'));
    }

    public function test_open_scheduled_cancellation_prevents_auto_renewal(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-cancel@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(3),
        ]);
        ServiceCancellation::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'mode' => 'period_end',
            'status' => 'scheduled',
            'reason' => 'Do not renew.',
            'requested_at' => $now,
        ]);

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=0 succeeded=0 failed=0 skipped=0.')
            ->assertExitCode(0);

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($now->copy()->addHours(3)));
        $this->assertSame(250000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseMissing('service_auto_renewal_attempts', [
            'service_id' => $service->id,
        ]);
    }

    public function test_auto_renew_skips_stale_candidate_when_auto_renew_was_disabled_before_charge(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-stale-disabled@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ]);
        $staleCandidate = Service::with(['user', 'product', 'orderItem'])->findOrFail($service->id);
        $service->forceFill(['auto_renew_enabled' => false])->save();

        $result = $this->processOne($staleCandidate);

        $this->assertSame('skipped', $result);
        $this->assertTrue($service->refresh()->expires_at->isSameSecond($now->copy()->addHours(4)));
        $this->assertSame(250000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $service->id,
            'status' => 'skipped',
            'attempts' => 0,
        ]);
    }

    public function test_auto_renew_skips_stale_candidate_when_expiry_changed_before_charge(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-stale-expiry@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ]);
        $staleCandidate = Service::with(['user', 'product', 'orderItem'])->findOrFail($service->id);
        $manualRenewedExpiresAt = $now->copy()->addDays(30)->addHours(4);
        $service->forceFill(['expires_at' => $manualRenewedExpiresAt])->save();

        $result = $this->processOne($staleCandidate);

        $this->assertSame('skipped', $result);
        $this->assertTrue($service->refresh()->expires_at->isSameSecond($manualRenewedExpiresAt));
        $this->assertSame(250000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $service->id,
            'status' => 'skipped',
            'attempts' => 0,
        ]);
    }

    public function test_retry_blocked_attempts_do_not_consume_command_limit_for_eligible_services(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-limit@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);

        for ($i = 1; $i <= 50; $i++) {
            $blocked = $this->serviceFor($customer, [
                'status' => 'active',
                'auto_renew_enabled' => true,
                'expires_at' => $now->copy()->addMinutes($i),
            ], ['code' => "auto-blocked-{$i}"]);

            DB::table('service_auto_renewal_attempts')->insert([
                'id' => (string) Str::uuid(),
                'service_id' => $blocked->id,
                'user_id' => $customer->id,
                'expires_at' => $blocked->expires_at,
                'status' => 'failed',
                'attempts' => 1,
                'next_attempt_at' => $now->copy()->addHour(),
                'renewed_expires_at' => null,
                'amount' => 99000,
                'currency' => 'VND',
                'last_error' => 'Wallet balance is not enough to pay this invoice.',
                'idempotency_key' => "service-auto-renew:{$blocked->id}:{$blocked->expires_at->toISOString()}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $eligible = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(12),
        ], ['code' => 'auto-eligible-after-blocked']);

        $this->artisan('services:auto-renew --limit=50')
            ->expectsOutput('Auto-renew processed=1 succeeded=1 failed=0 skipped=0.')
            ->assertExitCode(0);

        $this->assertTrue($eligible->refresh()->expires_at->isSameSecond($now->copy()->addDays(30)->addHours(12)));
        $this->assertSame(151000, Wallet::firstOrFail()->balance_amount);
    }

    public function test_internal_auto_renew_errors_are_sanitized_before_storage_and_customer_display(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('auto-sanitized@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ]);
        $renewals = \Mockery::mock(ServiceRenewalService::class);
        $renewals->shouldReceive('renew')
            ->once()
            ->andThrow(new RuntimeException('SQLSTATE[08006] host=db.internal token=secret'));
        $processor = new ServiceAutoRenewalProcessor($renewals, app(NotificationOutbox::class));

        $result = $this->processOne($service->load(['user', 'product', 'orderItem']), $processor);

        $this->assertSame('failed', $result);
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $service->id,
            'status' => 'failed',
            'last_error' => 'Auto-renew failed. We will retry automatically.',
        ]);

        $this->actingAs($customer)
            ->get("/services/{$service->id}")
            ->assertOk()
            ->assertSee('Auto-renew failed. We will retry automatically.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('db.internal')
            ->assertDontSee('token=secret');
    }

    public function test_admin_service_runbook_shows_auto_renewal_attempts(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('auto-admin@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
        ]);
        DB::table('service_auto_renewal_attempts')->insert([
            'id' => '11111111-1111-4111-8111-111111111111',
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'expires_at' => now()->addDay(),
            'status' => 'failed',
            'attempts' => 2,
            'next_attempt_at' => now()->addHour(),
            'renewed_expires_at' => null,
            'amount' => 99000,
            'currency' => 'VND',
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
            'idempotency_key' => 'service-auto-renew:test-admin-attempt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/admin/services/{$service->id}")
            ->assertOk()
            ->assertSee('Auto-renew')
            ->assertSee('Enabled')
            ->assertSee('Auto-Renewal Attempts')
            ->assertSee('failed')
            ->assertSee('Wallet balance is not enough to pay this invoice.');
    }

    private function serviceFor(User $user, array $serviceOverrides = [], array $productOverrides = []): Service
    {
        $product = Product::factory()->create($productOverrides + [
            'code' => 'auto-renew-product',
            'name' => 'Auto Renew Product',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'day',
            'lifecycle_count' => 30,
        ]);
        $order = Order::factory()->for($user)->create([
            'currency' => 'VND',
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'unit_amount' => 99000,
            'subtotal_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        return Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create($serviceOverrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'expires_at' => now()->addDay(),
            'meta' => [
                'duration_days' => 30,
                'lifecycle_policy' => [
                    'source' => 'local_policy',
                    'unit' => 'day',
                    'count' => 30,
                ],
            ],
        ]);
    }

    private function customerUser(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'auto-super-admin@example.test']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function processOne(Service $service, ?ServiceAutoRenewalProcessor $processor = null): string
    {
        $method = new \ReflectionMethod(ServiceAutoRenewalProcessor::class, 'processOne');
        $method->setAccessible(true);

        return $method->invoke($processor ?? app(ServiceAutoRenewalProcessor::class), $service);
    }
}
