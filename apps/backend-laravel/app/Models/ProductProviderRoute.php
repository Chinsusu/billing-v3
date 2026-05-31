<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'provider_account_id', 'enabled', 'priority', 'weight', 'billing_group_id', 'node_selector_type', 'node_name', 'options'])]
class ProductProviderRoute extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'weight' => 'integer',
            'options' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProvisioningProviderAccount::class, 'provider_account_id');
    }
}
