<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerResellerController extends Controller
{
    public function store(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'reseller_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (($validated['reseller_id'] ?? null) !== null) {
            User::role('reseller')->findOrFail($validated['reseller_id']);
        }

        $before = ['reseller_id' => $user->reseller_id];
        $user->forceFill(['reseller_id' => $validated['reseller_id'] ?? null])->save();

        $auditLogger->record($request->user(), 'customer_reseller_assigned', $user, $before, [
            'reseller_id' => $user->reseller_id,
        ], [], $request, $user->email);

        return redirect("/admin/customers/{$user->id}")->with('status', 'Customer reseller updated.');
    }
}
