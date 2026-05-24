<?php

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningJob;
use App\Models\ScheduledTaskRun;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminOpsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ops_health_counts_and_last_runs(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $admin = $this->adminUser();
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer, ['status' => 'active', 'expires_at' => now()->subMinute()]);
        ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'failed',
            'attempts' => 3,
            'idempotency_key' => 'ops-health-provisioning-failed',
            'payload' => ['product' => ['code' => $service->product_code]],
            'last_error' => 'Provisioning failed.',
        ]);
        ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'provider_account_id' => null,
            'action' => 'sync',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => 'ops-health-provider-pending',
            'payload' => ['context' => []],
        ]);
        BankIntegration::create([
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://bank.example.test',
            'transactions_path' => '/api/transactions',
            'enabled' => true,
        ]);
        ScheduledTaskRun::create([
            'task' => 'provider_actions_work',
            'command' => 'provider-actions:work --limit=50',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
            'duration_ms' => 50,
            'exit_code' => 0,
            'output' => 'Provider action jobs processed=0 failed=0.',
        ]);

        $response = $this->actingAs($admin)->get('/admin/ops-health');

        $response->assertOk()
            ->assertSee('Ops Health')
            ->assertSee('provider_actions_work')
            ->assertSee('success')
            ->assertSee('Provisioning Queue')
            ->assertSee('failed: 1')
            ->assertSee('Provider Action Queue')
            ->assertSee('pending: 1');

        $this->assertMatchesRegularExpression(
            '/<strong>\s*1\s*<\/strong>\s*<br>\s*Overdue Active Services/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<strong>\s*1\s*<\/strong>\s*<br>\s*Enabled Bank Integrations/',
            $response->getContent()
        );
    }

    public function test_ops_health_warns_when_scheduled_task_is_stale(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $admin = $this->adminUser();
        ScheduledTaskRun::create([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'duration_ms' => 25,
            'exit_code' => 0,
            'output' => 'No bank integrations.',
        ]);

        $response = $this->actingAs($admin)->get('/admin/ops-health');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<tr>(?:(?!<\/tr>).)*bank_sync_payments(?:(?!<\/tr>).)*warning(?:(?!<\/tr>).)*No successful run in the last 3 minutes\.(?:(?!<\/tr>).)*<\/tr>/s',
            $response->getContent()
        );
    }

    public function test_admin_dashboard_links_to_ops_health(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ops Health')
            ->assertSee('href="/admin/ops-health"', false);
    }

    public function test_customer_cannot_access_ops_health(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->get('/admin/ops-health')->assertForbidden();
    }

    private function serviceFor(User $user, array $serviceOverrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'ops-health-product',
            'name' => 'Ops Health Product',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);
        $order = Order::factory()->for($user)->create(['currency' => 'VND']);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
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

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
