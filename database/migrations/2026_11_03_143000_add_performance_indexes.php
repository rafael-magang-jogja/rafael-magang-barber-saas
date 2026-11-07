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
            $table->index(['tenant_id', 'date', 'status'], 'idx_queue_tickets_tenant_date_status');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->index(['tenant_id', 'date'], 'idx_attendances_tenant_date');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['tenant_id', 'status', 'scheduled_at'], 'idx_bookings_tenant_status_scheduled');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['barber_membership_id', 'payment_status', 'paid_at'], 'idx_orders_barber_status_paid');
        });

        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->index(['status'], 'idx_subscription_invoices_status');
        });

        Schema::table('memberships', function (Blueprint $table): void {
            $table->index(['user_id', 'is_active'], 'idx_memberships_user_active');
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropIndex('idx_queue_tickets_tenant_date_status');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('idx_attendances_tenant_date');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('idx_bookings_tenant_status_scheduled');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('idx_orders_barber_status_paid');
        });

        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->dropIndex('idx_subscription_invoices_status');
        });

        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropIndex('idx_memberships_user_active');
        });
    }
};
