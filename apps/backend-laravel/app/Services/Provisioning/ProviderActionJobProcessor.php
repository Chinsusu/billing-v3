<?php

namespace App\Services\Provisioning;

use App\Models\ProviderActionJob;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProviderActionJobProcessor
{
    public function __construct(
        private readonly ProviderServiceActionService $providerActions,
    ) {}

    /**
     * @return array{processed: int, failed: int}
     */
    public function work(int $limit = 50, bool $once = false): array
    {
        $processed = 0;
        $failed = 0;
        $limit = max(1, $limit);

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->claimNextJob();
            if (! $job instanceof ProviderActionJob) {
                break;
            }

            try {
                $this->processClaimedJob($job);
                $processed++;
            } catch (Throwable $exception) {
                $this->recordFailure($job, $exception);
                $failed++;
            }

            if ($once) {
                break;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    private function claimNextJob(): ?ProviderActionJob
    {
        return DB::transaction(function (): ?ProviderActionJob {
            $job = ProviderActionJob::query()
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $job instanceof ProviderActionJob) {
                return null;
            }

            $job->forceFill([
                'status' => 'processing',
                'attempts' => $job->attempts + 1,
                'available_at' => null,
            ])->save();

            return $job->fresh(['service']);
        });
    }

    private function processClaimedJob(ProviderActionJob $job): void
    {
        $service = $job->service;
        if (! $service instanceof Service) {
            throw new \RuntimeException('Provider action job service is missing.');
        }

        $context = data_get($job->payload, 'context', []);
        $context = is_array($context) ? $context : [];

        $result = $this->providerActions->execute($service, $job->action, $job->idempotency_key, $context);
        if ($result === null) {
            throw new \RuntimeException("Provider {$job->action} path is not configured.");
        }

        $this->applyServiceResult($service, $job->action, $context, $result);

        $job->forceFill([
            'status' => 'processed',
            'available_at' => null,
            'processed_at' => now(),
            'last_error' => null,
        ])->save();
    }

    private function applyServiceResult(Service $service, string $action, array $context, ProviderServiceActionResult $result): void
    {
        $updates = [];
        $meta = $service->meta ?? [];

        if ($action === 'suspend') {
            $updates['status'] = 'expired';
            $meta['expired_at'] = $context['expired_at'] ?? now()->toISOString();
            $updates['meta'] = $meta;
        }

        if ($action === 'cancel') {
            $updates['status'] = 'cancelled';
            $meta['cancelled_at'] = $context['cancelled_at'] ?? now()->toISOString();
            $updates['meta'] = $meta;
        }

        if ($action === 'sync') {
            if ($result->status !== null) {
                $updates['status'] = $result->status;
            }

            if ($result->expiresAt !== null) {
                $updates['expires_at'] = $result->expiresAt;
            }
        }

        if ($updates !== []) {
            $service->forceFill($updates)->save();
        }
    }

    private function recordFailure(ProviderActionJob $job, Throwable $exception): void
    {
        $finalAttempt = $job->attempts >= $job->max_attempts;

        $job->forceFill([
            'status' => $finalAttempt ? 'failed' : 'pending',
            'available_at' => $finalAttempt ? null : now()->addSeconds(60),
            'processed_at' => $finalAttempt ? now() : null,
            'last_error' => $exception->getMessage(),
        ])->save();
    }
}
