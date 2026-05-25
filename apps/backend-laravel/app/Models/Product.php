<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'type', 'status', 'price_amount', 'currency', 'duration_days', 'auto_renew_allowed', 'auto_renew_window_hours', 'auto_renew_retry_delay_minutes', 'auto_renew_max_attempts', 'description', 'config', 'provider_account_id', 'provider_plan_code', 'provider_region', 'provider_provision_path', 'provider_options', 'lifecycle_source', 'lifecycle_unit', 'lifecycle_count', 'provider_lifecycle_path', 'provider_lifecycle_ordered_at_path', 'provider_lifecycle_expires_at_path', 'provider_lifecycle_date_format', 'provider_lifecycle_timezone', 'provider_renew_path', 'provider_suspend_path', 'provider_cancel_path', 'provider_sync_path'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'duration_days' => 'integer',
            'auto_renew_allowed' => 'boolean',
            'auto_renew_window_hours' => 'integer',
            'auto_renew_retry_delay_minutes' => 'integer',
            'auto_renew_max_attempts' => 'integer',
            'lifecycle_count' => 'integer',
            'config' => 'array',
            'provider_options' => 'array',
        ];
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProvisioningProviderAccount::class, 'provider_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
