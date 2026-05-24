<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientWalletBalance;
use App\Models\Product;
use App\Services\Orders\OrderCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductOrderController extends Controller
{
    public function store(Request $request, Product $product, OrderCheckoutService $checkoutService): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);

        try {
            $order = $checkoutService->checkout($request->user(), $product);
        } catch (InsufficientWalletBalance $exception) {
            return redirect('/products')->withErrors(['wallet' => $exception->getMessage()]);
        }

        return redirect("/orders/{$order->id}")->with('status', 'Order paid and queued for provisioning.');
    }
}
