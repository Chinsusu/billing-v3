<?php

namespace App\Console\Commands;

use App\Models\BankIntegration;
use App\Services\Finance\PrivateBankClient;
use App\Services\Finance\PrivateBankTransactionProcessor;
use Illuminate\Console\Command;
use Throwable;

class SyncPrivateBankPaymentsCommand extends Command
{
    protected $signature = 'bank:sync-payments';

    protected $description = 'Sync private bank transactions into wallet payment intents.';

    public function handle(PrivateBankClient $client, PrivateBankTransactionProcessor $processor): int
    {
        BankIntegration::where('enabled', true)->orderBy('provider')->get()->each(function (BankIntegration $integration) use ($client, $processor): void {
            $counts = ['accepted' => 0, 'rejected' => 0, 'unmatched' => 0, 'duplicate' => 0];

            try {
                foreach ($client->transactions($integration) as $transaction) {
                    $status = $processor->process($integration, $transaction);
                    $counts[$status]++;
                }

                $integration->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => 'synced',
                    'last_sync_error' => null,
                ]);
            } catch (Throwable $exception) {
                $integration->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => 'failed',
                    'last_sync_error' => $exception->getMessage(),
                ]);
            }

            $this->info("{$integration->provider}: accepted={$counts['accepted']} rejected={$counts['rejected']} unmatched={$counts['unmatched']} duplicate={$counts['duplicate']}");
        });

        return self::SUCCESS;
    }
}
