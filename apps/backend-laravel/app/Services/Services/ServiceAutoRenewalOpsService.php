<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServiceAutoRenewalOpsService
{
    private const ATTEMPT_AUDIT_FIELDS = ['status', 'attempts', 'next_attempt_at', 'renewed_expires_at', 'last_error'];

    private const SERVICE_AUDIT_FIELDS = ['status', 'expires_at', 'auto_renew_enabled'];

    public function __construct(
        private readonly ServiceAutoRenewalPolicy $policy,
        private readonly ServiceAutoRenewalProcessor $processor,
        private readonly AuditLogger $audit,
    ) {}

    public function retryNow(ServiceAutoRenewalAttempt $attempt, User $actor, string $reason, Request $request): string
    {
        $attempt = $this->loadAttempt($attempt);
        $policy = $this->guardRetryableAttempt($attempt);
        $before = $this->audit->snapshot($attempt, self::ATTEMPT_AUDIT_FIELDS);

        if ($this->policy->isExhausted($attempt, $policy)) {
            $attempt->forceFill([
                'attempts' => max(0, $policy['max_attempts'] - 1),
                'next_attempt_at' => null,
            ])->save();
        } elseif ($attempt->next_attempt_at?->isFuture()) {
            $attempt->forceFill(['next_attempt_at' => null])->save();
        }

        $result = $this->processor->processService($attempt->service);
        $attempt->refresh();
        [$beforeChanges, $afterChanges] = $this->audit->diff($before, $this->audit->snapshot($attempt, self::ATTEMPT_AUDIT_FIELDS));
        $this->audit->record($actor, 'service_auto_renew_retried', $attempt, $beforeChanges, $afterChanges, [
            'reason' => $reason,
            'result' => $result,
            'service_id' => $attempt->service_id,
            'user_id' => $attempt->user_id,
            'attempt_id' => $attempt->id,
        ], $request, $attempt->service?->product_name);

        return $result;
    }

    public function resetAttempts(ServiceAutoRenewalAttempt $attempt, User $actor, string $reason, Request $request): void
    {
        $attempt = $this->loadAttempt($attempt);
        $policy = $this->guardRetryableAttempt($attempt);

        if (! $this->policy->isExhausted($attempt, $policy)) {
            throw ValidationException::withMessages(['attempt' => 'Only exhausted failed attempts can be reset.']);
        }

        $before = $this->audit->snapshot($attempt, self::ATTEMPT_AUDIT_FIELDS);
        $attempt->forceFill([
            'status' => 'failed',
            'attempts' => 0,
            'next_attempt_at' => now(),
            'last_error' => null,
        ])->save();
        $attempt->refresh();
        [$beforeChanges, $afterChanges] = $this->audit->diff($before, $this->audit->snapshot($attempt, self::ATTEMPT_AUDIT_FIELDS));
        $this->audit->record($actor, 'service_auto_renew_reset', $attempt, $beforeChanges, $afterChanges, [
            'reason' => $reason,
            'service_id' => $attempt->service_id,
            'user_id' => $attempt->user_id,
            'attempt_id' => $attempt->id,
        ], $request, $attempt->service?->product_name);
    }

    public function toggleService(Service $service, bool $enabled, User $actor, string $reason, Request $request): void
    {
        $service->loadMissing('product');

        if ($enabled && $service->status !== 'active') {
            throw ValidationException::withMessages(['service' => 'Only active services can enable auto-renew.']);
        }

        if ($enabled && ! $this->policy->forService($service)['allowed']) {
            throw ValidationException::withMessages(['service' => 'Auto-renew is not available for this product.']);
        }

        $before = $this->audit->snapshot($service, self::SERVICE_AUDIT_FIELDS);
        $service->forceFill(['auto_renew_enabled' => $enabled])->save();
        $service->refresh();
        [$beforeChanges, $afterChanges] = $this->audit->diff($before, $this->audit->snapshot($service, self::SERVICE_AUDIT_FIELDS));
        $this->audit->record($actor, 'service_auto_renew_toggled', $service, $beforeChanges, $afterChanges, [
            'reason' => $reason,
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'enabled' => $enabled,
        ], $request, $service->product_name);
    }

    /**
     * @param  array<int, string>  $attemptIds
     * @return array{applied: int, skipped: int}
     */
    public function bulkRetry(array $attemptIds, User $actor, string $reason, Request $request): array
    {
        $counts = ['applied' => 0, 'skipped' => 0];
        $selectedAttemptIds = $this->uniqueAttemptIds($attemptIds);
        $appliedAttemptIds = [];
        $skippedAttemptIds = [];
        $attempts = $this->attemptsForBulk($selectedAttemptIds);

        foreach ($attempts as $attempt) {
            try {
                $this->retryNow($attempt, $actor, $reason, $request);
                $counts['applied']++;
                $appliedAttemptIds[] = $attempt->id;
            } catch (ValidationException $exception) {
                $counts['skipped']++;
                $skippedAttemptIds[$attempt->id] = $this->firstValidationError($exception);
            }
        }

        foreach (array_values(array_diff($selectedAttemptIds, $attempts->pluck('id')->all())) as $missingAttemptId) {
            $counts['skipped']++;
            $skippedAttemptIds[$missingAttemptId] = 'Attempt not found.';
        }

        $this->audit->record($actor, 'service_auto_renew_bulk_retry', $actor, [], [], [
            'reason' => $reason,
            'selected' => count($selectedAttemptIds),
            'applied' => $counts['applied'],
            'skipped' => $counts['skipped'],
            'selected_attempt_ids' => $selectedAttemptIds,
            'applied_attempt_ids' => $appliedAttemptIds,
            'skipped_attempt_ids' => $skippedAttemptIds,
        ], $request, $actor->email);

        return $counts;
    }

    /**
     * @param  array<int, string>  $attemptIds
     * @return array{applied: int, skipped: int}
     */
    public function bulkDisable(array $attemptIds, User $actor, string $reason, Request $request): array
    {
        $counts = ['applied' => 0, 'skipped' => 0];
        $selectedAttemptIds = $this->uniqueAttemptIds($attemptIds);
        $appliedAttemptIds = [];
        $skippedAttemptIds = [];
        $seenServiceIds = [];
        $attempts = $this->attemptsForBulk($selectedAttemptIds);

        foreach ($attempts as $attempt) {
            $service = $attempt->service;
            if ($service === null) {
                $counts['skipped']++;
                $skippedAttemptIds[$attempt->id] = 'Attempt is missing its service.';

                continue;
            }

            if (isset($seenServiceIds[$service->id])) {
                $counts['skipped']++;
                $skippedAttemptIds[$attempt->id] = 'Service already handled in this bulk action.';

                continue;
            }

            $seenServiceIds[$service->id] = true;
            $this->toggleService($service, false, $actor, $reason, $request);
            $counts['applied']++;
            $appliedAttemptIds[] = $attempt->id;
        }

        foreach (array_values(array_diff($selectedAttemptIds, $attempts->pluck('id')->all())) as $missingAttemptId) {
            $counts['skipped']++;
            $skippedAttemptIds[$missingAttemptId] = 'Attempt not found.';
        }

        $this->audit->record($actor, 'service_auto_renew_bulk_disable', $actor, [], [], [
            'reason' => $reason,
            'selected' => count($selectedAttemptIds),
            'applied' => $counts['applied'],
            'skipped' => $counts['skipped'],
            'selected_attempt_ids' => $selectedAttemptIds,
            'applied_attempt_ids' => $appliedAttemptIds,
            'skipped_attempt_ids' => $skippedAttemptIds,
        ], $request, $actor->email);

        return $counts;
    }

    private function loadAttempt(ServiceAutoRenewalAttempt $attempt): ServiceAutoRenewalAttempt
    {
        return $attempt->load([
            'service.product',
            'service.user',
            'service.orderItem',
            'service.cancellations',
            'user',
        ]);
    }

    /**
     * @return array{allowed: bool, window_hours: int, retry_delay_minutes: int, max_attempts: int}
     */
    private function guardRetryableAttempt(ServiceAutoRenewalAttempt $attempt): array
    {
        if ($attempt->status !== 'failed') {
            throw ValidationException::withMessages(['attempt' => 'Only failed auto-renew attempts can be retried.']);
        }

        $service = $attempt->service;
        if ($service === null) {
            throw ValidationException::withMessages(['attempt' => 'Auto-renew attempt is missing its service.']);
        }

        if ($service->status !== 'active') {
            throw ValidationException::withMessages(['attempt' => 'Only active services can be retried.']);
        }

        if (! $service->auto_renew_enabled) {
            throw ValidationException::withMessages(['attempt' => 'Auto-renew must be enabled before retrying.']);
        }

        if ($service->expires_at === null || ! ($attempt->expires_at?->isSameSecond($service->expires_at) ?? false)) {
            throw ValidationException::withMessages(['attempt' => 'Auto-renew attempt no longer matches the current service expiry.']);
        }

        if ($service->cancellations->whereIn('status', ['requested', 'scheduled', 'queued'])->isNotEmpty()) {
            throw ValidationException::withMessages(['attempt' => 'Open cancellation requests prevent auto-renew retry.']);
        }

        $policy = $this->policy->forService($service);
        if (! $policy['allowed']) {
            throw ValidationException::withMessages(['attempt' => 'Auto-renew is not available for this product.']);
        }

        if (! $this->policy->isDue($service, now(), $policy)) {
            throw ValidationException::withMessages(['attempt' => 'Service is outside its renewal window.']);
        }

        return $policy;
    }

    /**
     * @param  array<int, string>  $attemptIds
     * @return Collection<int, ServiceAutoRenewalAttempt>
     */
    private function attemptsForBulk(array $attemptIds): Collection
    {
        return ServiceAutoRenewalAttempt::with(['service.product', 'service.user', 'service.orderItem', 'service.cancellations', 'user'])
            ->whereIn('id', array_unique($attemptIds))
            ->get();
    }

    /**
     * @param  array<int, string>  $attemptIds
     * @return list<string>
     */
    private function uniqueAttemptIds(array $attemptIds): array
    {
        return array_values(array_unique(array_map('strval', $attemptIds)));
    }

    private function firstValidationError(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (isset($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'Validation failed.';
    }
}
