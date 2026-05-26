<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('auto_renew_enabled')->default(false)->after('expires_at');
            $table->index(['auto_renew_enabled', 'status', 'expires_at'], 'services_auto_renew_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_auto_renew_due_index');
            $table->dropColumn('auto_renew_enabled');
        });
    }
};
