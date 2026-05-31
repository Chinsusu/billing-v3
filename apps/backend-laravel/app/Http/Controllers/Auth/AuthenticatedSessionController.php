<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if ($user?->disabled_at !== null) {
            $auditLogger->record(
                null,
                'user_login_blocked_disabled',
                $user,
                [],
                [],
                ['disabled_reason' => $user->disabled_reason],
                $request,
                $user->email,
            );

            throw ValidationException::withMessages([
                'email' => 'This account is disabled.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $loginAt = now();
        $user->forceFill([
            'last_login_at' => $loginAt,
            'last_login_ip' => $request->ip(),
            'last_login_user_agent' => $request->userAgent(),
        ])->save();
        $request->session()->put('mfa_passed', $user->mfa_enabled_at === null && $user->mfa_required_at === null);

        $auditLogger->record(
            $user,
            'user_login_succeeded',
            $user,
            [],
            ['last_login_at' => $loginAt],
            ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
            $request,
            $user->email,
        );

        if ($user->mfa_enabled_at !== null) {
            return redirect('/mfa/challenge');
        }

        if ($user->mfa_required_at !== null) {
            return redirect('/mfa/setup');
        }

        return redirect()->intended($this->homePathFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function homePathFor(User $user): string
    {
        return $user->can('admin.access') ? '/admin' : '/dashboard';
    }
}
