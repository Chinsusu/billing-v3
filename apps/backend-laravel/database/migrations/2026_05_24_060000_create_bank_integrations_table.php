<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 40)->unique();
            $table->string('name', 120);
            $table->string('base_url', 255);
            $table->string('transactions_path', 255)->default('/transactions');
            $table->string('account_number', 120)->nullable();
            $table->boolean('enabled')->default(false);
            $table->text('api_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('api_key_last_four', 4)->nullable();
            $table->string('webhook_secret_last_four', 4)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 30)->nullable();
            $table->text('last_sync_error')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_integrations');
    }
};
