<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_is_assigned_customer_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->post('/register', [
            'name' => 'Customer One',
            'email' => 'customer@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'customer@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('customer');

        $this->post('/login', [
            'email' => 'login@example.test',
            'password' => 'Password123!',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
