<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Console\Command;
use RuntimeException;

class ExpireServicesCommand extends Command
{
    protected $signature = 'services:expire';

    protected $description = 'Mark overdue active services as expired.';

    public function handle(ProviderServiceActionService $providerActions): int
    {
        $count = 0;
        $failed = 0;

        Service::query()
            ->with('product.providerAccount')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->each(function (Service $service) use (&$count, &$failed, $providerActions): void {
                if ($providerActions->hasConfiguredAction($service, 'suspend')) {
                    try {
                        $providerActions->execute($service, 'suspend', "service-suspend:{$service->id}:".now()->toISOString(), [
                            'expires_at' => $service->expires_at?->toISOString(),
                            'expired_at' => now()->toISOString(),
                        ]);
                    } catch (RuntimeException) {
                        $failed++;

                        return;
                    }
                }

                $meta = $service->meta ?? [];
                $meta['expired_at'] = now()->toISOString();

                $service->forceFill([
                    'status' => 'expired',
                    'meta' => $meta,
                ])->save();

                $count++;
            });

        $this->info("Expired {$count} services. Failed {$failed} services.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
