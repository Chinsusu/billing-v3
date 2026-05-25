<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Models\ServiceCancellation;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceAutoRenewalOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_retry_failed_attempt_now_and_renew_service(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $admin = $this->adminUser();
        $customer = $this->customerUser('ops-retry@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], [
            'auto_renew_max_attempts' => 2,
        ]);
        $attempt = $this->attemptFor($service, [
            'status' => 'failed',
            'attempts' => 2,
            'next_attempt_at' => null,
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
        ]);

        $this->actingAs($admin)
            ->from('/admin/renewals')
            ->post("/admin/renewals/{$attempt->id}/retry", [
                'reason' => 'Customer topped up wallet.',
            ])
            ->assertRedirect('/admin/renewals');

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($now->copy()->addDays(30)->addHours(4)));
        $this->assertSame(151000, Wallet::firstOrFail()->balance_amount);
        $attempt->refresh();
        $this->assertSame('succeeded', $attempt->status);
        $this->assertSame(2, $attempt->attempts);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'service_auto_renew_retried',
            'auditable_type' => ServiceAutoRenewalAttempt::class,
            'auditable_id' => $attempt->id,
        ]);
        $this->assertSame('Customer topped up wallet.', AdminAuditLog::where('action', 'service_auto_renew_retried')->firstOrFail()->metadata['reason']);
    }

    public function test_retry_rejects_unsafe_attempts(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $admin = $this->adminUser();
        $customer = $this->customerUser('ops-guard@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 800000]);
        $oldTarget = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], ['code' => 'ops-old-target']);
        $oldAttempt = $this->attemptFor($oldTarget, [
            'expires_at' => $now->copy()->addHour(),
            'status' => 'failed',
            'attempts' => 1,
        ]);
        $policyDisabled = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], [
            'code' => 'ops-policy-disabled',
            'auto_renew_allowed' => false,
        ]);
        $policyAttempt = $this->attemptFor($policyDisabled, ['status' => 'failed', 'attempts' => 1]);
        $cancelled = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], ['code' => 'ops-open-cancel']);
        ServiceCancellation::create([
            'service_id' => $cancelled->id,
            'user_id' => $customer->id,
            'requested_by_id' => $admin->id,
            'mode' => 'period_end',
            'status' => 'scheduled',
            'reason' => 'Customer requested cancellation.',
            'requested_at' => $now,
        ]);
        $cancelAttempt = $this->attemptFor($cancelled, ['status' => 'failed', 'attempts' => 1]);
        $inactive = $this->serviceFor($customer, [
            'status' => 'expired',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], ['code' => 'ops-inactive']);
        $inactiveAttempt = $this->attemptFor($inactive, ['status' => 'failed', 'attempts' => 1]);

        foreach ([$oldAttempt, $policyAttempt, $cancelAttempt, $inactiveAttempt] as $attempt) {
            $this->actingAs($admin)
                ->from('/admin/renewals')
                ->post("/admin/renewals/{$attempt->id}/retry", [
                    'reason' => 'Manual guardrail check.',
                ])
                ->assertRedirect('/admin/renewals')
                ->assertSessionHasErrors('attempt');
        }

        $this->assertSame(800000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, AdminAuditLog::where('action', 'service_auto_renew_retried')->count());
    }

    public function test_admin_can_reset_exhausted_attempt_with_reason(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $admin = $this->adminUser();
        $customer = $this->customerUser('ops-reset@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(3),
        ], [
            'auto_renew_max_attempts' => 3,
        ]);
        $attempt = $this->attemptFor($service, [
            'status' => 'failed',
            'attempts' => 3,
            'next_attempt_at' => null,
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
        ]);

        $this->actingAs($admin)
            ->from('/admin/renewals')
            ->post("/admin/renewals/{$attempt->id}/reset", [
                'reason' => 'Reset after support confirmed payment.',
            ])
            ->assertRedirect('/admin/renewals');

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);
        $this->assertSame(0, $attempt->attempts);
        $this->assertNull($attempt->last_error);
        $this->assertSame($now->toDateTimeString(), $attempt->next_attempt_at?->toDateTimeString());
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'service_auto_renew_reset',
            'auditable_id' => $attempt->id,
        ]);
    }

    public function test_admin_can_toggle_service_auto_renew_with_policy_guardrails(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('ops-toggle@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->from("/admin/services/{$service->id}")
            ->post("/admin/services/{$service->id}/auto-renew", [
                'enabled' => '0',
                'reason' => 'Customer requested manual renewal only.',
            ])
            ->assertRedirect("/admin/services/{$service->id}");

        $this->assertFalse($service->refresh()->auto_renew_enabled);

        $this->actingAs($admin)
            ->from("/admin/services/{$service->id}")
            ->post("/admin/services/{$service->id}/auto-renew", [
                'enabled' => '1',
                'reason' => 'Customer approved automatic renewal.',
            ])
            ->assertRedirect("/admin/services/{$service->id}");

        $this->assertTrue($service->refresh()->auto_renew_enabled);
        $this->assertSame(2, AdminAuditLog::where('action', 'service_auto_renew_toggled')->count());

        $service->product->forceFill(['auto_renew_allowed' => false])->save();
        $service->forceFill(['auto_renew_enabled' => false])->save();

        $this->actingAs($admin)
            ->from("/admin/services/{$service->id}")
            ->post("/admin/services/{$service->id}/auto-renew", [
                'enabled' => '1',
                'reason' => 'Try enabling against disabled policy.',
            ])
            ->assertRedirect("/admin/services/{$service->id}")
            ->assertSessionHasErrors('service');

        $this->assertFalse($service->refresh()->auto_renew_enabled);
    }

    public function test_admin_bulk_retry_and_bulk_disable_from_renewal_report(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $admin = $this->adminUser();
        $customer = $this->customerUser('ops-bulk@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 500000]);
        $first = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(2),
        ], ['code' => 'ops-bulk-first']);
        $second = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(3),
        ], ['code' => 'ops-bulk-second']);
        $firstAttempt = $this->attemptFor($first, ['status' => 'failed', 'attempts' => 1]);
        $secondAttempt = $this->attemptFor($second, ['status' => 'failed', 'attempts' => 1]);

        $this->actingAs($admin)
            ->from('/admin/renewals')
            ->post('/admin/renewals/bulk', [
                'action' => 'retry',
                'attempt_ids' => [$firstAttempt->id, $secondAttempt->id],
                'reason' => 'Bulk retry after wallet reconciliation.',
            ])
            ->assertRedirect('/admin/renewals')
            ->assertSessionHas('status', 'Bulk retry applied=2 skipped=0.');

        $this->assertSame('succeeded', $firstAttempt->refresh()->status);
        $this->assertSame('succeeded', $secondAttempt->refresh()->status);
        $this->assertSame(302000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(2, AdminAuditLog::where('action', 'service_auto_renew_retried')->count());
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'service_auto_renew_bulk_retry',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from('/admin/renewals')
            ->post('/admin/renewals/bulk', [
                'action' => 'disable',
                'attempt_ids' => [$firstAttempt->id, $secondAttempt->id],
                'reason' => 'Disable after renewal incident.',
            ])
            ->assertRedirect('/admin/renewals')
            ->assertSessionHas('status', 'Bulk disable applied=2 skipped=0.');

        $this->assertFalse($first->refresh()->auto_renew_enabled);
        $this->assertFalse($second->refresh()->auto_renew_enabled);
        $this->assertSame(2, AdminAuditLog::where('action', 'service_auto_renew_toggled')->count());
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'service_auto_renew_bulk_disable',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
        ]);
    }

    public function test_customer_cannot_access_admin_renewal_ops_routes(): void
    {
        $customer = $this->customerUser('ops-auth@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
        ]);
        $attempt = $this->attemptFor($service, ['status' => 'failed', 'attempts' => 1]);

        $this->actingAs($customer)->post("/admin/renewals/{$attempt->id}/retry", ['reason' => 'No access.'])->assertForbidden();
        $this->actingAs($customer)->post("/admin/renewals/{$attempt->id}/reset", ['reason' => 'No access.'])->assertForbidden();
        $this->actingAs($customer)->post('/admin/renewals/bulk', ['action' => 'retry', 'attempt_ids' => [$attempt->id], 'reason' => 'No access.'])->assertForbidden();
        $this->actingAs($customer)->post("/admin/services/{$service->id}/auto-renew", ['enabled' => '0', 'reason' => 'No access.'])->assertForbidden();
    }

    private function serviceFor(User $user, array $serviceOverrides = [], array $productOverrides = []): Service
    {
        $product = Product::factory()->create($productOverrides + [
            'code' => 'ops-renewal-product-'.strtolower(fake()->unique()->bothify('????')),
            'name' => 'Ops Renewal Product',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'day',
            'lifecycle_count' => 30,
            'auto_renew_allowed' => true,
            'auto_renew_window_hours' => 24,
            'auto_renew_retry_delay_minutes' => 60,
            'auto_renew_max_attempts' => 3,
        ]);
        $order = Order::factory()->for($user)->create(['currency' => 'VND']);
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
            'expires_at' => now()->addHours(4),
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

    private function attemptFor(Service $service, array $overrides = []): ServiceAutoRenewalAttempt
    {
        $expiresAt = $overrides['expires_at'] ?? $service->expires_at;

        return ServiceAutoRenewalAttempt::create($overrides + [
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'expires_at' => $expiresAt,
            'status' => 'failed',
            'attempts' => 1,
            'next_attempt_at' => now()->addHour(),
            'renewed_expires_at' => null,
            'amount' => 99000,
            'currency' => 'VND',
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
            'idempotency_key' => "service-auto-renew:{$service->id}:{$expiresAt?->toISOString()}",
        ]);
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'ops-super-admin@example.test']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
