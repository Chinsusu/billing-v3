<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProvisioningProviderAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLifecyclePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_provider_lookup_lifecycle_policy_on_product(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
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
            'response_status_path' => 'data.status',
        ]);

        $this->actingAs($admin)->post('/admin/products', [
            'code' => 'vps-provider-a-monthly',
            'name' => 'Provider A VPS Monthly',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 199000,
            'currency' => 'VND',
            'duration_days' => 30,
            'description' => 'Provider lookup lifecycle plan',
            'provider_account_id' => $account->id,
            'provider_plan_code' => 'A1',
            'provider_provision_path' => '/api/accounts/main/provision',
            'provider_options' => '{}',
            'lifecycle_source' => 'provider_lookup',
            'lifecycle_unit' => 'calendar_month',
            'lifecycle_count' => 1,
            'provider_lifecycle_path' => '/api/services/{external_id}',
            'provider_lifecycle_ordered_at_path' => 'data.ordered_at',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
            'provider_lifecycle_date_format' => 'iso8601',
            'provider_lifecycle_timezone' => 'Asia/Ho_Chi_Minh',
        ])->assertRedirect('/admin/products');

        $product = Product::where('code', 'vps-provider-a-monthly')->firstOrFail();
        $this->assertSame('provider_lookup', $product->lifecycle_source);
        $this->assertSame('calendar_month', $product->lifecycle_unit);
        $this->assertSame(1, $product->lifecycle_count);
        $this->assertSame('/api/services/{external_id}', $product->provider_lifecycle_path);
        $this->assertSame('data.ordered_at', $product->provider_lifecycle_ordered_at_path);
        $this->assertSame('data.expires_at', $product->provider_lifecycle_expires_at_path);
        $this->assertSame('iso8601', $product->provider_lifecycle_date_format);
        $this->assertSame('Asia/Ho_Chi_Minh', $product->provider_lifecycle_timezone);
    }
}
