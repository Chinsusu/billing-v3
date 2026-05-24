<?php

namespace App\Services\Provisioning\Drivers;

use App\Models\ProvisioningJob;
use App\Services\Provisioning\ProvisioningResult;

class SandboxProvisioningDriver
{
    public function execute(ProvisioningJob $job): ProvisioningResult
    {
        $productType = (string) data_get($job->payload, 'product.type');

        return new ProvisioningResult(
            status: 'processed',
            externalId: "sandbox-{$productType}-{$job->service_id}",
            config: [],
        );
    }
}
