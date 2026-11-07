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
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->string('xendit_invoice_id')->nullable()->after('gateway_ref')->index();
            $table->string('xendit_external_id')->nullable()->after('xendit_invoice_id')->index();
            $table->text('xendit_payment_url')->nullable()->after('xendit_external_id');
            $table->string('xendit_payment_method', 50)->nullable()->after('xendit_payment_url');
            $table->string('xendit_payment_channel', 50)->nullable()->after('xendit_payment_method');
            $table->dateTime('xendit_paid_at')->nullable()->after('xendit_payment_channel');
            $table->dateTime('xendit_expired_at')->nullable()->after('xendit_paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->dropIndex(['xendit_invoice_id']);
            $table->dropIndex(['xendit_external_id']);
            $table->dropColumn([
                'xendit_invoice_id',
                'xendit_external_id',
                'xendit_payment_url',
                'xendit_payment_method',
                'xendit_payment_channel',
                'xendit_paid_at',
                'xendit_expired_at',
            ]);
        });
    }
};
