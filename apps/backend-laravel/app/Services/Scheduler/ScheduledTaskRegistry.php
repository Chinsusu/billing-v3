<?php

namespace App\Services\Scheduler;

class ScheduledTaskRegistry
{
    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return [
            'bank_sync_payments' => 'bank:sync-payments',
            'payment_intents_expire' => 'payment-intents:expire',
            'provider_actions_work' => 'provider-actions:work --limit=50',
            'services_expire' => 'services:expire',
            'services_auto_renew' => 'services:auto-renew --limit=50',
            'service_cancellations_process_scheduled' => 'service-cancellations:process-scheduled --limit=50',
            'provider_actions_recover_stuck' => 'provider-actions:recover-stuck',
            'ops_alerts_evaluate' => 'ops-alerts:evaluate',
            'notifications_send' => 'notifications:send --limit=50',
        ];
    }

    public function commandFor(string $task): ?string
    {
        return $this->all()[$task] ?? null;
    }
}
