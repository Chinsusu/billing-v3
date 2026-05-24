<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\Service;
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
            'pendingProvisioningJobCount' => ProvisioningJob::where('status', 'pending')->count(),
        ]);
    }
}
