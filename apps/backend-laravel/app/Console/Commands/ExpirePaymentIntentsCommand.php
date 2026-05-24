<?php

namespace App\Console\Commands;

use App\Models\PaymentIntent;
use Illuminate\Console\Command;

class ExpirePaymentIntentsCommand extends Command
{
    protected $signature = 'payment-intents:expire';

    protected $description = 'Expire stale pending payment intents.';

    public function handle(): int
    {
        $expired = PaymentIntent::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired payment intents: {$expired}");

        return self::SUCCESS;
    }
}
