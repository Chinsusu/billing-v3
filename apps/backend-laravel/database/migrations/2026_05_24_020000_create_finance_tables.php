<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('balance_amount')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3)->default('VND');
            $table->text('description')->nullable();
            $table->json('lines')->default('[]');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('target_type', 30);
            $table->string('reference', 80)->unique();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('VND');
            $table->string('status', 20)->default('pending');
            $table->text('qr_payload');
            $table->json('meta')->default('{}');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->default('bank_sandbox');
            $table->string('provider_transaction_id', 120)->nullable()->unique();
            $table->string('reference', 120)->nullable()->index();
            $table->unsignedBigInteger('amount')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status', 30);
            $table->string('signature_status', 30)->default('valid');
            $table->json('payload')->default('{}');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('balance_after');
            $table->string('source_type', 60);
            $table->uuid('source_id')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->string('description')->nullable();
            $table->json('meta')->default('{}');
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('wallets');
    }
};
