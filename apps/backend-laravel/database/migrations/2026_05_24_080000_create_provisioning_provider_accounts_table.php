<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_provider_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 160);
            $table->string('driver', 40)->default('sandbox');
            $table->string('base_url')->nullable();
            $table->string('provision_path')->nullable();
            $table->string('auth_type', 20)->default('none');
            $table->string('auth_header_name', 80)->nullable();
            $table->text('api_key')->nullable();
            $table->string('api_key_last_four', 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->json('request_template')->default('{}');
            $table->string('response_external_id_path', 120)->default('external_id');
            $table->string('response_status_path', 120)->default('status');
            $table->string('response_config_path', 120)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->text('last_test_error')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_provider_accounts');
    }
};
