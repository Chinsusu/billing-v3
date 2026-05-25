<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_ticket_and_admin_can_add_internal_note(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = $this->customer('s36-customer@example.test');
        $admin = $this->supportAdmin('s36-support@example.test');

        $this->actingAs($customer)
            ->post('/support/tickets', [
                'subject' => 'Cannot renew service',
                'priority' => 'high',
                'body' => 'Renew button fails.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::where('subject', 'Cannot renew service')->firstOrFail();
        $this->assertSame($customer->id, $ticket->user_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->notes()->where('visibility', 'customer')->count());

        $this->actingAs($admin)
            ->post("/admin/support-tickets/{$ticket->id}/notes", [
                'visibility' => 'internal',
                'body' => 'Check provider action history.',
            ])
            ->assertRedirect("/admin/support-tickets/{$ticket->id}");

        $this->actingAs($customer)
            ->get("/support/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee('Renew button fails.')
            ->assertDontSee('Check provider action history.');

        $this->actingAs($admin)
            ->put("/admin/support-tickets/{$ticket->id}", [
                'status' => 'pending',
                'priority' => 'urgent',
                'assigned_to_id' => $admin->id,
            ])
            ->assertRedirect("/admin/support-tickets/{$ticket->id}");

        $ticket = SupportTicket::findOrFail($ticket->id);
        $this->assertSame('pending', $ticket->status);
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame($admin->id, $ticket->assigned_to_id);
        $this->assertSame(1, AdminAuditLog::where('action', 'support_ticket_created')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'support_ticket_updated')->count());
    }

    public function test_support_permissions_are_required_for_admin_ticket_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = $this->customer('s36-no-access@example.test');

        $this->actingAs($customer)->get('/admin/support-tickets')->assertForbidden();
        $this->assertTrue($this->supportAdmin('s36-support-access@example.test')->hasPermissionTo('support_tickets.manage'));
    }

    private function customer(string $email): User
    {
        return $this->userWithRole('customer', $email);
    }

    private function supportAdmin(string $email): User
    {
        return $this->userWithRole('support', $email);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
