<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserMfaController extends Controller
{
    public function require(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $before = ['mfa_required_at' => $user->mfa_required_at];

        $user->forceFill(['mfa_required_at' => now()])->save();

        $auditLogger->record(
            $request->user(),
            'user_mfa_required',
            $user,
            $before,
            ['mfa_required_at' => $user->mfa_required_at],
            [],
            $request,
            $user->email,
        );

        return redirect("/admin/users/{$user->id}")->with('status', 'MFA required for user.');
    }

    public function reset(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $before = [
            'mfa_enabled_at' => $user->mfa_enabled_at,
            'mfa_required_at' => $user->mfa_required_at,
            'recovery_code_count' => count($user->mfa_recovery_codes ?? []),
        ];

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
            'mfa_required_at' => null,
            'mfa_recovery_codes' => [],
        ])->save();

        $auditLogger->record(
            $request->user(),
            'user_mfa_reset',
            $user,
            $before,
            ['mfa_enabled_at' => null, 'mfa_required_at' => null, 'recovery_code_count' => 0],
            [],
            $request,
            $user->email,
        );

        return redirect("/admin/users/{$user->id}")->with('status', 'MFA reset for user.');
    }
}
