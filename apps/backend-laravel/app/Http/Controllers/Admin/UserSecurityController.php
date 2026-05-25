<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\AdminAuthorizationSafety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserSecurityController extends Controller
{
    public function sendResetLink(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $token = Str::random(64);
        $url = url("/password/setup/{$token}").'?email='.urlencode($user->email);
        $before = ['invited_at' => $user->invited_at];

        DB::transaction(function () use ($auditLogger, $before, $request, $token, $user): void {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            $user->forceFill(['invited_at' => now()])->save();

            $auditLogger->record(
                $request->user(),
                'user_password_setup_link_created',
                $user,
                $before,
                ['invited_at' => $user->invited_at],
                ['delivery' => 'manual_admin_copy'],
                $request,
                $user->email,
            );
        });

        return redirect("/admin/users/{$user->id}")
            ->with('status', "Password setup link created: {$url}")
            ->with('password_setup_url', $url);
    }

    public function forcePasswordReset(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($auditLogger, $request, $user): void {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = ['force_password_reset_at' => $lockedUser->force_password_reset_at];

            $lockedUser->forceFill(['force_password_reset_at' => now()])->save();

            $auditLogger->record(
                $request->user(),
                'user_force_password_reset_required',
                $lockedUser,
                $before,
                ['force_password_reset_at' => $lockedUser->force_password_reset_at],
                [],
                $request,
                $lockedUser->email,
            );
        });

        return redirect("/admin/users/{$user->id}")->with('status', 'Password reset will be required at next login.');
    }

    public function clearForcePasswordReset(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($auditLogger, $request, $user): void {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = ['force_password_reset_at' => $lockedUser->force_password_reset_at];

            $lockedUser->forceFill(['force_password_reset_at' => null])->save();

            $auditLogger->record(
                $request->user(),
                'user_force_password_reset_cleared',
                $lockedUser,
                $before,
                ['force_password_reset_at' => null],
                [],
                $request,
                $lockedUser->email,
            );
        });

        return redirect("/admin/users/{$user->id}")->with('status', 'Forced password reset cleared.');
    }

    public function disable(
        User $user,
        Request $request,
        AuditLogger $auditLogger,
        AdminAuthorizationSafety $authorizationSafety,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($request->user()?->is($user)) {
            return back()->withErrors(['security' => 'You cannot disable your own account.']);
        }

        DB::transaction(function () use ($auditLogger, $authorizationSafety, $request, $user, $validated): void {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $authorizationSafety->canDisableUser($lockedUser, true)) {
                throw ValidationException::withMessages([
                    'security' => 'You cannot disable the last enabled super admin.',
                ]);
            }

            $before = [
                'disabled_at' => $lockedUser->disabled_at,
                'disabled_reason' => $lockedUser->disabled_reason,
            ];

            $lockedUser->forceFill([
                'disabled_at' => now(),
                'disabled_reason' => $validated['reason'],
            ])->save();

            $auditLogger->record(
                $request->user(),
                'user_disabled',
                $lockedUser,
                $before,
                [
                    'disabled_at' => $lockedUser->disabled_at,
                    'disabled_reason' => $lockedUser->disabled_reason,
                ],
                [],
                $request,
                $lockedUser->email,
            );
        });

        return redirect("/admin/users/{$user->id}")->with('status', 'User disabled.');
    }

    public function enable(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($auditLogger, $request, $user): void {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = [
                'disabled_at' => $lockedUser->disabled_at,
                'disabled_reason' => $lockedUser->disabled_reason,
            ];

            $lockedUser->forceFill([
                'disabled_at' => null,
                'disabled_reason' => null,
            ])->save();

            $auditLogger->record(
                $request->user(),
                'user_enabled',
                $lockedUser,
                $before,
                ['disabled_at' => null, 'disabled_reason' => null],
                [],
                $request,
                $lockedUser->email,
            );
        });

        return redirect("/admin/users/{$user->id}")->with('status', 'User enabled.');
    }
}
