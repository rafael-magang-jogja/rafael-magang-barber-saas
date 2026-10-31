<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('barber_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('estimated_end_at');
            $table->unsignedSmallInteger('total_duration_minutes');
            $table->unsignedBigInteger('estimated_total');
            $table->string('status', 30)->default('confirmed');
            $table->string('source', 30)->default('customer_app');
            $table->text('notes')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'scheduled_at']);
            $table->index(['branch_id', 'barber_membership_id', 'scheduled_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('booking_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();
        });

        Schema::create('queue_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->char('prefix', 2)->default('A');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'date', 'prefix']);
        });

        Schema::create('queue_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('number', 20);
            $table->unsignedInteger('sequence');
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('requested_barber_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('type', 20)->default('walk_in');
            $table->string('status', 30)->default('waiting');
            $table->dateTime('priority_at');
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(30);
            $table->text('notes')->nullable();
            $table->dateTime('called_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedTinyInteger('skip_count')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['branch_id', 'date', 'number']);
            $table->index(['branch_id', 'date', 'status', 'priority_at']);
            $table->index(['tenant_id', 'barber_membership_id', 'date', 'status']);
        });

        Schema::create('queue_ticket_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('queue_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();
        });

        Schema::create('queue_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('queue_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['queue_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_ticket_events');
        Schema::dropIfExists('queue_ticket_items');
        Schema::dropIfExists('queue_tickets');
        Schema::dropIfExists('queue_counters');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
