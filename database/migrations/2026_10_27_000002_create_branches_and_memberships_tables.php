<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('opening_hours')->nullable();
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
            $table->unsignedSmallInteger('booking_slot_minutes')->default(30);
            $table->boolean('allow_customer_choose_barber')->default(true);
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('salary_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->default('base_plus_commission');
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->string('period', 20)->default('monthly');
            $table->json('target_bonus_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 30);
            $table->string('display_name')->nullable();
            $table->string('specialty')->nullable();
            $table->string('employment_type', 30)->nullable();
            $table->foreignId('salary_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('work_status', 20)->default('active');
            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('joined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'role', 'is_active']);
            $table->index(['tenant_id', 'branch_id', 'role']);
            $table->index(['user_id', 'tenant_id']);
        });

        Schema::create('customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0);
            $table->dateTime('last_visit_at')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('salary_schemes');
        Schema::dropIfExists('branches');
    }
};
