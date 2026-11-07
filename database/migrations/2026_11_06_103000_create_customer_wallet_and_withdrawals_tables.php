<?php

declare(strict_types=1);

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
        // 1. Tambah wallet_balance ke tabel customer_profiles & users
        if (Schema::hasTable('customer_profiles') && !Schema::hasColumn('customer_profiles', 'wallet_balance')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->unsignedBigInteger('wallet_balance')->default(0)->after('points');
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'wallet_balance')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('wallet_balance')->default(0)->after('phone');
            });
        }

        // 2. Tabel customer_wallet_transactions
        if (!Schema::hasTable('customer_wallet_transactions')) {
            Schema::create('customer_wallet_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('customer_profile_id')->nullable()->constrained('customer_profiles')->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->enum('type', ['credit', 'debit']);
                $table->string('category', 40); // refund_booking, booking_payment, withdrawal, withdrawal_reversal, adjustment
                $table->unsignedBigInteger('amount');
                $table->unsignedBigInteger('balance_before');
                $table->unsignedBigInteger('balance_after');
                $table->string('reference_id', 100)->nullable()->index('cwt_ref_idx');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'cwt_user_created_idx');
                $table->index(['customer_profile_id', 'created_at'], 'cwt_profile_created_idx');
            });
        }

        // 3. Tabel customer_withdrawals
        if (!Schema::hasTable('customer_withdrawals')) {
            Schema::create('customer_withdrawals', function (Blueprint $table): void {
                $table->id();
                $table->string('withdrawal_code', 40)->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('customer_profile_id')->nullable()->constrained('customer_profiles')->nullOnDelete();
                $table->unsignedBigInteger('amount');
                $table->unsignedBigInteger('admin_fee')->default(0);
                $table->unsignedBigInteger('net_amount');
                $table->string('bank_name', 50);
                $table->string('account_number', 50);
                $table->string('account_holder', 100);
                $table->string('status', 30)->default('pending')->index(); // pending, processing, completed, rejected
                $table->string('proof_path')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'cw_user_status_idx');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_withdrawals');
        Schema::dropIfExists('customer_wallet_transactions');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'wallet_balance')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('wallet_balance');
            });
        }

        if (Schema::hasTable('customer_profiles') && Schema::hasColumn('customer_profiles', 'wallet_balance')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->dropColumn('wallet_balance');
            });
        }
    }
};
