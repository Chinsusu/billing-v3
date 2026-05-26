<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'productCount' => Product::count(),
            'activeProductCount' => Product::active()->count(),
            'openInvoiceCount' => Invoice::where('status', 'open')->count(),
            'paymentEventCount' => PaymentEvent::count(),
            'orderCount' => Order::count(),
            'serviceCount' => Service::count(),
            'autoRenewEnabledServiceCount' => Service::where('status', 'active')->where('auto_renew_enabled', true)->count(),
            'failedAutoRenewAttemptCount' => ServiceAutoRenewalAttempt::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count(),
            'pendingProvisioningJobCount' => ProvisioningJob::where('status', 'pending')->count(),
        ]);
    }
}
