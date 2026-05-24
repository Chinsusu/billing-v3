<?php

namespace App\Services\Provisioning;

use App\Models\ProviderActionJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use InvalidArgumentException;

class ProviderActionJobDispatcher
{
    private const SUPPORTED_ACTIONS = ['suspend', 'cancel', 'sync'];

    public function enqueue(Service $service, string $action, string $idempotencyKey, array $context = []): ProviderActionJob
    {
        if (! in_array($action, self::SUPPORTED_ACTIONS, true)) {
            throw new InvalidArgumentException("Provider action {$action} cannot be queued.");
        }

        $existing = ProviderActionJob::where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof ProviderActionJob) {
            return $existing;
        }

        $provider = $this->providerSnapshot($service);
        $account = $this->providerAccount($provider);

        return ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'provider_account_id' => $account?->id,
            'action' => $action,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => $idempotencyKey,
            'payload' => ['context' => $context],
            'available_at' => null,
            'processed_at' => null,
            'last_error' => null,
        ]);
    }

    private function providerSnapshot(Service $service): array
    {
        $provider = $service->meta['provider'] ?? null;
        if (is_array($provider) && $provider !== []) {
            return $provider;
        }

        $service->loadMissing('product.providerAccount');
        $product = $service->product;
        $account = $product?->providerAccount;

        return [
            'account_id' => $account?->id,
            'account_slug' => $account?->slug,
        ];
    }

    private function providerAccount(array $provider): ?ProvisioningProviderAccount
    {
        if (! empty($provider['account_id'])) {
            return ProvisioningProviderAccount::find($provider['account_id']);
        }

        if (! empty($provider['account_slug'])) {
            return ProvisioningProviderAccount::where('slug', $provider['account_slug'])->first();
        }

        return null;
    }
}
