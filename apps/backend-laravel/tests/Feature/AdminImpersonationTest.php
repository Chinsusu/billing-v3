<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_impersonate_customer_and_stop(): void
    {
        $admin = $this->superAdmin('s35-admin@example.test');
        $customer = $this->customer('s35-customer@example.test');

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/impersonate")
            ->assertRedirect('/dashboard')
            ->assertSessionHas('impersonator_id', $admin->id);

        $this->assertAuthenticatedAs($customer);
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Impersonating s35-customer@example.test');

        $this->post('/impersonation/stop')
            ->assertRedirect('/admin/customers/'.$customer->id);

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(1, AdminAuditLog::where('action', 'customer_impersonation_started')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'customer_impersonation_stopped')->count());
    }

    public function test_admin_cannot_impersonate_admin_user(): void
    {
        $admin = $this->superAdmin('s35-main-admin@example.test');
        $otherAdmin = $this->superAdmin('s35-other-admin@example.test');

        $this->actingAs($admin)
            ->from("/admin/customers/{$otherAdmin->id}")
            ->post("/admin/customers/{$otherAdmin->id}/impersonate")
            ->assertRedirect("/admin/customers/{$otherAdmin->id}")
            ->assertSessionHasErrors('impersonation');

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(0, AdminAuditLog::where('action', 'customer_impersonation_started')->count());
    }

    private function superAdmin(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return $this->userWithRole('super_admin', $email);
    }

    private function customer(string $email): User
    {
        return $this->userWithRole('customer', $email);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
