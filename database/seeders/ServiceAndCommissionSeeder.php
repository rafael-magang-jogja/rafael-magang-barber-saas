<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\BranchServicePrice;
use App\Models\CommissionRule;
use App\Models\HairColor;
use App\Models\Membership;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

final class ServiceAndCommissionSeeder extends Seeder
{
    public function run(): void
    {
        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $kemang = Branch::query()->where('tenant_id', $barberKing->id)->where('code', 'KMG')->firstOrFail();

        $catCut = ServiceCategory::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Potong & Grooming'],
            ['sort_order' => 1, 'is_active' => true]
        );

        $catTreat = ServiceCategory::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Perawatan Rambut'],
            ['sort_order' => 2, 'is_active' => true]
        );

        $catColor = ServiceCategory::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Pewarnaan Rambut'],
            ['sort_order' => 3, 'is_active' => true]
        );

        $catProd = ServiceCategory::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Produk Styling'],
            ['sort_order' => 4, 'is_active' => true]
        );

        $services = [
            [
                'category_id' => $catCut->id,
                'name' => 'Haircut',
                'description' => 'Potong rambut rapi + konsultasi gaya + handuk hangat.',
                'price' => 50_000,
                'duration_minutes' => 45,
                'type' => 'service',
                'sort_order' => 1,
                'commission_flat' => 5_000,
            ],
            [
                'category_id' => $catCut->id,
                'name' => 'Shave',
                'description' => 'Cukur jenggot/kumis bersih dengan hot towel & aftershave.',
                'price' => 30_000,
                'duration_minutes' => 20,
                'type' => 'service',
                'sort_order' => 2,
                'commission_flat' => 4_000,
            ],
            [
                'category_id' => $catCut->id,
                'name' => 'Kids Haircut',
                'description' => 'Potong rambut anak di bawah 10 tahun.',
                'price' => 40_000,
                'duration_minutes' => 30,
                'type' => 'service',
                'sort_order' => 3,
                'commission_flat' => 5_000,
            ],
            [
                'category_id' => $catTreat->id,
                'name' => 'Hair Wash',
                'description' => 'Cuci rambut + pijat kepala relaksasi.',
                'price' => 25_000,
                'duration_minutes' => 15,
                'type' => 'service',
                'sort_order' => 4,
                'commission_flat' => 3_000,
            ],
            [
                'category_id' => $catColor->id,
                'name' => 'Coloring',
                'description' => 'Pewarnaan rambut premium.',
                'price' => 150_000,
                'duration_minutes' => 60,
                'type' => 'service',
                'sort_order' => 5,
                'commission_flat' => 25_000,
            ],
            [
                'category_id' => $catProd->id,
                'name' => 'Pomade Waterbased',
                'description' => 'Pomade Barber King 100gr.',
                'price' => 65_000,
                'duration_minutes' => 5,
                'type' => 'product',
                'sort_order' => 6,
                'commission_flat' => 5_000,
            ],
        ];

        foreach ($services as $item) {
            $service = Service::query()->updateOrCreate(
                ['tenant_id' => $barberKing->id, 'name' => $item['name']],
                [
                    'service_category_id' => $item['category_id'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'duration_minutes' => $item['duration_minutes'],
                    'is_commissionable' => true,
                    'type' => $item['type'],
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                ]
            );

            // Komisi flat per layanan (sesuai simulasi Rp5.000/layanan)
            CommissionRule::query()->updateOrCreate(
                [
                    'tenant_id' => $barberKing->id,
                    'scope' => 'service',
                    'service_id' => $service->id,
                    'membership_id' => null,
                ],
                [
                    'name' => 'Komisi '.$service->name,
                    'type' => 'flat',
                    'value' => $item['commission_flat'],
                    'priority' => 20,
                    'is_active' => true,
                ]
            );
        }

        // Contoh harga khusus cabang Kemang untuk Haircut (Rp55.000)
        $haircut = Service::query()->where('tenant_id', $barberKing->id)->where('name', 'Haircut')->firstOrFail();
        BranchServicePrice::query()->updateOrCreate(
            ['branch_id' => $kemang->id, 'service_id' => $haircut->id],
            [
                'tenant_id' => $barberKing->id,
                'price' => 55_000,
                'is_available' => true,
            ]
        );

        // Aturan komisi khusus barber Rizky: bagi hasil 30%
        $rizky = Membership::query()
            ->where('tenant_id', $barberKing->id)
            ->where('role', Role::Barber)
            ->where('display_name', 'Rizky')
            ->first();

        if ($rizky !== null) {
            CommissionRule::query()->updateOrCreate(
                [
                    'tenant_id' => $barberKing->id,
                    'scope' => 'global',
                    'membership_id' => $rizky->id,
                ],
                [
                    'name' => 'Bagi Hasil 30% - Rizky',
                    'type' => 'percentage',
                    'value' => 30,
                    'priority' => 50,
                    'is_active' => true,
                ]
            );
        }

        // Pilihan Warna Coloring & Stok
        $colors = [
            ['name' => 'Ash Grey', 'hex_code' => '#9CA3AF', 'stock' => 4, 'min_stock' => 5, 'is_active' => true],
            ['name' => 'Platinum Blonde', 'hex_code' => '#FDE68A', 'stock' => 6, 'min_stock' => 5, 'is_active' => true],
            ['name' => 'Dark Espresso Brown', 'hex_code' => '#451A03', 'stock' => 14, 'min_stock' => 5, 'is_active' => true],
            ['name' => 'Burgundy Wine', 'hex_code' => '#881337', 'stock' => 10, 'min_stock' => 4, 'is_active' => true],
            ['name' => 'Blue Black', 'hex_code' => '#1E293B', 'stock' => 12, 'min_stock' => 5, 'is_active' => true],
            ['name' => 'Caramel Chestnut', 'hex_code' => '#B45309', 'stock' => 9, 'min_stock' => 4, 'is_active' => true],
        ];

        foreach ($colors as $c) {
            HairColor::query()->updateOrCreate(
                ['tenant_id' => $barberKing->id, 'name' => $c['name']],
                [
                    'hex_code' => $c['hex_code'],
                    'stock' => $c['stock'],
                    'min_stock' => $c['min_stock'],
                    'is_active' => $c['is_active'],
                ]
            );
        }
    }
}
