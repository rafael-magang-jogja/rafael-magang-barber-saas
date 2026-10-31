<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SuperAdminSeeder::class,
            OwnerSeeder::class,
            StaffAndBarberSeeder::class,
            ServiceAndCommissionSeeder::class,
            OperationalDemoSeeder::class,
        ]);
    }
}
