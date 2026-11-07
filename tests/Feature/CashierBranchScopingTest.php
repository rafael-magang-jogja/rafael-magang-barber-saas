<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardQueryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CashierBranchScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_is_scoped_only_to_assigned_branch(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branches = Branch::query()->where('tenant_id', $tenant->id)->get();

        $branch1 = $branches[0];
        $branch2 = $branches[1];

        // Buat user kasir yang ditugaskan ke Branch 1
        $cashierUser = User::factory()->create(['email' => 'cashier.test@barberking.id']);
        Membership::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch1->id,
            'user_id' => $cashierUser->id,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        $service = app(DashboardQueryService::class);

        // Uji getCashierBoardData untuk kasir tersebut
        $data = $service->getCashierBoardData($cashierUser->id);

        $this->assertEquals($branch1->id, $data['cashierBranchId']);
        $this->assertCount(1, $data['branches']);
        $this->assertEquals($branch1->id, $data['branches']->first()->id);

        // Uji getCashierWalkInData untuk kasir tersebut
        $walkInData = $service->getCashierWalkInData($cashierUser->id);
        $this->assertEquals($branch1->id, $walkInData['cashierBranchId']);
        $this->assertCount(1, $walkInData['branches']);
        $this->assertEquals($branch1->id, $walkInData['branches']->first()->id);

        // Uji getCashierBookingsData untuk kasir tersebut
        $bookingsData = $service->getCashierBookingsData($cashierUser->id);
        $this->assertEquals($branch1->id, $bookingsData['cashierBranchId']);
    }

    public function test_owner_without_branch_assignment_sees_all_branches(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $service = app(DashboardQueryService::class);
        $data = $service->getCashierBoardData($owner->id);

        $this->assertNull($data['cashierBranchId']);
        $this->assertGreaterThan(1, $data['branches']->count());
    }

    public function test_cashier_dashboard_with_custom_date_filter_loads_without_query_exception(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $customer = User::factory()->create();
        $profile = $customer->customerProfiles()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299990001'],
            ['name' => 'Budi Customer']
        );

        \App\Models\Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'user_id' => $customer->id,
            'code' => 'BK-SDR-TESTDATE',
            'scheduled_at' => '2026-11-06 14:00:00',
            'estimated_end_at' => '2026-11-06 14:45:00',
            'total_duration_minutes' => 45,
            'estimated_total' => 50000,
            'source' => 'online',
            'status' => \App\Enums\BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($cashierUser)
            ->get("/cashier?branch_id={$branch->id}&custom_date=2026-11-06&date_filter=custom");

        $response->assertOk();
    }
}
