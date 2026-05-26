<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminServiceProviderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_service_provider_sync(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $admin = $this->adminUser();
        [$account, $service] = $this->providerBackedService([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        Http::fake();

        $this->actingAs($admin)
            ->from('/admin/services')
            ->post("/admin/services/{$service->id}/sync-provider")
            ->assertRedirect('/admin/services')
            ->assertSessionHas('status', 'Provider sync queued.');

        $service->refresh();
        $this->assertSame('expired', $service->status);
        $this->assertDatabaseHas('provider_action_jobs', [
            'service_id' => $service->id,
            'provider_account_id' => $account->id,
            'action' => 'sync',
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key' => "service-sync:{$service->id}:2026-05-24T09:00:00.000000Z",
        ]);
        $this->assertSame(1, ProviderActionJob::count());

        Http::assertNothingSent();
    }

    public function test_admin_services_index_shows_provider_sync_form(): void
    {
        $admin = $this->adminUser();
        [, $service] = $this->providerBackedService();

        $this->actingAs($admin)
            ->get('/admin/services')
            ->assertOk()
            ->assertSee("action=\"/admin/services/{$service->id}/sync-provider\"", false)
            ->assertSee('Sync Provider')
            ->assertSee("action=\"/admin/services/{$service->id}/cancel-provider\"", false)
            ->assertSee('Cancel Provider');
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function providerBackedService(array $serviceOverrides = []): array
    {
        $customer = User::factory()->create();
        $account = ProvisioningProviderAccount::create([
            'slug' => 'provider-a-main',
            'name' => 'Provider A Main',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-a.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'api_key' => 'provider-secret-1234',
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => [],
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'status',
            'response_config_path' => 'data.config',
        ]);
        $product = Product::factory()->create([
            'code' => 'vps-provider-a-30d',
            'name' => 'Provider A VPS 30 Days',
            'type' => 'vps',
            'status' => 'active',
            'provider_account_id' => $account->id,
            'provider_plan_code' => 'A1',
            'provider_region' => 'sgp1',
            'provider_cancel_path' => '/api/services/{external_id}/cancel',
            'provider_sync_path' => '/api/services/{external_id}',
            'lifecycle_source' => 'provider_response',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
        ]);
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
        ]);
        $provider = [
            'account_id' => $account->id,
            'account_slug' => $account->slug,
            'driver' => $account->driver,
            'plan_code' => 'A1',
            'region' => 'sgp1',
            'provision_path' => '/api/accounts/main/provision',
            'renew_path' => null,
            'suspend_path' => null,
            'cancel_path' => '/api/services/{external_id}/cancel',
            'sync_path' => '/api/services/{external_id}',
            'options' => [],
        ];
        $service = Service::factory()->for($customer)->for($order)->for($item, 'orderItem')->for($product)->create($serviceOverrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'external_id' => 'provider-service-123',
            'expires_at' => now()->addDays(10),
            'meta' => [
                'duration_days' => 30,
                'provider' => $provider,
                'lifecycle_policy' => [
                    'source' => 'provider_response',
                    'unit' => 'day',
                    'count' => 30,
                    'provider_lifecycle_path' => null,
                    'ordered_at_path' => null,
                    'expires_at_path' => 'data.expires_at',
                    'date_format' => 'iso8601',
                    'timezone' => 'UTC',
                ],
            ],
        ]);

        return [$account, $service];
    }
}
