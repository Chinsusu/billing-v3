<?php

namespace App\Services\Provisioning;

use App\Models\ProviderActionJob;
use App\Models\ProviderCallbackEvent;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\ServiceCancellation;
use Illuminate\Support\Facades\DB;

class ProviderCallbackProcessor
{
    public function __construct(
        private readonly JsonPath $jsonPath,
        private readonly PayloadRedactor $redactor,
    ) {}

    /**
     * @return array{status: string, status_code: int, event: ProviderCallbackEvent}
     */
    public function process(ProvisioningProviderAccount $account, array $payload): array
    {
        $providerEventId = $this->stringValue($this->jsonPath->get($payload, $account->callback_event_id_path));
        $externalId = $this->stringValue($this->jsonPath->get($payload, $account->callback_external_id_path));
        $action = $this->lowerString($this->jsonPath->get($payload, $account->callback_action_path));
        $providerStatus = $this->lowerString($this->jsonPath->get($payload, $account->callback_status_path));

        if ($providerEventId !== null) {
            $existing = ProviderCallbackEvent::where('provider_account_id', $account->id)
                ->where('provider_event_id', $providerEventId)
                ->first();

            if ($existing instanceof ProviderCallbackEvent) {
                return [
                    'status' => 'duplicate',
                    'status_code' => 200,
                    'event' => $existing,
                ];
            }
        }

        return DB::transaction(function () use ($account, $payload, $providerEventId, $externalId, $action, $providerStatus): array {
            $service = $this->findService($account, $externalId);
            $job = $service instanceof Service && $action !== null
                ? $this->findProviderActionJob($account, $service, $action)
                : null;
            $processingStatus = $service instanceof Service ? 'processed' : 'unmatched';

            $event = ProviderCallbackEvent::create([
                'provider_account_id' => $account->id,
                'service_id' => $service?->id,
                'provider_action_job_id' => $job?->id,
                'provider_event_id' => $providerEventId,
                'external_id' => $externalId,
                'action' => $action,
                'provider_status' => $providerStatus,
                'signature_status' => 'valid',
                'processing_status' => $processingStatus,
                'payload' => $this->redactor->redact($payload),
                'processed_at' => now(),
                'error' => null,
            ]);

            if ($service instanceof Service) {
                $this->reconcile($event, $service, $job, $action, $providerStatus);
            }

            return [
                'status' => $processingStatus,
                'status_code' => $processingStatus === 'processed' ? 200 : 202,
                'event' => $event->refresh(),
            ];
        });
    }

    private function findService(ProvisioningProviderAccount $account, ?string $externalId): ?Service
    {
        if ($externalId === null) {
            return null;
        }

        return Service::with('product')
            ->where('external_id', $externalId)
            ->latest()
            ->get()
            ->first(function (Service $service) use ($account): bool {
                $providerAccountId = data_get($service->meta, 'provider.account_id');

                return $providerAccountId === $account->id
                    || $service->product?->provider_account_id === $account->id;
            });
    }

    private function findProviderActionJob(ProvisioningProviderAccount $account, Service $service, string $action): ?ProviderActionJob
    {
        return ProviderActionJob::where('service_id', $service->id)
            ->where('provider_account_id', $account->id)
            ->where('action', $action)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first()
            ?? ProviderActionJob::where('service_id', $service->id)
                ->where('provider_account_id', $account->id)
                ->where('action', $action)
                ->latest()
                ->first();
    }

    private function reconcile(
        ProviderCallbackEvent $event,
        Service $service,
        ?ProviderActionJob $job,
        ?string $action,
        ?string $providerStatus,
    ): void {
        $targetStatus = $this->serviceStatusFor($action, $providerStatus);
        $meta = $service->meta ?? [];

        if ($targetStatus !== null) {
            if ($targetStatus === 'cancelled') {
                $meta['cancelled_at'] = now()->toISOString();
                $meta['cancellation'] = array_merge($meta['cancellation'] ?? [], [
                    'status' => 'completed',
                    'completed_at' => $meta['cancelled_at'],
                    'provider_callback_event_id' => $event->id,
                    'provider_action_job_id' => $job?->id,
                ]);
            }

            if ($targetStatus === 'expired' && $action === 'suspend') {
                $meta['expired_at'] = now()->toISOString();
            }

            $service->forceFill([
                'status' => $targetStatus,
                'meta' => $meta,
            ])->save();
        }

        if ($job instanceof ProviderActionJob) {
            if ($this->isFailureStatus($providerStatus)) {
                $job->forceFill([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'last_error' => "Provider callback status {$providerStatus}.",
                ])->save();
            } else {
                $job->forceFill([
                    'status' => 'processed',
                    'available_at' => null,
                    'processed_at' => now(),
                    'last_error' => null,
                ])->save();
            }
        }

        if ($action === 'cancel') {
            $this->completeCancellations($event, $service, $job);
        }
    }

    private function completeCancellations(ProviderCallbackEvent $event, Service $service, ?ProviderActionJob $job): void
    {
        $query = ServiceCancellation::where('service_id', $service->id)
            ->whereIn('status', ['queued', 'requested', 'scheduled']);

        if ($job instanceof ProviderActionJob) {
            $query->where(function ($nested) use ($job): void {
                $nested->where('provider_action_job_id', $job->id)
                    ->orWhereNull('provider_action_job_id');
            });
        }

        $query->get()->each(function (ServiceCancellation $cancellation) use ($event, $job): void {
            $meta = $cancellation->meta ?? [];
            $meta['provider_callback_event_id'] = $event->id;
            $meta['provider_completed_at'] = now()->toISOString();

            if ($job instanceof ProviderActionJob) {
                $meta['provider_action_job_id'] = $job->id;
            }

            $cancellation->forceFill([
                'status' => 'completed',
                'provider_action_job_id' => $job?->id ?? $cancellation->provider_action_job_id,
                'completed_at' => now(),
                'meta' => $meta,
            ])->save();
        });
    }

    private function serviceStatusFor(?string $action, ?string $providerStatus): ?string
    {
        if ($action === null || $providerStatus === null) {
            return null;
        }

        return match ($action) {
            'cancel' => in_array($providerStatus, ['cancelled', 'canceled', 'success', 'processed'], true) ? 'cancelled' : null,
            'suspend' => in_array($providerStatus, ['suspended', 'expired', 'success', 'processed'], true) ? 'expired' : null,
            'sync' => match ($providerStatus) {
                'active' => 'active',
                'expired', 'suspended' => 'expired',
                'cancelled', 'canceled' => 'cancelled',
                default => null,
            },
            default => null,
        };
    }

    private function isFailureStatus(?string $providerStatus): bool
    {
        return in_array($providerStatus, ['failed', 'failure', 'error', 'rejected'], true);
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function lowerString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== null ? strtolower($value) : null;
    }
}
