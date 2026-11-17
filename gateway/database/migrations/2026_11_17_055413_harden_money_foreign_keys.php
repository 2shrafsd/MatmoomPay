<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financial records outlive the things they point to: deleting a merchant or customer must fail, or
     * detach, rather than silently erase payments, refunds, subscriptions and invoices.
     *
     * @var list<string>
     */
    private const MONEY_TABLES = ['checkout_sessions', 'payment_intents', 'payments', 'refunds', 'subscriptions', 'subscription_invoices'];

    public function up(): void
    {
        foreach (self::MONEY_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['merchant_id']);
                $table->foreign('merchant_id')->references('id')->on('merchants')->restrictOnDelete();
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        foreach (self::MONEY_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['merchant_id']);
                $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            });
        }
    }
};
