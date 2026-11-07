<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Enums\WorkStatus;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QueueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BarberAttendanceRequiredForQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_barber_without_attendance_cannot_call_or_start_service(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Hapus absensi hari ini milik barber
        Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $barberMembership->id)
            ->delete();

        // Kosongkan tugas aktif
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081288880001'],
            ['name' => 'Customer Belum Absen']
        );

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'sequence' => 950,
            'number' => 'A-950',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Barber coba panggil -> ditolak karena belum absen masuk
        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => 'called',
            'barber_membership_id' => $barberMembership->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('queue');

        $ticket->refresh();
        $this->assertSame(QueueStatus::Waiting, $ticket->status);
    }

    public function test_barber_who_checked_out_cannot_call_queue(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Set absensi hari ini sudah check_out
        Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $barberMembership->id)
            ->delete();

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'check_in_at' => now()->subHours(8),
            'check_out_at' => now()->subMinute(),
            'status' => 'present',
        ]);

        // Kosongkan tugas aktif
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081288880002'],
            ['name' => 'Customer Pulang Shift']
        );

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'sequence' => 951,
            'number' => 'A-951',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => 'called',
            'barber_membership_id' => $barberMembership->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('queue');

        $ticket->refresh();
        $this->assertSame(QueueStatus::Waiting, $ticket->status);
    }

    public function test_barber_with_active_attendance_can_call_and_serve_ticket(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Pastikan absensi aktif (check_in_at ada, check_out_at null)
        Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $barberMembership->id)
            ->delete();

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'check_in_at' => now()->subHours(2),
            'check_out_at' => null,
            'status' => 'present',
        ]);

        // Kosongkan tugas aktif
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081288880003'],
            ['name' => 'Customer Sukses Absen']
        );

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'barber_membership_id' => $barberMembership->id,
            'date' => today()->toDateString(),
            'sequence' => 952,
            'number' => 'A-952',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        $response = $this->actingAs($barberUser)->post(route('cashier.tickets.transition', $ticket), [
            'status' => 'called',
            'barber_membership_id' => $barberMembership->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(QueueStatus::Called, $ticket->status);
    }

    public function test_barber_dashboard_shows_alert_when_attendance_missing(): void
    {
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('user_id', $barberUser->id)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Hapus absensi
        Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $barberMembership->id)
            ->delete();

        // Kosongkan tugas aktif
        QueueTicket::query()
            ->where('barber_membership_id', $barberMembership->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        // Buat tiket menunggu agar kartu giliran berikutnya muncul
        $customer = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '081288880004'],
            ['name' => 'Customer Antre Dashboard']
        );

        QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => today()->toDateString(),
            'sequence' => 953,
            'number' => 'A-953',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        $response = $this->actingAs($barberUser)->get(route('barber.dashboard'));
        $response->assertOk();
        $response->assertSee('Belum Absen Masuk Hari Ini');
        $response->assertSee('Buka Menu Absensi Sekarang');
        $response->assertDontSee('Panggil Antrean Ini');
    }

    public function test_find_ready_barber_ignores_barbers_who_have_not_checked_in(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Kosongkan semua antrean aktif
        QueueTicket::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->update(['status' => QueueStatus::Completed]);

        // Pastikan semua membership barber di cabang ini work_status = Active
        Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', \App\Enums\Role::Barber)
            ->update(['work_status' => WorkStatus::Active]);

        // Hapus SEMUA attendance hari ini
        Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->delete();

        $queueService = app(QueueService::class);
        $readyBarber = $queueService->findReadyBarberForBranch($tenant->id, $branch->id, today()->toDateString());

        // Tidak boleh ada barber yang terpilih jika belum ada yang absen
        $this->assertNull($readyBarber);

        // Jika salah satu barber absen masuk
        $firstBarber = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('role', \App\Enums\Role::Barber)
            ->firstOrFail();

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $firstBarber->id,
            'date' => today()->toDateString(),
            'check_in_at' => now(),
            'check_out_at' => null,
            'status' => 'present',
        ]);

        $readyBarberAfter = $queueService->findReadyBarberForBranch($tenant->id, $branch->id, today()->toDateString());
        $this->assertNotNull($readyBarberAfter);
        $this->assertSame($firstBarber->id, $readyBarberAfter->id);
    }
}
