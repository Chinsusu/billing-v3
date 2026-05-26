<?php

namespace App\Services\Services;

use App\Exceptions\InsufficientWalletBalance;
use App\Exceptions\ServiceAutoRenewalSkipped;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Finance\WalletService;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Provisioning\ProviderServiceActionService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServiceRenewalService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ServiceLifecyclePolicy $lifecyclePolicy,
        private readonly ProviderServiceActionService $providerActions,
        private readonly NotificationOutbox $notifications,
    ) {}

    public function renew(Service $service, User $user, ?Closure $precondition = null): Service
    {
        $providerError = null;
        $renewedService = DB::transaction(function () use ($service, $user, $precondition, &$providerError): Service {
            $lockedService = Service::with(['orderItem', 'product'])
                ->whereKey($service->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedService->user_id !== $user->id) {
                abort(404);
            }

            if ($lockedService->status !== 'active') {
                throw ValidationException::withMessages([
                    'service' => 'Only active services can be renewed.',
                ]);
            }

            if ($lockedService->expires_at === null) {
                throw ValidationException::withMessages([
                    'service' => 'Service expiry is missing.',
                ]);
            }

            if ($precondition !== null && $precondition($lockedService) === false) {
                throw new ServiceAutoRenewalSkipped('Auto-renewal candidate is no longer eligible.');
            }

            $policy = $this->lifecyclePolicy->forService($lockedService);
            $durationDays = (int) ($lockedService->meta['duration_days'] ?? $lockedService->orderItem?->duration_days ?? $policy['count'] ?? 0);
            if ($durationDays <= 0) {
                throw ValidationException::withMessages([
                    'service' => 'Service duration is missing.',
                ]);
            }

            $amount = (int) ($lockedService->product?->price_amount ?? $lockedService->orderItem?->unit_amount ?? 0);
            $currency = (string) ($lockedService->product?->currency ?? $lockedService->orderItem?->currency ?? 'VND');
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'service' => 'Service renewal price is missing.',
                ]);
            }

            $oldExpiresAt = $lockedService->expires_at->copy();
            $baseExpiresAt = $oldExpiresAt->greaterThan(now()) ? $oldExpiresAt : now();
            $newExpiresAt = $this->lifecyclePolicy->expiresAt($baseExpiresAt, $policy);
            $wallet = $this->walletService->walletFor($user, $currency);
            $lockedWallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($lockedWallet->balance_amount < $amount) {
                throw new InsufficientWalletBalance('Wallet balance is not enough to pay this invoice.');
            }

            $idempotencyKey = "service-renewal:{$lockedService->id}:{$oldExpiresAt->toISOString()}";
            try {
                $providerResult = $this->providerActions->execute($lockedService, 'renew', $idempotencyKey, [
                    'old_expires_at' => $oldExpiresAt->toISOString(),
                    'candidate_expires_at' => $newExpiresAt->toISOString(),
                ]);
            } catch (RuntimeException $exception) {
                $providerError = $exception->getMessage();

                return $lockedService->refresh();
            }

            if ($providerResult?->expiresAt !== null) {
                $newExpiresAt = $providerResult->expiresAt;
            }

            $this->walletService->debit(
                $lockedWallet,
                $amount,
                $currency,
                'service_renewal',
                $lockedService->id,
                $idempotencyKey,
                "Renew {$lockedService->product_name}",
                [
                    'old_expires_at' => $oldExpiresAt->toISOString(),
                    'new_expires_at' => $newExpiresAt->toISOString(),
                ]
            );

            $meta = $lockedService->meta ?? [];
            $meta['renewals'] = $meta['renewals'] ?? [];
            $meta['renewals'][] = [
                'renewed_at' => Carbon::now()->toISOString(),
                'old_expires_at' => $oldExpiresAt->toISOString(),
                'new_expires_at' => $newExpiresAt->toISOString(),
                'amount' => $amount,
                'currency' => strtoupper($currency),
            ];

            $lockedService->forceFill([
                'expires_at' => $newExpiresAt,
                'meta' => $meta,
            ])->save();

            $this->notifications->enqueue(
                $user,
                'service_renewed',
                $user->email,
                'Service renewed',
                "Your service {$lockedService->product_name} was renewed until {$newExpiresAt->toDateTimeString()}.",
                'service',
                $lockedService->id,
                "service-renewed:{$lockedService->id}:".$newExpiresAt->toISOString(),
                [
                    'service_id' => $lockedService->id,
                    'old_expires_at' => $oldExpiresAt->toISOString(),
                    'new_expires_at' => $newExpiresAt->toISOString(),
                    'amount' => $amount,
                    'currency' => strtoupper($currency),
                ],
            );

            return $lockedService->refresh();
        });

        if ($providerError !== null) {
            throw ValidationException::withMessages(['provider' => $providerError]);
        }

        return $renewedService;
    }
}
