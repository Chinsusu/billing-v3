<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use Illuminate\Console\Command;

class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'ops:purge-audit-logs {--days= : Retention window in days}';

    protected $description = 'Purge admin audit logs older than the configured retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('ops.audit_log_retention_days', 365));

        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = AdminAuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Purged {$deleted} audit log rows older than {$days} days.");

        return self::SUCCESS;
    }
}
