<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'enabled', 'severity', 'cooldown_minutes', 'webhook_url', 'webhook_secret', 'last_evaluated_at'])]
class OpsAlertRule extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'cooldown_minutes' => 'integer',
            'webhook_url' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'last_evaluated_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(OpsAlertEvent::class);
    }
}
