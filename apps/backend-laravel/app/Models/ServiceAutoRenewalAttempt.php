<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'user_id', 'expires_at', 'status', 'attempts', 'next_attempt_at', 'renewed_expires_at', 'amount', 'currency', 'last_error', 'idempotency_key'])]
class ServiceAutoRenewalAttempt extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'renewed_expires_at' => 'datetime',
            'amount' => 'integer',
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
}
