<?php

use App\Console\Commands\EvaluateOpsAlertsCommand;
use App\Console\Commands\ExpireServicesCommand;
use App\Console\Commands\RecoverStuckProviderActionJobsCommand;
use App\Console\Commands\RunScheduledTaskCommand;
use App\Console\Commands\SmokeProvisioningRuntimeCommand;
use App\Console\Commands\SyncPrivateBankPaymentsCommand;
use App\Console\Commands\WorkProviderActionJobsCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ExpireServicesCommand::class,
        EvaluateOpsAlertsCommand::class,
        RecoverStuckProviderActionJobsCommand::class,
        RunScheduledTaskCommand::class,
        SmokeProvisioningRuntimeCommand::class,
        SyncPrivateBankPaymentsCommand::class,
        WorkProviderActionJobsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/bank/sandbox',
            'internal/provisioning/jobs/*/execute',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
