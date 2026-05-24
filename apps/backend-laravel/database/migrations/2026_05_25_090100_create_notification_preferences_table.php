<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('channel', 30)->default('email');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'channel']);
            $table->index(['type', 'channel', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
