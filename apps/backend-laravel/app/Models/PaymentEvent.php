<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_intent_id', 'wallet_id', 'invoice_id', 'provider', 'provider_transaction_id', 'reference', 'amount', 'currency', 'status', 'signature_status', 'payload', 'processed_at'])]
class PaymentEvent extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
