<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerImpersonationController extends Controller
{
    public function start(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        if ($user->hasPermissionTo('admin.access')) {
            return back()->withErrors(['impersonation' => 'Admin users cannot be impersonated.']);
        }

        $actor = $request->user();
        $request->session()->put('impersonator_id', $actor->id);
        $request->session()->put('impersonator_email', $actor->email);
        $request->session()->put('impersonated_user_id', $user->id);
        $request->session()->put('impersonated_user_email', $user->email);
        $request->session()->put('mfa_passed', true);

        Auth::login($user);
        $request->session()->regenerate();

        $auditLogger->record($actor, 'customer_impersonation_started', $user, [], [], [], $request, $user->email);

        return redirect('/dashboard')->with('status', "Impersonating {$user->email}.");
    }

    public function stop(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        $impersonatedUserId = $request->session()->get('impersonated_user_id');
        abort_if($impersonatorId === null || $impersonatedUserId === null, 404);

        $impersonator = User::findOrFail($impersonatorId);
        $impersonated = User::findOrFail($impersonatedUserId);

        Auth::login($impersonator);
        $request->session()->forget([
            'impersonator_id',
            'impersonator_email',
            'impersonated_user_id',
            'impersonated_user_email',
        ]);
        $request->session()->put('mfa_passed', true);
        $request->session()->regenerate();

        $auditLogger->record($impersonator, 'customer_impersonation_stopped', $impersonated, [], [], [], $request, $impersonated->email);

        return redirect("/admin/customers/{$impersonated->id}")->with('status', 'Impersonation stopped.');
    }
}
