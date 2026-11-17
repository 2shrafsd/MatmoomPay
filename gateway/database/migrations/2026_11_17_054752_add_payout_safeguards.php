<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payout accounts must be re-verified after every change, and each settlement keeps the account it
     * was created for, so a later edit cannot redirect money that is already on its way.
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->timestamp('settlement_account_verified_at')->nullable()->after('settlement_iban');
            $table->foreignId('settlement_account_verified_by')->nullable()->after('settlement_account_verified_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->string('bank_name', 80)->nullable()->after('transactions_count');
            $table->string('account_name', 160)->nullable()->after('bank_name');
            $table->text('iban')->nullable()->after('account_name');
            $table->string('failure_reason', 255)->nullable()->after('bank_reference');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_name', 'iban', 'failure_reason']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_account_verified_by');
            $table->dropColumn('settlement_account_verified_at');
        });
    }
};
