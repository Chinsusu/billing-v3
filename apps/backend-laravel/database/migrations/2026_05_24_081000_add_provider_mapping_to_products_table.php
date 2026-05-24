<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignUuid('provider_account_id')->nullable()->constrained('provisioning_provider_accounts')->nullOnDelete();
            $table->string('provider_plan_code', 120)->nullable();
            $table->string('provider_region', 80)->nullable();
            $table->string('provider_provision_path')->nullable();
            $table->json('provider_options')->default('{}');

            $table->index(['provider_account_id', 'provider_plan_code']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['provider_account_id', 'provider_plan_code']);
            $table->dropForeign(['provider_account_id']);
            $table->dropColumn(['provider_account_id', 'provider_plan_code', 'provider_region', 'provider_provision_path', 'provider_options']);
        });
    }
};
