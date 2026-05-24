<?php

namespace App\Http\Controllers;

use App\Models\PaymentIntent;
use App\Services\Finance\PaymentIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WalletTopUpController extends Controller
{
    public function store(Request $request, PaymentIntentService $paymentIntentService): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:500000000'],
            'currency' => ['required', 'string', Rule::in(['VND'])],
        ]);

        $intent = $paymentIntentService->createWalletTopUp($request->user(), (int) $validated['amount'], $validated['currency']);

        return redirect("/wallet/top-ups/{$intent->id}");
    }

    public function show(Request $request, PaymentIntent $paymentIntent): View
    {
        abort_unless($paymentIntent->user_id === $request->user()->id, 404);

        return view('wallet.top-up', ['paymentIntent' => $paymentIntent]);
    }
}
