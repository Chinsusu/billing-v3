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
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private const STUCK_PROCESSING_MINUTES = 5;

    private const REVENUE_CURRENCY = 'VND';

    public function __invoke(): View
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $todayStart = $now->copy()->startOfDay();
        $pendingProvisioningJobCount = ProvisioningJob::where('status', 'pending')->count();
        $processingProvisioningJobCount = ProvisioningJob::where('status', 'processing')->count();
        $failedProvisioningJobCount = ProvisioningJob::where('status', 'failed')->count();
        $stuckProvisioningJobCount = ProvisioningJob::where('status', 'processing')
            ->where('updated_at', '<=', $now->copy()->subMinutes(self::STUCK_PROCESSING_MINUTES))
            ->count();
        $openSupportTicketCount = SupportTicket::whereIn('status', ['open', 'pending'])->count();
        $urgentSupportTicketCount = SupportTicket::whereIn('status', ['open', 'pending'])
            ->where('priority', 'urgent')
            ->count();

        return view('admin.dashboard', [
            'monthlyRevenueAmount' => $this->revenueBetween($monthStart, $now),
            'todayRevenueAmount' => $this->revenueBetween($todayStart, $now),
            'revenueCurrency' => self::REVENUE_CURRENCY,
            'openInvoiceAmount' => (int) Invoice::where('status', 'open')->where('currency', self::REVENUE_CURRENCY)->sum('total_amount'),
            'productCount' => Product::count(),
            'activeProductCount' => Product::active()->count(),
            'openInvoiceCount' => Invoice::where('status', 'open')->count(),
            'paymentEventCount' => PaymentEvent::count(),
            'paymentExceptionCount' => PaymentEvent::whereIn('status', ['unmatched', 'rejected', 'expired'])->count(),
            'orderCount' => Order::count(),
            'serviceCount' => Service::count(),
            'activeServiceCount' => Service::where('status', 'active')->count(),
            'pendingProvisioningServiceCount' => Service::where('status', 'pending_provision')->count(),
            'autoRenewEnabledServiceCount' => Service::where('status', 'active')->where('auto_renew_enabled', true)->count(),
            'failedAutoRenewAttemptCount' => ServiceAutoRenewalAttempt::where('status', 'failed')->where('updated_at', '>=', $now->copy()->subDays(7))->count(),
            'pendingProvisioningJobCount' => $pendingProvisioningJobCount,
            'processingProvisioningJobCount' => $processingProvisioningJobCount,
            'failedProvisioningJobCount' => $failedProvisioningJobCount,
            'stuckProvisioningJobCount' => $stuckProvisioningJobCount,
            'openSupportTicketCount' => $openSupportTicketCount,
            'urgentSupportTicketCount' => $urgentSupportTicketCount,
            'customerCount' => User::role('customer')->count(),
            'recentPaidOrders' => Order::with('user')
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->latest('paid_at')
                ->limit(5)
                ->get(),
            'provisioningQueueJobs' => ProvisioningJob::with(['service', 'user'])
                ->whereIn('status', ['failed', 'processing', 'pending'])
                ->orderByRaw("case status when 'failed' then 0 when 'processing' then 1 else 2 end")
                ->oldest('available_at')
                ->limit(8)
                ->get(),
            'serviceStatusCounts' => Service::select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->orderByDesc('aggregate')
                ->get(),
            'serviceTypeCounts' => Service::select('product_type', DB::raw('count(*) as aggregate'))
                ->groupBy('product_type')
                ->orderByDesc('aggregate')
                ->limit(6)
                ->get(),
        ]);
    }

    private function revenueBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $paidOrders = Order::where('status', 'paid')
            ->where('currency', self::REVENUE_CURRENCY)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total_amount');
        $paidInvoices = Invoice::where('status', 'paid')
            ->where('currency', self::REVENUE_CURRENCY)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total_amount');

        return (int) $paidOrders + (int) $paidInvoices;
    }
}
