<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\Drivers\GenericHttpProvisioningDriver;
use App\Services\Provisioning\Drivers\SandboxProvisioningDriver;
use RuntimeException;

class ProvisioningExecutor
{
    public function __construct(
        private readonly SandboxProvisioningDriver $sandboxDriver,
        private readonly GenericHttpProvisioningDriver $genericHttpDriver,
    ) {}

    public function execute(ProvisioningJob $job): ProvisioningResult
    {
        $provider = data_get($job->payload, 'product.provider', []);
        $account = $this->providerAccount($provider);
        $driver = $account?->driver ?? (string) ($provider['driver'] ?? 'sandbox');

        if ($account !== null && ! $account->enabled) {
            throw new RuntimeException("Provider account {$account->slug} is disabled.");
        }

        return match ($driver) {
            'sandbox' => $this->sandboxDriver->execute($job),
            'generic_http' => $this->genericHttpDriver->execute($job, $account ?? throw new RuntimeException('Generic HTTP provider account is required.')),
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

        return ProvisioningProviderAccount::where('slug', 'sandbox')->first();
    }
}
