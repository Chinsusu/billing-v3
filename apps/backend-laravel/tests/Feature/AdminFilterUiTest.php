<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFilterUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_index_filters_offer_searchable_options_without_helper_copy(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('filter-customer@example.test');
        $product = Product::factory()->create([
            'name' => 'Filter Product',
            'code' => 'filter-product',
            'status' => 'active',
        ]);
        $order = Order::factory()->for($customer)->create(['status' => 'paid']);
        $service = Service::factory()->for($customer)->for($order)->for($product)->create([
            'status' => 'active',
            'auto_renew_enabled' => true,
            'expires_at' => now()->addDays(3),
        ]);

        PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'BANK-FILTER-001',
            'reference' => 'PAY-FILTER-001',
            'amount' => 99000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => ['reference' => 'PAY-FILTER-001'],
            'processed_at' => now(),
        ]);

        NotificationEvent::create([
            'user_id' => $customer->id,
            'channel' => 'email',
            'type' => 'service_expiry_warning',
            'recipient_email' => 'notice-filter@example.test',
            'subject' => 'Service expiry',
            'body_text' => 'Your service is expiring.',
            'source_type' => 'service',
            'source_id' => (string) $service->id,
            'idempotency_key' => 'notice-filter-001',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'payload' => [],
        ]);

        ServiceAutoRenewalAttempt::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'expires_at' => $service->expires_at,
            'status' => 'failed',
            'attempts' => 1,
            'amount' => 99000,
            'currency' => 'VND',
            'idempotency_key' => 'renewal-filter-001',
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders?customer=filter-customer@example.test')
            ->assertOk()
            ->assertSee('order-filter-panel', false)
            ->assertSee('list="order-customer-options"', false)
            ->assertSee('<option value="filter-customer@example.test">', false)
            ->assertDontSee('Review customer orders, payment status, and provisioning outcomes.');

        $this->actingAs($admin)
            ->get('/admin/payment-events?reference=PAY-FILTER')
            ->assertOk()
            ->assertSee('payment-event-filter-panel', false)
            ->assertSee('list="payment-event-reference-options"', false)
            ->assertSee('<option value="PAY-FILTER-001">', false);

        $this->actingAs($admin)
            ->get('/admin/notification-events?recipient=notice-filter@example.test')
            ->assertOk()
            ->assertSee('notification-event-filter-panel', false)
            ->assertSee('list="notification-event-status-options"', false)
            ->assertSee('<option value="failed">', false)
            ->assertSee('list="notification-event-type-options"', false)
            ->assertSee('<option value="service_expiry_warning">', false)
            ->assertSee('list="notification-event-recipient-options"', false)
            ->assertSee('<option value="notice-filter@example.test">', false);

        $this->actingAs($admin)
            ->get('/admin/renewals?customer=filter-customer@example.test')
            ->assertOk()
            ->assertSee('renewal-filter-panel', false)
            ->assertSee('list="renewal-customer-options"', false)
            ->assertSee('<option value="filter-customer@example.test">', false)
            ->assertDontSee('<h2>Filters</h2>', false);
    }

    public function test_user_and_customer_indexes_offer_search_suggestions_without_helper_copy(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('customer-search-filter@example.test');
        $customer->forceFill(['name' => 'Customer Search Filter'])->save();

        $this->actingAs($admin)
            ->get('/admin/users?search=customer-search-filter')
            ->assertOk()
            ->assertSee('admin-user-filter-panel', false)
            ->assertSee('list="admin-user-search-options"', false)
            ->assertSee('<option value="customer-search-filter@example.test" label="Customer Search Filter">', false);

        $this->actingAs($admin)
            ->get('/admin/customers?search=customer-search-filter')
            ->assertOk()
            ->assertSee('customer-filter-panel', false)
            ->assertSee('list="admin-customer-search-options"', false)
            ->assertSee('<option value="customer-search-filter@example.test" label="Customer Search Filter">', false)
            ->assertDontSee('Search customer accounts, inspect billing state, and open account workbenches.');
    }

    public function test_admin_form_pages_do_not_render_section_helper_copy(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('form-copy-customer@example.test');
        Product::factory()->create(['status' => 'active']);
        $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-FORM-COPY']);

        $this->actingAs($admin)->put("/admin/invoices/{$invoice->id}", [
            'status' => 'paid',
            'provider_transaction_id' => 'MANUAL-FORM-COPY',
            'payment_reference' => 'INV-FORM-COPY',
        ]);

        $event = PaymentEvent::where('provider_transaction_id', 'MANUAL-FORM-COPY')->firstOrFail();

        foreach ([
            '/admin/products/create' => 'Customer-facing catalog details and admin status.',
            '/admin/orders/create' => 'Choose the customer wallet that will be charged.',
            '/admin/invoices/create' => 'Select the account that will own this invoice.',
            "/admin/invoices/{$invoice->id}/edit" => 'Payment timing and manual transaction details.',
            "/admin/payment-events/{$event->id}/edit" => 'Only manual admin transactions can be edited.',
        ] as $path => $copy) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk()
                ->assertDontSee('product-form-section-copy', false)
                ->assertDontSee($copy);
        }
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'admin-filter-ui@example.test']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(string $email): User
    {
        $customer = User::factory()->create(['email' => $email]);
        $customer->assignRole('customer');

        return $customer;
    }
}
