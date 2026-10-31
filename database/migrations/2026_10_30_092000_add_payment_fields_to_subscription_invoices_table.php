<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->foreignId('target_plan_id')->nullable()->after('subscription_id')->constrained('plans')->nullOnDelete();
            $table->string('type', 30)->default('upgrade')->after('target_plan_id'); // 'upgrade', 'downgrade', 'renewal'
            $table->string('payment_method', 30)->nullable()->after('gateway_ref'); // 'transfer', 'cash'
            $table->string('bank_name', 50)->nullable()->after('payment_method'); // 'BCA', 'Mandiri', 'BRI', 'BNI', etc.
            $table->string('proof_path')->nullable()->after('bank_name');
            $table->dateTime('proof_uploaded_at')->nullable()->after('proof_path');
            $table->text('notes')->nullable()->after('proof_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->dropForeign(['target_plan_id']);
            $table->dropColumn([
                'target_plan_id',
                'type',
                'payment_method',
                'bank_name',
                'proof_path',
                'proof_uploaded_at',
                'notes',
            ]);
        });
    }
};
