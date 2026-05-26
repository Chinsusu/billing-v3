<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class AdminDashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_prioritizes_revenue_services_and_queue_kpis(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-26 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email' => 's43-admin@example.test']);
        $admin->assignRole('super_admin');
        $customer = User::factory()->create(['email' => 's43-customer@example.test']);
        $customer->assignRole('customer');

        Order::factory()->for($customer)->create([
            'status' => 'paid',
            'total_amount' => 120000,
            'paid_at' => now()->subDays(2),
        ]);
        Order::factory()->for($customer)->create([
            'status' => 'paid',
            'total_amount' => 30000,
            'paid_at' => now(),
        ]);
        Order::factory()->for($customer)->create([
            'status' => 'paid',
            'total_amount' => 777000,
            'paid_at' => now()->subMonth(),
        ]);
        Order::factory()->for($customer)->create([
            'status' => 'pending',
            'total_amount' => 555000,
            'paid_at' => null,
        ]);
        Invoice::factory()->for($customer)->create([
            'status' => 'paid',
            'total_amount' => 49000,
            'paid_at' => now()->subDay(),
        ]);
        Invoice::factory()->for($customer)->create([
            'status' => 'open',
            'total_amount' => 300000,
        ]);
        PaymentIntent::factory()->for($customer)->create([
            'status' => 'paid',
            'amount' => 999000,
            'paid_at' => now(),
        ]);

        $activeServiceA = $this->serviceFor($customer, ['status' => 'active', 'product_name' => 'Active VPS A']);
        $this->serviceFor($customer, ['status' => 'active', 'product_name' => 'Active VPS B']);
        $pendingServiceA = $this->serviceFor($customer, ['status' => 'pending_provision', 'product_name' => 'Pending Proxy A']);
        $this->serviceFor($customer, ['status' => 'pending_provision', 'product_name' => 'Pending Proxy B']);
        $this->serviceFor($customer, ['status' => 'expired', 'product_name' => 'Expired Proxy']);

        $this->provisioningJobFor($pendingServiceA, [
            'status' => 'pending',
            'type' => 'provision_service',
            'created_at' => now()->subMinutes(18),
            'available_at' => now()->subMinutes(18),
        ]);
        $this->provisioningJobFor($activeServiceA, [
            'status' => 'processing',
            'type' => 'sync_provider',
            'updated_at' => now()->subMinutes(8),
        ]);
        $this->provisioningJobFor($activeServiceA, [
            'status' => 'failed',
            'type' => 'provision_service',
            'last_error' => 'Provider timeout.',
        ]);
        $this->provisioningJobFor($activeServiceA, [
            'status' => 'processed',
            'type' => 'provision_service',
        ]);

        PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'S43-UNMATCHED',
            'reference' => 'UNKNOWN',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'unmatched',
            'payload' => [],
            'processed_at' => now(),
        ]);
        PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'S43-REJECTED',
            'reference' => 'REJECTED',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'rejected',
            'payload' => [],
            'processed_at' => now(),
        ]);
        PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'S43-ACCEPTED',
            'reference' => 'ACCEPTED',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'accepted',
            'payload' => [],
            'processed_at' => now(),
        ]);

        SupportTicket::create([
            'user_id' => $customer->id,
            'opened_by_id' => $customer->id,
            'subject' => 'Provisioning is slow',
            'status' => 'open',
            'priority' => 'urgent',
            'last_activity_at' => now(),
        ]);
        SupportTicket::create([
            'user_id' => $customer->id,
            'opened_by_id' => $customer->id,
            'subject' => 'Need billing help',
            'status' => 'pending',
            'priority' => 'normal',
            'last_activity_at' => now(),
        ]);
        SupportTicket::create([
            'user_id' => $customer->id,
            'opened_by_id' => $customer->id,
            'subject' => 'Closed ticket',
            'status' => 'closed',
            'priority' => 'normal',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('admin-kpi-grid admin-kpi-grid--single-row', false)
            ->assertSee('body.app-shell--admin .admin-kpi-grid--single-row', false)
            ->assertSee('grid-template-columns: repeat(5, minmax(0, 1fr));', false)
            ->assertSee('stat-card--has-icon', false)
            ->assertSee('stat-card__icon', false)
            ->assertSee('data-stat-icon="revenue"', false)
            ->assertSee('data-stat-icon="services"', false)
            ->assertSee('data-stat-icon="provisioning"', false)
            ->assertSee('data-stat-icon="queue-risk"', false)
            ->assertSee('data-stat-icon="support"', false)
            ->assertSee('.stat-card--has-icon::before', false)
            ->assertSee('display: none;', false)
            ->assertSee('data-kpi-card="revenue-mtd"', false)
            ->assertSee('data-kpi-card="active-services"', false)
            ->assertSee('data-kpi-card="provisioning"', false)
            ->assertSee('data-kpi-card="queue-risk"', false)
            ->assertSee('data-kpi-card="open-support-tickets"', false)
            ->assertDontSee('data-kpi-card="open-invoice-amount"', false)
            ->assertDontSee('data-kpi-card="payment-exceptions"', false)
            ->assertSee('Revenue MTD')
            ->assertSee('199,000 VND')
            ->assertSee('Today 30,000 VND')
            ->assertSee('Active services')
            ->assertSee('2 currently active')
            ->assertDontSee('2 active / 2 pending')
            ->assertSee('Provisioning')
            ->assertSee('2 waiting services / 1 queued jobs')
            ->assertDontSee('Pending provisioning')
            ->assertDontSee('2 services waiting / 1 queued')
            ->assertSee('Queue risk')
            ->assertSee('1 failed / 1 stuck')
            ->assertSee('300,000 VND')
            ->assertSee('Open support tickets')
            ->assertSee('1 urgent')
            ->assertSee('Payment exceptions')
            ->assertSee('Provisioning queue')
            ->assertSee('data-dashboard-section="provisioning-queue-preview"', false)
            ->assertSee('admin-dashboard-queue-scroll', false)
            ->assertSee('height: 360px;', false)
            ->assertSee('overflow-y: auto;', false)
            ->assertSee('Pending Proxy A')
            ->assertSee('Provider timeout.')
            ->assertDontSee('Service mix')
            ->assertDontSee('By type')
            ->assertDontSee('999,000 VND');
    }

    public function test_admin_dashboard_queue_preview_is_fixed_and_recent_only_without_service_mix(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-26 11:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email' => 's43-preview-admin@example.test']);
        $admin->assignRole('super_admin');
        $customer = User::factory()->create(['email' => 's43-preview-customer@example.test']);
        $customer->assignRole('customer');
        $service = $this->serviceFor($customer, ['status' => 'active', 'product_name' => 'Preview Service']);

        foreach (range(1, 9) as $index) {
            $this->provisioningJobFor($service, [
                'status' => 'processing',
                'type' => sprintf('recent_job_%02d', $index),
                'created_at' => now()->subMinutes(20 - $index),
                'updated_at' => now()->subMinutes(20 - $index),
            ]);
        }

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-dashboard-section="provisioning-queue-preview"', false)
            ->assertSee('admin-dashboard-grid--queue-only', false)
            ->assertSee('admin-dashboard-panel--queue-preview', false)
            ->assertSee('admin-dashboard-queue-scroll', false)
            ->assertSee('height: 360px;', false)
            ->assertSee('overflow-y: auto;', false)
            ->assertSee('recent_job_09')
            ->assertSee('recent_job_02')
            ->assertDontSee('recent_job_01')
            ->assertDontSee('Service mix')
            ->assertDontSee('By type');
    }

    private function serviceFor(User $user, array $attributes = []): Service
    {
        $order = Order::factory()->for($user)->create([
            'status' => 'paid',
            'subtotal_amount' => 0,
            'total_amount' => 0,
            'paid_at' => now(),
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'unit_amount' => 0,
            'subtotal_amount' => 0,
        ]);

        return Service::factory()->create(array_merge([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
        ], $attributes));
    }

    private function provisioningJobFor(Service $service, array $attributes = []): ProvisioningJob
    {
        $timestamps = Arr::only($attributes, ['created_at', 'updated_at']);
        $job = ProvisioningJob::create(array_merge([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'type' => 'provision_service',
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key' => 's43-'.$service->id.'-'.uniqid(),
            'payload' => [],
            'available_at' => now(),
        ], Arr::except($attributes, ['created_at', 'updated_at'])));

        if ($timestamps !== []) {
            $job->timestamps = false;
            $job->forceFill($timestamps)->save();
            $job->timestamps = true;
        }

        return $job;
    }
}
