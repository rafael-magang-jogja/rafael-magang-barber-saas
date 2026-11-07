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
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dateTime('period_start')->nullable()->after('amount');
            $table->dateTime('period_end')->nullable()->after('period_start');
        });

        // Backfill data untuk invoice yang sudah ada
        DB::table('subscription_invoices')
            ->where('number', 'SUB-BK-202610-001')
            ->update([
                'period_start' => '2026-10-01 00:00:00',
                'period_end' => '2026-10-31 23:59:59',
            ]);

        DB::table('subscription_invoices')
            ->where('number', 'INV-SUB-20261102-SPED')
            ->update([
                'period_start' => '2026-11-02 07:43:11',
                'period_end' => '2026-12-02 07:43:11',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
