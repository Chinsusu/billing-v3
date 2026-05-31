<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Service;
use App\Services\Services\ServiceAutoRenewalPolicy;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services.index', [
            'services' => Service::with(['user', 'order', 'orderItem', 'product', 'autoRenewalAttempts' => fn ($query) => $query->latest()])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Service $service, ServiceAutoRenewalPolicy $renewalPolicy): View
    {
        $service = $service->load([
            'user',
            'order',
            'orderItem',
            'product',
            'provisioningJobs' => fn ($query) => $query->latest(),
            'providerActionJobs' => fn ($query) => $query->latest(),
            'providerCallbackEvents' => fn ($query) => $query->latest(),
            'provisioningExecutionLogs' => fn ($query) => $query->latest(),
            'cancellations' => fn ($query) => $query->latest(),
            'autoRenewalAttempts' => fn ($query) => $query->latest(),
        ]);

        $ledgerEntries = LedgerEntry::query()
            ->where(fn ($query) => $query
                ->where('source_type', 'order')
                ->where('source_id', $service->order_id))
            ->orWhere(fn ($query) => $query
                ->where('source_type', 'service')
                ->where('source_id', $service->id))
            ->orWhere(fn ($query) => $query
                ->where('user_id', $service->user_id)
                ->where('meta->service_id', $service->id))
            ->latest()
            ->limit(10)
            ->get();

        $relatedInvoices = Invoice::query()
            ->where('user_id', $service->user_id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.services.show', [
            'service' => $service,
            'autoRenewalPolicy' => $renewalPolicy->forService($service),
            'ledgerEntries' => $ledgerEntries,
            'relatedInvoices' => $relatedInvoices,
        ]);
    }
}
