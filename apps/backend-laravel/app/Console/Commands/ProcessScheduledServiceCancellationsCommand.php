<?php

namespace App\Console\Commands;

use App\Services\Services\ScheduledServiceCancellationProcessor;
use Illuminate\Console\Command;

class ProcessScheduledServiceCancellationsCommand extends Command
{
    protected $signature = 'service-cancellations:process-scheduled {--limit=50}';

    protected $description = 'Process due period-end service cancellation requests.';

    public function handle(ScheduledServiceCancellationProcessor $processor): int
    {
        $result = $processor->process((int) $this->option('limit'));

        $this->info("Scheduled cancellations completed={$result['completed']} queued={$result['queued']} skipped={$result['skipped']} failed={$result['failed']}.");

        return self::SUCCESS;
    }
}
