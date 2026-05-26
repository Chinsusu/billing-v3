<?php

namespace App\Services\Services;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use Illuminate\Support\Carbon;

class ServiceAutoRenewalPolicy
{
    public const DEFAULT_ALLOWED = true;

    public const DEFAULT_WINDOW_HOURS = 24;

    public const DEFAULT_RETRY_DELAY_MINUTES = 60;

    public const DEFAULT_MAX_ATTEMPTS = 3;

    public const MIN_WINDOW_HOURS = 1;

    public const MAX_WINDOW_HOURS = 24;

    public const MIN_RETRY_DELAY_MINUTES = 5;

    public const MAX_RETRY_DELAY_MINUTES = 10080;

    public const MIN_MAX_ATTEMPTS = 1;

    public const MAX_MAX_ATTEMPTS = 20;

    /**
     * @return array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}
     */
    public function forService(Service $service): array
    {
        return $this->forProduct($service->product);
    }

    /**
     * @return array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}
     */
    public function forProduct(?Product $product): array
    {
        return [
            'allowed' => (bool) ($product?->auto_renew_allowed ?? self::DEFAULT_ALLOWED),
            'window_hours' => $this->clampInt($product?->auto_renew_window_hours, self::DEFAULT_WINDOW_HOURS, self::MIN_WINDOW_HOURS, self::MAX_WINDOW_HOURS),
            'retry_delay_minutes' => $this->clampInt($product?->auto_renew_retry_delay_minutes, self::DEFAULT_RETRY_DELAY_MINUTES, self::MIN_RETRY_DELAY_MINUTES, self::MAX_RETRY_DELAY_MINUTES),
            'max_attempts' => $this->clampInt($product?->auto_renew_max_attempts, self::DEFAULT_MAX_ATTEMPTS, self::MIN_MAX_ATTEMPTS, self::MAX_MAX_ATTEMPTS),
        ];
    }

    /**
     * @param  array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}|null  $policy
     */
    public function isDue(Service $service, ?Carbon $now = null, ?array $policy = null): bool
    {
        if ($service->expires_at === null) {
            return false;
        }

        $policy ??= $this->forService($service);
        if (! $policy['allowed']) {
            return false;
        }

        $now ??= now();

        return $service->expires_at->lte($now->copy()->addHours($policy['window_hours']));
    }

    /**
     * @param  array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}  $policy
     */
    public function isExhausted(ServiceAutoRenewalAttempt $attempt, array $policy): bool
    {
        return $attempt->status === 'failed' && $attempt->attempts >= $policy['max_attempts'];
    }

    /**
     * @param  array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}  $policy
     */
    public function nextAttemptAt(int $nextAttemptCount, array $policy): ?Carbon
    {
        if ($nextAttemptCount >= $policy['max_attempts']) {
            return null;
        }

        return now()->addMinutes($policy['retry_delay_minutes']);
    }

    private function clampInt(mixed $value, int $default, int $min, int $max): int
    {
        $value = is_numeric($value) ? (int) $value : $default;

        return max($min, min($max, $value));
    }
}
