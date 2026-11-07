<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->string('refund_status', 30)->nullable()->after('status');
            $table->unsignedBigInteger('refund_amount')->default(0)->after('refund_status');
            $table->string('refund_bank_name', 60)->nullable()->after('refund_amount');
            $table->string('refund_account_number', 60)->nullable()->after('refund_bank_name');
            $table->string('refund_account_name', 100)->nullable()->after('refund_account_number');
            $table->text('refund_notes')->nullable()->after('refund_account_name');
            $table->dateTime('refund_requested_at')->nullable()->after('refund_notes');
            $table->dateTime('refunded_at')->nullable()->after('refund_requested_at');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete()->after('refunded_at');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('refund_status', 30)->nullable()->after('status');
            $table->unsignedBigInteger('refund_amount')->default(0)->after('refund_status');
            $table->string('refund_bank_name', 60)->nullable()->after('refund_amount');
            $table->string('refund_account_number', 60)->nullable()->after('refund_bank_name');
            $table->string('refund_account_name', 100)->nullable()->after('refund_account_number');
            $table->text('refund_notes')->nullable()->after('refund_account_name');
            $table->dateTime('refund_requested_at')->nullable()->after('refund_notes');
            $table->dateTime('refunded_at')->nullable()->after('refund_requested_at');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn([
                'refund_status',
                'refund_amount',
                'refund_bank_name',
                'refund_account_number',
                'refund_account_name',
                'refund_notes',
                'refund_requested_at',
                'refunded_at',
            ]);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn([
                'refund_status',
                'refund_amount',
                'refund_bank_name',
                'refund_account_number',
                'refund_account_name',
                'refund_notes',
                'refund_requested_at',
                'refunded_at',
            ]);
        });
    }
};
