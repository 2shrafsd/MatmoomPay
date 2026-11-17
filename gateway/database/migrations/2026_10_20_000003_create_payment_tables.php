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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->string('client_customer_id', 191);
            $table->timestamps();

            $table->unique(['merchant_id', 'livemode', 'client_customer_id']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('processor', 30);
            $table->text('processor_token');
            $table->char('fingerprint', 64);
            $table->char('bin', 6);
            $table->char('last_four', 4);
            $table->string('brand', 20);
            $table->string('card_type', 10);
            $table->unsignedTinyInteger('expiry_month');
            $table->unsignedSmallInteger('expiry_year');
            $table->string('nickname', 40)->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'fingerprint']);
        });

        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->string('title', 80);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedBigInteger('min_amount')->nullable();
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('collect_phone')->default(false);
            $table->boolean('collect_email')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'created_at']);
        });

        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 48)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_reference_id', 191);
            $table->string('mode', 20)->default('payment');
            $table->json('products');
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3);
            $table->text('success_url');
            $table->text('cancel_url');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('invoice', 24)->unique();
            $table->boolean('save_card_on_success')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('expire_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'client_reference_id']);
            $table->index(['merchant_id', 'livemode', 'created_at']);
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_reference_id', 191);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->text('return_url');
            $table->json('metadata')->nullable();
            $table->string('status', 30);
            $table->json('next_action')->nullable();
            $table->timestamp('expire_at');
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'client_reference_id']);
            $table->index(['merchant_id', 'livemode', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('checkout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->string('failure_reason', 255)->nullable();
            $table->string('masked_card', 25);
            $table->char('card_bin', 6);
            $table->char('card_last_four', 4);
            $table->string('card_brand', 20);
            $table->string('card_type', 10);
            $table->char('card_fingerprint', 64)->nullable();
            $table->unsignedTinyInteger('card_expiry_month')->nullable();
            $table->unsignedSmallInteger('card_expiry_year')->nullable();
            $table->string('processor', 30);
            $table->string('processor_reference', 80)->nullable()->index();
            $table->text('card_token')->nullable();
            $table->string('challenge_type', 10)->nullable();
            $table->unsignedTinyInteger('challenge_attempts')->default(0);
            $table->boolean('save_card')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'status', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reason', 255);
            $table->json('metadata')->nullable();
            $table->string('status', 20);
            $table->string('processor_reference', 80)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('checkout_sessions');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customers');
    }
};
