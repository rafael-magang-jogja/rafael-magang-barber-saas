<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BarberMobileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_barber_dashboard_renders_mobile_layout_with_ready_status_and_stats(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barberUser)->get(route('barber.dashboard'));

        $response->assertOk();
        $response->assertSee('Staff BarberOS');
        $response->assertSee('Pelanggan di Kursi Anda');
        $response->assertSee('Antrean Menunggu di Cabang');
        $response->assertSee('Ready');
    }

    public function test_barber_can_call_waiting_ticket_in_branch_when_idle_and_ready(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Finish any seeded active tickets for this barber first
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299990001'],
            ['name' => 'Budi Antre']
        );

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => today()->toDateString(),
            'sequence' => 50,
            'number' => 'A-050',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => QueueStatus::Called->value,
            'barber_membership_id' => $barberMembership->id,
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertSame(QueueStatus::Called, $ticket->status);
        $this->assertSame($barberMembership->id, $ticket->barber_membership_id);
    }

    public function test_barber_cannot_call_another_ticket_when_already_serving_active_ticket(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299990002'],
            ['name' => 'Pelanggan Pertama']
        );

        // Ensure ticket 1 is active in service with this barber
        $ticket1 = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'sequence' => 51,
            'number' => 'A-051',
            'type' => 'walk_in',
            'status' => QueueStatus::InService,
            'priority_at' => now(),
            'served_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Ticket 2: Waiting
        $ticket2 = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => today()->toDateString(),
            'sequence' => 52,
            'number' => 'A-052',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Attempting to call Ticket 2 should fail with redirect and error message
        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket2), [
            'status' => QueueStatus::Called->value,
            'barber_membership_id' => $barberMembership->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('queue');

        $ticket2->refresh();
        $this->assertSame(QueueStatus::Waiting, $ticket2->status);
    }

    public function test_barber_can_start_service_and_complete_service(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081299990003'],
            ['name' => 'Pelanggan Sukses']
        );

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'sequence' => 53,
            'number' => 'A-053',
            'type' => 'walk_in',
            'status' => QueueStatus::Called,
            'priority_at' => now(),
            'called_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Start service
        $startResponse = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => QueueStatus::InService->value,
        ]);
        $startResponse->assertRedirect();
        $ticket->refresh();
        $this->assertSame(QueueStatus::InService, $ticket->status);

        // Complete service
        $completeResponse = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => QueueStatus::Completed->value,
        ]);
        $completeResponse->assertRedirect();
        $ticket->refresh();
        $this->assertSame(QueueStatus::Completed, $ticket->status);
    }

    public function test_barber_can_view_profile_page_with_logout_button(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barberUser)->get(route('barber.profile'));

        $response->assertOk();
        $response->assertSee('Profil Staff');
        $response->assertSee('Keluar Akun');
        $response->assertSee(route('logout'));
    }

    public function test_barber_attendance_page_contains_work_status_and_break_controls(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barberUser)->get(route('barber.attendance'));

        $response->assertOk();
        $response->assertSee('Status Jam Kerja &amp; Istirahat', false);
        $response->assertSee(route('barber.work-status'));
    }

    public function test_barber_can_update_password_from_profile_page(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barberUser)->put(route('barber.profile.password'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Kata sandi berhasil diperbarui.');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $barberUser->fresh()->password));
    }

    public function test_barber_can_view_edit_profile_page(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barberUser)->get(route('barber.profile.edit'));

        $response->assertOk();
        $response->assertSee('Edit Profil');
        $response->assertSee('Informasi Pribadi');
        $response->assertSee(route('barber.profile.update'));
    }

    public function test_barber_can_update_profile_information(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();

        $response = $this->actingAs($barberUser)->put(route('barber.profile.update'), [
            'name' => 'Andi Pratama Baru',
            'display_name' => 'Barber Andi Pro',
            'email' => 'andi.baru@barberking.id',
            'phone' => '081233445566',
            'specialty' => 'Classic Cut & Fade',
        ]);

        $response->assertRedirect(route('barber.profile'));
        $response->assertSessionHas('status', 'Informasi pribadi berhasil diperbarui.');

        $barberUser->refresh();
        $membership->refresh();

        $this->assertSame('Andi Pratama Baru', $barberUser->name);
        $this->assertSame('andi.baru@barberking.id', $barberUser->email);
        $this->assertSame('081233445566', $barberUser->phone);
        $this->assertSame('Barber Andi Pro', $membership->display_name);
        $this->assertSame('Classic Cut & Fade', $membership->specialty);
    }

    public function test_barber_can_view_earnings_page_with_pro_chart_when_plan_is_pro_or_business(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barberUser)->get(route('barber.earnings'));

        $response->assertOk();
        $response->assertSee('Penghasilan &amp; Komisi', false);
        $response->assertSee('Total Komisi');
        $response->assertSee('Riwayat Komisi Layanan');
        // Default seeded tenant is Pro
        $response->assertSee('Grafik Pendapatan');
    }

    public function test_barber_earnings_page_does_not_render_chart_when_plan_is_starter(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $starterPlan = \App\Models\Plan::query()->where('code', 'starter')->firstOrFail();

        $subscription = $tenant->activeSubscription();
        if ($subscription !== null) {
            $subscription->update(['plan_id' => $starterPlan->id]);
        }

        $response = $this->actingAs($barberUser)->get(route('barber.earnings'));

        $response->assertOk();
        $response->assertSee('Penghasilan &amp; Komisi', false);
        $response->assertSee('Total Komisi');
        $response->assertDontSee('Grafik Pendapatan');
    }

    public function test_barber_can_filter_earnings_by_different_periods(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        // 1. Filter Hari Ini
        $todayResponse = $this->actingAs($barberUser)->get(route('barber.earnings', ['period' => 'today']));
        $todayResponse->assertOk();
        $todayResponse->assertSee('Hari Ini');

        // 2. Filter 7 Hari Terakhir
        $weekResponse = $this->actingAs($barberUser)->get(route('barber.earnings', ['period' => '7days']));
        $weekResponse->assertOk();
        $weekResponse->assertSee('7 Hari Terakhir');

        // 3. Filter Bulan Lalu
        $lastMonthResponse = $this->actingAs($barberUser)->get(route('barber.earnings', ['period' => 'last_month']));
        $lastMonthResponse->assertOk();
        $lastMonthResponse->assertSee('Bulan Lalu');

        // 4. Filter Kustom
        $customResponse = $this->actingAs($barberUser)->get(route('barber.earnings', [
            'period' => 'custom',
            'date_from' => now()->subDays(5)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));
        $customResponse->assertOk();
        $customResponse->assertSee('Dari Tanggal');
        $customResponse->assertSee('Sampai Tanggal');
    }

    public function test_barber_can_recall_called_ticket(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Bersihkan active tickets sebelumnya
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'date' => now()->toDateString(),
            'number' => 'A-088',
            'sequence' => 88,
            'type' => 'walkin',
            'status' => QueueStatus::Called,
            'barber_membership_id' => $barberMembership->id,
            'called_at' => now()->subMinutes(2),
            'version' => 1,
        ]);

        $oldCalledAt = $ticket->called_at;

        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.recall', $ticket));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $ticket->refresh();
        $this->assertTrue($ticket->called_at->gt($oldCalledAt));
    }

    public function test_barber_can_skip_ticket_first_time_and_shift_down_one_position(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        QueueTicket::query()
            ->where('branch_id', $branch->id)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        // Tiket 1: Sedang dipanggil barber Andi
        $ticketA = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'date' => now()->toDateString(),
            'number' => 'A-010',
            'sequence' => 10,
            'type' => 'walkin',
            'status' => QueueStatus::Called,
            'barber_membership_id' => $barberMembership->id,
            'priority_at' => now()->subMinutes(10),
            'called_at' => now()->subMinute(),
            'skip_count' => 0,
            'version' => 1,
        ]);

        // Tiket 2: Menunggu berikutnya di cabang
        $ticketB = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'date' => now()->toDateString(),
            'number' => 'A-011',
            'sequence' => 1,
            'type' => 'walkin',
            'status' => QueueStatus::Waiting,
            'priority_at' => now()->subMinutes(5),
            'skip_count' => 0,
            'version' => 1,
        ]);

        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.skip', $ticketA));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $ticketA->refresh();
        $ticketB->refresh();

        // Tiket A sekarang berstatus waiting dengan skip_count = 1
        $this->assertSame(QueueStatus::Waiting, $ticketA->status);
        $this->assertSame(1, $ticketA->skip_count);

        // Tiket B maju menjadi urutan pertama, Tiket A mundur ke urutan kedua
        $this->assertSame(1, $ticketB->sequence);
        $this->assertSame(2, $ticketA->sequence);
    }

    public function test_barber_skipping_ticket_second_time_cancels_ticket_automatically(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        QueueTicket::query()
            ->where('branch_id', $branch->id)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        // Tiket yang dipanggil kedua kalinya (skip_count sudah 1)
        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'date' => now()->toDateString(),
            'number' => 'A-020',
            'sequence' => 20,
            'type' => 'walkin',
            'status' => QueueStatus::Called,
            'barber_membership_id' => $barberMembership->id,
            'called_at' => now()->subMinute(),
            'skip_count' => 1,
            'version' => 1,
        ]);

        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.skip', $ticket));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $ticket->refresh();

        // Tiket otomatis dibatalkan karena 2x tidak hadir
        $this->assertSame(QueueStatus::Cancelled, $ticket->status);
        $this->assertSame(2, $ticket->skip_count);
        $this->assertStringContainsString('Dibatalkan otomatis: 2x tidak hadir', (string) $ticket->notes);
    }
}
