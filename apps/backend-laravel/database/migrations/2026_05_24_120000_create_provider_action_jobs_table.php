<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_action_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('provider_account_id')->nullable()->constrained('provisioning_provider_accounts')->nullOnDelete();
            $table->string('action', 40);
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->string('idempotency_key', 180)->unique();
            $table->json('payload')->default('{}');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['service_id', 'status']);
            $table->index(['provider_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_action_jobs');
    }
};
