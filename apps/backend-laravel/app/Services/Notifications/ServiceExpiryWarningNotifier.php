<?php

namespace App\Services\Notifications;

use App\Models\Service;

class ServiceExpiryWarningNotifier
{
    public function __construct(private readonly NotificationOutbox $outbox) {}

    public function enqueueDueWarnings(): int
    {
        $count = 0;

        Service::with('user')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addHours(72))
            ->orderBy('expires_at')
            ->limit(200)
            ->get()
            ->each(function (Service $service) use (&$count): void {
                if ($service->user === null || $service->expires_at === null) {
                    return;
                }

                $event = $this->outbox->enqueue(
                    $service->user,
                    'service_expiry_warning',
                    $service->user->email,
                    'Service expiry warning',
                    "Your service {$service->product_name} expires at {$service->expires_at->toDateTimeString()}.",
                    'service',
                    $service->id,
                    "service-expiry-warning:{$service->id}:".$service->expires_at->toISOString(),
                    [
                        'service_id' => $service->id,
                        'product_name' => $service->product_name,
                        'expires_at' => $service->expires_at->toISOString(),
                    ],
                );

                if ($event->wasRecentlyCreated) {
                    $count++;
                }
            });

        return $count;
    }
}
