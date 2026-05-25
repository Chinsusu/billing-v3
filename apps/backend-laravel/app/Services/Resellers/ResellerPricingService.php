<?php

namespace App\Services\Resellers;

use App\Models\Product;
use App\Models\ResellerPriceOverride;
use App\Models\User;

class ResellerPricingService
{
    /**
     * @return array{amount: int, currency: string, source: string, reseller_id: int|null}
     */
    public function priceFor(User $user, Product $product): array
    {
        if ($user->reseller_id !== null) {
            $override = ResellerPriceOverride::where('reseller_id', $user->reseller_id)
                ->where('product_id', $product->id)
                ->first();

            if ($override !== null) {
                return [
                    'amount' => $override->price_amount,
                    'currency' => $product->currency,
                    'source' => 'reseller_override',
                    'reseller_id' => $user->reseller_id,
                ];
            }
        }

        return [
            'amount' => $product->price_amount,
            'currency' => $product->currency,
            'source' => 'product_price',
            'reseller_id' => $user->reseller_id,
        ];
    }
}
