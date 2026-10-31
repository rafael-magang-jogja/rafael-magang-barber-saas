<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'superadmin@barberos.id'],
            [
                'name' => 'Super Admin BarberOS',
                'phone' => '081100000001',
                'password' => Hash::make('password'),
                'is_superadmin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
