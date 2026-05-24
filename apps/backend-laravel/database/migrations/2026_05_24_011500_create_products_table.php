<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('type', 20);
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('price_amount');
            $table->char('currency', 3)->default('VND');
            $table->unsignedInteger('duration_days')->default(30);
            $table->text('description')->nullable();
            $table->json('config')->default('{}');
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
