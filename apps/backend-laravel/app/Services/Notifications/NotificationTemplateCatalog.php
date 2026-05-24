<?php

namespace App\Services\Notifications;

class NotificationTemplateCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function defaults(): array
    {
        return [
            'wallet_credited' => [
                'type' => 'wallet_credited',
                'channel' => 'email',
                'name' => 'Wallet credited',
                'subject_template' => 'Wallet credited',
                'body_template' => 'Your wallet was credited {{amount}} {{currency}}.',
                'variables' => ['amount', 'currency'],
            ],
            'invoice_paid' => [
                'type' => 'invoice_paid',
                'channel' => 'email',
                'name' => 'Invoice paid',
                'subject_template' => 'Invoice {{invoice_number}} paid',
                'body_template' => 'Invoice {{invoice_number}} was paid.',
                'variables' => ['invoice_number', 'amount', 'currency', 'user.email'],
            ],
            'service_provisioned' => [
                'type' => 'service_provisioned',
                'channel' => 'email',
                'name' => 'Service provisioned',
                'subject_template' => 'Service provisioned',
                'body_template' => 'Your service is active. External ID: {{external_id}}.',
                'variables' => ['external_id', 'service_id'],
            ],
            'service_renewed' => [
                'type' => 'service_renewed',
                'channel' => 'email',
                'name' => 'Service renewed',
                'subject_template' => 'Service renewed',
                'body_template' => 'Your service {{service_id}} was renewed until {{new_expires_at}}.',
                'variables' => ['service_id', 'old_expires_at', 'new_expires_at', 'amount', 'currency'],
            ],
            'service_cancellation_requested' => [
                'type' => 'service_cancellation_requested',
                'channel' => 'email',
                'name' => 'Service cancellation requested',
                'subject_template' => 'Service cancellation requested',
                'body_template' => 'Your cancellation request for service {{service_id}} was received.',
                'variables' => ['service_id', 'cancellation_id', 'mode', 'reason'],
            ],
            'service_cancellation_completed' => [
                'type' => 'service_cancellation_completed',
                'channel' => 'email',
                'name' => 'Service cancellation completed',
                'subject_template' => 'Service cancellation completed',
                'body_template' => 'Your service {{service_id}} was cancelled.',
                'variables' => ['service_id', 'cancellation_id'],
            ],
            'service_expiry_warning' => [
                'type' => 'service_expiry_warning',
                'channel' => 'email',
                'name' => 'Service expiry warning',
                'subject_template' => 'Service expiring soon',
                'body_template' => 'Your service {{service_id}} expires at {{expires_at}}.',
                'variables' => ['service_id', 'expires_at'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function customerTypes(): array
    {
        return collect($this->defaults())
            ->mapWithKeys(fn (array $definition, string $type): array => [$type => $definition['name']])
            ->all();
    }
}
