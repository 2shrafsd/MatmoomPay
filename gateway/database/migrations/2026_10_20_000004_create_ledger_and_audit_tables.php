<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('gross_amount');
            $table->bigInteger('fee_amount');
            $table->bigInteger('refund_amount');
            $table->bigInteger('net_amount');
            $table->unsignedInteger('transactions_count');
            $table->string('status', 20)->default('pending');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('bank_reference', 80)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });

        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->boolean('livemode');
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'settlement_id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->string('key', 255);
            $table->char('request_fingerprint', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['merchant_id', 'livemode', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('balance_transactions');
        Schema::dropIfExists('settlements');
    }
};
