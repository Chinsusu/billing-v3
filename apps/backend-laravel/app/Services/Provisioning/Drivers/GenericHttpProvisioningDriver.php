<?php

namespace App\Services\Provisioning\Drivers;

use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\JsonPath;
use App\Services\Provisioning\ProviderLifecycleDateParser;
use App\Services\Provisioning\ProvisioningExecutionRecorder;
use App\Services\Provisioning\ProvisioningResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenericHttpProvisioningDriver
{
    public function __construct(
        private readonly JsonPath $jsonPath,
        private readonly ProvisioningExecutionRecorder $recorder,
        private readonly ProviderLifecycleDateParser $dateParser,
    ) {}

    public function execute(ProvisioningJob $job, ProvisioningProviderAccount $account): ProvisioningResult
    {
        $provider = data_get($job->payload, 'product.provider', []);
        $url = $account->endpointUrl($provider['provision_path'] ?? null);
        if ($url === null) {
            throw new RuntimeException('Provider account endpoint is not configured.');
        }

        $requestBody = $this->requestBody($job, $account, $provider);
        $log = $this->recorder->startForJob($job, $account, 'provision_service', $account->driver, $url, $requestBody);
        $startedAt = microtime(true);

        $response = $this->pendingRequest($account)->post($url, $requestBody);
        $durationMs = $this->recorder->durationSince($startedAt);
        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (! $response->successful()) {
            $this->recorder->failure($log, $durationMs, 'provider_http_error', "Provider returned HTTP {$response->status()}.", $response->status(), $data);

            throw new RuntimeException("Provider returned HTTP {$response->status()}.");
        }

        if ($data === []) {
            $this->recorder->failure($log, $durationMs, 'provider_invalid_json', 'Provider returned invalid JSON.', $response->status());

            throw new RuntimeException('Provider returned invalid JSON.');
        }

        $externalId = $this->jsonPath->get($data, $account->response_external_id_path);
        $status = $this->jsonPath->get($data, $account->response_status_path);
        $config = $this->jsonPath->get($data, $account->response_config_path);

        if (! is_string($externalId) || $externalId === '') {
            $this->recorder->failure($log, $durationMs, 'provider_missing_external_id', 'Provider response did not include an external id.', $response->status(), $data);

            throw new RuntimeException('Provider response did not include an external id.');
        }

        if (! in_array(strtolower((string) $status), ['active', 'processed', 'success'], true)) {
            $this->recorder->failure($log, $durationMs, 'provider_unsupported_status', "Provider returned unsupported status {$status}.", $response->status(), $data);

            throw new RuntimeException("Provider returned unsupported status {$status}.");
        }

        $this->recorder->success($log, $durationMs, $response->status(), $data);
        [$orderedAt, $expiresAt] = $this->lifecycleDates($job, $account, $externalId, $data);

        return new ProvisioningResult(
            status: 'processed',
            externalId: $externalId,
            config: is_array($config) ? $config : [],
            orderedAt: $orderedAt,
            expiresAt: $expiresAt,
        );
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

    private function requestBody(ProvisioningJob $job, ProvisioningProviderAccount $account, array $provider): array
    {
        $product = data_get($job->payload, 'product', []);
        $base = [
            'idempotency_key' => $job->idempotency_key,
            'order_id' => $job->order_id,
            'service_id' => $job->service_id,
            'user_id' => $job->user_id,
            'product' => [
                'id' => $product['id'] ?? null,
                'code' => $product['code'] ?? null,
                'name' => $product['name'] ?? null,
                'type' => $product['type'] ?? null,
                'duration_days' => $product['duration_days'] ?? null,
                'config' => $product['config'] ?? [],
                'plan_code' => $provider['plan_code'] ?? null,
                'region' => $provider['region'] ?? null,
                'options' => $provider['options'] ?? [],
            ],
        ];

        return array_replace_recursive($account->request_template ?? [], $base);
    }

    private function lifecycleDates(ProvisioningJob $job, ProvisioningProviderAccount $account, string $externalId, array $provisionData): array
    {
        $policy = data_get($job->payload, 'product.lifecycle_policy', []);
        if (! is_array($policy) || ($policy['source'] ?? 'local_policy') === 'local_policy') {
            return [null, null];
        }

        if (($policy['source'] ?? null) === 'provider_response') {
            return $this->parseLifecycleDates($provisionData, $policy, 'provider_response');
        }

        if (($policy['source'] ?? null) !== 'provider_lookup') {
            return [null, null];
        }

        $path = $policy['provider_lifecycle_path'] ?? null;
        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('Provider lifecycle lookup path is not configured.');
        }

        $path = str_replace('{external_id}', rawurlencode($externalId), $path);
        $url = $account->endpointUrl($path);
        if ($url === null) {
            throw new RuntimeException('Provider lifecycle lookup endpoint is not configured.');
        }

        $lookupLog = $this->recorder->startForJob($job, $account, 'provider_lifecycle_lookup', $account->driver, $url, [
            'external_id' => $externalId,
            'path' => $path,
        ]);
        $startedAt = microtime(true);
        $response = $this->pendingRequest($account)->get($url);
        $durationMs = $this->recorder->durationSince($startedAt);
        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (! $response->successful()) {
            $message = "Provider lifecycle lookup returned HTTP {$response->status()}.";
            $this->recorder->failure($lookupLog, $durationMs, 'provider_lifecycle_http_error', $message, $response->status(), $data);

            throw new RuntimeException($message);
        }

        if ($data === []) {
            $message = 'Provider lifecycle lookup returned invalid JSON.';
            $this->recorder->failure($lookupLog, $durationMs, 'provider_lifecycle_invalid_json', $message, $response->status());

            throw new RuntimeException($message);
        }

        try {
            [$orderedAt, $expiresAt] = $this->parseLifecycleDates($data, $policy, 'provider_lifecycle');
        } catch (RuntimeException $exception) {
            $this->recorder->failure($lookupLog, $durationMs, $exception->getMessage(), $this->messageForLifecycleCode($exception->getMessage()), $response->status(), $data);

            throw new RuntimeException($this->messageForLifecycleCode($exception->getMessage()));
        }

        $this->recorder->success($lookupLog, $durationMs, $response->status(), $data);

        return [$orderedAt, $expiresAt];
    }

    private function parseLifecycleDates(array $data, array $policy, string $source): array
    {
        $orderedAt = $this->jsonPath->get($data, $policy['ordered_at_path'] ?? null);
        if ($orderedAt === null || $orderedAt === '') {
            throw new RuntimeException($source === 'provider_response' ? 'provider_lifecycle_missing_ordered_at' : 'provider_lifecycle_missing_ordered_at');
        }

        $expiresAt = $this->jsonPath->get($data, $policy['expires_at_path'] ?? null);
        if ($expiresAt === null || $expiresAt === '') {
            throw new RuntimeException('provider_lifecycle_missing_expires_at');
        }

        try {
            return [
                $this->dateParser->parse($orderedAt, (string) ($policy['date_format'] ?? 'iso8601'), (string) ($policy['timezone'] ?? 'UTC')),
                $this->dateParser->parse($expiresAt, (string) ($policy['date_format'] ?? 'iso8601'), (string) ($policy['timezone'] ?? 'UTC')),
            ];
        } catch (\Throwable) {
            throw new RuntimeException('provider_lifecycle_invalid_date');
        }
    }

    private function messageForLifecycleCode(string $code): string
    {
        return match ($code) {
            'provider_lifecycle_missing_ordered_at' => 'Provider lifecycle lookup did not include ordered_at.',
            'provider_lifecycle_missing_expires_at' => 'Provider lifecycle lookup did not include expires_at.',
            'provider_lifecycle_invalid_date' => 'Provider lifecycle lookup returned invalid date.',
            default => 'Provider lifecycle lookup failed.',
        };
    }
}
