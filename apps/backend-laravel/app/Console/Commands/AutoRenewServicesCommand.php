<?php

namespace App\Console\Commands;

use App\Services\Services\ServiceAutoRenewalProcessor;
use Illuminate\Console\Command;

class AutoRenewServicesCommand extends Command
{
    protected $signature = 'services:auto-renew {--limit=50}';

    protected $description = 'Auto-renew due services that opted in.';

    public function handle(ServiceAutoRenewalProcessor $processor): int
    {
        $result = $processor->process((int) $this->option('limit'));

        $this->info("Auto-renew processed={$result['processed']} succeeded={$result['succeeded']} failed={$result['failed']} skipped={$result['skipped']}.");

        return self::SUCCESS;
    }
}
