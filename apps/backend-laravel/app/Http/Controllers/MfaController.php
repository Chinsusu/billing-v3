<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditLogger;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function setup(Request $request, TotpService $totp): View
    {
        $user = $request->user();

        if ($user->mfa_secret === null) {
            $user->forceFill(['mfa_secret' => $totp->generateSecret()])->save();
        }

        return view('mfa.setup', [
            'secret' => $user->mfa_secret,
            'provisioningUri' => $totp->provisioningUri($user->email, $user->mfa_secret),
        ]);
    }

    public function enable(Request $request, TotpService $totp, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        if ($user->mfa_secret === null || ! $totp->verify($user->mfa_secret, $validated['code'])) {
            return back()->withErrors(['code' => 'The authenticator code is invalid.']);
        }

        $plainRecoveryCodes = $totp->recoveryCodes();
        $hashedRecoveryCodes = array_map(fn (string $code): string => Hash::make($code), $plainRecoveryCodes);
        $before = [
            'mfa_enabled_at' => $user->mfa_enabled_at,
            'mfa_required_at' => $user->mfa_required_at,
        ];

        $user->forceFill([
            'mfa_enabled_at' => now(),
            'mfa_required_at' => null,
            'mfa_recovery_codes' => $hashedRecoveryCodes,
        ])->save();
        $request->session()->put('mfa_passed', true);

        $auditLogger->record(
            $user,
            'user_mfa_enabled',
            $user,
            $before,
            ['mfa_enabled_at' => $user->mfa_enabled_at, 'mfa_required_at' => null],
            [],
            $request,
            $user->email,
        );

        return redirect('/dashboard')
            ->with('status', 'MFA enabled.')
            ->with('mfa_recovery_codes', $plainRecoveryCodes);
    }

    public function challenge(): View
    {
        return view('mfa.challenge');
    }

    public function verify(Request $request, TotpService $totp): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        if ($user->mfa_secret === null || ! $totp->verify($user->mfa_secret, $validated['code'])) {
            return back()->withErrors(['code' => 'The authenticator code is invalid.']);
        }

        $request->session()->put('mfa_passed', true);

        return redirect()->intended('/dashboard');
    }

    public function recovery(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'recovery_code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $remaining = [];
        $matched = false;

        foreach ($user->mfa_recovery_codes ?? [] as $hashedCode) {
            if (! $matched && Hash::check($validated['recovery_code'], $hashedCode)) {
                $matched = true;

                continue;
            }

            $remaining[] = $hashedCode;
        }

        if (! $matched) {
            return back()->withErrors(['recovery_code' => 'The recovery code is invalid.']);
        }

        $user->forceFill(['mfa_recovery_codes' => $remaining])->save();
        $request->session()->put('mfa_passed', true);
        $auditLogger->record($user, 'user_mfa_recovery_code_used', $user, [], ['remaining_recovery_codes' => count($remaining)], [], $request, $user->email);

        return redirect()->intended('/dashboard');
    }
}
