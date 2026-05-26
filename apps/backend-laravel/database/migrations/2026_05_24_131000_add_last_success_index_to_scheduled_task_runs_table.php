<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_task_runs', function (Blueprint $table): void {
            $table->index(['task', 'status', 'finished_at'], 'scheduled_task_runs_task_status_finished_at_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('scheduled_task_runs')) {
            return;
        }

        Schema::table('scheduled_task_runs', function (Blueprint $table): void {
            $table->dropIndex('scheduled_task_runs_task_status_finished_at_index');
        });
    }
};
