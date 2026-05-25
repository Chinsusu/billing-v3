<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('scheduled-tasks:run bank_sync_payments')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('bank_sync_payments');

Schedule::command('scheduled-tasks:run payment_intents_expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('payment_intents_expire');

Schedule::command('scheduled-tasks:run provider_actions_work')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('provider_actions_work');

Schedule::command('scheduled-tasks:run services_expire')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('services_expire');

Schedule::command('scheduled-tasks:run services_auto_renew')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('services_auto_renew');

Schedule::command('scheduled-tasks:run service_cancellations_process_scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('service_cancellations_process_scheduled');

Schedule::command('scheduled-tasks:run provider_actions_recover_stuck')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('provider_actions_recover_stuck');

Schedule::command('scheduled-tasks:run ops_alerts_evaluate')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('ops_alerts_evaluate');

Schedule::command('scheduled-tasks:run notifications_send')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('notifications_send');
