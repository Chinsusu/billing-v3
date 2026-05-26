<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('provider_renew_path')->nullable()->after('provider_lifecycle_timezone');
            $table->string('provider_suspend_path')->nullable()->after('provider_renew_path');
            $table->string('provider_cancel_path')->nullable()->after('provider_suspend_path');
            $table->string('provider_sync_path')->nullable()->after('provider_cancel_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'provider_renew_path',
                'provider_suspend_path',
                'provider_cancel_path',
                'provider_sync_path',
            ]);
        });
    }
};
