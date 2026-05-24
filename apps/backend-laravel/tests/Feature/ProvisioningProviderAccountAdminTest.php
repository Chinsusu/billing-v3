<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisioningProviderAccountAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_provider_account_with_encrypted_secret_and_masked_ui(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/provisioning-provider-accounts', [
            'slug' => 'provider-a-main',
            'name' => 'Provider A Main',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-a.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'auth_header_name' => '',
            'api_key' => 'provider-secret-1234',
            'enabled' => '1',
            'timeout_seconds' => 20,
            'request_template' => '{"source":"billing"}',
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'data.status',
            'response_config_path' => 'data.config',
        ])->assertRedirect('/admin/provisioning-provider-accounts');

        $account = DB::table('provisioning_provider_accounts')->first();
        $this->assertSame('provider-a-main', $account->slug);
        $this->assertSame('Provider A Main', $account->name);
        $this->assertSame('generic_http', $account->driver);
        $this->assertSame('1234', $account->api_key_last_four);
        $this->assertStringNotContainsString('provider-secret-1234', $account->api_key);
        $this->assertSame('provider-secret-1234', decrypt($account->api_key));

        $this->actingAs($admin)
            ->get('/admin/provisioning-provider-accounts')
            ->assertOk()
            ->assertSee('Provider A Main')
            ->assertSee('generic_http')
            ->assertSee('Configured ...1234')
            ->assertDontSee('provider-secret-1234');
    }

    public function test_admin_can_update_public_provider_config_without_overwriting_blank_secret(): void
    {
        $admin = $this->adminUser();
        $accountId = (string) Str::uuid();
        DB::table('provisioning_provider_accounts')->insert([
            'id' => $accountId,
            'slug' => 'provider-a-main',
            'name' => 'Provider A Main',
            'driver' => 'generic_http',
            'base_url' => 'https://old-provider.example.test',
            'provision_path' => '/old/provision',
            'auth_type' => 'bearer',
            'auth_header_name' => null,
            'api_key' => encrypt('provider-secret-1234'),
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => json_encode(['source' => 'billing']),
            'response_external_id_path' => 'external_id',
            'response_status_path' => 'status',
            'response_config_path' => null,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->put("/admin/provisioning-provider-accounts/{$accountId}", [
            'slug' => 'provider-a-main',
            'name' => 'Provider A Updated',
            'driver' => 'generic_http',
            'base_url' => 'https://new-provider.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'auth_header_name' => '',
            'api_key' => '',
            'enabled' => '0',
            'timeout_seconds' => 30,
            'request_template' => '{"source":"billing","account":"main"}',
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'data.status',
            'response_config_path' => 'data.config',
        ])->assertRedirect('/admin/provisioning-provider-accounts');

        $account = DB::table('provisioning_provider_accounts')->where('id', $accountId)->first();
        $this->assertSame('Provider A Updated', $account->name);
        $this->assertFalse((bool) $account->enabled);
        $this->assertSame('1234', $account->api_key_last_four);
        $this->assertSame('provider-secret-1234', decrypt($account->api_key));
    }

    public function test_customer_cannot_access_provider_account_admin_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/provisioning-provider-accounts')->assertForbidden();
        $this->actingAs($customer)->post('/admin/provisioning-provider-accounts', [])->assertForbidden();
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
