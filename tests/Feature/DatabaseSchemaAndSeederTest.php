<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Payroll;
use App\Models\Plan;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseSchemaAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_seeder_can_run_independently_after_plan_seeder(): void
    {
        $this->seed([
            PlanSeeder::class,
            OwnerSeeder::class,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@barberking.id',
            'is_superadmin' => false,
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'barber-king',
            'name' => 'Barber King',
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'gentleman-cut',
            'name' => 'Gentleman Cut Barbershop',
        ]);
    }

    public function test_full_database_seeder_populates_all_domains_and_respects_tenant_isolation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(2, Tenant::query()->count());
        $this->assertTrue(User::query()->where('email', 'superadmin@barberos.id')->value('is_superadmin'));

        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $gentlemanCut = Tenant::query()->where('slug', 'gentleman-cut')->firstOrFail();

        // Uji isolasi Global Scope BelongsToTenant
        app(CurrentTenant::class)->set($barberKing);
        $this->assertSame(2, Branch::query()->count());
        $this->assertSame(6, Service::query()->count());
        $this->assertSame(7, QueueTicket::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payroll::query()->count());

        // Saat pindah konteks ke tenant Gentleman Cut, data Barber King terisolasi (tidak bocor)
        app(CurrentTenant::class)->set($gentlemanCut);
        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(0, Service::query()->count());
        $this->assertSame(0, QueueTicket::query()->count());
        $this->assertSame(0, Order::query()->count());

        app(CurrentTenant::class)->set(null);
        $this->assertSame(5, Membership::query()->where('tenant_id', $barberKing->id)->where('role', Role::Barber)->count());
    }
}
