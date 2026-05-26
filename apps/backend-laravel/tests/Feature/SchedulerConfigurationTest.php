<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    public function test_laravel_schedule_registers_maintenance_tasks(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduled-tasks:run bank_sync_payments')
            ->expectsOutputToContain('scheduled-tasks:run provider_actions_work')
            ->expectsOutputToContain('scheduled-tasks:run services_expire')
            ->expectsOutputToContain('scheduled-tasks:run services_auto_renew')
            ->expectsOutputToContain('scheduled-tasks:run service_cancellations_process_scheduled')
            ->expectsOutputToContain('scheduled-tasks:run provider_actions_recover_stuck')
            ->expectsOutputToContain('scheduled-tasks:run notifications_send')
            ->assertExitCode(0);
    }

    public function test_compose_defines_scheduler_service(): void
    {
        $compose = $this->readComposeFile();

        $scheduler = $this->composeServiceBlock($compose, 'scheduler');

        $this->assertStringContainsString('container_name: billing_v3_scheduler', $scheduler);
        $this->assertStringContainsString('php artisan schedule:work', $scheduler);
        $this->assertMatchesRegularExpression('/depends_on:.*backend:\s+condition: service_healthy/s', $scheduler);
        $this->assertMatchesRegularExpression('/depends_on:.*postgres:\s+condition: service_healthy/s', $scheduler);
    }

    public function test_compose_defines_https_proxy_service(): void
    {
        $compose = $this->readComposeFile();

        $proxy = $this->composeServiceBlock($compose, 'proxy');

        $this->assertStringContainsString('container_name: billing_v3_proxy', $proxy);
        $this->assertStringContainsString('"443:443"', $proxy);
        $this->assertStringContainsString('./caddy/Caddyfile:/etc/caddy/Caddyfile:ro', $proxy);
        $this->assertMatchesRegularExpression('/depends_on:.*backend:\s+condition: service_healthy/s', $proxy);
    }

    private function readComposeFile(): string
    {
        $paths = [
            base_path('../../infra/docker-compose.dev.yml'),
            '/infra/docker-compose.dev.yml',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        $this->fail('Expected infra/docker-compose.dev.yml to be readable from the backend test runtime.');
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
