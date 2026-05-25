<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSessionController extends Controller
{
    public function revoke(User $user, string $sessionId, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        abort_if($session === null, 404);

        DB::table('sessions')->where('id', $sessionId)->delete();

        $auditLogger->record(
            $request->user(),
            'user_session_revoked',
            $user,
            [],
            [],
            [
                'session_id' => $sessionId,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
            ],
            $request,
            $user->email,
        );

        return redirect("/admin/users/{$user->id}")->with('status', 'Session revoked.');
    }
}
