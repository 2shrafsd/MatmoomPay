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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->string('name', 80);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('interval', 10);
            $table->unsignedTinyInteger('interval_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['merchant_id', 'livemode']);
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('payment_link_id')->constrained()->nullOnDelete();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checkout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end')->index();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'livemode', 'status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('livemode');
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_due');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_payment_attempt')->nullable()->index();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plans');
    }
};
