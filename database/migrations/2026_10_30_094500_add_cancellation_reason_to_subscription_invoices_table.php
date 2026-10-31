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
            $table->text('cancellation_reason')->nullable()->after('notes');
            $table->string('cancelled_by', 30)->nullable()->after('cancellation_reason'); // 'owner', 'superadmin'
            $table->dateTime('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'cancellation_reason',
                'cancelled_by',
                'cancelled_at',
            ]);
        });
    }
};
