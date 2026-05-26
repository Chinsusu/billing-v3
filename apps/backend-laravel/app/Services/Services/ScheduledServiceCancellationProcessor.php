<?php

namespace App\Services\Services;

use App\Models\ProviderActionJob;
use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScheduledServiceCancellationProcessor
{
    public function __construct(
        private readonly ProviderServiceActionService $providerActions,
        private readonly ProviderActionJobDispatcher $providerActionJobs,
        private readonly NotificationOutbox $notifications,
    ) {}

    /**
     * @return array{completed: int, queued: int, skipped: int, failed: int}
     */
    public function process(int $limit = 50): array
    {
        $counts = ['completed' => 0, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
        $limit = max(1, $limit);

        ServiceCancellation::query()
            ->where('mode', 'period_end')
            ->where('status', 'scheduled')
            ->whereHas('service', function ($query): void {
                $query->where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            })
            ->orderBy('requested_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (ServiceCancellation $cancellation) use (&$counts): void {
                try {
                    $result = $this->processOne($cancellation);
                    $counts[$result]++;
                } catch (Throwable) {
                    $counts['failed']++;
                }
            });

        return $counts;
    }

    private function processOne(ServiceCancellation $cancellation): string
    {
        return DB::transaction(function () use ($cancellation): string {
            $lockedCancellation = ServiceCancellation::whereKey($cancellation->id)->lockForUpdate()->firstOrFail();

            if ($lockedCancellation->status !== 'scheduled') {
                return 'skipped';
            }

            $service = Service::whereKey($lockedCancellation->service_id)->lockForUpdate()->firstOrFail();

            if ($service->status !== 'active' || $service->expires_at === null || $service->expires_at->isFuture()) {
                return 'skipped';
            }

            if ($this->providerActions->hasConfiguredAction($service, 'cancel')) {
                $job = $this->queueProviderCancellation($service, $lockedCancellation);
                $this->markQueued($service, $lockedCancellation, $job);

                return 'queued';
            }

            $this->completeLocalCancellation($service, $lockedCancellation);

            return 'completed';
        });
    }

    private function queueProviderCancellation(Service $service, ServiceCancellation $cancellation): ProviderActionJob
    {
        return $this->providerActionJobs->enqueue(
            $service,
            'cancel',
            "service-cancel:{$service->id}:period-end:{$cancellation->id}",
            [
                'cancelled_at' => now()->toISOString(),
                'requested_by_id' => $cancellation->requested_by_id,
                'service_cancellation_id' => $cancellation->id,
                'mode' => 'period_end',
            ],
        );
    }

    private function markQueued(Service $service, ServiceCancellation $cancellation, ProviderActionJob $job): void
    {
        $serviceMeta = $service->meta ?? [];
        $serviceMeta['cancellation'] = array_merge($serviceMeta['cancellation'] ?? [], [
            'mode' => 'period_end',
            'status' => 'queued',
            'provider_action_job_id' => $job->id,
            'queued_at' => now()->toISOString(),
        ]);
        $service->forceFill(['meta' => $serviceMeta])->save();

        $cancellationMeta = $cancellation->meta ?? [];
        $cancellationMeta['provider_action_job_id'] = $job->id;
        $cancellationMeta['queued_at'] = now()->toISOString();

        $cancellation->forceFill([
            'status' => 'queued',
            'provider_action_job_id' => $job->id,
            'meta' => $cancellationMeta,
        ])->save();
    }

    private function completeLocalCancellation(Service $service, ServiceCancellation $cancellation): void
    {
        $cancelledAt = now()->toISOString();
        $serviceMeta = $service->meta ?? [];
        $serviceMeta['cancelled_at'] = $cancelledAt;
        $serviceMeta['cancellation'] = array_merge($serviceMeta['cancellation'] ?? [], [
            'mode' => 'period_end',
            'status' => 'completed',
            'completed_by' => 'scheduled_processor',
            'completed_at' => $cancelledAt,
        ]);

        $service->forceFill([
            'status' => 'cancelled',
            'meta' => $serviceMeta,
        ])->save();

        $cancellationMeta = $cancellation->meta ?? [];
        $cancellationMeta['cancelled_at'] = $cancelledAt;
        $cancellationMeta['completed_by'] = 'scheduled_processor';

        $cancellation->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'meta' => $cancellationMeta,
        ])->save();

        $user = $cancellation->user ?? $service->user;
        if ($user !== null) {
            $this->notifications->enqueue(
                $user,
                'service_cancellation_completed',
                $user->email,
                'Service cancellation completed',
                "Your service {$service->product_name} was cancelled.",
                'service',
                $service->id,
                "service-cancellation-completed:{$cancellation->id}",
                [
                    'service_id' => $service->id,
                    'service_cancellation_id' => $cancellation->id,
                    'completed_by' => 'scheduled_processor',
                ],
            );
        }
    }
}
