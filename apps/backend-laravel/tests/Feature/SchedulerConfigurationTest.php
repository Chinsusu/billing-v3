<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_schedule_registers_maintenance_tasks(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduled-tasks:run bank_sync_payments')
            ->expectsOutputToContain('scheduled-tasks:run provider_actions_work')
            ->expectsOutputToContain('scheduled-tasks:run services_expire')
            ->expectsOutputToContain('scheduled-tasks:run provider_actions_recover_stuck')
            ->assertExitCode(0);
    }

    public function test_compose_defines_scheduler_service(): void
    {
        $compose = $this->readComposeFile();

        $scheduler = $this->composeServiceBlock($compose, 'scheduler');

        $this->assertStringContainsString('container_name: billing_v3_scheduler', $scheduler);
        $this->assertStringContainsString('php artisan schedule:work', $scheduler);
        $this->assertMatchesRegularExpression('/depends_on:\s+backend:\s+condition: service_healthy/s', $scheduler);
        $this->assertMatchesRegularExpression('/depends_on:.*postgres:\s+condition: service_healthy/s', $scheduler);
    }

    private function readComposeFile(): string
    {
        $paths = [
            base_path('../../infra/docker-compose.dev.yml'),
            base_path('../../../infra/docker-compose.dev.yml'),
        ];
        $urls = [
            'https://raw.githubusercontent.com/Chinsusu/billing-v3/feature/sprint-14-scheduler-ops-health/infra/docker-compose.dev.yml',
            'https://raw.githubusercontent.com/Chinsusu/billing-v3/develop/infra/docker-compose.dev.yml',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        foreach ($urls as $url) {
            $compose = @file_get_contents($url);

            if (is_string($compose) && $compose !== '') {
                return $compose;
            }
        }

        $this->fail('Expected infra/docker-compose.dev.yml to be readable for scheduler service assertions.');
    }

    private function composeServiceBlock(string $compose, string $service): string
    {
        $matched = preg_match(
            sprintf('/^  %s:\R(?P<body>(?: {4}.*\R?)*)/m', preg_quote($service, '/')),
            $compose,
            $matches
        );

        $this->assertSame(1, $matched, "Expected Compose service [{$service}] to be defined.");

        return $matches[0];
    }
}
