<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BarberTimeOff;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TimeOffApprovalAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_owner_can_approve_time_off_without_class_not_found_error(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('role', 'barber')->firstOrFail();

        $timeOff = BarberTimeOff::query()->create([
            'tenant_id' => $barberMembership->tenant_id,
            'membership_id' => $barberMembership->id,
            'type' => 'sick',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'reason' => 'Demam tinggi flu',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->post(route('owner.time-offs.approve', $timeOff));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertEquals('approved', $timeOff->fresh()->status);

        // Pastikan record attendance terbuat sesuai tipe izin
        $this->assertDatabaseHas('attendances', [
            'tenant_id' => $barberMembership->tenant_id,
            'membership_id' => $barberMembership->id,
            'status' => 'sick',
        ]);
        $this->assertTrue(
            \App\Models\Attendance::query()->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembership->id)
                ->whereDate('date', now()->toDateString())
                ->where('status', 'sick')
                ->exists()
        );
    }

    public function test_owner_can_reject_time_off_with_notes(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $barberMembership = Membership::query()->where('role', 'barber')->firstOrFail();

        $timeOff = BarberTimeOff::query()->create([
            'tenant_id' => $barberMembership->tenant_id,
            'membership_id' => $barberMembership->id,
            'type' => 'leave',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'reason' => 'Keperluan keluarga',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->post(route('owner.time-offs.reject', $timeOff), [
            'notes' => 'Antrean hari ini sedang ramai.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $fresh = $timeOff->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertEquals('Antrean hari ini sedang ramai.', $fresh->notes);
    }

    public function test_owner_time_offs_page_renders_approval_and_rejection_popups(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->actingAs($owner)->get(route('owner.time-offs'));

        $response->assertOk();
        $response->assertSee('modal-approve-time-off', false);
        $response->assertSee('modal-reject-time-off', false);
        $response->assertSee('openApproveModal', false);
        $response->assertSee('openRejectModal', false);
    }

    public function test_cashier_attendance_page_does_not_have_duplicate_flash_alert(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)
            ->withSession(['status' => 'Permohonan izin berhasil diajukan'])
            ->get('/cashier/attendance');

        $response->assertOk();
        // Pastikan pesan hanya muncul 1 kali di halaman
        $content = $response->getContent();
        $count = substr_count($content, 'Permohonan izin berhasil diajukan');
        $this->assertEquals(1, $count, 'Flash alert should only be rendered once.');
    }

    public function test_owner_attendances_renders_indonesian_status_labels(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->actingAs($owner)->get(route('owner.attendances'));

        $response->assertOk();
        $response->assertDontSee('>Sick<', false);
    }
}
