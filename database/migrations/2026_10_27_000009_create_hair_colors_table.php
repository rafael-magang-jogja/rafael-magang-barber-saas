<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hair_colors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('hex_code', 16)->default('#6B7280');
            $table->unsignedInteger('stock')->default(10);
            $table->unsignedInteger('min_stock')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('hair_color_id')->nullable()->after('barber_membership_id')->constrained('hair_colors')->nullOnDelete();
        });

        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->foreignId('hair_color_id')->nullable()->after('barber_membership_id')->constrained('hair_colors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hair_color_id');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hair_color_id');
        });

        Schema::dropIfExists('hair_colors');
    }
};
