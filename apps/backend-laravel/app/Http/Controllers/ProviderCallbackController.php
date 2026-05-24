<?php

namespace App\Http\Controllers;

use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\ProviderCallbackProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class ProviderCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        ProvisioningProviderAccount $provisioningProviderAccount,
        ProviderCallbackProcessor $processor,
    ): JsonResponse {
        if (! $provisioningProviderAccount->enabled) {
            return response()->json(['status' => 'provider_disabled'], 403);
        }

        $secret = (string) $provisioningProviderAccount->callback_secret;
        if ($secret === '') {
            return response()->json(['status' => 'callback_not_configured'], 403);
        }

        $body = $request->getContent();
        $signature = (string) $request->header('X-Provider-Signature', '');
        $expected = hash_hmac('sha256', $body, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['status' => 'invalid_signature'], 401);
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['status' => 'invalid_json'], 422);
        }

        if (! is_array($payload)) {
            return response()->json(['status' => 'invalid_json'], 422);
        }

        $result = $processor->process($provisioningProviderAccount, $payload);

        return response()->json([
            'status' => $result['status'],
            'event_id' => $result['event']->id,
        ], $result['status_code']);
    }
}
