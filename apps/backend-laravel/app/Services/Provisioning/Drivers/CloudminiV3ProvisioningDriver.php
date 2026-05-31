<?php

namespace App\Services\Provisioning\Drivers;

use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\ProvisioningExecutionRecorder;
use App\Services\Provisioning\ProvisioningResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudminiV3ProvisioningDriver
{
    public function __construct(private readonly ProvisioningExecutionRecorder $recorder) {}

    public function execute(ProvisioningJob $job): ProvisioningResult
    {
        $provider = data_get($job->payload, 'product.provider', []);
        $options = $this->options($provider);
        $route = $this->selectRoute($provider, $options);
        $account = $route['account'];
        $resolved = $route['resolved'];
        $reservationId = null;

        if ((bool) ($options['reserve_capacity'] ?? false)) {
            $reservationId = $this->createReservation($account, $job, $options, $resolved);
        }

        $payload = [
            'kind' => $options['kind'],
            'group_id' => $resolved['group_id'],
            'node_id' => $resolved['node_id'],
            'protocol' => $options['protocol'],
            'bandwidth_limit_mb' => (int) ($options['bandwidth_limit_mb'] ?? 0),
            'speed_limit_mbps' => (int) ($options['speed_limit_mbps'] ?? 0),
            'reservation_id' => $reservationId,
            'external_ref' => $job->service_id,
            'preferred_outbound_ip' => $options['preferred_outbound_ip'] ?? null,
        ];
        $url = $this->endpoint($account, '/api/v3/proxies');
        $log = $this->recorder->startForJob($job, $account, 'cloudmini_create_proxy', $account->driver, $url, $payload);
        $startedAt = microtime(true);

        $response = $this->pendingRequest($account)
            ->withHeaders(['Idempotency-Key' => $job->idempotency_key])
            ->post($url, $payload);
        $durationMs = $this->recorder->durationSince($startedAt);
        $data = $this->data($response->json());

        if (! $response->successful()) {
            $this->recorder->failure($log, $durationMs, 'cloudmini_create_http_error', "Cloudmini create returned HTTP {$response->status()}.", $response->status(), $data);

            throw new RuntimeException("Cloudmini create returned HTTP {$response->status()}.");
        }

        $operationId = data_get($data, 'operation.id');
        if (! is_string($operationId) || $operationId === '') {
            $this->recorder->failure($log, $durationMs, 'cloudmini_missing_operation', 'Cloudmini create did not return operation id.', $response->status(), $data);

            throw new RuntimeException('Cloudmini create did not return operation id.');
        }

        $operation = $this->pollOperation($account, $operationId);
        $snapshot = data_get($operation, 'resource_snapshot');
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $externalId = data_get($snapshot, 'id') ?: data_get($operation, 'resource_id');

        if (! is_string($externalId) || $externalId === '') {
            $this->recorder->failure($log, $durationMs, 'cloudmini_missing_external_id', 'Cloudmini operation did not return proxy id.', $response->status(), $operation);

            throw new RuntimeException('Cloudmini operation did not return proxy id.');
        }

        $this->recorder->success($log, $durationMs, $response->status(), $data);

        return new ProvisioningResult(
            status: 'processed',
            externalId: $externalId,
            config: $snapshot,
        );
    }

    private function selectRoute(array $provider, array $options): array
    {
        $routes = collect($provider['routes'] ?? [])
            ->sortBy([['priority', 'asc'], ['weight', 'desc']])
            ->values();

        foreach ($routes as $route) {
            $accountId = $route['provider_account_id'] ?? $route['account_id'] ?? null;
            $account = is_string($accountId) ? ProvisioningProviderAccount::find($accountId) : null;
            if (! $account instanceof ProvisioningProviderAccount || $account->driver !== 'cloudmini_v3' || ! $account->enabled) {
                continue;
            }

            try {
                $resolved = $this->resolveInventory($account, $route, $options);
            } catch (RuntimeException) {
                continue;
            }

            return ['account' => $account, 'resolved' => $resolved];
        }

        throw new RuntimeException('No Cloudmini route has available capacity.');
    }

    private function resolveInventory(ProvisioningProviderAccount $account, array $route, array $options): array
    {
        $groups = $this->get($account, '/api/v3/inventory/groups', ['kind' => $options['kind']]);
        $group = collect($groups)
            ->first(fn (array $item): bool => ($item['billing_group_id'] ?? null) === ($route['billing_group_id'] ?? null) && $this->hasCapacity($item));

        if (! is_array($group)) {
            throw new RuntimeException('Cloudmini group has no available capacity.');
        }

        $nodeId = null;
        if ($options['kind'] === 'residential' || ($route['node_selector_type'] ?? 'auto') === 'node_name') {
            $nodes = $this->get($account, '/api/v3/inventory/nodes', ['kind' => $options['kind'], 'group_id' => $group['id']]);
            $nodeName = $route['node_name'] ?? null;
            $node = collect($nodes)->first(function (array $item) use ($nodeName): bool {
                if (! $this->hasCapacity($item)) {
                    return false;
                }

                return $nodeName === null || $nodeName === '' || ($item['name'] ?? null) === $nodeName;
            });

            if (! is_array($node)) {
                throw new RuntimeException('Cloudmini node has no available capacity.');
            }

            $nodeId = $node['id'];
        }

        return [
            'billing_group_id' => $route['billing_group_id'],
            'group_id' => $group['id'],
            'node_id' => $nodeId,
        ];
    }

    private function createReservation(ProvisioningProviderAccount $account, ProvisioningJob $job, array $options, array $resolved): string
    {
        $response = $this->pendingRequest($account)->post($this->endpoint($account, '/api/v3/capacity/reservations'), [
            'kind' => $options['kind'],
            'group_id' => $resolved['group_id'],
            'node_id' => $resolved['node_id'],
            'quantity' => 1,
            'ttl_seconds' => 300,
            'external_ref' => $job->service_id,
        ]);
        $data = $this->data($response->json());
        $reservationId = $data['id'] ?? null;
        if (! $response->successful() || ! is_string($reservationId) || $reservationId === '') {
            throw new RuntimeException('Cloudmini reservation failed.');
        }

        return $reservationId;
    }

    private function pollOperation(ProvisioningProviderAccount $account, string $operationId): array
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->pendingRequest($account)->get($this->endpoint($account, "/api/v3/operations/{$operationId}"));
            $data = $this->data($response->json());
            $state = (string) ($data['state'] ?? '');

            if (! $response->successful()) {
                throw new RuntimeException("Cloudmini operation poll returned HTTP {$response->status()}.");
            }

            if ($state === 'succeeded') {
                return $data;
            }

            if (in_array($state, ['failed', 'timed_out', 'cancelled'], true)) {
                throw new RuntimeException("Cloudmini operation {$state}.");
            }
        }

        throw new RuntimeException('Cloudmini operation did not finish.');
    }

    private function get(ProvisioningProviderAccount $account, string $path, array $query): array
    {
        $response = $this->pendingRequest($account)->get($this->endpoint($account, $path), $query);
        if (! $response->successful()) {
            throw new RuntimeException("Cloudmini inventory returned HTTP {$response->status()}.");
        }

        $data = $this->data($response->json());

        return array_values(array_filter(is_array($data) ? $data : [], 'is_array'));
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

    private function endpoint(ProvisioningProviderAccount $account, string $path): string
    {
        return rtrim((string) $account->base_url, '/').'/'.ltrim($path, '/');
    }

    private function options(array $provider): array
    {
        $options = is_array($provider['options'] ?? null) ? $provider['options'] : [];

        return array_replace([
            'kind' => 'ipv4_dc',
            'protocol' => 'default',
            'bandwidth_limit_mb' => 0,
            'speed_limit_mbps' => 0,
            'reserve_capacity' => false,
        ], $options);
    }

    private function data(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $data = $json['data'] ?? $json;

        return is_array($data) ? $data : [];
    }

    private function hasCapacity(array $item): bool
    {
        if (($item['sell_state'] ?? 'sellable') === 'exhausted') {
            return false;
        }

        return (int) ($item['allocatable_units'] ?? $item['free_ip_count'] ?? 0) > 0
            || (int) ($item['free_ip_count'] ?? $item['allocatable_units'] ?? 0) > 0;
    }
}
