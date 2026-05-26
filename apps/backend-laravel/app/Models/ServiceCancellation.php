<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'user_id', 'requested_by_id', 'provider_action_job_id', 'mode', 'status', 'reason', 'meta', 'requested_at', 'completed_at'])]
class ServiceCancellation extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function providerActionJob(): BelongsTo
    {
        return $this->belongsTo(ProviderActionJob::class);
    }
}
