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
        if (Schema::hasTable('barber_time_offs') && !Schema::hasColumn('barber_time_offs', 'attachment_path')) {
            Schema::table('barber_time_offs', function (Blueprint $table): void {
                $table->string('attachment_path', 500)->nullable()->after('reason');
            });
        }

        if (Schema::hasTable('salary_schemes') && !Schema::hasColumn('salary_schemes', 'alpha_penalty_nominal')) {
            Schema::table('salary_schemes', function (Blueprint $table): void {
                $table->unsignedBigInteger('alpha_penalty_nominal')->default(0)->after('base_salary');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('barber_time_offs') && Schema::hasColumn('barber_time_offs', 'attachment_path')) {
            Schema::table('barber_time_offs', function (Blueprint $table): void {
                $table->dropColumn('attachment_path');
            });
        }

        if (Schema::hasTable('salary_schemes') && Schema::hasColumn('salary_schemes', 'alpha_penalty_nominal')) {
            Schema::table('salary_schemes', function (Blueprint $table): void {
                $table->dropColumn('alpha_penalty_nominal');
            });
        }
    }
};
