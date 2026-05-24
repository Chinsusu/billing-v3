<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'order_id', 'order_item_id', 'product_id', 'product_code', 'product_name', 'product_type', 'status', 'external_id', 'config', 'meta', 'provisioned_at', 'expires_at'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'meta' => 'array',
            'provisioned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function provisioningJobs(): HasMany
    {
        return $this->hasMany(ProvisioningJob::class);
    }

    public function provisioningExecutionLogs(): HasMany
    {
        return $this->hasMany(ProvisioningExecutionLog::class);
    }

    public function providerActionJobs(): HasMany
    {
        return $this->hasMany(ProviderActionJob::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(ServiceCancellation::class);
    }
}
