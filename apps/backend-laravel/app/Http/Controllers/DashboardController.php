<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Service;
use App\Services\Finance\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WalletService $walletService): View
    {
        $user = $request->user();
        $wallet = $walletService->walletFor($user);

        return view('dashboard', [
            'wallet' => $wallet,
            'openInvoiceCount' => Invoice::where('user_id', $user->id)->where('status', 'open')->count(),
            'activeServiceCount' => Service::where('user_id', $user->id)->where('status', 'active')->count(),
            'recentInvoices' => Invoice::where('user_id', $user->id)->latest()->limit(5)->get(),
            'recentOrders' => Order::where('user_id', $user->id)->latest()->limit(5)->get(),
            'recentServices' => Service::where('user_id', $user->id)->latest()->limit(5)->get(),
            'recentTopUps' => PaymentIntent::where('user_id', $user->id)->where('type', 'wallet_topup')->latest()->limit(5)->get(),
        ]);
    }
}
