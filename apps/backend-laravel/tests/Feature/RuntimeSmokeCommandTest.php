<?php

namespace Tests\Feature;

use App\Models\ProvisioningJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeSmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_command_creates_a_pending_provisioning_job_when_worker_is_not_running(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('runtime:smoke-provisioning', ['--timeout' => 0])
            ->expectsOutputToContain('Created smoke order')
            ->expectsOutputToContain('Timed out waiting for service provisioning')
            ->assertExitCode(1);

        $this->assertDatabaseHas('provisioning_jobs', [
            'type' => 'provision_service',
            'status' => 'pending',
        ]);
        $this->assertSame(1, ProvisioningJob::count());
    }
}
