<?php

namespace App\Services\Finance;

use App\Models\BankIntegration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrivateBankClient
{
    public function transactions(BankIntegration $integration): array
    {
        $request = Http::acceptJson();

        if ($integration->api_key !== null && $integration->api_key !== '') {
            $request = $request->withToken($integration->api_key);
        }

        $response = $request->get($integration->transactionsUrl());

        if ($response->failed()) {
            throw new RuntimeException("Bank API returned HTTP {$response->status()}.");
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_key_exists('transactions', $payload) || ! is_array($payload['transactions'])) {
            throw new RuntimeException('Bank API response is missing transactions array.');
        }

        return $payload['transactions'];
    }
}
