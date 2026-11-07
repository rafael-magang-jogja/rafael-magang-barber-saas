<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\QueueRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BookingAutoConfirmAnd15MinQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    public function test_booking_payment_proof_upload_auto_confirms_and_issues_queue_ticket_immediately(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = User::query()->where('phone', '081234567890')->first()
            ?? User::factory()->create(['phone' => '081234567890', 'is_superadmin' => false]);
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // 1. Buat booking baru yang dijadwalkan hari ini jam 14:45
        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->customerProfiles()->firstOrCreate(['tenant_id' => $tenant->id, 'phone' => $customer->phone], ['name' => $customer->name])->id,
            'user_id' => $customer->id,
            'code' => 'BK-SDR-TEST',
            'scheduled_at' => Carbon::parse('2026-11-06 14:45:00'),
            'estimated_end_at' => Carbon::parse('2026-11-06 15:15:00'),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
        ]);
        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        $this->assertNull($booking->queueTicket);
        $this->assertEquals(BookingStatus::Pending, $booking->status);

        // 2. Upload bukti transfer
        $fakeProof = UploadedFile::fake()->image('bukti_transfer.jpg');
        $response = $this->actingAs($customer)
            ->post(route('customer.bookings.proof', $booking->id), [
                'payment_proof' => $fakeProof,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $booking->refresh();

        // 3. Status harus otomatis Confirmed/CheckedIn tanpa konfirmasi kasir
        $this->assertNotNull($booking->payment_approved_at);
        $this->assertNotNull($booking->payment_proof_path);
        $this->assertEquals(BookingStatus::CheckedIn, $booking->status);

        // 4. Queue ticket harus langsung terbit dengan nomor B-xxx
        $ticket = $booking->queueTicket;
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('B-', $ticket->number);
        $this->assertEquals('2026-11-06', $ticket->date->toDateString());
        $this->assertEquals(QueueStatus::Waiting, $ticket->status);

        // 5. Order lunas otomatis terbuat
        $this->assertNotNull($ticket->order);
        $this->assertEquals(PaymentStatus::Paid, $ticket->order->payment_status);

        // 6. Halaman detail booking menampilkan nomor antrean di bagian pembayaran reservasi
        $detailResponse = $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Pembayaran Reservasi');
        $detailResponse->assertSee('No. Antrean:');
        $detailResponse->assertSee($ticket->number);
        $detailResponse->assertDontSee('Menunggu Verifikasi Kasir');
    }

    public function test_booking_detail_shows_today_queue_list_if_scheduled_for_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = User::query()->where('phone', '081234567890')->first()
            ?? User::factory()->create(['phone' => '081234567890', 'is_superadmin' => false]);
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Buat antrean walk-in lain yang aktif di cabang hari ini
        $walkinProfile = $customer->customerProfiles()->firstOrCreate(['tenant_id' => $tenant->id, 'phone' => '087711223344'], ['name' => 'Budi Walkin']);
        $walkinTicket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $walkinProfile->id,
            'date' => '2026-11-06',
            'sequence' => 1,
            'number' => 'A-001',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);
        $walkinTicket->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        // Buat booking hari ini yang belum bayar
        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->customerProfiles()->firstOrCreate(['tenant_id' => $tenant->id, 'phone' => $customer->phone], ['name' => $customer->name])->id,
            'user_id' => $customer->id,
            'code' => 'BK-SDR-TODAY',
            'scheduled_at' => Carbon::parse('2026-11-06 15:00:00'),
            'estimated_end_at' => Carbon::parse('2026-11-06 15:30:00'),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
        ]);
        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id));
        $response->assertOk();
        $response->assertSee('Daftar Antrean Hari Ini');
        $response->assertSee('A-001');
        $response->assertSee('Budi Walkin');
    }

    public function test_booking_queue_ticket_only_appears_in_cashier_waiting_board_15_minutes_before_schedule(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = User::query()->where('phone', '081234567890')->first()
            ?? User::factory()->create(['phone' => '081234567890', 'is_superadmin' => false]);
        $profile = $customer->customerProfiles()->firstOrCreate(['tenant_id' => $tenant->id, 'phone' => $customer->phone], ['name' => $customer->name]);

        // 1. Simulasikan jam 14:00 (45 menit sebelum jam booking 14:45)
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00'));

        // Buat tiket walk-in A-001
        $walkinTicket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'date' => '2026-11-06',
            'sequence' => 1,
            'number' => 'A-001',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => Carbon::parse('2026-11-06 14:00:00'),
        ]);

        // Buat tiket booking B-001 yang dijadwalkan jam 14:45
        $bookingTicket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'date' => '2026-11-06',
            'sequence' => 1,
            'number' => 'B-001',
            'type' => 'booking',
            'status' => QueueStatus::Waiting,
            'priority_at' => Carbon::parse('2026-11-06 14:45:00'),
        ]);

        $queueRepo = app(QueueRepository::class);

        // Jam 14:00: Antrean kasir HANYA memuat A-001. B-001 BELUM MUNCUL karena jamnya masih 14:45 (> 15 menit)
        $groupedAt1400 = $queueRepo->getTodayTicketsGroupedByStatus('2026-11-06', $branch->id);
        $waitingTickets1400 = $groupedAt1400->get('waiting', collect());

        $this->assertTrue($waitingTickets1400->contains('number', 'A-001'));
        $this->assertFalse($waitingTickets1400->contains('number', 'B-001'));

        // 2. Majukan waktu ke jam 14:29 (16 menit sebelum 14:45)
        $this->travelTo(Carbon::parse('2026-11-06 14:29:00'));
        $groupedAt1429 = $queueRepo->getTodayTicketsGroupedByStatus('2026-11-06', $branch->id);
        $waitingTickets1429 = $groupedAt1429->get('waiting', collect());
        $this->assertFalse($waitingTickets1429->contains('number', 'B-001'));

        // 3. Majukan waktu ke jam 14:30 (TEPAT 15 menit sebelum 14:45)
        $this->travelTo(Carbon::parse('2026-11-06 14:30:00'));
        $groupedAt1430 = $queueRepo->getTodayTicketsGroupedByStatus('2026-11-06', $branch->id);
        $waitingTickets1430 = $groupedAt1430->get('waiting', collect());

        // Sekarang B-001 SUDAH MUNCUL di antrean kasir!
        $this->assertTrue($waitingTickets1430->contains('number', 'A-001'));
        $this->assertTrue($waitingTickets1430->contains('number', 'B-001'));
    }
}
