<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('disabled_at')->nullable();
            $table->text('disabled_reason')->nullable();
            $table->timestamp('force_password_reset_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_password_reset_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('last_login_user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'disabled_at',
                'disabled_reason',
                'force_password_reset_at',
                'invited_at',
                'last_password_reset_at',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent',
            ]);
        });
    }
};
