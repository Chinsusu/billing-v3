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
            'provider_actions_recover_stuck' => 'provider-actions:recover-stuck',
            'ops_alerts_evaluate' => 'ops-alerts:evaluate',
        ];
    }

    public function commandFor(string $task): ?string
    {
        return $this->all()[$task] ?? null;
    }
}
