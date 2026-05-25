<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaIsVerified
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->mfa_enabled_at !== null && $request->session()->get('mfa_passed') !== true) {
            return redirect('/mfa/challenge');
        }

        if ($user->mfa_required_at !== null && $user->mfa_enabled_at === null) {
            return redirect('/mfa/setup');
        }

        return $next($request);
    }
}
