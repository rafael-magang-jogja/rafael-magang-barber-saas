<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Enums\WorkStatus;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FairQueueAndAutoBarberCallingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_dashboard_has_no_call_button_and_no_start_service_button(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Panggil Antrean Berikutnya');
        $response->assertDontSee('Mulai Cukur');
        $response->assertSee('Menunggu Giliran');
    }

    public function test_cashier_and_owner_cannot_call_or_start_service(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299991111'],
            ['name' => 'Antrean Test Role']
        );

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => today()->toDateString(),
            'sequence' => 900,
            'number' => 'A-900',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Kasir coba panggil -> ditolak
        $responseCashier = $this->actingAs($cashier)->post(route('cashier.tickets.transition', $ticket), [
            'status' => 'called',
        ]);
        $responseCashier->assertRedirect();
        $responseCashier->assertSessionHasErrors('queue');

        // Owner coba panggil via call-next -> ditolak
        $responseOwner = $this->actingAs($owner)->post(route('cashier.tickets.call-next'), [
            'branch_id' => $branch->id,
        ]);
        $responseOwner->assertRedirect();
        $responseOwner->assertSessionHasErrors('queue');

        // Tiket tetap waiting
        $ticket->refresh();
        $this->assertSame(QueueStatus::Waiting, $ticket->status);
    }

    public function test_barber_can_call_and_start_service_from_barber_chair(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299992222'],
            ['name' => 'Pelanggan Barber']
        );

        $barberMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $barberUser->id)
            ->firstOrFail();
        $barberMembership->update(['work_status' => WorkStatus::Active]);

        // Selesaikan tugas aktif barber sebelumnya agar bisa memanggil antrean baru
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'sequence' => 901,
            'number' => 'A-901',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Barber memanggil tiket
        $callResponse = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => 'called',
            'barber_membership_id' => $barberMembership->id,
        ]);
        $callResponse->assertRedirect();
        $callResponse->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(QueueStatus::Called, $ticket->status);

        // Barber memulai cukur
        $startResponse = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => 'in_service',
            'barber_membership_id' => $barberMembership->id,
        ]);
        $startResponse->assertRedirect();
        $startResponse->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(QueueStatus::InService, $ticket->status);
    }
}
