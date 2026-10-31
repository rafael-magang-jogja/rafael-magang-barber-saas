<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('scope', 20)->default('global');
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('flat');
            $table->unsignedBigInteger('value');
            $table->unsignedSmallInteger('priority')->default(10);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'priority']);
        });

        Schema::create('payrolls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'period_start', 'period_end']);
        });

        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('services_count')->default(0);
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->bigInteger('commission_total')->default(0);
            $table->bigInteger('bonus_total')->default(0);
            $table->bigInteger('deduction_total')->default(0);
            $table->bigInteger('tip_total')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->bigInteger('net_total')->default(0);
            $table->json('scheme_snapshot')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'membership_id']);
            $table->index(['tenant_id', 'membership_id']);
        });

        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('commission');
            $table->bigInteger('base_amount');
            $table->bigInteger('amount');
            $table->dateTime('earned_at');
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'membership_id', 'earned_at']);
            $table->index(['tenant_id', 'payroll_id']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label');
            $table->unsignedBigInteger('amount');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('commission_rules');
    }
};
