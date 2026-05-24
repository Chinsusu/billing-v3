<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Models\User;
use App\Services\Finance\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRenewalService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ServiceLifecyclePolicy $lifecyclePolicy,
    ) {}

    public function renew(Service $service, User $user): Service
    {
        return DB::transaction(function () use ($service, $user): Service {
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

            $this->walletService->debit(
                $wallet,
                $amount,
                $currency,
                'service_renewal',
                $lockedService->id,
                "service-renewal:{$lockedService->id}:{$oldExpiresAt->toISOString()}",
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

            return $lockedService->refresh();
        });
    }
}
