<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ForcedPasswordResetController extends Controller
{
    public function edit(): View
    {
        return view('auth.forced-password-reset');
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($auditLogger, $request, $user, $validated): void {
            $before = [
                'force_password_reset_at' => $user->force_password_reset_at,
                'last_password_reset_at' => $user->last_password_reset_at,
            ];

            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'force_password_reset_at' => null,
                'last_password_reset_at' => now(),
            ])->save();

            $auditLogger->record(
                $user,
                'user_forced_password_reset_completed',
                $user,
                $before,
                [
                    'force_password_reset_at' => null,
                    'last_password_reset_at' => $user->last_password_reset_at,
                ],
                ['method' => 'authenticated_forced_reset'],
                $request,
                $user->email,
            );
        });

        return redirect('/dashboard')->with('status', 'Password changed.');
    }
}
