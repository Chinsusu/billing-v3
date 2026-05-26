<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_alert_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('type', 60)->default('ops_health');
            $table->boolean('enabled')->default(true);
            $table->string('severity', 30)->default('warning');
            $table->unsignedInteger('cooldown_minutes')->default(15);
            $table->text('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'type']);
        });

        Schema::create('ops_alert_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ops_alert_rule_id')->nullable()->constrained('ops_alert_rules')->nullOnDelete();
            $table->string('fingerprint', 180);
            $table->string('severity', 30);
            $table->string('status', 30)->default('open');
            $table->string('title', 180);
            $table->text('message');
            $table->json('context')->default('{}');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delivery_status', 30)->nullable();
            $table->text('delivery_error')->nullable();
            $table->timestamps();

            $table->index(['ops_alert_rule_id', 'fingerprint', 'status']);
            $table->index(['status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_alert_events');
        Schema::dropIfExists('ops_alert_rules');
    }
};
