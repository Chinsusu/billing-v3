<?php

namespace App\Console\Commands;

use App\Models\ProviderActionJob;
use Illuminate\Console\Command;

class RecoverStuckProviderActionJobsCommand extends Command
{
    protected $signature = 'provider-actions:recover-stuck {--stuck-minutes=5}';

    protected $description = 'Recover stale provider action jobs left in processing state.';

    public function handle(): int
    {
        $threshold = now()->subMinutes(max(1, (int) $this->option('stuck-minutes')));
        $requeued = 0;
        $failed = 0;

        ProviderActionJob::query()
            ->where('status', 'processing')
            ->where('updated_at', '<=', $threshold)
            ->orderBy('updated_at')
            ->get()
            ->each(function (ProviderActionJob $job) use (&$requeued, &$failed): void {
                if ($job->attempts >= $job->max_attempts) {
                    $job->forceFill([
                        'status' => 'failed',
                        'available_at' => null,
                        'processed_at' => now(),
                        'last_error' => 'Stale processing job exceeded max attempts.',
                    ])->save();
                    $failed++;

                    return;
                }

                $job->forceFill([
                    'status' => 'pending',
                    'available_at' => null,
                    'processed_at' => null,
                    'last_error' => 'Recovered stale processing job.',
                ])->save();
                $requeued++;
            });

        $this->info("Provider action jobs requeued={$requeued} failed={$failed}.");

        return self::SUCCESS;
    }
}
