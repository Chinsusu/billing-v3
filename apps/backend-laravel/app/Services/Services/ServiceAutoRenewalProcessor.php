<?php

namespace App\Services\Services;

use App\Exceptions\InsufficientWalletBalance;
use App\Exceptions\ServiceAutoRenewalSkipped;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServiceAutoRenewalProcessor
{
    private const CUSTOMER_ERROR_MESSAGE = 'Auto-renew failed. We will retry automatically.';

    private const STALE_PROCESSING_MINUTES = 15;

    private readonly ServiceAutoRenewalPolicy $policy;

    public function __construct(
        private readonly ServiceRenewalService $renewals,
        private readonly NotificationOutbox $notifications,
        ?ServiceAutoRenewalPolicy $policy = null,
    ) {
        $this->policy = $policy ?? app(ServiceAutoRenewalPolicy::class);
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int}
     */
    public function process(int $limit = 50): array
    {
        $counts = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'skipped' => 0];
        $limit = max(1, $limit);

        foreach ($this->dueServices() as $service) {
            if ($counts['processed'] >= $limit) {
                break;
            }

            $result = $this->processOne($service);
            $counts[$result]++;

            if ($result === 'succeeded' || $result === 'failed') {
                $counts['processed']++;
            }
        }

        return $counts;
    }

    private function dueServices()
    {
        $now = now();
        $processingCutoff = $now->copy()->subMinutes(self::STALE_PROCESSING_MINUTES);

        return Service::query()
            ->with(['user', 'product', 'orderItem'])
            ->where('status', 'active')
            ->where('auto_renew_enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addHours(ServiceAutoRenewalPolicy::MAX_WINDOW_HOURS))
            ->where(function ($query): void {
                $query->whereNull('product_id')
                    ->orWhereHas('product', function ($query): void {
                        $query->where('auto_renew_allowed', true);
                    });
            })
            ->whereDoesntHave('cancellations', function ($query): void {
                $query->whereIn('status', ['requested', 'scheduled', 'queued']);
            })
            ->whereDoesntHave('autoRenewalAttempts', function ($query) use ($now, $processingCutoff): void {
                $query->whereColumn('service_auto_renewal_attempts.expires_at', 'services.expires_at')
                    ->where(function ($query) use ($now, $processingCutoff): void {
                        $query->where('status', 'succeeded')
                            ->orWhere(function ($query) use ($now): void {
                                $query->where('status', 'failed')
                                    ->where(function ($query) use ($now): void {
                                        $query->whereNull('next_attempt_at')
                                            ->orWhere('next_attempt_at', '>', $now);
                                    });
                            })
                            ->orWhere(function ($query) use ($processingCutoff): void {
                                $query->where('status', 'processing')
                                    ->where('updated_at', '>', $processingCutoff);
                            });
                    });
            })
            ->orderBy('expires_at')
            ->orderBy('id')
            ->cursor();
    }

    public function processService(Service $service): string
    {
        return $this->processOne($service);
    }

    private function processOne(Service $service): string
    {
        $service->loadMissing(['user', 'product', 'orderItem']);

        if ($service->expires_at === null || $service->user === null) {
            return 'skipped';
        }

        $policy = $this->policy->forService($service);
        if (! $this->policy->isDue($service, now(), $policy)) {
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

        if ($this->policy->isExhausted($attempt, $policy)) {
            return 'skipped';
        }

        $amount = (int) ($service->product?->price_amount ?? $service->orderItem?->unit_amount ?? 0);
        $currency = strtoupper((string) ($service->product?->currency ?? $service->orderItem?->currency ?? 'VND'));

        $attempt->forceFill([
            'status' => 'processing',
            'amount' => $amount > 0 ? $amount : null,
            'currency' => $currency !== '' ? $currency : null,
            'next_attempt_at' => null,
            'last_error' => null,
        ])->save();

        try {
            $renewed = $this->renewals->renew(
                $service,
                $service->user,
                fn (Service $lockedService): bool => $this->isStillEligible($lockedService, $targetExpiresAt),
            );
        } catch (ServiceAutoRenewalSkipped) {
            $this->markSkipped($attempt);

            return 'skipped';
        } catch (Throwable $exception) {
            $this->markFailed($attempt, $service, $targetExpiresAt, $exception, $policy);

            return 'failed';
        }

        $attempt->forceFill([
            'status' => 'succeeded',
            'attempts' => $attempt->attempts + 1,
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

    private function isStillEligible(Service $service, Carbon $targetExpiresAt): bool
    {
        if (! $service->auto_renew_enabled || $service->status !== 'active' || $service->expires_at === null) {
            return false;
        }

        if (! $service->expires_at->isSameSecond($targetExpiresAt)) {
            return false;
        }

        if (! $this->policy->isDue($service, now())) {
            return false;
        }

        return ! $service->cancellations()
            ->whereIn('status', ['requested', 'scheduled', 'queued'])
            ->exists();
    }

    private function markSkipped(ServiceAutoRenewalAttempt $attempt): void
    {
        $attempt->forceFill([
            'status' => 'skipped',
            'next_attempt_at' => null,
            'last_error' => null,
        ])->save();
    }

    /**
     * @param  array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}  $policy
     */
    private function markFailed(ServiceAutoRenewalAttempt $attempt, Service $service, Carbon $targetExpiresAt, Throwable $exception, array $policy): void
    {
        $message = $this->exceptionMessage($exception);
        $nextAttemptCount = $attempt->attempts + 1;
        $nextAttemptAt = $this->policy->nextAttemptAt($nextAttemptCount, $policy);

        $attempt->forceFill([
            'status' => 'failed',
            'attempts' => $nextAttemptCount,
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
                'next_attempt_at' => $nextAttemptAt?->toISOString(),
                'error' => $message,
            ],
        );
    }

    private function exceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof InsufficientWalletBalance) {
            return $exception->getMessage();
        }

        if ($exception instanceof ValidationException) {
            $first = collect($exception->errors())
                ->only(['service', 'wallet'])
                ->flatten()
                ->first();

            if (is_string($first) && $first !== '') {
                return Str::limit($first, 1000, '');
            }
        }

        return self::CUSTOMER_ERROR_MESSAGE;
    }
}
