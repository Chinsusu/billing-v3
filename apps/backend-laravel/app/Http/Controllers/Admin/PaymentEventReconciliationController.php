<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Finance\PaymentEventReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentEventReconciliationController extends Controller
{
    public function __construct(private readonly PaymentEventReconciliationService $reconciliation) {}

    public function __invoke(Request $request, PaymentEvent $paymentEvent): RedirectResponse
    {
        $validated = $request->validate([
            'user_email' => ['required', 'email', 'exists:users,email'],
        ]);

        $customer = User::where('email', $validated['user_email'])->firstOrFail();

        try {
            $this->reconciliation->reconcile($paymentEvent, $customer, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment_event' => $exception->getMessage()])->withInput();
        }

        return redirect("/admin/payment-events/{$paymentEvent->id}")->with('status', 'Payment event reconciled.');
    }
}
