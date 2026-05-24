<?php

namespace App\Console\Commands;

use App\Models\Service;
use Illuminate\Console\Command;

class ExpireServicesCommand extends Command
{
    protected $signature = 'services:expire';

    protected $description = 'Mark overdue active services as expired.';

    public function handle(): int
    {
        $count = 0;

        Service::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->each(function (Service $service) use (&$count): void {
                $meta = $service->meta ?? [];
                $meta['expired_at'] = now()->toISOString();

                $service->forceFill([
                    'status' => 'expired',
                    'meta' => $meta,
                ])->save();

                $count++;
            });

        $this->info("Expired {$count} services.");

        return self::SUCCESS;
    }
}
