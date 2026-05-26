<?php

namespace App\Services\Provisioning\Drivers;

use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\ProvisioningExecutionRecorder;
use App\Services\Provisioning\ProvisioningResult;

class SandboxProvisioningDriver
{
    public function __construct(private readonly ProvisioningExecutionRecorder $recorder) {}

    public function execute(ProvisioningJob $job, ?ProvisioningProviderAccount $account = null): ProvisioningResult
    {
        $log = $this->recorder->startForJob($job, $account, 'provision_service', 'sandbox', null, [
            'action' => 'provision',
            'product' => data_get($job->payload, 'product', []),
        ]);
        $productType = (string) data_get($job->payload, 'product.type');
        $this->recorder->success($log, 0);

        return new ProvisioningResult(
            status: 'processed',
            externalId: "sandbox-{$productType}-{$job->service_id}",
            config: [],
        );
    }
}
