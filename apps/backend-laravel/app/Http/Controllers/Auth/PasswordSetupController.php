<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    public function show(string $token, Request $request): RedirectResponse|View
    {
        if ($request->user()?->force_password_reset_at !== null) {
            return redirect('/password/forced-reset');
        }

        return view('auth.password-setup', [
            'email' => (string) $request->query('email', ''),
            'token' => $token,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        if ($request->user()?->force_password_reset_at !== null) {
            return redirect('/password/forced-reset');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $token = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();
        $tokenExpired = $token !== null && $this->tokenIsExpired($token);

        if ($tokenExpired) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
        }

        if (! $token || $tokenExpired || ! Hash::check($validated['token'], $token->token)) {
            return back()
                ->withErrors(['token' => 'This password setup link is invalid or expired.'])
                ->withInput(['email' => $validated['email']]);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();

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

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $auditLogger->record(
                null,
                'user_password_reset_completed',
                $user,
                $before,
                [
                    'force_password_reset_at' => null,
                    'last_password_reset_at' => $user->last_password_reset_at,
                ],
                ['method' => 'setup_link'],
                $request,
                $user->email,
            );
        });

        return redirect('/login')->with('status', 'Password updated. You can now log in.');
    }

    private function tokenIsExpired(object $token): bool
    {
        if ($token->created_at === null) {
            return true;
        }

        $broker = (string) config('auth.defaults.passwords', 'users');
        $expiresMinutes = (int) config("auth.passwords.{$broker}.expire", 60);

        return Carbon::parse($token->created_at)->addMinutes($expiresMinutes)->isPast();
    }
}
