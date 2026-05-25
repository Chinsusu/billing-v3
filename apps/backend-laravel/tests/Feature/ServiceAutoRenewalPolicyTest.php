<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceAutoRenewalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_product_renewal_policy(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('Auto-renew Policy')
            ->assertSee('Renewal Window Hours');

        $this->actingAs($admin)
            ->post('/admin/products', $this->productPayload([
                'code' => 'policy-vps-30d',
                'auto_renew_allowed' => '0',
                'auto_renew_window_hours' => 12,
                'auto_renew_retry_delay_minutes' => 30,
                'auto_renew_max_attempts' => 2,
            ]))
            ->assertRedirect('/admin/products');

        $product = Product::where('code', 'policy-vps-30d')->firstOrFail();
        $this->assertFalse($product->auto_renew_allowed);
        $this->assertSame(12, $product->auto_renew_window_hours);
        $this->assertSame(30, $product->auto_renew_retry_delay_minutes);
        $this->assertSame(2, $product->auto_renew_max_attempts);
        $this->assertStringContainsString(
            'auto_renew_window_hours',
            json_encode(DB::table('admin_audit_logs')->where('auditable_id', $product->id)->latest('created_at')->value('after'))
        );

        $this->actingAs($admin)
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('Auto-renew Policy')
            ->assertSee('Renewal Max Attempts');

        $this->actingAs($admin)
            ->put("/admin/products/{$product->id}", $this->productPayload([
                'code' => 'policy-vps-30d',
                'name' => 'Policy VPS Updated',
                'auto_renew_allowed' => '1',
                'auto_renew_window_hours' => 6,
                'auto_renew_retry_delay_minutes' => 15,
                'auto_renew_max_attempts' => 4,
            ]))
            ->assertRedirect('/admin/products');

        $product->refresh();
        $this->assertTrue($product->auto_renew_allowed);
        $this->assertSame(6, $product->auto_renew_window_hours);
        $this->assertSame(15, $product->auto_renew_retry_delay_minutes);
        $this->assertSame(4, $product->auto_renew_max_attempts);
    }

    public function test_customer_cannot_enable_auto_renew_when_product_policy_disallows_it(): void
    {
        $customer = $this->customerUser('policy-disabled@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => false,
        ], [
            'auto_renew_allowed' => false,
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/auto-renew", ['enabled' => '1'])
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHasErrors('enabled');

        $this->assertFalse($service->refresh()->auto_renew_enabled);

        $service->forceFill(['auto_renew_enabled' => true])->save();

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/auto-renew", ['enabled' => '0'])
            ->assertRedirect("/services/{$service->id}");

        $this->assertFalse($service->refresh()->auto_renew_enabled);
    }

    public function test_auto_renew_respects_product_due_window_before_creating_attempt(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('policy-window@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 300000]);
        $outsideWindow = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(20),
        ], [
            'code' => 'policy-outside-window',
            'auto_renew_window_hours' => 12,
        ]);
        $insideWindow = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(10),
        ], [
            'code' => 'policy-inside-window',
            'auto_renew_window_hours' => 12,
        ]);

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=1 succeeded=1 failed=0 skipped=1.')
            ->assertExitCode(0);

        $this->assertTrue($outsideWindow->refresh()->expires_at->isSameSecond($now->copy()->addHours(20)));
        $this->assertTrue($insideWindow->refresh()->expires_at->isSameSecond($now->copy()->addDays(30)->addHours(10)));
        $this->assertSame(201000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseMissing('service_auto_renewal_attempts', [
            'service_id' => $outsideWindow->id,
        ]);
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $insideWindow->id,
            'status' => 'succeeded',
            'attempts' => 1,
        ]);
    }

    public function test_policy_window_skips_do_not_starve_later_eligible_services(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('policy-starvation@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 300000]);

        for ($i = 1; $i <= 3; $i++) {
            $this->serviceFor($customer, [
                'status' => 'active',
                'auto_renew_enabled' => true,
                'expires_at' => $now->copy()->addHours(2),
            ], [
                'code' => "policy-outside-short-window-{$i}",
                'auto_renew_window_hours' => 1,
            ]);
        }

        $eligible = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(10),
        ], [
            'code' => 'policy-eligible-after-short-window',
            'auto_renew_window_hours' => 24,
        ]);

        $this->artisan('services:auto-renew --limit=1')
            ->expectsOutput('Auto-renew processed=1 succeeded=1 failed=0 skipped=3.')
            ->assertExitCode(0);

        $this->assertTrue($eligible->refresh()->expires_at->isSameSecond($now->copy()->addDays(30)->addHours(10)));
        $this->assertSame(201000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(1, DB::table('service_auto_renewal_attempts')->count());
        $this->assertDatabaseHas('service_auto_renewal_attempts', [
            'service_id' => $eligible->id,
            'status' => 'succeeded',
        ]);
    }

    public function test_retry_delay_and_max_attempts_follow_product_policy(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('policy-retry@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], [
            'auto_renew_retry_delay_minutes' => 15,
            'auto_renew_max_attempts' => 2,
        ]);

        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=1 succeeded=0 failed=1 skipped=0.')
            ->assertExitCode(0);

        $attempt = DB::table('service_auto_renewal_attempts')->where('service_id', $service->id)->first();
        $this->assertSame(1, $attempt->attempts);
        $this->assertSame($now->copy()->addMinutes(15)->toDateTimeString(), Carbon::parse($attempt->next_attempt_at)->toDateTimeString());

        $this->travelTo($now->copy()->addMinutes(16));
        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=1 succeeded=0 failed=1 skipped=0.')
            ->assertExitCode(0);

        $attempt = DB::table('service_auto_renewal_attempts')->where('service_id', $service->id)->first();
        $this->assertSame(2, $attempt->attempts);
        $this->assertNull($attempt->next_attempt_at);

        $this->travelTo($now->copy()->addMinutes(30));
        $this->artisan('services:auto-renew --limit=10')
            ->expectsOutput('Auto-renew processed=0 succeeded=0 failed=0 skipped=0.')
            ->assertExitCode(0);
        $this->assertSame(2, DB::table('service_auto_renewal_attempts')->where('service_id', $service->id)->value('attempts'));
    }

    public function test_admin_renewal_report_summarizes_and_filters_attempts(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $admin = $this->adminUser();
        $customer = $this->customerUser('report-customer@example.test');
        $target = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], [
            'code' => 'report-target-product',
            'name' => 'Report Target Product',
            'auto_renew_window_hours' => 12,
            'auto_renew_max_attempts' => 2,
        ]);
        $other = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(4),
        ], [
            'code' => 'report-other-product',
            'name' => 'Report Other Product',
        ]);
        $this->insertAutoRenewAttempt($target, [
            'status' => 'failed',
            'attempts' => 2,
            'next_attempt_at' => null,
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
        ]);
        $this->insertAutoRenewAttempt($other, [
            'status' => 'succeeded',
            'attempts' => 1,
            'renewed_expires_at' => $now->copy()->addDays(30),
        ]);
        $old = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(5),
        ], [
            'code' => 'report-old-product',
            'name' => 'Report Old Product',
        ]);
        $this->insertAutoRenewAttempt($old, [
            'status' => 'failed',
            'attempts' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get('/admin/renewals')
            ->assertOk()
            ->assertSee('Renewal Reporting')
            ->assertSee('Auto-renew enabled')
            ->assertSee('Failed attempts')
            ->assertSee('Exhausted attempts')
            ->assertDontSee($old->id);

        $this->actingAs($admin)
            ->get("/admin/renewals?status=failed&product_id={$target->product_id}&customer=report-customer")
            ->assertOk()
            ->assertSee('Report Target Product')
            ->assertSee('failed')
            ->assertSee('2 / 2')
            ->assertSee('Exhausted')
            ->assertDontSee($other->id);

        $this->actingAs($customer)->get('/admin/renewals')->assertForbidden();
    }

    public function test_customer_dashboard_and_service_list_show_renewal_reporting(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('customer-report@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(6),
        ], [
            'name' => 'Customer Renewal Product',
            'auto_renew_window_hours' => 12,
            'auto_renew_retry_delay_minutes' => 30,
            'auto_renew_max_attempts' => 3,
        ]);
        $this->insertAutoRenewAttempt($service, [
            'status' => 'failed',
            'attempts' => 1,
            'next_attempt_at' => $now->copy()->addMinutes(30),
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
        ]);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Auto-renew enabled')
            ->assertSee('Due for auto-renew')
            ->assertSee('Failed auto-renew');

        $this->actingAs($customer)
            ->get('/services')
            ->assertOk()
            ->assertSee('Auto-renew')
            ->assertSee('Latest renewal')
            ->assertSee('Customer Renewal Product')
            ->assertSee('failed')
            ->assertSee('Next retry');
    }

    public function test_customer_reporting_ignores_attempts_for_old_expiry_targets(): void
    {
        $now = Carbon::parse('2026-05-25 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser('customer-current-expiry@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => $now->copy()->addHours(6),
        ], [
            'name' => 'Current Expiry Renewal Product',
        ]);
        $this->insertAutoRenewAttempt($service, [
            'status' => 'failed',
            'attempts' => 2,
            'next_attempt_at' => $now->copy()->addMinutes(30),
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
        ]);
        $service->forceFill(['expires_at' => $now->copy()->addDays(30)])->save();

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('0</strong><br>Failed auto-renew', false);

        $this->actingAs($customer)
            ->get('/services')
            ->assertOk()
            ->assertSee('Current Expiry Renewal Product')
            ->assertSee('Latest renewal')
            ->assertDontSee('failed')
            ->assertDontSee('Next retry');

        $this->actingAs($customer)
            ->get("/services/{$service->id}")
            ->assertOk()
            ->assertSee('No auto-renewal attempts yet.')
            ->assertDontSee('Wallet balance is not enough to pay this invoice.');
    }

    private function productPayload(array $overrides = []): array
    {
        return $overrides + [
            'code' => 'policy-product-'.Str::lower(Str::random(6)),
            'name' => 'Policy Product',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
            'description' => 'Renewal policy product',
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'day',
            'lifecycle_count' => 30,
            'provider_lifecycle_date_format' => 'iso8601',
            'provider_lifecycle_timezone' => 'UTC',
            'auto_renew_allowed' => '1',
            'auto_renew_window_hours' => 24,
            'auto_renew_retry_delay_minutes' => 60,
            'auto_renew_max_attempts' => 3,
        ];
    }

    private function serviceFor(User $user, array $serviceOverrides = [], array $productOverrides = []): Service
    {
        $product = Product::factory()->create($productOverrides + [
            'name' => 'Renewal Policy Product',
            'type' => 'vps',
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

    private function insertAutoRenewAttempt(Service $service, array $overrides = []): void
    {
        DB::table('service_auto_renewal_attempts')->insert($overrides + [
            'id' => (string) Str::uuid(),
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'expires_at' => $service->expires_at,
            'status' => 'failed',
            'attempts' => 1,
            'next_attempt_at' => now()->addHour(),
            'renewed_expires_at' => null,
            'amount' => 99000,
            'currency' => 'VND',
            'last_error' => 'Wallet balance is not enough to pay this invoice.',
            'idempotency_key' => "service-auto-renew:{$service->id}:{$service->expires_at?->toISOString()}",
            'created_at' => now(),
            'updated_at' => now(),
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
        $admin = User::factory()->create(['email' => 'policy-super-admin@example.test']);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
