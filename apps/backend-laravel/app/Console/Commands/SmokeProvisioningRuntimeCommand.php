<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\User;
use App\Services\Finance\WalletService;
use App\Services\Orders\OrderCheckoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SmokeProvisioningRuntimeCommand extends Command
{
    protected $signature = 'runtime:smoke-provisioning {--timeout=30 : Seconds to wait for the worker daemon}';

    protected $description = 'Create a seeded customer order and wait for the worker to provision it.';

    public function handle(OrderCheckoutService $checkoutService, WalletService $walletService): int
    {
        $timeout = max(0, (int) $this->option('timeout'));
        $customer = User::where('email', 'customer@billing.test')->first();
        $product = Product::active()->where('code', 'proxy-vn-30d')->first();

        if (! $customer || ! $product) {
            $this->error('Seeded customer and proxy-vn-30d product are required before running the runtime smoke.');

            return self::FAILURE;
        }

        $wallet = $walletService->walletFor($customer, $product->currency);
        $wallet->refresh();
        if ($wallet->balance_amount < $product->price_amount) {
            $walletService->credit(
                $wallet,
                $product->price_amount - $wallet->balance_amount,
                $product->currency,
                'runtime_smoke',
                null,
                'runtime-smoke-credit:'.Str::uuid(),
                'Runtime smoke wallet credit',
                ['product_code' => $product->code]
            );
        }

        $order = $checkoutService->checkout($customer, $product);
        $service = $order->services()->firstOrFail();
        $job = ProvisioningJob::where('service_id', $service->id)->latest()->firstOrFail();

        $this->info("Created smoke order {$order->order_number} service={$service->id} job={$job->id}");

        $deadline = time() + $timeout;
        do {
            $service->refresh();
            $job->refresh();

            if ($service->status === 'active' && $job->status === 'processed') {
                $this->info("Provisioned smoke service {$service->id} external_id={$service->external_id}");

                return self::SUCCESS;
            }

            if (time() >= $deadline) {
                break;
            }

            sleep(1);
        } while (true);

        $this->error("Timed out waiting for service provisioning service_status={$service->status} job_status={$job->status} last_error=".($job->last_error ?? '-'));

        return self::FAILURE;
    }
}
