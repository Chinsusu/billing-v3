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
        $before = ['force_password_reset_at' => $user->force_password_reset_at];

        $user->forceFill(['force_password_reset_at' => now()])->save();

        $auditLogger->record(
            $request->user(),
            'user_force_password_reset_required',
            $user,
            $before,
            ['force_password_reset_at' => $user->force_password_reset_at],
            [],
            $request,
            $user->email,
        );

        return redirect("/admin/users/{$user->id}")->with('status', 'Password reset will be required at next login.');
    }

    public function clearForcePasswordReset(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $before = ['force_password_reset_at' => $user->force_password_reset_at];

        $user->forceFill(['force_password_reset_at' => null])->save();

        $auditLogger->record(
            $request->user(),
            'user_force_password_reset_cleared',
            $user,
            $before,
            ['force_password_reset_at' => null],
            [],
            $request,
            $user->email,
        );

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

        if (! $authorizationSafety->canDisableUser($user)) {
            return back()->withErrors(['security' => 'You cannot disable the last enabled super admin.']);
        }

        $before = [
            'disabled_at' => $user->disabled_at,
            'disabled_reason' => $user->disabled_reason,
        ];

        $user->forceFill([
            'disabled_at' => now(),
            'disabled_reason' => $validated['reason'],
        ])->save();

        $auditLogger->record(
            $request->user(),
            'user_disabled',
            $user,
            $before,
            [
                'disabled_at' => $user->disabled_at,
                'disabled_reason' => $user->disabled_reason,
            ],
            [],
            $request,
            $user->email,
        );

        return redirect("/admin/users/{$user->id}")->with('status', 'User disabled.');
    }

    public function enable(User $user, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $before = [
            'disabled_at' => $user->disabled_at,
            'disabled_reason' => $user->disabled_reason,
        ];

        $user->forceFill([
            'disabled_at' => null,
            'disabled_reason' => null,
        ])->save();

        $auditLogger->record(
            $request->user(),
            'user_enabled',
            $user,
            $before,
            ['disabled_at' => null, 'disabled_reason' => null],
            [],
            $request,
            $user->email,
        );

        return redirect("/admin/users/{$user->id}")->with('status', 'User enabled.');
    }
}
