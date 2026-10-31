<?php

declare(strict_types=1);

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $oldCat = ServiceCategory::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Perawatan & Warna')
                ->first();

            if ($oldCat !== null) {
                $oldCat->update([
                    'name' => 'Perawatan Rambut',
                    'sort_order' => 2,
                ]);

                $colorCat = ServiceCategory::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Pewarnaan Rambut',
                    'sort_order' => 3,
                    'is_active' => true,
                ]);

                ServiceCategory::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', 'Produk Styling')
                    ->update(['sort_order' => 4]);

                Service::query()
                    ->where('tenant_id', $tenant->id)
                    ->where(function ($q): void {
                        $q->where('name', 'LIKE', '%color%')
                            ->orWhere('name', 'LIKE', '%warna%');
                    })
                    ->update(['service_category_id' => $colorCat->id]);
            }
        }
    }

    public function down(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $treatCat = ServiceCategory::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Perawatan Rambut')
                ->first();

            $colorCat = ServiceCategory::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Pewarnaan Rambut')
                ->first();

            if ($treatCat !== null && $colorCat !== null) {
                Service::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('service_category_id', $colorCat->id)
                    ->update(['service_category_id' => $treatCat->id]);

                $treatCat->update(['name' => 'Perawatan & Warna', 'sort_order' => 2]);
                $colorCat->delete();

                ServiceCategory::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', 'Produk Styling')
                    ->update(['sort_order' => 3]);
            }
        }
    }
};
