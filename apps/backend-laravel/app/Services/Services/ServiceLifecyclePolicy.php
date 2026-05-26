<?php

namespace App\Services\Services;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Carbon;

class ServiceLifecyclePolicy
{
    public function forProduct(Product $product): array
    {
        return [
            'source' => $product->lifecycle_source ?: 'local_policy',
            'unit' => $product->lifecycle_unit ?: 'day',
            'count' => (int) ($product->lifecycle_count ?: $product->duration_days ?: 30),
            'provider_lifecycle_path' => $product->provider_lifecycle_path,
            'ordered_at_path' => $product->provider_lifecycle_ordered_at_path,
            'expires_at_path' => $product->provider_lifecycle_expires_at_path,
            'date_format' => $product->provider_lifecycle_date_format ?: 'iso8601',
            'timezone' => $product->provider_lifecycle_timezone ?: 'UTC',
        ];
    }

    public function forService(Service $service): array
    {
        $policy = $service->meta['lifecycle_policy'] ?? null;
        if (is_array($policy)) {
            return $this->normalize($policy, (int) ($service->meta['duration_days'] ?? $service->orderItem?->duration_days ?? 30));
        }

        return $this->normalize([
            'source' => 'local_policy',
            'unit' => 'day',
            'count' => (int) ($service->meta['duration_days'] ?? $service->orderItem?->duration_days ?? 30),
        ]);
    }

    public function expiresAt(Carbon $base, array $policy): Carbon
    {
        $policy = $this->normalize($policy);

        return match ($policy['unit']) {
            'calendar_month' => $base->copy()->addMonthsNoOverflow($policy['count']),
            default => $base->copy()->addDays($policy['count']),
        };
    }

    private function normalize(array $policy, int $fallbackDays = 30): array
    {
        $count = (int) ($policy['count'] ?? $fallbackDays);
        if ($count <= 0) {
            $count = $fallbackDays > 0 ? $fallbackDays : 30;
        }

        return [
            'source' => (string) ($policy['source'] ?? 'local_policy'),
            'unit' => (string) ($policy['unit'] ?? 'day'),
            'count' => $count,
            'provider_lifecycle_path' => $policy['provider_lifecycle_path'] ?? null,
            'ordered_at_path' => $policy['ordered_at_path'] ?? null,
            'expires_at_path' => $policy['expires_at_path'] ?? null,
            'date_format' => (string) ($policy['date_format'] ?? 'iso8601'),
            'timezone' => (string) ($policy['timezone'] ?? 'UTC'),
        ];
    }
}
