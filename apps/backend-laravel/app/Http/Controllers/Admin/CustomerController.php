<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\User;
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

    public function show(User $user): View
    {
        $user->load('reseller');

        return view('admin.customers.show', [
            'customer' => $user,
            'resellers' => User::role('reseller')->orderBy('email')->get(),
            'wallets' => $user->wallets()->latest()->get(),
            'ledgerEntries' => LedgerEntry::where('user_id', $user->id)->latest()->limit(20)->get(),
            'invoices' => $user->invoices()->latest()->limit(10)->get(),
            'orders' => $user->orders()->latest()->limit(10)->get(),
            'services' => $user->services()->latest()->limit(10)->get(),
        ]);
    }
}
