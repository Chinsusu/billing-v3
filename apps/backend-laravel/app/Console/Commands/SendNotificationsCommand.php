<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

class SendNotificationsCommand extends Command
{
    protected $signature = 'notifications:send {--limit=50}';

    protected $description = 'Send pending notification outbox events.';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $result = $dispatcher->send((int) $this->option('limit'));

        $this->info("Notifications sent={$result['sent']} retried={$result['retried']} failed={$result['failed']}.");

        return self::SUCCESS;
    }
}
