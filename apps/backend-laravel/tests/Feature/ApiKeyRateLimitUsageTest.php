<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiKeyUsageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyRateLimitUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_api_request_creates_usage_log_and_customer_can_view_recent_usage(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s41-usage@example.test');
        [$plainKey, $apiKey] = $this->createApiKey($user, ['account.read'], 60);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->withHeader('User-Agent', 'S41 usage test')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('X-RateLimit-Remaining', '59')
            ->assertJsonPath('data.email', 's41-usage@example.test');

        $this->assertDatabaseHas('api_key_usage_logs', [
            'api_key_id' => $apiKey->id,
            'user_id' => $user->id,
            'api_key_prefix' => $apiKey->prefix,
            'route_name' => 'api.v1.me',
            'method' => 'GET',
            'path' => 'api/v1/me',
            'status_code' => 200,
            'error_reason' => null,
        ]);

        $this->actingAs($user)
            ->get('/api-keys')
            ->assertOk()
            ->assertSee('Recent Usage')
            ->assertSee('api.v1.me')
            ->assertSee('200');
    }

    public function test_scope_failure_creates_usage_log(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s41-scope@example.test');
        [$plainKey, $apiKey] = $this->createApiKey($user, [], 60);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('X-RateLimit-Remaining', '60');

        $this->assertDatabaseHas('api_key_usage_logs', [
            'api_key_id' => $apiKey->id,
            'user_id' => $user->id,
            'api_key_prefix' => $apiKey->prefix,
            'status_code' => 403,
            'error_reason' => 'missing_scope',
        ]);
    }

    public function test_per_key_rate_limit_returns_429_and_logs_limited_request(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s41-limit@example.test');
        [$plainKey, $apiKey] = $this->createApiKey($user, ['account.read'], 2);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '1');

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/api/v1/me')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertJsonPath('message', 'Too many requests.');

        $this->assertSame(3, ApiKeyUsageLog::where('api_key_id', $apiKey->id)->count());
        $this->assertDatabaseHas('api_key_usage_logs', [
            'api_key_id' => $apiKey->id,
            'status_code' => 429,
            'error_reason' => 'rate_limited',
        ]);
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: string, 1: ApiKey}
     */
    private function createApiKey(User $user, array $scopes, int $rateLimitPerMinute): array
    {
        $response = $this->actingAs($user)
            ->post('/api-keys', [
                'name' => "S41 {$rateLimitPerMinute}",
                'scopes' => $scopes,
                'rate_limit_per_minute' => $rateLimitPerMinute,
            ])
            ->assertRedirect('/api-keys')
            ->assertSessionHas('plain_api_key');

        $plainKey = (string) $response->baseResponse->getSession()->get('plain_api_key');
        $apiKey = ApiKey::where('name', "S41 {$rateLimitPerMinute}")->firstOrFail();

        return [$plainKey, $apiKey];
    }

    private function customer(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
