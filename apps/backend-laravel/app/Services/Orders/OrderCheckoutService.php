<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use App\Services\Finance\WalletService;
use App\Services\Resellers\ResellerPricingService;
use App\Services\Services\ServiceLifecyclePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCheckoutService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ServiceLifecyclePolicy $lifecyclePolicy,
        private readonly ResellerPricingService $resellerPricing,
    ) {}

    public function checkout(User $user, Product $product): Order
    {
        return DB::transaction(function () use ($user, $product): Order {
            $product->loadMissing('providerAccount', 'providerRoutes.providerAccount');
            $providerAccount = $product->providerAccount ?: ProvisioningProviderAccount::where('slug', 'sandbox')->first();
            $lifecyclePolicy = $this->lifecyclePolicy->forProduct($product);
            $providerSnapshot = $this->providerSnapshot($product, $providerAccount);
            $price = $this->resellerPricing->priceFor($user, $product);
            $expiresAt = $this->lifecyclePolicy->expiresAt(now(), $lifecyclePolicy);

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->newOrderNumber(),
                'status' => 'pending',
                'subtotal_amount' => $price['amount'],
                'total_amount' => $price['amount'],
                'currency' => $price['currency'],
                'meta' => ['price' => $price],
            ]);

            $item = $order->items()->create([
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'product_type' => $product->type,
                'quantity' => 1,
                'unit_amount' => $price['amount'],
                'subtotal_amount' => $price['amount'],
                'currency' => $price['currency'],
                'duration_days' => $product->duration_days,
                'config_snapshot' => $product->config ?? [],
            ]);

            $wallet = $this->walletService->walletFor($user, $product->currency);
            $this->walletService->debit(
                $wallet,
                $price['amount'],
                $price['currency'],
                'order',
                $order->id,
                "order-payment:{$order->id}",
                "Order {$order->order_number}",
                ['product_code' => $product->code, 'price_source' => $price['source']]
            );

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $service = Service::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'product_type' => $product->type,
                'status' => 'pending_provision',
                'config' => $product->config ?? [],
                'meta' => [
                    'duration_days' => $product->duration_days,
                    'lifecycle_policy' => $lifecyclePolicy,
                    'provider' => $providerSnapshot,
                ],
                'expires_at' => $expiresAt,
            ]);

            ProvisioningJob::create([
                'order_id' => $order->id,
                'service_id' => $service->id,
                'user_id' => $user->id,
                'type' => 'provision_service',
                'status' => 'pending',
                'attempts' => 0,
                'idempotency_key' => "service-provision:{$service->id}",
                'payload' => [
                    'action' => 'provision',
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'user_id' => $user->id,
                    'product' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                        'type' => $product->type,
                        'duration_days' => $product->duration_days,
                        'config' => $product->config ?? [],
                        'lifecycle_policy' => $lifecyclePolicy,
                        'provider' => $providerSnapshot,
                        'price' => $price,
                    ],
                ],
                'available_at' => now(),
            ]);

            return $order->refresh()->load('items', 'services');
        });
    }

    private function newOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function providerSnapshot(Product $product, ?ProvisioningProviderAccount $providerAccount): array
    {
        $routes = $product->providerRoutes
            ->filter(fn ($route): bool => $route->enabled && $route->providerAccount?->driver === 'cloudmini_v3')
            ->sortBy([
                ['priority', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($route): array => [
                'provider_account_id' => $route->provider_account_id,
                'account_slug' => $route->providerAccount?->slug,
                'priority' => $route->priority,
                'weight' => $route->weight,
                'billing_group_id' => $route->billing_group_id,
                'node_selector_type' => $route->node_selector_type,
                'node_name' => $route->node_name,
                'options' => $route->options ?? [],
            ])
            ->values()
            ->all();

        if ($routes !== []) {
            return [
                'account_id' => null,
                'account_slug' => null,
                'driver' => 'cloudmini_v3',
                'plan_code' => $product->provider_plan_code,
                'region' => $product->provider_region,
                'provision_path' => null,
                'renew_path' => null,
                'suspend_path' => null,
                'cancel_path' => null,
                'sync_path' => null,
                'options' => $product->provider_options ?? [],
                'routes' => $routes,
            ];
        }

        return [
            'account_id' => $providerAccount?->id,
            'account_slug' => $providerAccount?->slug ?? 'sandbox',
            'driver' => $providerAccount?->driver ?? 'sandbox',
            'plan_code' => $product->provider_plan_code,
            'region' => $product->provider_region,
            'provision_path' => $product->provider_provision_path ?: $providerAccount?->provision_path,
            'renew_path' => $product->provider_renew_path,
            'suspend_path' => $product->provider_suspend_path,
            'cancel_path' => $product->provider_cancel_path,
            'sync_path' => $product->provider_sync_path,
            'options' => $product->provider_options ?? [],
        ];
    }
}
