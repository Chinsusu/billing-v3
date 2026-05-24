<?php

namespace Tests\Feature;

use App\Models\ProvisioningProviderAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProvisioningProviderAccountTestHarnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_test_generic_provider_account_and_record_execution_log(): void
    {
        $admin = $this->adminUser();
        $account = $this->providerAccount();

        Http::fake([
            'https://provider-a.example.test/api/provision' => Http::response([
                'ok' => true,
                'token' => 'response-token-secret',
            ]),
        ]);

        $this->actingAs($admin)
            ->post("/admin/provisioning-provider-accounts/{$account->id}/test")
            ->assertRedirect('/admin/provisioning-provider-accounts');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer provider-secret-1234')
            && $request->data()['action'] === 'provider_account_test'
            && $request->data()['provider_account'] === 'provider-a-main');

        $account->refresh();
        $this->assertSame('passed', $account->last_test_status);
        $this->assertNotNull($account->last_tested_at);
        $this->assertNull($account->last_test_error);

        $log = DB::table('provisioning_execution_logs')->where('provider_account_id', $account->id)->first();
        $this->assertSame('provider_account_test', $log->action);
        $this->assertSame('success', $log->status);
        $this->assertStringNotContainsString('provider-secret-1234', $this->payloadString($log->request_payload));
        $this->assertStringNotContainsString('response-token-secret', $this->payloadString($log->response_payload));
    }

    public function test_failed_provider_account_test_updates_status_and_logs_error(): void
    {
        $admin = $this->adminUser();
        $account = $this->providerAccount();

        Http::fake([
            'https://provider-a.example.test/api/provision' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        $this->actingAs($admin)
            ->post("/admin/provisioning-provider-accounts/{$account->id}/test")
            ->assertRedirect('/admin/provisioning-provider-accounts');

        $account->refresh();
        $this->assertSame('failed', $account->last_test_status);
        $this->assertSame('Provider test returned HTTP 401.', $account->last_test_error);

        $log = DB::table('provisioning_execution_logs')->where('provider_account_id', $account->id)->first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('provider_http_error', $log->error_code);
        $this->assertStringNotContainsString('provider-secret-1234', $this->payloadString($log->request_payload));
    }

    private function providerAccount(): ProvisioningProviderAccount
    {
        return ProvisioningProviderAccount::create([
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
            'response_external_id_path' => 'external_id',
            'response_status_path' => 'status',
        ]);
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function payloadString(mixed $payload): string
    {
        return is_string($payload) ? $payload : json_encode($payload);
    }
}
