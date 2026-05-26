<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 80);
            $table->string('channel', 30)->default('email');
            $table->string('name', 120);
            $table->string('subject_template', 255);
            $table->text('body_template');
            $table->json('variables')->default('[]');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['type', 'channel']);
            $table->index(['enabled', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
