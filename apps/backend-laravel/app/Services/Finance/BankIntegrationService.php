<?php

namespace App\Services\Finance;

use App\Models\BankIntegration;
use App\Models\User;

class BankIntegrationService
{
    public function create(array $attributes, User $actor): BankIntegration
    {
        return BankIntegration::create($this->attributesForSave($attributes) + [
            'created_by_id' => $actor->id,
            'updated_by_id' => $actor->id,
        ]);
    }

    public function update(BankIntegration $integration, array $attributes, User $actor): BankIntegration
    {
        $integration->update($this->attributesForSave($attributes, $integration) + [
            'updated_by_id' => $actor->id,
        ]);

        return $integration->refresh();
    }

    private function attributesForSave(array $attributes, ?BankIntegration $existing = null): array
    {
        $apiKey = (string) ($attributes['api_key'] ?? '');
        $webhookSecret = (string) ($attributes['webhook_secret'] ?? '');

        $data = [
            'provider' => $attributes['provider'],
            'name' => $attributes['name'],
            'base_url' => rtrim($attributes['base_url'], '/'),
            'transactions_path' => '/'.ltrim($attributes['transactions_path'], '/'),
            'account_number' => $attributes['account_number'] ?? null,
            'enabled' => (bool) ($attributes['enabled'] ?? false),
        ];

        if ($existing === null || $apiKey !== '') {
            $data['api_key'] = $apiKey !== '' ? $apiKey : null;
            $data['api_key_last_four'] = $this->lastFour($apiKey);
        }

        if ($existing === null || $webhookSecret !== '') {
            $data['webhook_secret'] = $webhookSecret !== '' ? $webhookSecret : null;
            $data['webhook_secret_last_four'] = $this->lastFour($webhookSecret);
        }

        return $data;
    }

    private function lastFour(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return substr($value, -4);
    }
}
