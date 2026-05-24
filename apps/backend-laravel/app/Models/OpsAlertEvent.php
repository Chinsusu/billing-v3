<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ops_alert_rule_id', 'fingerprint', 'severity', 'status', 'title', 'message', 'context', 'first_seen_at', 'last_seen_at', 'acknowledged_at', 'resolved_at', 'acknowledged_by_id', 'resolved_by_id', 'delivery_status', 'delivery_error'])]
class OpsAlertEvent extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OpsAlertRule::class, 'ops_alert_rule_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
