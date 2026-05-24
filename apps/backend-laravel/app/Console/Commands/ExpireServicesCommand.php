<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Console\Command;

class ExpireServicesCommand extends Command
{
    protected $signature = 'services:expire';

    protected $description = 'Mark overdue active services as expired.';

    public function handle(ProviderServiceActionService $providerActions, ProviderActionJobDispatcher $providerActionJobs): int
    {
        $count = 0;
        $queued = 0;

        Service::query()
            ->with('product.providerAccount')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->each(function (Service $service) use (&$count, &$queued, $providerActions, $providerActionJobs): void {
                if ($providerActions->hasConfiguredAction($service, 'suspend')) {
                    $providerActionJobs->enqueue($service, 'suspend', "service-suspend:{$service->id}:".now()->toISOString(), [
                        'expires_at' => $service->expires_at?->toISOString(),
                        'expired_at' => now()->toISOString(),
                    ]);
                    $queued++;

                    return;
                }

                $meta = $service->meta ?? [];
                $meta['expired_at'] = now()->toISOString();

                $service->forceFill([
                    'status' => 'expired',
                    'meta' => $meta,
                ])->save();

                $count++;
            });

        $this->info("Expired {$count} services. Queued {$queued} provider action jobs.");

        return self::SUCCESS;
    }
}
