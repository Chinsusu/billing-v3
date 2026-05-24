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
            ->withCount(['invoices', 'orders', 'services'])
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
        return view('admin.customers.show', [
            'customer' => $user,
            'wallets' => $user->wallets()->latest()->get(),
            'ledgerEntries' => LedgerEntry::where('user_id', $user->id)->latest()->limit(20)->get(),
            'invoices' => $user->invoices()->latest()->limit(10)->get(),
            'orders' => $user->orders()->latest()->limit(10)->get(),
            'services' => $user->services()->latest()->limit(10)->get(),
        ]);
    }
}
