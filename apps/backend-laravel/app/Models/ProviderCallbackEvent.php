<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_account_id', 'service_id', 'provider_action_job_id', 'provider_event_id', 'external_id', 'action', 'provider_status', 'signature_status', 'processing_status', 'payload', 'processed_at', 'error'])]
class ProviderCallbackEvent extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProvisioningProviderAccount::class, 'provider_account_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function providerActionJob(): BelongsTo
    {
        return $this->belongsTo(ProviderActionJob::class);
    }
}
