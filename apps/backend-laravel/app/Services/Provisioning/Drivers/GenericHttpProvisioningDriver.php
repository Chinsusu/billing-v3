<?php

namespace App\Services\Provisioning\Drivers;

use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\JsonPath;
use App\Services\Provisioning\ProvisioningResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenericHttpProvisioningDriver
{
    public function __construct(private readonly JsonPath $jsonPath) {}

    public function execute(ProvisioningJob $job, ProvisioningProviderAccount $account): ProvisioningResult
    {
        $provider = data_get($job->payload, 'product.provider', []);
        $url = $account->endpointUrl($provider['provision_path'] ?? null);
        if ($url === null) {
            throw new RuntimeException('Provider account endpoint is not configured.');
        }

        $response = $this->pendingRequest($account)->post($url, $this->requestBody($job, $account, $provider));
        if (! $response->successful()) {
            throw new RuntimeException("Provider returned HTTP {$response->status()}.");
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Provider returned invalid JSON.');
        }

        $externalId = $this->jsonPath->get($data, $account->response_external_id_path);
        $status = $this->jsonPath->get($data, $account->response_status_path);
        $config = $this->jsonPath->get($data, $account->response_config_path);

        if (! is_string($externalId) || $externalId === '') {
            throw new RuntimeException('Provider response did not include an external id.');
        }

        if (! in_array(strtolower((string) $status), ['active', 'processed', 'success'], true)) {
            throw new RuntimeException("Provider returned unsupported status {$status}.");
        }

        return new ProvisioningResult(
            status: 'processed',
            externalId: $externalId,
            config: is_array($config) ? $config : [],
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
}
