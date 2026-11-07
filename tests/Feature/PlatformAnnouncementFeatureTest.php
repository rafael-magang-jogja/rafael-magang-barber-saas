<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\PlatformAnnouncementPopup;
use App\Models\PlatformAnnouncement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformAnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class PlatformAnnouncementFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $owner;
    private User $cashier;
    private User $barber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superadmin = User::query()->where('is_superadmin', true)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $this->owner = $tenant->owner;
        $this->cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $this->barber = User::query()->where('email', 'bima@barberking.id')->firstOrFail();
    }

    public function test_superadmin_can_view_and_create_announcement(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('platform.announcements'));
        $response->assertOk();
        $response->assertSee('Siarkan Pengumuman Baru');
        $response->assertSee('Riwayat Log Siaran Pengumuman');

        $storeResponse = $this->actingAs($this->superadmin)->post(route('platform.announcements.store'), [
            'title' => 'Pemeliharaan Server Berkala',
            'message' => 'Server akan dilakukan pembaruan sistem pada pukul 02:00 WIB.',
            'type' => 'maintenance',
            'target_role' => 'owner',
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('status');

        $this->assertDatabaseHas('platform_announcements', [
            'title' => 'Pemeliharaan Server Berkala',
            'type' => 'maintenance',
            'target_role' => 'owner',
            'is_active' => true,
        ]);

        // Simulasikan pembuatan pengumuman dengan checkbox 'Langsung aktifkan siaran' TIDAK dicentang
        $inactiveResponse = $this->actingAs($this->superadmin)->post(route('platform.announcements.store'), [
            'title' => 'Draft Pengumuman',
            'message' => 'Pesan masih berupa draft dan belum aktif.',
            'type' => 'info',
            'target_role' => 'all',
            // Tanpa field is_active (karena checkbox uncheck tidak dikirim browser)
        ]);

        $inactiveResponse->assertRedirect();
        $this->assertDatabaseHas('platform_announcements', [
            'title' => 'Draft Pengumuman',
            'is_active' => false,
        ]);
    }

    public function test_superadmin_can_toggle_and_delete_announcement(): void
    {
        $announcement = PlatformAnnouncement::query()->create([
            'title' => 'Fitur Baru Rilis',
            'message' => 'Telah hadir fitur cetak slip gaji.',
            'type' => 'info',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        // Toggle nonaktif
        $this->actingAs($this->superadmin)->patch(route('platform.announcements.toggle', $announcement))
            ->assertRedirect();
        $this->assertFalse($announcement->fresh()->is_active);

        // Delete
        $this->actingAs($this->superadmin)->delete(route('platform.announcements.destroy', $announcement))
            ->assertRedirect();
        $this->assertDatabaseMissing('platform_announcements', ['id' => $announcement->id]);
    }

    public function test_announcement_target_filtering_works_correctly(): void
    {
        $service = app(PlatformAnnouncementService::class);

        // Pengumuman khusus Owner
        $ownerOnly = PlatformAnnouncement::query()->create([
            'title' => 'Pesan Khusus Owner',
            'message' => 'Laporan bulanan telah tersedia.',
            'type' => 'info',
            'target_role' => 'owner',
            'is_active' => true,
        ]);

        // Owner dapat melihat pengumuman
        $pendingForOwner = $service->getPendingAnnouncementForUser($this->owner, 'owner');
        $this->assertNotNull($pendingForOwner);
        $this->assertEquals($ownerOnly->id, $pendingForOwner->id);

        // Barber TIDAK dapat melihat pengumuman khusus owner
        $pendingForBarber = $service->getPendingAnnouncementForUser($this->barber, 'barber');
        $this->assertNull($pendingForBarber);

        // Kasir TIDAK dapat melihat pengumuman khusus owner
        $pendingForCashier = $service->getPendingAnnouncementForUser($this->cashier, 'admin');
        $this->assertNull($pendingForCashier);
    }

    public function test_announcement_is_shown_once_per_session(): void
    {
        $service = app(PlatformAnnouncementService::class);

        $announcement = PlatformAnnouncement::query()->create([
            'title' => 'Pengumuman Global',
            'message' => 'Informasi penting untuk semua pengguna.',
            'type' => 'warning',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        // Panggilan pertama: Ada pengumuman yang harus tampil
        $pending = $service->getPendingAnnouncementForUser($this->owner, 'owner');
        $this->assertNotNull($pending);

        // User menutup popup tanpa mencentang 'Jangan tampilkan lagi' (permanently = false)
        $this->actingAs($this->owner)->postJson(route('platform-announcements.dismiss', $announcement), [
            'permanently' => false,
        ])->assertOk();

        // Dalam sesi yang sama, pengumuman TIDAK BOLEH tampil lagi
        $pendingSameSession = $service->getPendingAnnouncementForUser($this->owner, 'owner');
        $this->assertNull($pendingSameSession);

        // Belum di-dismiss permanen di database
        $this->assertDatabaseHas('platform_announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->owner->id,
            'dismissed_permanently' => false,
        ]);
    }

    public function test_announcement_with_permanent_dismiss_never_shown_again(): void
    {
        $service = app(PlatformAnnouncementService::class);

        $announcement = PlatformAnnouncement::query()->create([
            'title' => 'Pengumuman Penting',
            'message' => 'Pesan satu kali baca.',
            'type' => 'urgent',
            'target_role' => 'all',
            'is_active' => true,
        ]);

        // User menutup dengan checklist 'Jangan tampilkan lagi' (permanently = true)
        $this->actingAs($this->owner)->postJson(route('platform-announcements.dismiss', $announcement), [
            'permanently' => true,
        ])->assertOk();

        $this->assertDatabaseHas('platform_announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->owner->id,
            'dismissed_permanently' => true,
        ]);

        // Bersihkan sesi untuk mensimulasikan login di sesi baru / perangkat lain
        session()->forget('shown_announcements');

        // Pengumuman TETAP TIDAK AKAN MUNCUL karena sudah dicentang jangan tampilkan lagi
        $pendingNewSession = $service->getPendingAnnouncementForUser($this->owner, 'owner');
        $this->assertNull($pendingNewSession);
    }

    public function test_livewire_popup_polls_every_5s_and_dismisses_smoothly(): void
    {
        $announcement = PlatformAnnouncement::query()->create([
            'title' => 'Pembaruan Fitur Livewire',
            'message' => 'Popup sekarang real-time dengan Livewire polling 5 detik.',
            'type' => 'info',
            'target_role' => 'owner',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->owner)
            ->test(PlatformAnnouncementPopup::class, ['role' => 'owner'])
            ->assertSeeHtml('wire:poll.5s')
            ->assertSee('Pembaruan Fitur Livewire')
            ->assertSee('Popup sekarang real-time dengan Livewire polling 5 detik.')
            ->set('permanently', true)
            ->call('dismissWithCheckbox')
            ->assertDontSee('Pembaruan Fitur Livewire');

        $this->assertDatabaseHas('platform_announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->owner->id,
            'dismissed_permanently' => true,
        ]);
    }

    public function test_announcement_with_today_only_duration_expires_automatically_tomorrow(): void
    {
        $service = app(PlatformAnnouncementService::class);

        // Superadmin buat pengumuman dengan durasi hari ini saja
        $storeResponse = $this->actingAs($this->superadmin)->post(route('platform.announcements.store'), [
            'title' => 'Promo Flash Hari Ini',
            'message' => 'Hanya berlaku hari ini saja sampai 23:59 WIB.',
            'type' => 'info',
            'target_role' => 'all',
            'duration_type' => 'today_only',
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect();
        $announcement = PlatformAnnouncement::query()->where('title', 'Promo Flash Hari Ini')->firstOrFail();

        $this->assertNotNull($announcement->ends_at);
        $this->assertFalse($announcement->isExpired());
        $this->assertSame('Hari Ini Saja (s/d 23:59 WIB)', $announcement->durationLabel());

        // Hari ini pengumuman aktif dan berhak diterima user
        $pendingToday = $service->getPendingAnnouncementForUser($this->owner, 'owner');
        $this->assertNotNull($pendingToday);
        $this->assertSame($announcement->id, $pendingToday->id);

        // Mensimulasikan pergantian hari ke besok
        $this->travel(1)->days();

        // Di hari berikutnya, pengumuman otomatis kedaluwarsa dan tidak muncul lagi ke user manapun
        $this->assertTrue($announcement->fresh()->isExpired());
        $pendingTomorrow = $service->getPendingAnnouncementForUser($this->owner, 'owner');
        $this->assertNull($pendingTomorrow);

        // Kembalikan waktu
        $this->travelBack();
    }
}
