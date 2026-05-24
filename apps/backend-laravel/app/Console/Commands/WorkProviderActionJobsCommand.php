<?php

namespace App\Console\Commands;

use App\Services\Provisioning\ProviderActionJobProcessor;
use Illuminate\Console\Command;

class WorkProviderActionJobsCommand extends Command
{
    protected $signature = 'provider-actions:work {--once} {--limit=50}';

    protected $description = 'Process pending provider action jobs.';

    public function handle(ProviderActionJobProcessor $processor): int
    {
        $result = $processor->work((int) $this->option('limit'), (bool) $this->option('once'));

        $this->info("Provider action jobs processed={$result['processed']} failed={$result['failed']}.");

        return self::SUCCESS;
    }
}
