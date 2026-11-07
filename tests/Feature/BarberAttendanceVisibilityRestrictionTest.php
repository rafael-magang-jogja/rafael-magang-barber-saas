<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\CashierBoard;
use App\Livewire\TvQueueDisplay;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardQueryService;
use App\Services\QueueService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BarberAttendanceVisibilityRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_barber_who_has_not_checked_in_is_hidden_from_tv_and_present_barber_is_visible(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barberAndi = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membershipAndi = Membership::query()->where('user_id', $barberAndi->id)->firstOrFail();

        // Hapus tiket demo dan absensi Andi hari ini
        \App\Models\QueueTicket::query()->where('tenant_id', $tenant->id)->delete();
        Attendance::query()->where('membership_id', $membershipAndi->id)->whereDate('date', now()->toDateString())->delete();

        // Di TV display, Andi seharusnya tidak muncul di status staff barber
        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => $tenant->slug,
            'branchCode' => $branch->code,
        ])
            ->assertDontSee($membershipAndi->display_name);

        // Ketika Andi absen masuk hari ini (check-in)
        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $membershipAndi->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHour(),
            'check_out_at' => null,
            'status' => 'present',
            'late_minutes' => 0,
        ]);

        // Di TV display, Andi sekarang harus muncul
        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => $tenant->slug,
            'branchCode' => $branch->code,
        ])
            ->assertSee($membershipAndi->display_name);
    }

    public function test_barber_who_has_checked_out_is_hidden_from_tv(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barberAndi = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membershipAndi = Membership::query()->where('user_id', $barberAndi->id)->firstOrFail();

        // Hapus tiket demo
        \App\Models\QueueTicket::query()->where('tenant_id', $tenant->id)->delete();

        // Set absensi Andi menjadi sudah checkout / pulang
        Attendance::query()->where('membership_id', $membershipAndi->id)->whereDate('date', now()->toDateString())->delete();
        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $membershipAndi->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHours(8),
            'check_out_at' => now()->subHour(), // Sudah pulang
            'status' => 'present',
            'late_minutes' => 0,
        ]);

        Livewire::test(TvQueueDisplay::class, [
            'tenantSlug' => $tenant->slug,
            'branchCode' => $branch->code,
        ])
            ->assertDontSee($membershipAndi->display_name);
    }

    public function test_barber_who_has_not_checked_in_or_has_checked_out_is_hidden_from_cashier_board_chair_monitor(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $barberAndi = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membershipAndi = Membership::query()->where('user_id', $barberAndi->id)->firstOrFail();

        // 1. Belum absen -> tidak muncul di monitor kursi
        Attendance::query()->where('membership_id', $membershipAndi->id)->whereDate('date', now()->toDateString())->delete();

        Livewire::actingAs($cashier)
            ->test(CashierBoard::class)
            ->assertDontSeeHtml('wire:key="chair-barber-' . $membershipAndi->id . '"');

        // 2. Sudah absen masuk -> muncul di monitor kursi
        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $membershipAndi->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHours(2),
            'check_out_at' => null,
            'status' => 'present',
        ]);

        Livewire::actingAs($cashier)
            ->test(CashierBoard::class)
            ->assertSeeHtml('wire:key="chair-barber-' . $membershipAndi->id . '"');

        // 3. Sudah pulang (check-out) -> hilang lagi dari monitor kursi
        Attendance::query()->where('membership_id', $membershipAndi->id)->update([
            'check_out_at' => now(),
        ]);

        Livewire::actingAs($cashier)
            ->test(CashierBoard::class)
            ->assertDontSeeHtml('wire:key="chair-barber-' . $membershipAndi->id . '"');
    }

    public function test_walk_in_kasir_and_customer_walk_in_only_show_present_barbers_while_booking_shows_all_barbers(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        // Ambil 2 barber: Barber A hadir, Barber B belum hadir/sudah pulang
        $barbers = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', Role::Barber)
            ->get();

        $this->assertGreaterThanOrEqual(2, $barbers->count());
        $barberA = $barbers[0];
        $barberB = $barbers[1];

        // Barber A: Hadir hari ini (check-in, belum check-out)
        Attendance::query()->where('membership_id', $barberA->id)->whereDate('date', now()->toDateString())->delete();
        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $barberA->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHour(),
            'check_out_at' => null,
            'status' => 'present',
        ]);

        // Barber B: Sudah pulang (check-out not null)
        Attendance::query()->where('membership_id', $barberB->id)->whereDate('date', now()->toDateString())->delete();
        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $barberB->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHours(6),
            'check_out_at' => now()->subMinutes(10),
            'status' => 'present',
        ]);

        $queryService = app(DashboardQueryService::class);

        // 1. Data Walk-in Kasir
        $walkInData = $queryService->getCashierWalkInData();
        $this->assertTrue($walkInData['barbers']->contains('id', $barberA->id));
        $this->assertFalse($walkInData['barbers']->contains('id', $barberB->id));

        // 2. Data Customer Portal: walk-in barbers vs booking barbers
        $customerData = $queryService->getCustomerPortalData(tenantSlug: $tenant->slug, branchCodeOrId: $branch->id);
        // Walkin barbers hanya barber A
        $this->assertTrue($customerData['walkinBarbers']->contains('id', $barberA->id));
        $this->assertFalse($customerData['walkinBarbers']->contains('id', $barberB->id));

        // Booking barbers KELUAR SEMUA (baik barber A maupun barber B)
        $this->assertTrue($customerData['barbers']->contains('id', $barberA->id));
        $this->assertTrue($customerData['barbers']->contains('id', $barberB->id));
    }

    public function test_cannot_create_walk_in_ticket_for_barber_who_has_not_checked_in_or_checked_out(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barberAndi = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membershipAndi = Membership::query()->where('user_id', $barberAndi->id)->firstOrFail();

        // Hapus absensi Andi
        Attendance::query()->where('membership_id', $membershipAndi->id)->whereDate('date', now()->toDateString())->delete();

        $queueService = app(QueueService::class);

        // Harus melempar DomainException karena barber belum absen masuk
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Barber yang dipilih belum hadir / absen masuk atau sudah pulang hari ini.');

        $queueService->createWalkInTicket(
            branch: $branch,
            customerName: 'Customer Test',
            customerPhone: '08123456789',
            barberMembershipId: $membershipAndi->id,
        );
    }

    public function test_barber_attendance_page_hides_work_status_card_when_not_checked_in_or_checked_out(): void
    {
        $barberAndi = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membershipAndi = Membership::query()->where('user_id', $barberAndi->id)->firstOrFail();

        // 1. Belum absen masuk -> kartu status kerja & istirahat di-hide
        Attendance::query()->where('membership_id', $membershipAndi->id)->whereDate('date', now()->toDateString())->delete();

        $response = $this->actingAs($barberAndi)->get('/barber/attendance');
        $response->assertOk();
        $response->assertDontSee('Status Jam Kerja &amp; Istirahat', false);
        $response->assertDontSee('Mulai Jam Istirahat (Break)');

        // 2. Sudah absen masuk (belum checkout) -> kartu status kerja & istirahat tampil
        Attendance::query()->create([
            'tenant_id' => $membershipAndi->tenant_id,
            'branch_id' => $membershipAndi->branch_id,
            'membership_id' => $membershipAndi->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHour(),
            'check_out_at' => null,
            'status' => 'present',
        ]);

        $response = $this->actingAs($barberAndi)->get('/barber/attendance');
        $response->assertOk();
        $response->assertSee('Status Jam Kerja &amp; Istirahat', false);
        $response->assertSee('Kursi Anda siap menerima antrean pangkas');
        $response->assertSee('Mulai Jam Istirahat (Break)');

        // 3. Sudah absen pulang (checkout) -> kartu status kerja & istirahat menghilang lagi
        Attendance::query()->where('membership_id', $membershipAndi->id)->whereDate('date', now()->toDateString())->update([
            'check_out_at' => now(),
        ]);

        $response = $this->actingAs($barberAndi)->get('/barber/attendance');
        $response->assertOk();
        $response->assertDontSee('Status Jam Kerja &amp; Istirahat', false);
        $response->assertDontSee('Mulai Jam Istirahat (Break)');
    }
}
