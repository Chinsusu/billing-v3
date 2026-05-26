<?php

namespace App\Services\Security;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Str;

class ApiKeyManager
{
    /**
     * @param  list<string>  $scopes
     * @return array{0: ApiKey, 1: string}
     */
    public function create(User $user, string $name, array $scopes, ?int $rateLimitPerMinute = null): array
    {
        $secret = 'bv3_'.Str::random(48);
        $prefix = substr($secret, 0, 12);
        $rateLimitPerMinute ??= (int) config('api_keys.default_rate_limit_per_minute', 60);

        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => $this->hash($secret),
            'scopes' => array_values(array_unique($scopes)),
            'rate_limit_per_minute' => max(1, $rateLimitPerMinute),
        ]);

        return [$apiKey, $secret];
    }

    public function findValid(string $plainKey): ?ApiKey
    {
        $apiKey = ApiKey::with('user')
            ->where('prefix', substr($plainKey, 0, 12))
            ->whereNull('revoked_at')
            ->first();

        if ($apiKey === null || ! hash_equals($apiKey->key_hash, $this->hash($plainKey))) {
            return null;
        }

        return $apiKey;
    }

    private function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }
}
