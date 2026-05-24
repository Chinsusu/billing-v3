<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceRenewalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_renew_active_service_from_wallet(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->addDays(10),
            'meta' => ['duration_days' => 30],
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}");

        $service->refresh();
        $this->assertSame('active', $service->status);
        $this->assertTrue($service->expires_at->isSameSecond($now->copy()->addDays(40)));
        $this->assertSame(101000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 99000,
            'balance_after' => 101000,
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
        ]);
        $this->assertCount(1, $service->meta['renewals']);
        $this->assertSame(99000, $service->meta['renewals'][0]['amount']);
        $this->assertSame('VND', $service->meta['renewals'][0]['currency']);
    }

    public function test_renewal_uses_current_product_price(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor(
            $customer,
            ['status' => 'active', 'expires_at' => $now->copy()->addDays(5)],
            ['price_amount' => 120000],
            ['unit_amount' => 99000, 'subtotal_amount' => 99000]
        );

        $this->actingAs($customer)->post("/services/{$service->id}/renew");

        $this->assertSame(80000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
            'amount' => 120000,
        ]);
    }

    public function test_calendar_month_renewal_uses_no_overflow_from_service_snapshot(): void
    {
        $now = Carbon::parse('2026-01-20 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-01-31 09:00:00'),
            'meta' => [
                'duration_days' => 30,
                'lifecycle_policy' => [
                    'source' => 'local_policy',
                    'unit' => 'calendar_month',
                    'count' => 1,
                    'provider_lifecycle_path' => null,
                    'ordered_at_path' => null,
                    'expires_at_path' => null,
                    'date_format' => 'iso8601',
                    'timezone' => 'UTC',
                ],
            ],
        ]);

        $this->actingAs($customer)->post("/services/{$service->id}/renew");

        $service->refresh();
        $this->assertTrue($service->expires_at->isSameSecond(Carbon::parse('2026-02-28 09:00:00')));
        $this->assertSame('2026-02-28T09:00:00.000000Z', $service->meta['renewals'][0]['new_expires_at']);
    }

    public function test_renewal_falls_back_to_order_item_snapshot_when_product_is_missing(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor(
            $customer,
            [
                'status' => 'active',
                'expires_at' => $now->copy()->addDays(5),
                'product_id' => null,
            ],
            ['price_amount' => 120000],
            ['unit_amount' => 77000, 'subtotal_amount' => 77000]
        );

        $this->actingAs($customer)->post("/services/{$service->id}/renew");

        $this->assertSame(123000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
            'amount' => 77000,
        ]);
    }

    public function test_insufficient_wallet_balance_does_not_renew_service(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->addDays(10),
        ]);
        $oldExpiresAt = $service->expires_at->copy();

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHasErrors('wallet');

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($oldExpiresAt));
        $this->assertSame(1000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_customer_cannot_renew_another_users_service(): void
    {
        $owner = $this->customerUser();
        $service = $this->serviceFor($owner, [
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);
        $oldExpiresAt = $service->expires_at->copy();
        $other = User::factory()->create();
        $other->assignRole('customer');
        Wallet::factory()->for($other)->create(['balance_amount' => 200000]);

        $this->actingAs($other)->post("/services/{$service->id}/renew")->assertNotFound();

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($oldExpiresAt));
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_expired_services_cannot_be_renewed(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHasErrors('service');

        $this->assertSame('expired', $service->refresh()->status);
        $this->assertSame(200000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_services_expire_command_marks_overdue_active_services_expired(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        $overdue = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->subMinute(),
        ]);
        $future = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->addDay(),
        ], ['code' => 'proxy-vn-60d']);

        $this->artisan('services:expire')
            ->expectsOutput('Expired 1 services.')
            ->assertExitCode(0);

        $this->assertSame('expired', $overdue->refresh()->status);
        $this->assertSame($now->toISOString(), $overdue->meta['expired_at']);
        $this->assertSame('active', $future->refresh()->status);
    }

    private function serviceFor(
        User $user,
        array $serviceOverrides = [],
        array $productOverrides = [],
        array $itemOverrides = []
    ): Service {
        $product = Product::factory()->create($productOverrides + [
            'code' => 'proxy-vn-30d',
            'name' => 'Vietnam Proxy 30 Days',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);
        $order = Order::factory()->for($user)->create([
            'currency' => 'VND',
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create($itemOverrides + [
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
            'expires_at' => now()->addDays(10),
            'meta' => ['duration_days' => 30],
        ]);
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
