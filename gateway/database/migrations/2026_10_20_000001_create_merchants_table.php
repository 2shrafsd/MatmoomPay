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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('password');
            $table->string('locale', 5)->nullable()->after('is_platform_admin');
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->string('name', 120);
            $table->string('legal_name', 160);
            $table->string('activity', 80);
            $table->string('commercial_registration', 40)->nullable();
            $table->string('email');
            $table->string('phone', 20);
            $table->string('website')->nullable();
            $table->char('country', 2)->default('OM');
            $table->string('settlement_bank_name', 80)->nullable();
            $table->string('settlement_account_name', 160)->nullable();
            $table->text('settlement_iban')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('status_reason', 500)->nullable();
            $table->unsignedInteger('fee_rate_bps');
            $table->unsignedInteger('fee_fixed')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('merchant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['merchant_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_merchant_id')->nullable()->after('locale')->constrained('merchants')->nullOnDelete();
        });

        Schema::create('merchant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_merchant_id');
            $table->dropColumn(['is_platform_admin', 'locale']);
        });

        Schema::dropIfExists('merchant_user');
        Schema::dropIfExists('merchants');
    }
};
