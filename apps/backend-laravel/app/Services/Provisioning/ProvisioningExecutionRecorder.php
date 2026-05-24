<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningExecutionLog;
use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;

class ProvisioningExecutionRecorder
{
    public function __construct(private readonly PayloadRedactor $redactor) {}

    public function startForJob(
        ProvisioningJob $job,
        ?ProvisioningProviderAccount $account,
        string $action,
        string $driver,
        ?string $endpoint,
        array $requestPayload = [],
    ): ProvisioningExecutionLog {
        return ProvisioningExecutionLog::create([
            'provisioning_job_id' => $job->id,
            'service_id' => $job->service_id,
            'provider_account_id' => $account?->id,
            'action' => $action,
            'driver' => $driver,
            'endpoint' => $endpoint,
            'status' => 'pending',
            'duration_ms' => 0,
            'request_payload' => $this->redactor->redact($requestPayload),
            'response_payload' => [],
        ]);
    }

    public function startForProviderAccount(
        ProvisioningProviderAccount $account,
        string $action,
        ?string $endpoint,
        array $requestPayload = [],
    ): ProvisioningExecutionLog {
        return ProvisioningExecutionLog::create([
            'provider_account_id' => $account->id,
            'action' => $action,
            'driver' => $account->driver,
            'endpoint' => $endpoint,
            'status' => 'pending',
            'duration_ms' => 0,
            'request_payload' => $this->redactor->redact($requestPayload),
            'response_payload' => [],
        ]);
    }

    public function success(
        ProvisioningExecutionLog $log,
        int $durationMs,
        ?int $httpStatus = null,
        array $responsePayload = [],
    ): ProvisioningExecutionLog {
        $log->update([
            'status' => 'success',
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'error_code' => null,
            'error_message' => null,
            'response_payload' => $this->redactor->redact($responsePayload),
        ]);

        return $log->refresh();
    }

    public function failure(
        ProvisioningExecutionLog $log,
        int $durationMs,
        string $errorCode,
        string $errorMessage,
        ?int $httpStatus = null,
        array $responsePayload = [],
    ): ProvisioningExecutionLog {
        $log->update([
            'status' => 'failed',
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'response_payload' => $this->redactor->redact($responsePayload),
        ]);

        return $log->refresh();
    }

    public function durationSince(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
