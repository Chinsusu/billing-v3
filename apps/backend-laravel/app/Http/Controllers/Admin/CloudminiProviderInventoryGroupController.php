<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningProviderAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class CloudminiProviderInventoryGroupController extends Controller
{
    public function __invoke(ProvisioningProviderAccount $provisioningProviderAccount): JsonResponse
    {
        abort_unless($provisioningProviderAccount->driver === 'cloudmini_v3', 404);

        $groups = collect(['ipv4_dc', 'residential'])
            ->flatMap(fn (string $kind): array => $this->groupsForKind($provisioningProviderAccount, $kind))
            ->values()
            ->all();

        return response()->json([
            'account' => [
                'id' => $provisioningProviderAccount->id,
                'slug' => $provisioningProviderAccount->slug,
                'name' => $provisioningProviderAccount->name,
                'base_url' => $provisioningProviderAccount->base_url,
            ],
            'groups' => $groups,
        ]);
    }

    private function groupsForKind(ProvisioningProviderAccount $account, string $kind): array
    {
        $response = $this->pendingRequest($account)
            ->get(rtrim((string) $account->base_url, '/').'/api/v3/inventory/groups', ['kind' => $kind]);

        abort_unless($response->successful(), 502, "Cloudmini inventory groups returned HTTP {$response->status()}.");

        $data = $response->json('data');
        $items = is_array($data) ? $data : [];

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $group): array => [
                'kind' => $kind,
                'id' => $group['id'] ?? null,
                'name' => $group['name'] ?? null,
                'billing_group_id' => $group['billing_group_id'] ?? null,
                'sell_state' => $group['sell_state'] ?? null,
                'allocatable_units' => $group['allocatable_units'] ?? null,
                'free_ip_count' => $group['free_ip_count'] ?? null,
            ])
            ->all();
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
}
