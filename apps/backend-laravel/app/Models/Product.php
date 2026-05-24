<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'type', 'status', 'price_amount', 'currency', 'duration_days', 'description', 'config', 'provider_account_id', 'provider_plan_code', 'provider_region', 'provider_provision_path', 'provider_options'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'duration_days' => 'integer',
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
