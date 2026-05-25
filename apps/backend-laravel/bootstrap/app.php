<?php

use App\Console\Commands\AutoRenewServicesCommand;
use App\Console\Commands\EvaluateOpsAlertsCommand;
use App\Console\Commands\ExpirePaymentIntentsCommand;
use App\Console\Commands\ExpireServicesCommand;
use App\Console\Commands\ProcessScheduledServiceCancellationsCommand;
use App\Console\Commands\RecoverStuckProviderActionJobsCommand;
use App\Console\Commands\RunScheduledTaskCommand;
use App\Console\Commands\SendNotificationsCommand;
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
        AutoRenewServicesCommand::class,
        ExpirePaymentIntentsCommand::class,
        ExpireServicesCommand::class,
        EvaluateOpsAlertsCommand::class,
        ProcessScheduledServiceCancellationsCommand::class,
        RecoverStuckProviderActionJobsCommand::class,
        RunScheduledTaskCommand::class,
        SendNotificationsCommand::class,
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
            'webhooks/providers/*',
            'internal/provisioning/jobs/*/execute',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
