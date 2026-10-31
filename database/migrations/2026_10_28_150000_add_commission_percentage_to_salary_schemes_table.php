<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('salary_schemes', 'commission_percentage')) {
            Schema::table('salary_schemes', function (Blueprint $table): void {
                $table->unsignedSmallInteger('commission_percentage')->nullable()->after('base_salary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('salary_schemes', 'commission_percentage')) {
            Schema::table('salary_schemes', function (Blueprint $table): void {
                $table->dropColumn('commission_percentage');
            });
        }
    }
};
