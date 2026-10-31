<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Livewire\TvQueueDisplay;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class TvDisplayLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_tv_display_page_renders_livewire_component_with_wire_poll_3s(): void
    {
        $response = $this->get('/b/barber-king/tv/SDR');

        $response->assertOk();
        $response->assertSee('wire:poll.3s', false);
        $response->assertSee('SEDANG DIPANGGIL');
        $response->assertSee('ANTREAN BERIKUTNYA');
        $response->assertSee('STATUS STAFF BARBER');
    }

    public function test_tv_livewire_component_renders_called_ticket_and_barber_statuses(): void
    {
        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => 'barber-king',
            'branchCode' => 'SDR',
        ])
            ->assertSee('SEDANG DIPANGGIL')
            ->assertSee('STATUS STAFF BARBER')
            ->assertSee('ANTREAN BERIKUTNYA')
            ->assertSeeHtml('wire:poll.3s');
    }

    public function test_tv_livewire_component_refreshes_dynamically_when_new_ticket_is_called(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = Membership::query()->where('user_id', $barber->id)->firstOrFail();

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299990099'],
            ['name' => 'Testing Antrean TV']
        );

        $newTicket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $membership->id,
            'date' => today()->toDateString(),
            'sequence' => 999,
            'number' => 'A-999',
            'type' => 'walk_in',
            'status' => QueueStatus::Called,
            'called_at' => now(),
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => $tenant->slug,
            'branchCode' => $branch->code,
        ])
            ->assertSee('A-999')
            ->assertSee('Kursi ' . $membership->display_name);
    }

    public function test_tv_livewire_component_renders_static_staff_grid_when_four_or_fewer_barbers(): void
    {
        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => 'barber-king',
            'branchCode' => 'SDR',
        ])
            ->assertSeeHtml('data-has-staff-rolling="false"')
            ->assertDontSeeHtml('data-is-clone="true"');
    }

    public function test_tv_livewire_component_activates_horizontal_rolling_when_more_than_four_barbers(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'SDR')->firstOrFail();

        // Tambah barber ke-5 ke cabang Sudirman
        $extraUser = User::query()->create([
            'name' => 'Fajar Santoso',
            'email' => 'fajar@barberking.id',
            'phone' => '081399998877',
            'password' => bcrypt('password'),
        ]);

        Membership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $extraUser->id,
            'branch_id' => $branch->id,
            'role' => \App\Enums\Role::Barber,
            'display_name' => 'Fajar',
            'specialty' => 'Buzz Cut · Two Block',
            'is_active' => true,
            'work_status' => \App\Enums\WorkStatus::Active,
        ]);

        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => $tenant->slug,
            'branchCode' => $branch->code,
        ])
            ->assertSeeHtml('data-has-staff-rolling="true"')
            ->assertSeeHtml('data-tv-staff-window')
            ->assertSeeHtml('data-tv-staff-track')
            ->assertSeeHtml('data-is-clone="true"')
            ->assertSee('Fajar');
    }
}
