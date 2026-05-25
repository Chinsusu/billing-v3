<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('auto_renew_allowed')->default(true)->after('duration_days');
            $table->unsignedInteger('auto_renew_window_hours')->default(24)->after('auto_renew_allowed');
            $table->unsignedInteger('auto_renew_retry_delay_minutes')->default(60)->after('auto_renew_window_hours');
            $table->unsignedInteger('auto_renew_max_attempts')->default(3)->after('auto_renew_retry_delay_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_renew_allowed',
                'auto_renew_window_hours',
                'auto_renew_retry_delay_minutes',
                'auto_renew_max_attempts',
            ]);
        });
    }
};
