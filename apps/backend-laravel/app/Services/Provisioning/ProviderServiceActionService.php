<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProviderServiceActionService
{
    private const PATH_KEYS = [
        'renew' => 'renew_path',
        'suspend' => 'suspend_path',
        'cancel' => 'cancel_path',
        'sync' => 'sync_path',
    ];

    public function __construct(
        private readonly JsonPath $jsonPath,
        private readonly ProviderLifecycleDateParser $dateParser,
        private readonly ProvisioningExecutionRecorder $recorder,
    ) {}

    public function execute(Service $service, string $action, string $idempotencyKey, array $context = []): ?ProviderServiceActionResult
    {
        if (! array_key_exists($action, self::PATH_KEYS)) {
            throw new RuntimeException("Unsupported provider service action {$action}.");
        }

        $provider = $this->providerSnapshot($service);
        $path = $provider[self::PATH_KEYS[$action]] ?? null;
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $account = $this->providerAccount($provider);
        if (! $account instanceof ProvisioningProviderAccount) {
            throw new RuntimeException('Provider account is not configured for this service.');
        }

        if (str_contains($path, '{external_id}') && empty($service->external_id)) {
            throw new RuntimeException('Provider service external id is missing.');
        }

        $path = $this->renderPath($path, $service, $provider);
        $url = $account->endpointUrl($path);
        if ($url === null) {
            throw new RuntimeException('Provider service action endpoint is not configured.');
        }

        $payload = $this->requestBody($service, $action, $idempotencyKey, $provider, $context);
        $log = $this->recorder->startForService($service, $account, "provider_service_{$action}", $account->driver, $url, $payload);
        $startedAt = microtime(true);

        try {
            $response = $action === 'sync'
                ? $this->pendingRequest($account)->get($url)
                : $this->pendingRequest($account)->post($url, $payload);
        } catch (\Throwable $exception) {
            $this->recorder->failure($log, $this->recorder->durationSince($startedAt), 'provider_action_request_failed', $exception->getMessage());

            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        $durationMs = $this->recorder->durationSince($startedAt);
        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (! $response->successful()) {
            $message = "Provider {$action} action returned HTTP {$response->status()}.";
            $this->recorder->failure($log, $durationMs, 'provider_action_http_error', $message, $response->status(), $data);

            throw new RuntimeException($message);
        }

        if ($action === 'sync' && $data === []) {
            $message = 'Provider sync action returned invalid JSON.';
            $this->recorder->failure($log, $durationMs, 'provider_action_invalid_json', $message, $response->status());

            throw new RuntimeException($message);
        }

        try {
            $expiresAt = $this->expiresAtFromResponse($service, $data);
        } catch (RuntimeException $exception) {
            $this->recorder->failure($log, $durationMs, 'provider_action_invalid_date', $exception->getMessage(), $response->status(), $data);

            throw $exception;
        }

        $this->recorder->success($log, $durationMs, $response->status(), $data);

        return new ProviderServiceActionResult(
            status: $this->statusFromResponse($account, $data),
            expiresAt: $expiresAt,
            response: $data,
        );
    }

    public function hasConfiguredAction(Service $service, string $action): bool
    {
        if (! array_key_exists($action, self::PATH_KEYS)) {
            return false;
        }

        $provider = $this->providerSnapshot($service);
        $path = $provider[self::PATH_KEYS[$action]] ?? null;

        return is_string($path) && trim($path) !== '';
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

    private function providerSnapshot(Service $service): array
    {
        $provider = $service->meta['provider'] ?? null;
        if (is_array($provider) && $provider !== []) {
            return $provider;
        }

        $service->loadMissing('product.providerAccount');
        $product = $service->product;
        $account = $product?->providerAccount;

        return [
            'account_id' => $account?->id,
            'account_slug' => $account?->slug,
            'driver' => $account?->driver ?? 'sandbox',
            'plan_code' => $product?->provider_plan_code,
            'region' => $product?->provider_region,
            'provision_path' => $product?->provider_provision_path ?: $account?->provision_path,
            'renew_path' => $product?->provider_renew_path,
            'suspend_path' => $product?->provider_suspend_path,
            'cancel_path' => $product?->provider_cancel_path,
            'sync_path' => $product?->provider_sync_path,
            'options' => $product?->provider_options ?? [],
        ];
    }

    private function providerAccount(array $provider): ?ProvisioningProviderAccount
    {
        if (! empty($provider['account_id'])) {
            return ProvisioningProviderAccount::find($provider['account_id']);
        }

        if (! empty($provider['account_slug'])) {
            return ProvisioningProviderAccount::where('slug', $provider['account_slug'])->first();
        }

        return null;
    }

    private function renderPath(string $path, Service $service, array $provider): string
    {
        $replacements = [
            '{external_id}' => rawurlencode((string) $service->external_id),
            '{service_id}' => rawurlencode((string) $service->id),
            '{product_code}' => rawurlencode((string) $service->product_code),
            '{plan_code}' => rawurlencode((string) ($provider['plan_code'] ?? '')),
            '{region}' => rawurlencode((string) ($provider['region'] ?? '')),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $path);
    }

    private function requestBody(Service $service, string $action, string $idempotencyKey, array $provider, array $context): array
    {
        $base = [
            'action' => $action,
            'idempotency_key' => $idempotencyKey,
            'service_id' => $service->id,
            'external_id' => $service->external_id,
            'product' => [
                'code' => $service->product_code,
                'name' => $service->product_name,
                'type' => $service->product_type,
                'plan_code' => $provider['plan_code'] ?? null,
                'region' => $provider['region'] ?? null,
                'options' => $provider['options'] ?? [],
            ],
            'context' => $context,
        ];

        $account = $this->providerAccount($provider);

        return array_replace_recursive($account?->request_template ?? [], $base);
    }

    private function expiresAtFromResponse(Service $service, array $data): ?Carbon
    {
        $path = data_get($service->meta, 'lifecycle_policy.expires_at_path');
        if (! is_string($path) || trim($path) === '' || $data === []) {
            return null;
        }

        $value = $this->jsonPath->get($data, $path);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->dateParser->parse(
                $value,
                (string) data_get($service->meta, 'lifecycle_policy.date_format', 'iso8601'),
                (string) data_get($service->meta, 'lifecycle_policy.timezone', 'UTC'),
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Provider action returned invalid expiry date.', previous: $exception);
        }
    }

    private function statusFromResponse(ProvisioningProviderAccount $account, array $data): ?string
    {
        if ($data === []) {
            return null;
        }

        $status = $this->jsonPath->get($data, $account->response_status_path);
        if (! is_string($status) || trim($status) === '') {
            return null;
        }

        return match (strtolower($status)) {
            'active', 'processed', 'success' => 'active',
            'suspended', 'expired' => 'expired',
            'cancelled', 'canceled' => 'cancelled',
            default => strtolower($status),
        };
    }
}
