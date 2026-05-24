<?php

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankIntegrationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_bank_integration_with_encrypted_secrets(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/bank-integrations', [
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://bank.example.test',
            'transactions_path' => '/api/transactions',
            'account_number' => '123456789',
            'enabled' => '1',
            'api_key' => 'private-api-secret-1234',
            'webhook_secret' => 'webhook-secret-5678',
        ])->assertRedirect('/admin/bank-integrations');

        $integration = BankIntegration::firstOrFail();
        $this->assertSame('private-api-secret-1234', $integration->api_key);
        $this->assertSame('webhook-secret-5678', $integration->webhook_secret);
        $this->assertSame('1234', $integration->api_key_last_four);
        $this->assertSame('5678', $integration->webhook_secret_last_four);

        $raw = DB::table('bank_integrations')->where('id', $integration->id)->first();
        $this->assertStringNotContainsString('private-api-secret-1234', $raw->api_key);
        $this->assertStringNotContainsString('webhook-secret-5678', $raw->webhook_secret);

        $this->actingAs($admin)
            ->get('/admin/bank-integrations')
            ->assertOk()
            ->assertSee('Private Bank')
            ->assertSee('Configured')
            ->assertSee('1234')
            ->assertDontSee('private-api-secret-1234')
            ->assertDontSee('webhook-secret-5678');
    }

    public function test_admin_can_update_public_config_without_overwriting_blank_secrets(): void
    {
        $admin = $this->adminUser();
        $integration = BankIntegration::create([
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://old-bank.example.test',
            'transactions_path' => '/old/transactions',
            'account_number' => 'OLD-ACCOUNT',
            'enabled' => true,
            'api_key' => 'private-api-secret-1234',
            'webhook_secret' => 'webhook-secret-5678',
            'api_key_last_four' => '1234',
            'webhook_secret_last_four' => '5678',
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/admin/bank-integrations/{$integration->id}", [
            'provider' => 'private_bank',
            'name' => 'Updated Bank',
            'base_url' => 'https://new-bank.example.test',
            'transactions_path' => '/api/transactions',
            'account_number' => 'NEW-ACCOUNT',
            'enabled' => '0',
            'api_key' => '',
            'webhook_secret' => '',
        ])->assertRedirect('/admin/bank-integrations');

        $integration->refresh();
        $this->assertSame('Updated Bank', $integration->name);
        $this->assertFalse($integration->enabled);
        $this->assertSame('private-api-secret-1234', $integration->api_key);
        $this->assertSame('webhook-secret-5678', $integration->webhook_secret);
    }

    public function test_admin_can_test_bank_connection_without_exposing_secret_to_view(): void
    {
        $admin = $this->adminUser();
        $integration = BankIntegration::create([
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://bank.example.test',
            'transactions_path' => '/api/transactions',
            'account_number' => '123456789',
            'enabled' => true,
            'api_key' => 'private-api-secret-1234',
            'api_key_last_four' => '1234',
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);

        Http::fake([
            'https://bank.example.test/api/transactions' => Http::response(['transactions' => []]),
        ]);

        $this->actingAs($admin)
            ->post("/admin/bank-integrations/{$integration->id}/test")
            ->assertRedirect('/admin/bank-integrations');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer private-api-secret-1234'));

        $integration->refresh();
        $this->assertSame('tested', $integration->last_sync_status);
        $this->assertNotNull($integration->last_tested_at);
        $this->assertNull($integration->last_sync_error);
    }

    public function test_customer_cannot_access_bank_integration_admin_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/bank-integrations')->assertForbidden();
        $this->actingAs($customer)->post('/admin/bank-integrations', [])->assertForbidden();
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
