<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Livewire\CashierBoard;
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

final class CashierBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_dashboard_page_renders_livewire_board_with_wire_poll_5s(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get('/cashier');

        $response->assertOk();
        $response->assertSee('wire:poll.5s', false);
        $response->assertSee('Papan Antrean &amp; POS', false);
        $response->assertSee('otomatis disegarkan tiap 5 detik', false);
    }

    public function test_cashier_board_livewire_component_renders_tickets_and_chairs(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        Livewire::actingAs($cashier)
            ->test(CashierBoard::class)
            ->assertSeeHtml('wire:poll.5s')
            ->assertSee('Papan Antrean & POS')
            ->assertSee('Monitor Kursi Barber')
            ->assertSee('Menunggu')
            ->assertSee('Dipanggil')
            ->assertSee('Sedang Dicukur')
            ->assertSee('Selesai / Bayar');
    }

    public function test_cashier_board_livewire_component_updates_dynamically_when_tickets_change(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barber->id)->firstOrFail();

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '089999888123'],
            ['name' => 'Budi Santoso']
        );

        // Buat tiket antrean baru
        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => now()->toDateString(),
            'number' => 'Z-999',
            'sequence' => 999,
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);

        $test = Livewire::actingAs($cashier)
            ->test(CashierBoard::class)
            ->assertSee('Z-999')
            ->assertSee('Budi Santoso');

        // Update status tiket menjadi called
        $ticket->update([
            'status' => QueueStatus::Called,
            'called_at' => now(),
        ]);

        // Simulasikan poll 5 detik berikutnya
        $test->call('$refresh')
            ->assertSee('Z-999')
            ->assertSee('Dipanggil Barber');
    }

    public function test_cashier_board_filters_by_status(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        Livewire::actingAs($cashier)
            ->test(CashierBoard::class, ['statusFilter' => 'waiting'])
            ->assertSee('Menunggu')
            ->assertDontSee('Sedang Dicukur');
    }
}
