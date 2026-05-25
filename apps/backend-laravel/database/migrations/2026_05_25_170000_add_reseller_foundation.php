<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('reseller_price_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('reseller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_amount');
            $table->timestamps();

            $table->unique(['reseller_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_price_overrides');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reseller_id');
        });
    }
};
