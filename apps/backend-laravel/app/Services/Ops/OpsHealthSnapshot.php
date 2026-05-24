<?php

namespace App\Services\Ops;

use App\Models\BankIntegration;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningJob;
use App\Models\ScheduledTaskRun;
use App\Models\Service;
use Carbon\CarbonInterface;

class OpsHealthSnapshot
{
    private const TASKS = [
        'bank_sync_payments' => 3,
        'provider_actions_work' => 3,
        'services_expire' => 15,
        'service_cancellations_process_scheduled' => 15,
        'provider_actions_recover_stuck' => 15,
        'notifications_send' => 3,
    ];

    public function data(): array
    {
        $now = now();

        return [
            'tasks' => $this->taskHealth($now),
            'provisioningQueue' => $this->queueHealth(ProvisioningJob::class),
            'providerActionQueue' => $this->queueHealth(ProviderActionJob::class),
            'overdueActiveServiceCount' => Service::where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->count(),
            'enabledBankIntegrationCount' => BankIntegration::where('enabled', true)->count(),
        ];
    }

    private function taskHealth(CarbonInterface $now): array
    {
        return collect(self::TASKS)->mapWithKeys(function (int $freshMinutes, string $task) use ($now): array {
            $lastRun = ScheduledTaskRun::where('task', $task)->latest('started_at')->first();
            $lastSuccess = ScheduledTaskRun::where('task', $task)
                ->where('status', 'success')
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->first();
            $status = 'ok';
            $message = 'Recent successful run.';

            if (! $lastSuccess || $lastSuccess->finished_at->lt($now->copy()->subMinutes($freshMinutes))) {
                $status = 'warning';
                $message = "No successful run in the last {$freshMinutes} minutes.";
            }

            if (
                $lastRun?->status === 'running'
                && $lastRun->started_at->lt($now->copy()->subMinutes($freshMinutes))
            ) {
                $status = 'warning';
                $message = "Latest run has been running for more than {$freshMinutes} minutes.";
            }

            if ($lastRun?->status === 'failed') {
                $status = 'failed';
                $message = $lastRun->error ?: 'Last run failed.';
            }

            return [$task => [
                'status' => $status,
                'message' => $message,
                'lastRun' => $lastRun,
                'lastSuccess' => $lastSuccess,
                'freshMinutes' => $freshMinutes,
            ]];
        })->all();
    }

    private function queueHealth(string $modelClass): array
    {
        $pending = $modelClass::where('status', 'pending')->count();
        $processing = $modelClass::where('status', 'processing')->count();
        $failed = $modelClass::where('status', 'failed')->count();
        $status = 'ok';

        if ($failed > 0) {
            $status = 'failed';
        } elseif ($pending + $processing > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'pending' => $pending,
            'processing' => $processing,
            'failed' => $failed,
        ];
    }
}
