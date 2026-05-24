<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Finance\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceRefundCreditController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $service->loadMissing('user');
        $currency = strtoupper($validated['currency']);
        $reference = filled($validated['reference'] ?? null) ? trim((string) $validated['reference']) : null;
        $idempotencyKey = $reference
            ? "service-refund:{$service->id}:{$reference}"
            : 'service-refund:'.$service->id.':'.Str::uuid()->toString();
        $actor = $request->user();

        $this->wallets->credit(
            $this->wallets->walletFor($service->user, $currency),
            (int) $validated['amount'],
            $currency,
            'service_refund',
            $service->id,
            $idempotencyKey,
            $validated['reason'],
            [
                'actor_id' => $actor?->id,
                'actor_email' => $actor?->email,
                'service_id' => $service->id,
                'reference' => $reference,
            ],
        );

        return redirect("/admin/services/{$service->id}")->with('status', 'Refund credit recorded.');
    }
}
