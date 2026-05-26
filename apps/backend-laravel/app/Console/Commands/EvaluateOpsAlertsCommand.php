<?php

namespace App\Console\Commands;

use App\Services\Ops\OpsAlertEvaluator;
use Illuminate\Console\Command;

class EvaluateOpsAlertsCommand extends Command
{
    protected $signature = 'ops-alerts:evaluate';

    protected $description = 'Evaluate ops health and create alert events.';

    public function handle(OpsAlertEvaluator $evaluator): int
    {
        $created = $evaluator->evaluate();
        $this->info("Ops alerts created={$created}.");

        return self::SUCCESS;
    }
}
