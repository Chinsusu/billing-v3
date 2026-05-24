<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_execution_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('provisioning_job_id')->nullable()->constrained('provisioning_jobs')->nullOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignUuid('provider_account_id')->nullable()->constrained('provisioning_provider_accounts')->nullOnDelete();
            $table->string('action', 80);
            $table->string('driver', 40);
            $table->string('endpoint')->nullable();
            $table->string('status', 30);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->default('{}');
            $table->json('response_payload')->default('{}');
            $table->timestamps();

            $table->index(['provisioning_job_id', 'created_at']);
            $table->index(['provider_account_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_execution_logs');
    }
};
