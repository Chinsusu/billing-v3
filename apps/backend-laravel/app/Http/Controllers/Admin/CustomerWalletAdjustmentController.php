<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientWalletBalance;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerWalletAdjustmentController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $currency = strtoupper($validated['currency']);
        $reference = filled($validated['reference'] ?? null) ? trim((string) $validated['reference']) : null;
        $wallet = $this->wallets->walletFor($user, $currency);
        $idempotencyKey = $reference
            ? "admin-wallet-adjustment:{$user->id}:{$currency}:{$reference}"
            : 'admin-wallet-adjustment:'.Str::uuid()->toString();
        $actor = $request->user();

        $meta = [
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'direction' => $validated['direction'],
            'reason' => $validated['reason'],
            'reference' => $reference,
        ];

        try {
            if ($validated['direction'] === 'credit') {
                $this->wallets->credit($wallet, (int) $validated['amount'], $currency, 'admin_wallet_adjustment', null, $idempotencyKey, $validated['reason'], $meta);
            } else {
                $this->wallets->debit($wallet, (int) $validated['amount'], $currency, 'admin_wallet_adjustment', null, $idempotencyKey, $validated['reason'], $meta);
            }
        } catch (InsufficientWalletBalance) {
            return back()->withErrors(['amount' => 'Wallet balance is not enough for this adjustment.'])->withInput();
        }

        return redirect("/admin/customers/{$user->id}")->with('status', 'Wallet adjustment recorded.');
    }
}
