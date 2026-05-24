<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'service_id', 'user_id', 'type', 'status', 'attempts', 'idempotency_key', 'payload', 'available_at', 'processed_at', 'last_error'])]
class ProvisioningJob extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'payload' => 'array',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
