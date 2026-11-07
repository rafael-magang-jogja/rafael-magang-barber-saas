<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('payment_proof_path')->nullable()->after('notes');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_path');
            $table->timestamp('payment_approved_at')->nullable()->after('payment_proof_uploaded_at');
            $table->foreignId('payment_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('payment_approved_at');
            $table->string('payment_rejected_reason')->nullable()->after('payment_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_approved_by');
            $table->dropColumn([
                'payment_proof_path',
                'payment_proof_uploaded_at',
                'payment_approved_at',
                'payment_rejected_reason',
            ]);
        });
    }
};
