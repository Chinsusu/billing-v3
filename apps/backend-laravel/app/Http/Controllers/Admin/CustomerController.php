<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $customers = User::role('customer')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->with(['wallets' => fn ($query) => $query->orderBy('currency')])
            ->withCount([
                'invoices',
                'orders',
                'services as active_services_count' => fn ($query) => $query->where('status', 'active'),
                'services as suspended_services_count' => fn ($query) => $query->where('status', 'suspended'),
                'services as cancelled_services_count' => fn ($query) => $query->where('status', 'cancelled'),
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $user->load('reseller');
        $activityPerPage = 10;
        $activeCustomerTab = $this->activeCustomerTab($request);

        return view('admin.customers.show', [
            'customer' => $user,
            'activeCustomerTab' => $activeCustomerTab,
            'resellers' => User::role('reseller')->orderBy('email')->get(),
            'wallets' => $user->wallets()->latest()->get(),
            'ledgerEntries' => $this->activityPaginator(
                LedgerEntry::where('user_id', $user->id)->latest(),
                $request,
                'ledger_page',
                'ledger',
                $activityPerPage,
            ),
            'invoices' => $this->activityPaginator(
                $user->invoices()->latest(),
                $request,
                'invoices_page',
                'invoices',
                $activityPerPage,
            ),
            'orders' => $this->activityPaginator(
                $user->orders()->latest(),
                $request,
                'orders_page',
                'orders',
                $activityPerPage,
            ),
            'services' => $this->activityPaginator(
                $user->services()->latest(),
                $request,
                'services_page',
                'services',
                $activityPerPage,
            ),
        ]);
    }

    private function activeCustomerTab(Request $request): string
    {
        $tab = (string) $request->query('activity_tab', 'details');

        return in_array($tab, ['details', 'ledger', 'invoices', 'orders', 'services'], true) ? $tab : 'details';
    }

    private function activityPaginator($query, Request $request, string $pageName, string $tab, int $perPage): LengthAwarePaginator
    {
        return $query
            ->paginate($perPage, ['*'], $pageName)
            ->appends(array_merge(
                $request->except(['activity_tab', 'ledger_page', 'invoices_page', 'orders_page', 'services_page']),
                ['activity_tab' => $tab],
            ));
    }
}
