<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\Audit\AuditLogger;
use App\Services\Security\ApiKeyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    private const SCOPES = ['account.read'];

    public function index(Request $request): View
    {
        return view('api-keys.index', [
            'apiKeys' => $request->user()->apiKeys()->latest()->get(),
            'scopes' => self::SCOPES,
        ]);
    }

    public function store(Request $request, ApiKeyManager $manager, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(self::SCOPES)],
        ]);

        [$apiKey, $plainKey] = $manager->create($request->user(), $validated['name'], $validated['scopes'] ?? []);

        $auditLogger->record($request->user(), 'api_key_created', $apiKey, [], [
            'name' => $apiKey->name,
            'prefix' => $apiKey->prefix,
            'scopes' => $apiKey->scopes,
        ], [], $request, $apiKey->name);

        return redirect('/api-keys')
            ->with('status', 'API key created. Copy it now; it will not be shown again.')
            ->with('plain_api_key', $plainKey);
    }

    public function revoke(ApiKey $apiKey, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 404);
        $before = ['revoked_at' => $apiKey->revoked_at];

        $apiKey->forceFill(['revoked_at' => now()])->save();

        $auditLogger->record($request->user(), 'api_key_revoked', $apiKey, $before, [
            'revoked_at' => $apiKey->revoked_at,
        ], [], $request, $apiKey->name);

        return redirect('/api-keys')->with('status', 'API key revoked.');
    }
}
