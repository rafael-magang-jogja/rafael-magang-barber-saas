<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Attendance;
use App\Models\BarberSchedule;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\SalaryScheme;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class StaffAndBarberSeeder extends Seeder
{
    public function run(): void
    {
        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $sudirman = Branch::query()->where('tenant_id', $barberKing->id)->where('code', 'SDR')->firstOrFail();
        $kemang = Branch::query()->where('tenant_id', $barberKing->id)->where('code', 'KMG')->firstOrFail();

        $seniorScheme = SalaryScheme::query()
            ->where('tenant_id', $barberKing->id)
            ->where('name', 'like', '%Senior Barber%')
            ->firstOrFail();

        $shareScheme = SalaryScheme::query()
            ->where('tenant_id', $barberKing->id)
            ->where('type', 'commission_only')
            ->firstOrFail();

        $adminScheme = SalaryScheme::query()
            ->where('tenant_id', $barberKing->id)
            ->where('name', 'Staf Admin / Kasir (Gaji Pokok)')
            ->first() ?? $seniorScheme;

        // Admin / Kasir Cabang Sudirman (Siti)
        $cashierSiti = User::query()->updateOrCreate(
            ['email' => 'kasir.sudirman@barberking.id'],
            [
                'name' => 'Siti Rahmawati',
                'phone' => '081310000001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        Membership::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'user_id' => $cashierSiti->id, 'role' => Role::Admin],
            [
                'branch_id' => $sudirman->id,
                'display_name' => 'Siti',
                'employment_type' => 'permanent',
                'salary_scheme_id' => $adminScheme->id,
                'work_status' => WorkStatus::Active,
                'permissions' => ['can_refund' => true],
                'is_active' => true,
                'joined_at' => now()->subMonths(8)->toDateString(),
            ]
        );

        // Barbers Cabang Sudirman & Kemang (sesuai mockup: Andi, Bima, Rizky, Doni, Eko)
        $barbers = [
            [
                'name' => 'Andi Pratama',
                'display_name' => 'Andi',
                'email' => 'andi@barberking.id',
                'phone' => '081310000011',
                'branch_id' => $sudirman->id,
                'specialty' => 'Fade · Pompadour',
                'scheme_id' => $seniorScheme->id,
                'work_status' => WorkStatus::Active,
                'rating_avg' => 4.90,
                'reviews_count' => 128,
            ],
            [
                'name' => 'Bima Saputra',
                'display_name' => 'Bima',
                'email' => 'bima@barberking.id',
                'phone' => '081310000012',
                'branch_id' => $sudirman->id,
                'specialty' => 'Crop · Undercut',
                'scheme_id' => $seniorScheme->id,
                'work_status' => WorkStatus::Active,
                'rating_avg' => 4.80,
                'reviews_count' => 94,
            ],
            [
                'name' => 'Rizky Maulana',
                'display_name' => 'Rizky',
                'email' => 'rizky@barberking.id',
                'phone' => '081310000013',
                'branch_id' => $sudirman->id,
                'specialty' => 'Coloring · Perm',
                'scheme_id' => $shareScheme->id,
                'work_status' => WorkStatus::Active,
                'rating_avg' => 4.75,
                'reviews_count' => 72,
            ],
            [
                'name' => 'Doni Kurniawan',
                'display_name' => 'Doni',
                'email' => 'doni@barberking.id',
                'phone' => '081310000014',
                'branch_id' => $sudirman->id,
                'specialty' => 'Classic Cut · Shave',
                'scheme_id' => $seniorScheme->id,
                'work_status' => WorkStatus::Active,
                'rating_avg' => 4.85,
                'reviews_count' => 61,
            ],
            [
                'name' => 'Eko Prasetyo',
                'display_name' => 'Eko',
                'email' => 'eko@barberking.id',
                'phone' => '081310000015',
                'branch_id' => $kemang->id,
                'specialty' => 'Executive Cut',
                'scheme_id' => $seniorScheme->id,
                'work_status' => WorkStatus::Break,
                'rating_avg' => 4.70,
                'reviews_count' => 45,
            ],
        ];

        $today = now()->toDateString();

        foreach ($barbers as $b) {
            $user = User::query()->updateOrCreate(
                ['email' => $b['email']],
                [
                    'name' => $b['name'],
                    'phone' => $b['phone'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $membership = Membership::query()->updateOrCreate(
                ['tenant_id' => $barberKing->id, 'user_id' => $user->id, 'role' => Role::Barber],
                [
                    'branch_id' => $b['branch_id'],
                    'display_name' => $b['display_name'],
                    'specialty' => $b['specialty'],
                    'employment_type' => 'permanent',
                    'salary_scheme_id' => $b['scheme_id'],
                    'work_status' => $b['work_status'],
                    'rating_avg' => $b['rating_avg'],
                    'reviews_count' => $b['reviews_count'],
                    'is_active' => true,
                    'joined_at' => now()->subMonths(6)->toDateString(),
                ]
            );

            // Jadwal mingguan (Senin-Minggu)
            for ($day = 0; $day <= 6; $day++) {
                BarberSchedule::query()->updateOrCreate(
                    ['membership_id' => $membership->id, 'day_of_week' => $day],
                    [
                        'tenant_id' => $barberKing->id,
                        'branch_id' => $b['branch_id'],
                        'start_time' => '10:00:00',
                        'end_time' => '21:00:00',
                        'break_start' => '12:00:00',
                        'break_end' => '13:00:00',
                        'is_off' => false,
                    ]
                );
            }
        }

        // =====================================================================
        // TENANT 2: Gentleman Cut Barbershop (Cabang Tebet)
        // =====================================================================
        $gentlemanCut = Tenant::query()->where('slug', 'gentleman-cut')->first();
        if ($gentlemanCut) {
            $tebetBranch = Branch::query()->withoutGlobalScope('tenant')
                ->where('tenant_id', $gentlemanCut->id)
                ->where('code', 'TBT')
                ->first();

            $gcBarberUser = User::query()->updateOrCreate(
                ['email' => 'fajar.barber@gentlemancut.id'],
                [
                    'name' => 'Fajar Ramadhan',
                    'phone' => '081210000030',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $gcMembership = Membership::query()->updateOrCreate(
                ['tenant_id' => $gentlemanCut->id, 'user_id' => $gcBarberUser->id, 'role' => Role::Barber],
                [
                    'branch_id' => $tebetBranch?->id,
                    'display_name' => 'Fajar',
                    'specialty' => 'Classic Cut & Shaving Expert',
                    'employment_type' => 'permanent',
                    'salary_scheme_id' => null,
                    'work_status' => WorkStatus::Active,
                    'rating_avg' => 4.85,
                    'reviews_count' => 38,
                    'is_active' => true,
                    'joined_at' => now()->subMonths(3)->toDateString(),
                ]
            );

            for ($day = 0; $day <= 6; $day++) {
                BarberSchedule::query()->updateOrCreate(
                    ['membership_id' => $gcMembership->id, 'day_of_week' => $day],
                    [
                        'tenant_id' => $gentlemanCut->id,
                        'branch_id' => $tebetBranch?->id,
                        'start_time' => '10:00:00',
                        'end_time' => '21:00:00',
                        'break_start' => '12:00:00',
                        'break_end' => '13:00:00',
                        'is_off' => false,
                    ]
                );
            }
        }
    }
}
