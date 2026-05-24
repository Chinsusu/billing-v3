<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provisioning_job_id', 'service_id', 'provider_account_id', 'action', 'driver', 'endpoint', 'status', 'http_status', 'duration_ms', 'error_code', 'error_message', 'request_payload', 'response_payload'])]
class ProvisioningExecutionLog extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function provisioningJob(): BelongsTo
    {
        return $this->belongsTo(ProvisioningJob::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProvisioningProviderAccount::class, 'provider_account_id');
    }
}
