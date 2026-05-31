<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisioningProviderAccountAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_account_create_and_edit_pages_use_scannable_form_sections(): void
    {
        $admin = $this->adminUser();
        $accountId = (string) Str::uuid();
        DB::table('provisioning_provider_accounts')->insert([
            'id' => $accountId,
            'slug' => 'provider-ui',
            'name' => 'Provider UI',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-ui.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'header',
            'auth_header_name' => 'X-API-Key',
            'api_key' => app('encrypter')->encrypt('provider-ui-secret', false),
            'api_key_last_four' => 'cret',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => json_encode(['source' => 'billing']),
            'response_external_id_path' => 'external_id',
            'response_status_path' => 'status',
            'response_config_path' => null,
            'callback_event_id_path' => 'event_id',
            'callback_external_id_path' => 'external_id',
            'callback_action_path' => 'action',
            'callback_status_path' => 'status',
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['/admin/provisioning-provider-accounts/create', "/admin/provisioning-provider-accounts/{$accountId}/edit"] as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk()
                ->assertSee('product-form-panel', false)
                ->assertSee('provider-account-form', false)
                ->assertSee('product-form-grid', false)
                ->assertSee('product-form-section--provider-identity', false)
                ->assertSee('product-form-section--provider-http', false)
                ->assertSee('product-form-section--provider-auth', false)
                ->assertSee('product-form-section--provider-mapping', false)
                ->assertSee('product-form-section--provider-callbacks', false)
                ->assertSee('data-provider-driver-select', false)
                ->assertSee('data-provider-base-url-field', false)
                ->assertSee('data-provider-generic-http-field', false)
                ->assertSee('data-provider-generic-mapping-section', false)
                ->assertSee('data-provider-callback-section', false)
                ->assertSee('data-provider-auth-type-field', false)
                ->assertSee('data-provider-cloudmini-auth-type', false)
                ->assertSee('data-provider-auth-select', false)
                ->assertSee('data-provider-auth-header-field', false)
                ->assertSee('data-provider-api-key-field', false)
                ->assertSee('Back to Provider Accounts')
                ->assertDontSee('<h2>Provider Callbacks</h2>', false)
                ->assertDontSee('<p><button type="submit">Save Provider Account</button></p>', false)
                ->assertSeeInOrder([
                    'Provider identity',
                    'HTTP connection',
                    'Authentication',
                    'Request & response mapping',
                    'Provider callbacks',
                ]);
        }
    }

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
        $this->assertSame('provider-secret-1234', app('encrypter')->decrypt($account->api_key, false));

        $this->actingAs($admin)
            ->get('/admin/provisioning-provider-accounts')
            ->assertOk()
            ->assertSee('Provider A Main')
            ->assertSee('Generic HTTP')
            ->assertSee('API key ...1234')
            ->assertDontSee('provider-secret-1234');
    }

    public function test_admin_can_create_cloudmini_provider_account_with_encrypted_secret(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/provisioning-provider-accounts', [
            'slug' => 'cloudmini-prod-1',
            'name' => 'Cloudmini Prod 1',
            'driver' => 'cloudmini_v3',
            'base_url' => 'https://cloudmini-prod-1.example.test',
            'provision_path' => '',
            'auth_type' => 'header',
            'auth_header_name' => 'X-API-Key',
            'api_key' => 'cloudmini-secret-1234',
            'enabled' => '1',
            'timeout_seconds' => 15,
            'request_template' => '',
            'response_external_id_path' => 'resource_snapshot.id',
            'response_status_path' => 'state',
            'response_config_path' => 'resource_snapshot',
            'callback_event_id_path' => 'event_id',
            'callback_external_id_path' => 'external_id',
            'callback_action_path' => 'action',
            'callback_status_path' => 'status',
        ])->assertRedirect('/admin/provisioning-provider-accounts');

        $account = DB::table('provisioning_provider_accounts')->where('slug', 'cloudmini-prod-1')->first();
        $this->assertSame('cloudmini_v3', $account->driver);
        $this->assertSame('1234', $account->api_key_last_four);
        $this->assertStringNotContainsString('cloudmini-secret-1234', $account->api_key);
        $this->assertSame('cloudmini-secret-1234', app('encrypter')->decrypt($account->api_key, false));

        $this->actingAs($admin)
            ->get('/admin/provisioning-provider-accounts')
            ->assertOk()
            ->assertSee('Cloudmini Prod 1')
            ->assertSee('Cloudmini V3')
            ->assertSee('Base URL (Server)')
            ->assertSee('https://cloudmini-prod-1.example.test')
            ->assertSee('API key ...1234')
            ->assertDontSee('cloudmini-secret-1234');
    }

    public function test_provider_account_index_groups_cloudmini_servers_by_driver(): void
    {
        $admin = $this->adminUser();
        $cloudminiOne = (string) Str::uuid();
        $cloudminiTwo = (string) Str::uuid();
        $generic = (string) Str::uuid();

        foreach ([
            [$cloudminiOne, 'cloudmini-prod-1', 'Cloudmini Prod 1', 'cloudmini_v3', 'https://cloudmini-prod-1.example.test', null, 'header', 'X-API-Key', '1234'],
            [$cloudminiTwo, 'cloudmini-prod-2', 'Cloudmini Prod 2', 'cloudmini_v3', 'https://cloudmini-prod-2.example.test', null, 'header', 'X-API-Key', '5678'],
            [$generic, 'provider-a-main', 'Provider A Main', 'generic_http', 'https://provider-a.example.test', '/api/provision', 'bearer', null, '9999'],
        ] as [$id, $slug, $name, $driver, $baseUrl, $provisionPath, $authType, $authHeaderName, $lastFour]) {
            DB::table('provisioning_provider_accounts')->insert([
                'id' => $id,
                'slug' => $slug,
                'name' => $name,
                'driver' => $driver,
                'base_url' => $baseUrl,
                'provision_path' => $provisionPath,
                'auth_type' => $authType,
                'auth_header_name' => $authHeaderName,
                'api_key' => app('encrypter')->encrypt("secret-{$lastFour}", false),
                'api_key_last_four' => $lastFour,
                'enabled' => true,
                'timeout_seconds' => 15,
                'request_template' => '{}',
                'response_external_id_path' => $driver === 'cloudmini_v3' ? 'resource_snapshot.id' : 'external_id',
                'response_status_path' => $driver === 'cloudmini_v3' ? 'state' : 'status',
                'response_config_path' => $driver === 'cloudmini_v3' ? 'resource_snapshot' : null,
                'created_by_id' => $admin->id,
                'updated_by_id' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->get('/admin/provisioning-provider-accounts')
            ->assertOk()
            ->assertSeeInOrder([
                'Cloudmini V3',
                '2 servers',
                'Cloudmini Prod 1',
                'https://cloudmini-prod-1.example.test',
                'API key ...1234',
                'Cloudmini Prod 2',
                'https://cloudmini-prod-2.example.test',
                'API key ...5678',
                'Generic HTTP',
                'Provider A Main',
            ])
            ->assertSee('Add Cloudmini Server')
            ->assertSee('Load Groups')
            ->assertSee("/admin/provisioning-provider-accounts/{$cloudminiOne}/inventory/groups")
            ->assertSee('Stable Billing Group ID')
            ->assertSee('Server Group ID')
            ->assertSee('Base URL (Server)')
            ->assertDontSee('secret-1234')
            ->assertDontSee('secret-5678');
    }

    public function test_admin_can_fetch_cloudmini_inventory_groups_for_provider_account(): void
    {
        $admin = $this->adminUser();
        $accountId = (string) Str::uuid();
        DB::table('provisioning_provider_accounts')->insert([
            'id' => $accountId,
            'slug' => 'cloudmini-prod-1',
            'name' => 'Cloudmini Prod 1',
            'driver' => 'cloudmini_v3',
            'base_url' => 'https://cloudmini-prod-1.example.test',
            'provision_path' => null,
            'auth_type' => 'header',
            'auth_header_name' => 'X-API-Key',
            'api_key' => app('encrypter')->encrypt('cloudmini-secret-1234', false),
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => '{}',
            'response_external_id_path' => 'resource_snapshot.id',
            'response_status_path' => 'state',
            'response_config_path' => 'resource_snapshot',
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://cloudmini-prod-1.example.test/api/v3/inventory/groups?kind=ipv4_dc' => Http::response([
                'data' => [
                    [
                        'id' => 'group-uuid-1',
                        'name' => 'VN Datacenter',
                        'billing_group_id' => 'vn-dc',
                        'sell_state' => 'sellable',
                        'allocatable_units' => 12,
                    ],
                ],
            ]),
            'https://cloudmini-prod-1.example.test/api/v3/inventory/groups?kind=residential' => Http::response([
                'data' => [
                    [
                        'id' => 'group-uuid-2',
                        'name' => 'VN Residential',
                        'billing_group_id' => 'vn-residential',
                        'sell_state' => 'limited',
                        'free_ip_count' => 4,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->getJson("/admin/provisioning-provider-accounts/{$accountId}/inventory/groups")
            ->assertOk()
            ->assertJsonPath('account.slug', 'cloudmini-prod-1')
            ->assertJsonPath('groups.0.kind', 'ipv4_dc')
            ->assertJsonPath('groups.0.billing_group_id', 'vn-dc')
            ->assertJsonPath('groups.1.kind', 'residential')
            ->assertJsonPath('groups.1.billing_group_id', 'vn-residential');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://cloudmini-prod-1.example.test/api/v3/inventory/groups?kind=ipv4_dc'
            && $request->hasHeader('X-API-Key', 'cloudmini-secret-1234'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://cloudmini-prod-1.example.test/api/v3/inventory/groups?kind=residential'
            && $request->hasHeader('X-API-Key', 'cloudmini-secret-1234'));
    }

    public function test_admin_can_create_cloudmini_provider_account_without_generic_mapping_fields(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/provisioning-provider-accounts', [
            'slug' => 'cloudmini-prod-2',
            'name' => 'Cloudmini Prod 2',
            'driver' => 'cloudmini_v3',
            'base_url' => 'https://cloudmini-prod-2.example.test',
            'auth_type' => 'header',
            'auth_header_name' => 'X-API-Key',
            'api_key' => 'cloudmini-secret-5678',
            'enabled' => '1',
            'timeout_seconds' => 15,
        ])->assertRedirect('/admin/provisioning-provider-accounts');

        $account = DB::table('provisioning_provider_accounts')->where('slug', 'cloudmini-prod-2')->first();
        $this->assertSame('cloudmini_v3', $account->driver);
        $this->assertNull($account->provision_path);
        $this->assertSame([], json_decode($account->request_template, true));
        $this->assertSame('resource_snapshot.id', $account->response_external_id_path);
        $this->assertSame('state', $account->response_status_path);
        $this->assertSame('resource_snapshot', $account->response_config_path);
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
            'api_key' => app('encrypter')->encrypt('provider-secret-1234', false),
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
        $this->assertSame('provider-secret-1234', app('encrypter')->decrypt($account->api_key, false));
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
