<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ResellerPriceOverride;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerPriceOverrideController extends Controller
{
    public function index(): View
    {
        return view('admin.reseller-price-overrides.index', [
            'overrides' => ResellerPriceOverride::with(['reseller', 'product'])->latest()->paginate(20),
            'products' => Product::orderBy('name')->get(),
            'resellers' => User::role('reseller')->orderBy('email')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'reseller_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'price_amount' => ['required', 'integer', 'min:0'],
        ]);

        User::role('reseller')->findOrFail($validated['reseller_id']);

        $override = ResellerPriceOverride::firstOrNew([
            'reseller_id' => $validated['reseller_id'],
            'product_id' => $validated['product_id'],
        ]);
        $before = $override->exists ? ['price_amount' => $override->price_amount] : [];
        $override->fill(['price_amount' => $validated['price_amount']])->save();

        $auditLogger->record($request->user(), 'reseller_price_override_upserted', $override, $before, [
            'reseller_id' => $override->reseller_id,
            'product_id' => $override->product_id,
            'price_amount' => $override->price_amount,
        ], [], $request);

        return redirect('/admin/reseller-price-overrides')->with('status', 'Reseller price override saved.');
    }
}
