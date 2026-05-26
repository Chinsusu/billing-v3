<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_callback_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_account_id')->constrained('provisioning_provider_accounts')->cascadeOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('provider_action_job_id')->nullable()->constrained('provider_action_jobs')->nullOnDelete();
            $table->string('provider_event_id', 160)->nullable();
            $table->string('external_id', 160)->nullable();
            $table->string('action', 40)->nullable();
            $table->string('provider_status', 60)->nullable();
            $table->string('signature_status', 30)->default('valid');
            $table->string('processing_status', 30);
            $table->json('payload')->default('{}');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['provider_account_id', 'provider_event_id']);
            $table->index(['provider_account_id', 'processing_status']);
            $table->index(['service_id', 'created_at']);
            $table->index(['external_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_callback_events');
    }
};
