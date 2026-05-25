<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServiceAutoRenewalProcessor
{
    private const DUE_WINDOW_HOURS = 24;
    private const RETRY_DELAY_MINUTES = 60;
    private const STALE_PROCESSING_MINUTES = 15;

    public function __construct(
        private readonly ServiceRenewalService $renewals,
        private readonly NotificationOutbox $notifications,
    ) {}

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int}
     */
    public function process(int $limit = 50): array
    {
        $counts = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'skipped' => 0];
        $limit = max(1, $limit);

        $this->dueServices($limit)->each(function (Service $service) use (&$counts): void {
            $result = $this->processOne($service);
            $counts[$result]++;

            if ($result === 'succeeded' || $result === 'failed') {
                $counts['processed']++;
            }
        });

        return $counts;
    }

    private function dueServices(int $limit)
    {
        return Service::query()
            ->with(['user', 'product', 'orderItem'])
            ->where('status', 'active')
            ->where('auto_renew_enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addHours(self::DUE_WINDOW_HOURS))
            ->whereDoesntHave('cancellations', function ($query): void {
                $query->whereIn('status', ['requested', 'scheduled', 'queued']);
            })
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function processOne(Service $service): string
    {
        $service->loadMissing(['user', 'product', 'orderItem']);

        if ($service->expires_at === null || $service->user === null) {
            return 'skipped';
        }

        $targetExpiresAt = $service->expires_at->copy();
        $attempt = $this->attemptFor($service, $targetExpiresAt);

        if ($attempt->status === 'succeeded') {
            return 'skipped';
        }

        if (! $attempt->wasRecentlyCreated && $attempt->status === 'processing' && $attempt->updated_at?->gt(now()->subMinutes(self::STALE_PROCESSING_MINUTES))) {
            return 'skipped';
        }

        if ($attempt->status === 'failed' && $attempt->next_attempt_at?->isFuture()) {
            return 'skipped';
        }

        $amount = (int) ($service->product?->price_amount ?? $service->orderItem?->unit_amount ?? 0);
        $currency = strtoupper((string) ($service->product?->currency ?? $service->orderItem?->currency ?? 'VND'));

        $attempt->forceFill([
            'status' => 'processing',
            'attempts' => $attempt->attempts + 1,
            'amount' => $amount > 0 ? $amount : null,
            'currency' => $currency !== '' ? $currency : null,
            'next_attempt_at' => null,
            'last_error' => null,
        ])->save();

        try {
            $renewed = $this->renewals->renew($service, $service->user);
        } catch (Throwable $exception) {
            $this->markFailed($attempt, $service, $targetExpiresAt, $exception);

            return 'failed';
        }

        $attempt->forceFill([
            'status' => 'succeeded',
            'renewed_expires_at' => $renewed->expires_at,
            'next_attempt_at' => null,
            'last_error' => null,
        ])->save();

        return 'succeeded';
    }

    private function attemptFor(Service $service, Carbon $targetExpiresAt): ServiceAutoRenewalAttempt
    {
        $idempotencyKey = "service-auto-renew:{$service->id}:{$targetExpiresAt->toISOString()}";

        return ServiceAutoRenewalAttempt::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'service_id' => $service->id,
                'user_id' => $service->user_id,
                'expires_at' => $targetExpiresAt,
                'status' => 'processing',
                'attempts' => 0,
            ],
        );
    }

    private function markFailed(ServiceAutoRenewalAttempt $attempt, Service $service, Carbon $targetExpiresAt, Throwable $exception): void
    {
        $message = $this->exceptionMessage($exception);
        $nextAttemptAt = now()->addMinutes(self::RETRY_DELAY_MINUTES);

        $attempt->forceFill([
            'status' => 'failed',
            'next_attempt_at' => $nextAttemptAt,
            'last_error' => $message,
        ])->save();

        if ($service->user === null) {
            return;
        }

        $this->notifications->enqueue(
            $service->user,
            'service_auto_renew_failed',
            $service->user->email,
            'Auto-renew failed',
            "Auto-renew failed for service {$service->product_name}.",
            'service',
            $service->id,
            "service-auto-renew-failed:{$attempt->id}:{$attempt->attempts}",
            [
                'service_id' => $service->id,
                'product_name' => $service->product_name,
                'expires_at' => $targetExpiresAt->toISOString(),
                'next_attempt_at' => $nextAttemptAt->toISOString(),
                'error' => $message,
            ],
        );
    }

    private function exceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $first = collect($exception->errors())->flatten()->first();

            if (is_string($first) && $first !== '') {
                return Str::limit($first, 1000, '');
            }
        }

        return Str::limit($exception->getMessage(), 1000, '');
    }
}
