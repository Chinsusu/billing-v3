<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningProviderAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProviderAccountTester
{
    public function __construct(private readonly ProvisioningExecutionRecorder $recorder) {}

    public function test(ProvisioningProviderAccount $account): void
    {
        if ($account->driver === 'sandbox') {
            $log = $this->recorder->startForProviderAccount($account, 'provider_account_test', null, [
                'action' => 'provider_account_test',
                'provider_account' => $account->slug,
            ]);
            $this->recorder->success($log, 0);
            $this->markPassed($account);

            return;
        }

        if ($account->driver === 'cloudmini_v3') {
            $endpoint = rtrim((string) $account->base_url, '/').'/api/v3/capabilities';
            $log = $this->recorder->startForProviderAccount($account, 'provider_account_test', $endpoint, [
                'action' => 'cloudmini_capabilities',
                'provider_account' => $account->slug,
            ]);
            $startedAt = microtime(true);

            try {
                $response = $this->pendingRequest($account)->get($endpoint);
                $payload = $response->json();
                $payload = is_array($payload) ? $payload : [];
                $durationMs = $this->recorder->durationSince($startedAt);

                if (! $response->successful()) {
                    $message = "Provider test returned HTTP {$response->status()}.";
                    $this->recorder->failure($log, $durationMs, 'provider_http_error', $message, $response->status(), $payload);
                    $this->markFailed($account, $message);

                    return;
                }

                $this->recorder->success($log, $durationMs, $response->status(), $payload);
                $this->markPassed($account);
            } catch (Throwable $exception) {
                $message = 'Provider test failed: '.$exception->getMessage();
                $this->recorder->failure($log, $this->recorder->durationSince($startedAt), 'provider_request_failed', $message);
                $this->markFailed($account, $message);
            }

            return;
        }

        $endpoint = $account->endpointUrl();
        $payload = [
            'action' => 'provider_account_test',
            'provider_account' => $account->slug,
            'timestamp' => now()->toISOString(),
        ];
        $log = $this->recorder->startForProviderAccount($account, 'provider_account_test', $endpoint, $payload);
        $startedAt = microtime(true);

        try {
            $response = $this->pendingRequest($account)->post($endpoint, $payload);
            $responsePayload = $response->json();
            $responsePayload = is_array($responsePayload) ? $responsePayload : [];
            $durationMs = $this->recorder->durationSince($startedAt);

            if (! $response->successful()) {
                $message = "Provider test returned HTTP {$response->status()}.";
                $this->recorder->failure($log, $durationMs, 'provider_http_error', $message, $response->status(), $responsePayload);
                $this->markFailed($account, $message);

                return;
            }

            $this->recorder->success($log, $durationMs, $response->status(), $responsePayload);
            $this->markPassed($account);
        } catch (Throwable $exception) {
            $message = 'Provider test failed: '.$exception->getMessage();
            $this->recorder->failure($log, $this->recorder->durationSince($startedAt), 'provider_request_failed', $message);
            $this->markFailed($account, $message);
        }
    }

    private function pendingRequest(ProvisioningProviderAccount $account): PendingRequest
    {
        $request = Http::timeout($account->timeout_seconds)->acceptJson();

        if ($account->auth_type === 'bearer' && $account->api_key !== null) {
            return $request->withToken($account->api_key);
        }

        if ($account->auth_type === 'header' && $account->api_key !== null && $account->auth_header_name !== null) {
            return $request->withHeaders([$account->auth_header_name => $account->api_key]);
        }

        return $request;
    }

    private function markPassed(ProvisioningProviderAccount $account): void
    {
        $account->update([
            'last_tested_at' => now(),
            'last_test_status' => 'passed',
            'last_test_error' => null,
        ]);
    }

    private function markFailed(ProvisioningProviderAccount $account, string $message): void
    {
        $account->update([
            'last_tested_at' => now(),
            'last_test_status' => 'failed',
            'last_test_error' => $message,
        ]);
    }
}
