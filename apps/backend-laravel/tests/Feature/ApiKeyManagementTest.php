<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ApiKey;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_use_and_revoke_api_key(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s37-api@example.test');

        $response = $this->actingAs($user)
            ->post('/api-keys', [
                'name' => 'CLI token',
                'scopes' => ['account.read'],
            ])
            ->assertRedirect('/api-keys')
            ->assertSessionHas('plain_api_key');

        $plainKey = (string) $response->baseResponse->getSession()->get('plain_api_key');
        $apiKey = ApiKey::where('name', 'CLI token')->firstOrFail();

        $this->assertStringStartsWith($apiKey->prefix, $plainKey);
        $this->assertStringNotContainsString($plainKey, (string) $apiKey->key_hash);
        $this->assertSame(['account.read'], $apiKey->scopes);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 's37-api@example.test');

        $this->assertNotNull(ApiKey::findOrFail($apiKey->id)->last_used_at);

        $this->actingAs($user)
            ->post("/api-keys/{$apiKey->id}/revoke")
            ->assertRedirect('/api-keys');

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->assertSame(1, AdminAuditLog::where('action', 'api_key_created')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'api_key_revoked')->count());
    }

    public function test_api_key_requires_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s37-scope@example.test');

        $response = $this->actingAs($user)
            ->post('/api-keys', [
                'name' => 'No account scope',
                'scopes' => [],
            ])
            ->assertRedirect('/api-keys');

        $plainKey = (string) $response->baseResponse->getSession()->get('plain_api_key');

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertForbidden();
    }

    private function customer(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
