<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Notifications\NotificationTemplateCatalog;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationTemplatePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_template_and_outbox_renders_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        $admin = User::factory()->create(['email' => 'template-admin@example.test']);
        $admin->assignRole('super_admin');
        $customer = User::factory()->create(['email' => 'template-customer@example.test']);

        $template = NotificationTemplate::where('type', 'invoice_paid')->where('channel', 'email')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/notification-templates')
            ->assertOk()
            ->assertSee('invoice_paid')
            ->assertSee('/admin/notification-templates/'.$template->id.'/edit', false);

        $this->actingAs($admin)
            ->put("/admin/notification-templates/{$template->id}", [
                'name' => 'Invoice paid custom',
                'enabled' => '1',
                'subject_template' => 'Invoice {{invoice_number}} paid',
                'body_template' => 'Hello {{user.email}}, paid {{amount}} {{currency}}.',
            ])
            ->assertRedirect('/admin/notification-templates');

        app(NotificationOutbox::class)->enqueue(
            $customer,
            'invoice_paid',
            $customer->email,
            'Fallback subject',
            'Fallback body',
            'invoice',
            'invoice-template-test',
            'invoice-paid:template-test',
            [
                'invoice_number' => 'INV-TEMPLATE-001',
                'amount' => 50000,
                'currency' => 'VND',
            ],
        );

        $event = DB::table('notification_events')->where('idempotency_key', 'invoice-paid:template-test')->first();

        $this->assertSame('Invoice INV-TEMPLATE-001 paid', $event->subject);
        $this->assertSame('Hello template-customer@example.test, paid 50000 VND.', $event->body_text);
    }

    public function test_customer_preferences_disable_and_reenable_customer_notifications_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        $customer = User::factory()->create(['email' => 'preference-customer@example.test']);
        $customer->assignRole('customer');

        $this->actingAs($customer)
            ->get('/notification-preferences')
            ->assertOk()
            ->assertSee('Invoice paid')
            ->assertSee('Wallet credited');

        $this->actingAs($customer)
            ->post('/notification-preferences', [
                'enabled_types' => ['wallet_credited'],
            ])
            ->assertRedirect('/notification-preferences');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $customer->id,
            'type' => 'invoice_paid',
            'channel' => 'email',
            'enabled' => false,
        ]);

        $suppressed = app(NotificationOutbox::class)->enqueue(
            $customer,
            'invoice_paid',
            $customer->email,
            'Invoice paid',
            'Your invoice was paid.',
            'invoice',
            'invoice-disabled-test',
            'invoice-paid:disabled-test',
            ['invoice_number' => 'INV-DISABLED-001'],
        );

        $this->assertNull($suppressed);
        $this->assertDatabaseMissing('notification_events', [
            'idempotency_key' => 'invoice-paid:disabled-test',
        ]);

        app(NotificationOutbox::class)->enqueueOperator(
            'provider_action_failed',
            'Provider action failed',
            'Provider suspend failed.',
            'provider_action_job',
            'provider-job-preference-test',
            'provider-action-failed:preference-test',
        );
        $this->assertDatabaseHas('notification_events', [
            'type' => 'provider_action_failed',
            'idempotency_key' => 'provider-action-failed:preference-test',
            'status' => 'pending',
        ]);

        $this->actingAs($customer)
            ->post('/notification-preferences', [
                'enabled_types' => ['invoice_paid', 'wallet_credited'],
            ])
            ->assertRedirect('/notification-preferences');

        app(NotificationOutbox::class)->enqueue(
            $customer,
            'invoice_paid',
            $customer->email,
            'Invoice paid',
            'Your invoice was paid.',
            'invoice',
            'invoice-enabled-test',
            'invoice-paid:enabled-test',
            ['invoice_number' => 'INV-ENABLED-001'],
        );

        $this->assertDatabaseHas('notification_events', [
            'idempotency_key' => 'invoice-paid:enabled-test',
            'type' => 'invoice_paid',
            'recipient_email' => 'preference-customer@example.test',
            'status' => 'pending',
        ]);
    }

    public function test_template_seeder_is_idempotent_and_routes_are_protected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        $catalog = app(NotificationTemplateCatalog::class);
        $customer = User::factory()->create(['email' => 'protected-customer@example.test']);
        $customer->assignRole('customer');
        $admin = User::factory()->create(['email' => 'protected-admin@example.test']);
        $admin->assignRole('super_admin');
        $expectedCustomerTypes = array_keys($catalog->customerTypes());
        $actualCustomerTypes = NotificationTemplate::whereIn('type', $expectedCustomerTypes)->pluck('type')->all();
        sort($expectedCustomerTypes);
        sort($actualCustomerTypes);

        $this->assertSame(count($catalog->defaults()), NotificationTemplate::count());
        $this->assertSame($expectedCustomerTypes, $actualCustomerTypes);

        $this->get('/notification-preferences')->assertRedirect('/login');

        $this->actingAs($customer)
            ->get('/admin/notification-templates')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Notification Templates')
            ->assertSee('/admin/notification-templates', false);

        $this->assertSame(0, NotificationPreference::count());
    }
}
