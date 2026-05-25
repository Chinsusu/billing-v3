<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_auto_renewal_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('renewed_expires_at')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('last_error')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->timestamps();

            $table->unique(['service_id', 'expires_at'], 'service_auto_renewal_attempts_target_unique');
            $table->index(['status', 'next_attempt_at']);
            $table->index(['service_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_auto_renewal_attempts');
    }
};
