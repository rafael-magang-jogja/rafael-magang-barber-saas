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
        Schema::table('barber_time_offs', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('type');
            $table->text('notes')->nullable()->after('reason');
            $table->index(['tenant_id', 'status']);
        });

        // Set status = 'approved' untuk data yang sudah disetujui sebelumnya
        \Illuminate\Support\Facades\DB::table('barber_time_offs')
            ->whereNotNull('approved_by')
            ->update(['status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barber_time_offs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn(['status', 'notes']);
        });
    }
};
