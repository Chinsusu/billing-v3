<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCancellationController extends Controller
{
    public function __invoke(
        Request $request,
        Service $service,
        ProviderServiceActionService $providerActions,
        ProviderActionJobDispatcher $providerActionJobs,
        NotificationOutbox $notifications,
    ): RedirectResponse {
        abort_unless($service->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'mode' => ['required', 'in:immediate,period_end'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($service->status !== 'active') {
            throw ValidationException::withMessages(['service' => 'Only active services can be cancelled.']);
        }

        $existing = ServiceCancellation::where('service_id', $service->id)
            ->whereIn('status', ['queued', 'scheduled', 'requested'])
            ->latest()
            ->first();

        if ($existing instanceof ServiceCancellation) {
            return redirect("/services/{$service->id}")->with('status', 'Cancellation already requested.');
        }

        $message = DB::transaction(function () use ($service, $request, $providerActions, $providerActionJobs, $notifications, $validated): string {
            $lockedService = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $user = $request->user();
            $meta = $lockedService->meta ?? [];
            $reason = $validated['reason'] ?? null;

            if ($validated['mode'] === 'period_end') {
                $meta['cancellation'] = [
                    'mode' => 'period_end',
                    'requested_at' => now()->toISOString(),
                    'reason' => $reason,
                ];
                $lockedService->forceFill(['meta' => $meta])->save();

                $cancellation = ServiceCancellation::create([
                    'service_id' => $lockedService->id,
                    'user_id' => $lockedService->user_id,
                    'requested_by_id' => $user->id,
                    'mode' => 'period_end',
                    'status' => 'scheduled',
                    'reason' => $reason,
                    'meta' => ['expires_at' => $lockedService->expires_at?->toISOString()],
                    'requested_at' => now(),
                ]);

                $this->enqueueCancellationRequested($notifications, $user, $lockedService, $cancellation);

                return 'Cancellation scheduled for period end.';
            }

            $cancellation = ServiceCancellation::create([
                'service_id' => $lockedService->id,
                'user_id' => $lockedService->user_id,
                'requested_by_id' => $user->id,
                'mode' => 'immediate',
                'status' => 'requested',
                'reason' => $reason,
                'meta' => [],
                'requested_at' => now(),
            ]);

            if ($providerActions->hasConfiguredAction($lockedService, 'cancel')) {
                $job = $providerActionJobs->enqueue($lockedService, 'cancel', "service-cancel:{$lockedService->id}:customer-request", [
                    'cancelled_at' => now()->toISOString(),
                    'requested_by_id' => $user->id,
                    'service_cancellation_id' => $cancellation->id,
                ]);

                $cancellation->forceFill([
                    'status' => 'queued',
                    'provider_action_job_id' => $job->id,
                    'meta' => ['provider_action_job_id' => $job->id],
                ])->save();

                $meta['cancellation'] = [
                    'mode' => 'immediate',
                    'status' => 'queued',
                    'requested_at' => now()->toISOString(),
                    'provider_action_job_id' => $job->id,
                    'reason' => $reason,
                ];
                $lockedService->forceFill(['meta' => $meta])->save();

                $this->enqueueCancellationRequested($notifications, $user, $lockedService, $cancellation);

                return 'Provider cancellation queued.';
            }

            $meta['cancelled_at'] = now()->toISOString();
            $meta['cancellation'] = [
                'mode' => 'immediate',
                'status' => 'completed',
                'requested_at' => now()->toISOString(),
                'reason' => $reason,
            ];
            $lockedService->forceFill([
                'status' => 'cancelled',
                'meta' => $meta,
            ])->save();

            $cancellation->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'meta' => ['cancelled_at' => $meta['cancelled_at']],
            ])->save();

            $this->enqueueCancellationRequested($notifications, $user, $lockedService, $cancellation);
            $this->enqueueCancellationCompleted($notifications, $user, $lockedService, $cancellation);

            return 'Service cancelled.';
        });

        return redirect("/services/{$service->id}")->with('status', $message);
    }

    private function enqueueCancellationRequested(NotificationOutbox $notifications, User $user, Service $service, ServiceCancellation $cancellation): void
    {
        $notifications->enqueue(
            $user,
            'service_cancellation_requested',
            $user->email,
            'Service cancellation requested',
            "Cancellation was requested for service {$service->product_name}.",
            'service_cancellation',
            $cancellation->id,
            "service-cancellation-requested:{$cancellation->id}",
            [
                'service_id' => $service->id,
                'service_cancellation_id' => $cancellation->id,
                'mode' => $cancellation->mode,
                'status' => $cancellation->status,
            ],
        );
    }

    private function enqueueCancellationCompleted(NotificationOutbox $notifications, User $user, Service $service, ServiceCancellation $cancellation): void
    {
        $notifications->enqueue(
            $user,
            'service_cancellation_completed',
            $user->email,
            'Service cancelled',
            "Service {$service->product_name} was cancelled.",
            'service',
            $service->id,
            "service-cancellation-completed:{$cancellation->id}",
            [
                'service_id' => $service->id,
                'service_cancellation_id' => $cancellation->id,
                'mode' => $cancellation->mode,
            ],
        );
    }
}
