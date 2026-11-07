<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('payout_bank_name', 50)->nullable()->after('address');
            $table->string('payout_account_number', 50)->nullable()->after('payout_bank_name');
            $table->string('payout_account_holder', 100)->nullable()->after('payout_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'payout_bank_name',
                'payout_account_number',
                'payout_account_holder',
            ]);
        });
    }
};
