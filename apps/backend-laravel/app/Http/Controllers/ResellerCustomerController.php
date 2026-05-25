<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerCustomerController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->hasRole('reseller'), 403);

        return view('reseller.customers.index', [
            'customers' => $request->user()
                ->resellerCustomers()
                ->withCount(['orders', 'services'])
                ->orderBy('email')
                ->paginate(20),
        ]);
    }
}
