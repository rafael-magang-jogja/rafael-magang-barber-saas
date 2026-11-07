<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 60)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 30)->default('pending'); // pending, processing, completed, rejected
            $table->string('bank_name', 50);
            $table->string('account_number', 50);
            $table->string('account_holder', 100);
            $table->string('transfer_proof_path')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_payout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_payout_id')->constrained('tenant_payouts')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payout_items');
        Schema::dropIfExists('tenant_payouts');
    }
};
