<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30)->default('email');
            $table->string('type', 80);
            $table->string('recipient_email', 255);
            $table->string('subject', 255);
            $table->text('body_text');
            $table->string('source_type', 80);
            $table->string('source_id', 120)->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload')->default('{}');
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['type', 'created_at']);
            $table->index(['recipient_email', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
