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
            $table->boolean('is_cashier_input')->default(false)->after('type');
            $table->index(['branch_id', 'is_cashier_input']);
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'is_cashier_input']);
            $table->dropColumn('is_cashier_input');
        });
    }
};
