<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use Illuminate\View\View;

class PaymentEventController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.payment-events.index', [
            'paymentEvents' => PaymentEvent::latest()->paginate(20),
        ]);
    }
}
