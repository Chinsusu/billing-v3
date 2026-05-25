<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Services\Finance\WalletService;
use App\Services\Services\ServiceAutoRenewalPolicy;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WalletService $walletService, ServiceAutoRenewalPolicy $renewalPolicy): View
    {
        $user = $request->user();
        $wallet = $walletService->walletFor($user);
        $autoRenewServices = Service::with('product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('auto_renew_enabled', true)
            ->whereNotNull('expires_at')
            ->get();

        return view('dashboard', [
            'wallet' => $wallet,
            'openInvoiceCount' => Invoice::where('user_id', $user->id)->where('status', 'open')->count(),
            'activeServiceCount' => Service::where('user_id', $user->id)->where('status', 'active')->count(),
            'autoRenewEnabledCount' => $autoRenewServices->count(),
            'dueForAutoRenewCount' => $autoRenewServices->filter(fn (Service $service): bool => $renewalPolicy->isDue($service))->count(),
            'failedAutoRenewCount' => ServiceAutoRenewalAttempt::where('user_id', $user->id)
                ->where('status', 'failed')
                ->whereHas('service', fn ($query) => $query->where('status', 'active'))
                ->count(),
            'recentInvoices' => Invoice::where('user_id', $user->id)->latest()->limit(5)->get(),
            'recentOrders' => Order::where('user_id', $user->id)->latest()->limit(5)->get(),
            'recentServices' => Service::with(['autoRenewalAttempts' => fn ($query) => $query->latest()])
                ->where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(),
            'recentTopUps' => PaymentIntent::where('user_id', $user->id)->where('type', 'wallet_topup')->latest()->limit(5)->get(),
        ]);
    }
}
