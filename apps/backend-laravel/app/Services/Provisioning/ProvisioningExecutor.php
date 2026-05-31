<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\Drivers\CloudminiV3ProvisioningDriver;
use App\Services\Provisioning\Drivers\GenericHttpProvisioningDriver;
use App\Services\Provisioning\Drivers\SandboxProvisioningDriver;
use RuntimeException;

class ProvisioningExecutor
{
    public function __construct(
        private readonly SandboxProvisioningDriver $sandboxDriver,
        private readonly GenericHttpProvisioningDriver $genericHttpDriver,
        private readonly CloudminiV3ProvisioningDriver $cloudminiV3Driver,
    ) {}

    public function execute(ProvisioningJob $job): ProvisioningResult
    {
        $provider = data_get($job->payload, 'product.provider', []);
        $declaredDriver = $provider['driver'] ?? null;
        $account = $this->providerAccount($provider);
        $driver = (string) ($declaredDriver ?? $account?->driver ?? 'sandbox');

        if ($account !== null && ! $account->enabled) {
            throw new RuntimeException("Provider account {$account->slug} is disabled.");
        }

        return match ($driver) {
            'sandbox' => $this->sandboxDriver->execute($job, $account),
            'generic_http' => $this->genericHttpDriver->execute($job, $account ?? throw new RuntimeException('Generic HTTP provider account is required.')),
            'cloudmini_v3' => $this->cloudminiV3Driver->execute($job),
            default => throw new RuntimeException("Unsupported provisioning driver {$driver}."),
        };
    }

    private function providerAccount(array $provider): ?ProvisioningProviderAccount
    {
        if (! empty($provider['account_id'])) {
            return ProvisioningProviderAccount::find($provider['account_id']);
        }

        if (! empty($provider['account_slug'])) {
            return ProvisioningProviderAccount::where('slug', $provider['account_slug'])->first();
        }

        if (array_key_exists('driver', $provider)) {
            return null;
        }

        return ProvisioningProviderAccount::where('slug', 'sandbox')->first();
    }
}
