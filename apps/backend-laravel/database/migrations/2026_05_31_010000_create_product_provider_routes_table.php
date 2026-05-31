<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_provider_routes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('provider_account_id')->constrained('provisioning_provider_accounts')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('weight')->default(100);
            $table->string('billing_group_id', 160);
            $table->string('node_selector_type', 30)->default('auto');
            $table->string('node_name', 160)->nullable();
            $table->json('options')->default('{}');
            $table->timestamps();

            $table->index(['product_id', 'enabled', 'priority']);
            $table->index(['provider_account_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_provider_routes');
    }
};
