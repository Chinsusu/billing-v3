<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiKeyUsageLog;
use App\Services\Security\ApiKeyManager;
use App\Services\Security\ApiKeyRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyManager $apiKeys,
        private readonly ApiKeyRateLimiter $rateLimiter,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $apiKey = $this->apiKeys->findValid(trim(substr($header, 7)));
        if ($apiKey === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($scopes as $scope) {
            if (! $apiKey->hasScope($scope)) {
                $this->logUsage($apiKey, $request, 403, 'missing_scope');

                return response()->json(['message' => 'Forbidden.'], 403, $this->rateLimitHeaders($apiKey));
            }
        }

        $rateLimit = $this->rateLimiter->hit($apiKey);
        if (! $rateLimit['allowed']) {
            $this->logUsage($apiKey, $request, 429, 'rate_limited');

            return response()->json(['message' => 'Too many requests.'], 429, [
                ...$this->rateLimitHeaders($apiKey, $rateLimit),
                'Retry-After' => (string) $rateLimit['retry_after'],
            ]);
        }

        $request->attributes->set('api_key', $apiKey);
        $request->setUserResolver(fn () => $apiKey->user);

        $response = $next($request);

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $this->logUsage($apiKey, $request, $response->getStatusCode());

        foreach ($this->rateLimitHeaders($apiKey, $rateLimit) as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    /**
     * @param  array{allowed: bool, limit: int, remaining: int, reset_at: int, retry_after: int}|null  $rateLimit
     * @return array<string, string>
     */
    private function rateLimitHeaders(ApiKey $apiKey, ?array $rateLimit = null): array
    {
        $limit = $rateLimit['limit'] ?? $this->rateLimiter->limitFor($apiKey);
        $remaining = $rateLimit['remaining'] ?? $limit;
        $resetAt = $rateLimit['reset_at'] ?? now()->copy()->startOfMinute()->addMinute()->timestamp;

        return [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) $resetAt,
        ];
    }

    private function logUsage(ApiKey $apiKey, Request $request, int $statusCode, ?string $errorReason = null): void
    {
        ApiKeyUsageLog::create([
            'api_key_id' => $apiKey->id,
            'user_id' => $apiKey->user_id,
            'api_key_prefix' => $apiKey->prefix,
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'error_reason' => $errorReason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
