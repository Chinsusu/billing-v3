<?php

namespace App\Services\Security;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Cache;

class ApiKeyRateLimiter
{
    /**
     * @return array{allowed: bool, limit: int, remaining: int, reset_at: int, retry_after: int}
     */
    public function hit(ApiKey $apiKey): array
    {
        $limit = $this->limitFor($apiKey);
        $now = now();
        $windowStartedAt = $now->copy()->startOfMinute()->timestamp;
        $resetAt = $windowStartedAt + 60;
        $retryAfter = max(1, $resetAt - $now->timestamp);
        $cacheKey = "api-key-rate:{$apiKey->id}:{$windowStartedAt}";

        Cache::add($cacheKey, 0, $now->copy()->addSeconds($retryAfter + 1));
        $count = (int) Cache::increment($cacheKey);

        return [
            'allowed' => $count <= $limit,
            'limit' => $limit,
            'remaining' => max(0, $limit - $count),
            'reset_at' => $resetAt,
            'retry_after' => $retryAfter,
        ];
    }

    public function limitFor(ApiKey $apiKey): int
    {
        return max(1, (int) ($apiKey->rate_limit_per_minute ?: config('api_keys.default_rate_limit_per_minute', 60)));
    }
}
