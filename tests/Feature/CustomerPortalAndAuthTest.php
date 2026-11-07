<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class CustomerPortalAndAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_page_displays_registration_link(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee(route('register'));
        $response->assertSee('Daftar');
    }

    public function test_customer_can_register_and_is_redirected_to_customer_dashboard(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Budi Santoso',
            'phone' => '081299998888',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'phone' => '081299998888',
            'email' => 'budi@example.com',
            'is_superadmin' => false,
        ]);

        $this->assertAuthenticated();
    }

    public function test_customer_login_redirects_to_neutral_customer_dashboard(): void
    {
        $customer = User::factory()->create([
            'phone' => '081211112222',
            'password' => bcrypt('secret123'),
            'is_superadmin' => false,
        ]);

        $response = $this->post(route('login.store'), [
            'login' => '081211112222',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
    }

    public function test_customer_dashboard_renders_with_neutral_multi_tenant_view(): void
    {
        $customer = User::factory()->create([
            'phone' => '081233334444',
            'is_superadmin' => false,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard Pelanggan');
        $response->assertSee('Buka Kamera Scan QR');
        $response->assertDontSee('Scan dari Foto HP');
        $response->assertSee(route('customer.booking'));
        $response->assertSee(route('customer.history'));
        $response->assertSee(route('customer.profile'));
        $response->assertSee('Pilihan Barbershop Mitra');
    }

    public function test_customer_dashboard_switches_to_scanned_tenant_branch_list(): void
    {
        $customer = User::factory()->create([
            'phone' => '081233334444',
            'is_superadmin' => false,
        ]);

        $tenants = Tenant::query()->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $tenants->count());
        $scannedTenant = $tenants[0];
        $otherTenant = $tenants[1];

        // 1. Simulasikan customer yang telah scan QR code milik $scannedTenant
        $response = $this->actingAs($customer)
            ->withSession([
                'scanned_customer_tenant_id' => $scannedTenant->id,
                'scanned_customer_tenant_slug' => $scannedTenant->slug,
            ])
            ->get(route('customer.dashboard'));

        $response->assertOk();
        // Harus menampilkan daftar cabang dari tenant yang di-scan
        $response->assertSee('Daftar Cabang ' . $scannedTenant->name);
        // Harus melihat cabang milik tenant yang di-scan
        foreach ($scannedTenant->branches()->where('is_active', true)->get() as $branch) {
            $response->assertSee($branch->name);
        }
        // TIDAK BOLEH menampilkan Pilihan Barbershop Mitra (outlet kompetitor)
        $response->assertDontSee('Pilihan Barbershop Mitra');
        $response->assertDontSee($otherTenant->name);
    }

    public function test_customer_dashboard_shows_only_latest_active_ticket(): void
    {
        $customer = User::factory()->create([
            'phone' => '081255556666',
            'is_superadmin' => false,
        ]);

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profile = CustomerProfile::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'date' => today()->toDateString(),
            'sequence' => 1,
            'number' => 'A-099',
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertSee('A-099');
        $response->assertSee('Antrean Aktif Anda');
    }

    public function test_customer_dashboard_shows_active_booking_instead_of_old_history_when_booking_exists(): void
    {
        $customer = User::factory()->create([
            'phone' => '081255557777',
            'is_superadmin' => false,
        ]);

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profile = CustomerProfile::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        // Buat 1 riwayat tiket lama yang sudah selesai
        QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'date' => today()->subDays(2)->toDateString(),
            'sequence' => 1,
            'number' => 'A-001',
            'type' => 'walk_in',
            'status' => QueueStatus::Completed,
            'priority_at' => now()->subDays(2),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        // Buat 1 booking aktif mendatang
        $scheduledAt = now()->addDay()->setHour(14)->setMinute(0);
        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'user_id' => $customer->id,
            'code' => 'BK-TEST-1234',
            'scheduled_at' => $scheduledAt,
            'estimated_end_at' => $scheduledAt->copy()->addMinutes(30),
            'total_duration_minutes' => 30,
            'status' => \App\Enums\BookingStatus::Confirmed,
            'estimated_total' => 50000,
        ]);

        \App\Models\BookingItem::query()->create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => 50000,
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertSee('Reservasi Booking Anda');
        $response->assertSee('BK-TEST-1234');
        $response->assertSee('Buka Detail &amp; Pembayaran Booking', false);
        $response->assertDontSee('Riwayat Kunjungan Terbaru');
    }

    public function test_customer_can_create_booking_from_dedicated_booking_page(): void
    {
        $customer = User::factory()->create([
            'phone' => '081277778888',
            'is_superadmin' => false,
        ]);

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $response = $this->actingAs($customer)->get(route('customer.booking'));
        $response->assertOk();
        $response->assertSee('Reservasi Jadwal Potong');
        $response->assertSee('Konfirmasi &amp; Lanjut Bayar', false);

        $storeResponse = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i'),
            'notes' => 'Tolong rapi samping',
        ]);

        $booking = Booking::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
        $storeResponse->assertRedirect(route('customer.bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $customer->id,
        ]);
    }

    public function test_booking_with_mismatched_branch_fails_gracefully_without_404(): void
    {
        $customer = User::factory()->create([
            'phone' => '081299990000',
            'is_superadmin' => false,
        ]);

        $tenantA = Tenant::query()->firstOrFail();
        $serviceA = Service::query()->where('tenant_id', $tenantA->id)->firstOrFail();

        $tenantB = Tenant::query()->skip(1)->first();
        if (! $tenantB) {
            $tenantB = Tenant::query()->create([
                'name' => 'Barbershop B',
                'slug' => 'barbershop-b',
                'status' => \App\Enums\TenantStatus::Active,
            ]);
            Branch::query()->create([
                'tenant_id' => $tenantB->id,
                'name' => 'Cabang B1',
                'code' => 'CB1',
                'phone' => '081200001111',
                'address' => 'Jl. B1 No. 1',
                'is_active' => true,
            ]);
        }
        $branchB = Branch::query()->where('tenant_id', $tenantB->id)->firstOrFail();

        $response = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenantA->id,
            'branch_id' => $branchB->id,
            'service_ids' => [$serviceA->id],
            'scheduled_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('branch_id');
    }

    public function test_customer_portal_shows_cancel_button_and_does_not_404(): void
    {
        $customer = User::factory()->create([
            'phone' => '081211112222',
            'is_superadmin' => false,
        ]);

        $tenant = Tenant::query()->firstOrFail();

        $response = $this->actingAs($customer)->get(route('customer.portal', $tenant->slug));
        $response->assertOk();
        $response->assertSee('Batal Memesan / Kembali ke Dashboard');
    }

    public function test_history_shows_status_without_background_and_invoice_button_for_completed(): void
    {
        $customer = User::factory()->create([
            'phone' => '081244445555',
            'is_superadmin' => false,
        ]);

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profile = CustomerProfile::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'date' => today()->toDateString(),
            'sequence' => 99,
            'number' => 'A-987',
            'type' => 'walk_in',
            'status' => QueueStatus::Completed,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        $order = \App\Models\Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'queue_ticket_id' => $ticket->id,
            'customer_profile_id' => $profile->id,
            'number' => 'INV-TEST-0001',
            'subtotal' => 50000,
            'discount_total' => 0,
            'tip_amount' => 0,
            'grand_total' => 50000,
            'paid_total' => 50000,
            'refunded_total' => 0,
            'payment_status' => \App\Enums\PaymentStatus::Paid,
            'idempotency_key' => 'idem-test-1',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($customer)->get(route('customer.history'));

        $response->assertOk();
        $response->assertSee('A-987');
        $response->assertSee('Selesai');
        $response->assertSee('Lihat E-Invoice');
        $response->assertSee(route('invoices.show', 'INV-TEST-0001'));
    }

    public function test_customer_can_view_and_update_profile(): void
    {
        $customer = User::factory()->create([
            'name' => 'Nama Lama',
            'phone' => '081288889999',
            'is_superadmin' => false,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.profile'));
        $response->assertOk();
        $response->assertSee('Profil Saya');
        $response->assertSee('data-password-toggle', false);

        $updateResponse = $this->actingAs($customer)->put(route('customer.profile.update'), [
            'name' => 'Nama Baru',
            'phone' => '081288889999',
            'email' => 'namabaru@example.com',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Nama Baru',
            'email' => 'namabaru@example.com',
        ]);
    }

    public function test_owner_can_create_and_update_branch_with_required_phone_address_and_maps_url(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();

        // 1. Validasi gagal jika phone dan address tidak diisi
        $failResponse = $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('owner.branches.store'), [
                'name' => 'Cabang Baru',
                'code' => 'CBR',
                'late_tolerance_minutes' => 15,
            ]);

        $failResponse->assertSessionHasErrors(['phone', 'address']);

        // 2. Berhasil simpan cabang dengan phone, address, dan maps_url
        $successResponse = $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('owner.branches.store'), [
                'name' => 'Cabang Senopati',
                'code' => 'SNP',
                'phone' => '0215550199',
                'address' => 'Jl. Senopati No. 88, Jakarta Selatan',
                'maps_url' => 'https://maps.google.com/?q=-6.229,106.808',
                'late_tolerance_minutes' => 15,
            ]);

        $successResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', [
            'code' => 'SNP',
            'phone' => '0215550199',
            'address' => 'Jl. Senopati No. 88, Jakarta Selatan',
            'maps_url' => 'https://maps.google.com/?q=-6.229,106.808',
        ]);

        $branch = Branch::query()->where('code', 'SNP')->firstOrFail();

        // 3. Owner dapat mengupdate cabang
        $updateBranchResponse = $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('owner.branches.update', $branch), [
                'name' => 'Cabang Senopati Updated',
                'code' => 'SNP',
                'phone' => '0215550999',
                'address' => 'Jl. Senopati No. 99, Jakarta Selatan',
                'maps_url' => 'https://maps.app.goo.gl/xyz123',
                'late_tolerance_minutes' => 20,
            ]);

        $updateBranchResponse->assertRedirect(route('owner.branches'));
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Cabang Senopati Updated',
            'phone' => '0215550999',
            'maps_url' => 'https://maps.app.goo.gl/xyz123',
        ]);

        // 4. Test halaman edit cabang
        $editPageResponse = $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('owner.branches.edit', $branch));
        $editPageResponse->assertOk();
        $editPageResponse->assertSee('Edit Cabang: Cabang Senopati Updated');

        // Hapus cabang uji agar kuota cabang kembali tersedia
        $branch->delete();

        // 5. Test tampilan halaman daftar cabang memuat tombol tambah cabang saat kuota tersedia
        $branchesIndexResponse = $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('owner.branches'));
        $branchesIndexResponse->assertOk();
        $branchesIndexResponse->assertSee(route('owner.branches.create'));

        // 6. Test halaman form tambah cabang baru
        $createPageResponse = $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('owner.branches.create'));
        $createPageResponse->assertOk();
        $createPageResponse->assertSee('Nama Cabang');
        $createPageResponse->assertSee('Kode Cabang (Prefix)');
        $createPageResponse->assertSee('Toleransi Telat Booking (Menit)');
        $editPageResponse->assertSee('Status Operasional Cabang');
    }

    public function test_customer_portal_qr_loads_branch_specific_barbers_without_collision(): void
    {
        $customer = User::factory()->create(['phone' => '081277771234']);
        $tenant = Tenant::query()->firstOrFail();
        $branchSdr = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'SDR')->firstOrFail();
        $branchKmg = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'KMG')->firstOrFail();

        // 1. Scan QR TV Kemang (/b/barber-king?branch=KMG)
        $responseKmg = $this->actingAs($customer)->get(route('customer.portal', [
            'tenant' => $tenant->slug,
            'branch' => 'KMG',
        ]));

        $responseKmg->assertOk();
        $responseKmg->assertSee('Cabang Kemang');
        $responseKmg->assertSee('Eko');
        $responseKmg->assertDontSee('Andi');

        // 2. Scan QR TV Sudirman (/b/barber-king?branch=SDR)
        $responseSdr = $this->actingAs($customer)->get(route('customer.portal', [
            'tenant' => $tenant->slug,
            'branch' => 'SDR',
        ]));

        $responseSdr->assertOk();
        $responseSdr->assertSee('Cabang Sudirman');
        $responseSdr->assertSee('Andi');
        $responseSdr->assertDontSee('Eko');
    }

    public function test_customer_cannot_assign_barber_from_another_branch(): void
    {
        $customer = User::factory()->create(['phone' => '081277775678']);
        $tenant = Tenant::query()->firstOrFail();
        $branchKmg = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'KMG')->firstOrFail();
        $barberSdr = Membership::query()->where('tenant_id', $tenant->id)->where('display_name', 'Andi')->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $response = $this->actingAs($customer)->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branchKmg->id,
            'customer_name' => 'Budi',
            'customer_phone' => '081299998888',
            'service_ids' => [$service->id],
            'barber_membership_id' => $barberSdr->id,
        ]);

        $response->assertSessionHasErrors(['queue']);
    }

    public function test_customer_new_queue_and_booking_redirect_to_dedicated_detail_pages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 14:00:00'));
        $customer = User::factory()->create(['phone' => '081233445566']);
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', \App\Enums\Role::Barber)->firstOrFail();

        \App\Models\Attendance::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'membership_id' => $barber->id,
                'date' => Carbon::now()->toDateString(),
            ],
            [
                'status' => 'present',
                'check_in_at' => Carbon::now()->subHours(2),
                'check_out_at' => null,
            ]
        );

        // 1. Ambil Antrean Baru -> redirect ke customer.tickets.show
        $queueResponse = $this->actingAs($customer)->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Doni Customer',
            'customer_phone' => '081233445566',
            'service_ids' => [$service->id],
            'barber_membership_id' => $barber->id,
            'notes' => 'Fade tipis samping',
        ]);

        $ticket = QueueTicket::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $queueResponse->assertRedirect(route('customer.tickets.show', $ticket->id));

        // 2. Buka Halaman Detail Antrean -> pastikan menampilkan detail layanan, harga, dan nama staff
        $ticketView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $ticketView->assertOk();
        $ticketView->assertSee($ticket->number);
        $ticketView->assertSee($service->name);
        $ticketView->assertSee($barber->display_name);
        $ticketView->assertSee('Fade tipis samping');
        $ticketView->assertSee(number_format($service->price, 0, ',', '.'));
    }

    public function test_ticket_detail_shows_selesai_button_after_staff_completes_and_allows_rating_and_payment(): void
    {
        $customer = User::factory()->create(['phone' => '081244556677']);
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', \App\Enums\Role::Barber)->firstOrFail();

        $profile = CustomerProfile::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profile->id,
            'barber_membership_id' => $barber->id,
            'date' => today()->toDateString(),
            'sequence' => 88,
            'number' => 'A-888',
            'type' => 'walk_in',
            'status' => QueueStatus::InService,
            'priority_at' => now(),
            'estimated_duration_minutes' => 30,
            'version' => 1,
        ]);

        \App\Models\QueueTicketItem::query()->create([
            'queue_ticket_id' => $ticket->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        // A. Saat belum bayar: tombol pembayaran di muka muncul
        $unpaidView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $unpaidView->assertOk();
        $unpaidView->assertSee('Bayar Sekarang');
        $unpaidView->assertSee(route('customer.tickets.checkout', $ticket->id));

        // A2. Saat sudah dibuat order unpaid (menunggu konfirmasi), halaman tetap ok dan mengarahkan ke waiting-payment
        $order = \App\Models\Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'queue_ticket_id' => $ticket->id,
            'number' => 'ORD-TEST-999',
            'idempotency_key' => 'IDEM-TEST-999',
            'subtotal' => 50000,
            'grand_total' => 50000,
            'payment_status' => \App\Enums\PaymentStatus::Unpaid,
        ]);
        $ticketWithOrderView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $ticketWithOrderView->assertOk();
        $ticketWithOrderView->assertSee(route('customer.tickets.waiting-payment', $ticket->id));
        $order->delete();

        // B. Staff dapat menyelesaikan cukur
        $ticket->update(['status' => QueueStatus::Completed, 'completed_at' => now()]);

        // C. Customer melihat halaman: tombol bayar tetap ada jika belum lunas
        $completedView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $completedView->assertOk();
        $completedView->assertSee('Bayar Sekarang');
        $completedView->assertSee(route('customer.tickets.checkout', $ticket->id));

        // D. Customer membuka halaman checkout & rating
        $checkoutView = $this->actingAs($customer)->get(route('customer.tickets.checkout', $ticket->id));
        $checkoutView->assertOk();
        $checkoutView->assertSee('Berikan Penilaian untuk Staff');
        $checkoutView->assertSee($barber->display_name);
        $checkoutView->assertSee('Pilih Metode Pembayaran');

        // E. Customer kirim ulasan bintang 5 dan pilih QRIS (menunggu konfirmasi kasir)
        $processResponse = $this->actingAs($customer)->post(route('customer.tickets.process-checkout', $ticket->id), [
            'rating' => 5,
            'comment' => 'Potongan sangat rapi dan mantap!',
            'payment_method' => 'qris',
        ]);

        $order = \App\Models\Order::query()->where('queue_ticket_id', $ticket->id)->firstOrFail();
        $processResponse->assertRedirect(route('customer.tickets.waiting-payment', $ticket->id));

        // Halaman menunggu konfirmasi kasir menampilkan status
        $waitingView = $this->actingAs($customer)->get(route('customer.tickets.waiting-payment', $ticket->id));
        $waitingView->assertOk();
        $waitingView->assertSee('Menunggu Konfirmasi Kasir');

        // Order masih unpaid sebelum dikonfirmasi kasir
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => \App\Enums\PaymentStatus::Unpaid,
        ]);

        // Review telah tersimpan
        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'barber_membership_id' => $barber->id,
            'rating' => 5,
            'comment' => 'Potongan sangat rapi dan mantap!',
        ]);

        // F. Kasir mengonfirmasi bahwa pembayaran QRIS telah masuk
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $confirmResponse = $this->actingAs($cashierUser)->post(route('cashier.tickets.confirm-payment', $ticket->id));
        $confirmResponse->assertRedirect();

        // G. Verifikasi order lunas dan customer langsung diarahkan ke e-invoice
        $order->refresh();
        $this->assertSame(\App\Enums\PaymentStatus::Paid, $order->payment_status);

        $customerInvoiceRedirect = $this->actingAs($customer)->get(route('customer.tickets.waiting-payment', $ticket->id));
        $customerInvoiceRedirect->assertRedirect(route('invoices.show', $order->number));
    }

    public function test_pre_payment_does_not_create_premature_review_until_service_completed(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barber = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('role', 'barber')
            ->firstOrFail();

        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = User::factory()->create(['phone' => '087812349999']);

        $customerProfile = CustomerProfile::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'name' => $customer->name,
            'phone' => '087812349999',
        ]);

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customerProfile->id,
            'barber_membership_id' => $barber->id,
            'sequence' => 99,
            'type' => 'walk_in',
            'priority_at' => now(),
            'number' => 'A-099',
            'status' => QueueStatus::Waiting,
            'date' => now()->toDateString(),
        ]);

        \App\Models\QueueTicketItem::query()->create([
            'queue_ticket_id' => $ticket->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        // 1. Saat antrean masih Waiting (bayar di muka), halaman checkout tidak meminta bintang
        $checkoutView = $this->actingAs($customer)->get(route('customer.tickets.checkout', $ticket->id));
        $checkoutView->assertOk();
        $checkoutView->assertSee('Catatan Penilaian');
        $checkoutView->assertDontSee('Bagaimana pelayanan dari');

        // 2. Customer membayar di muka (pilih cash)
        $processResponse = $this->actingAs($customer)->post(route('customer.tickets.process-checkout', $ticket->id), [
            'payment_method' => 'cash',
        ]);
        $processResponse->assertRedirect(route('customer.tickets.waiting-payment', $ticket->id));

        // Order terbuat, tapi review BELUM boleh ada
        $order = \App\Models\Order::query()->where('queue_ticket_id', $ticket->id)->firstOrFail();
        $this->assertDatabaseMissing('reviews', [
            'order_id' => $order->id,
        ]);

        // 3. Kasir konfirmasi pembayaran lunas
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $this->actingAs($cashierUser)->post(route('cashier.tickets.confirm-payment', $ticket->id));
        $order->refresh();
        $this->assertSame(\App\Enums\PaymentStatus::Paid, $order->payment_status);

        // 4. Tiket masih Waiting (belum selesai dicukur), detail tiket TIDAK boleh menampilkan kartu review
        $ticketView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $ticketView->assertOk();
        $ticketView->assertDontSee('Penilaian Anda untuk Staff');
        $ticketView->assertDontSee('Beri Penilaian Layanan Staff');

        // Mencoba submit review saat belum selesai juga harus dicegah
        $prematureReviewResponse = $this->actingAs($customer)->post(route('customer.tickets.review', $ticket->id), [
            'rating' => 5,
            'comment' => 'Harusnya belum bisa nilai',
        ]);
        $prematureReviewResponse->assertSessionHasErrors(['review']);

        // 5. Staff baru saja menyelesaikan cukur (status -> Completed)
        $ticket->update(['status' => QueueStatus::Completed, 'completed_at' => now()]);

        // 6. Sekarang customer melihat tiketnya dan muncul form penilaian staff
        $completedTicketView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $completedTicketView->assertOk();
        $completedTicketView->assertSee('Beri Penilaian Layanan Staff');

        // 7. Customer memberikan rating 5 bintang setelah selesai -> tetap berada di halaman detail tiket
        $reviewSubmitResponse = $this->actingAs($customer)
            ->from(route('customer.tickets.show', $ticket->id))
            ->post(route('customer.tickets.review', $ticket->id), [
                'rating' => 5,
                'comment' => 'Pelayanan ramah dan hasil potongan sangat presisi!',
            ]);
        $reviewSubmitResponse->assertRedirect(route('customer.tickets.show', $ticket->id));

        // 8. Review sudah tersimpan dan kartu ulasan muncul di detail tiket
        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'rating' => 5,
        ]);

        $finalTicketView = $this->actingAs($customer)->get(route('customer.tickets.show', $ticket->id));
        $finalTicketView->assertSee('Penilaian Anda untuk Staff');
        $finalTicketView->assertSee('5 / 5');
    }

    public function test_guest_can_access_tenant_portal_directly_without_login(): void
    {
        $tenant = Tenant::query()->firstOrFail();

        $response = $this->get(route('customer.portal', $tenant->slug));

        $response->assertOk();
        $response->assertSee($tenant->name);
        $response->assertSee('Ambil Nomor Antrean Sekarang');
        $response->assertSee('Booking Jadwal');
        $response->assertSee("Punya akun pelanggan di {$tenant->name}?");
        $response->assertSee(route('tenant.login', $tenant->slug));
    }

    public function test_guest_sees_tenant_branded_login_and_register_pages(): void
    {
        $tenant = Tenant::query()->firstOrFail();

        $loginResponse = $this->get(route('tenant.login', $tenant->slug));
        $loginResponse->assertOk();
        $loginResponse->assertSee("Masuk ke {$tenant->name}");
        $loginResponse->assertSee(route('tenant.register', $tenant->slug));
        $loginResponse->assertSee(route('tenant.login.store', $tenant->slug));

        $registerResponse = $this->get(route('tenant.register', $tenant->slug));
        $registerResponse->assertOk();
        $registerResponse->assertSee("Daftar Akun di {$tenant->name}");
        $registerResponse->assertSee(route('tenant.login', $tenant->slug));
        $registerResponse->assertSee(route('tenant.register.store', $tenant->slug));
    }

    public function test_guest_can_register_via_tenant_register_and_redirects_to_tenant_portal(): void
    {
        $tenant = Tenant::query()->firstOrFail();

        $response = $this->post(route('tenant.register.store', $tenant->slug), [
            'name' => 'Doni Darmawan',
            'phone' => '081288887777',
            'email' => 'doni@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('customer.portal', $tenant->slug));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Doni Darmawan',
            'phone' => '081288887777',
        ]);
    }

    public function test_customer_can_login_via_tenant_login_and_redirects_to_tenant_portal(): void
    {
        $tenant = Tenant::query()->firstOrFail();

        User::factory()->create([
            'phone' => '081277776666',
            'password' => bcrypt('password123'),
            'is_superadmin' => false,
        ]);

        $response = $this->post(route('tenant.login.store', $tenant->slug), [
            'login' => '081277776666',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('customer.portal', $tenant->slug));
        $this->assertAuthenticated();
        /** @var \Illuminate\Auth\SessionGuard $guard */
        $guard = Auth::guard();
        $response->assertCookie($guard->getRecallerName());
    }

    public function test_staff_logging_in_via_tenant_login_is_safely_redirected_to_staff_dashboard(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $ownerUser = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->post(route('tenant.login.store', $tenant->slug), [
            'login' => 'owner@barberking.id',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
    }

    public function test_guest_without_login_is_redirected_to_login_when_attempting_booking(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $response = $this->post(route('customer.tenant-booking.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Tamu Booking',
            'customer_phone' => '081399998888',
            'service_ids' => [$service->id],
            'scheduled_at' => now()->addDay()->setTime(15, 30)->format('Y-m-d\TH:i'),
            'notes' => 'Tamu tanpa login',
        ]);

        $response->assertRedirect($tenant->loginUrl());
    }

    public function test_tenant_brand_color_dynamically_overrides_system_amber_theme(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['brand_color' => '#10B981']);

        // 1. Pada portal customer, warna brand owner meng-override palet tema
        $portalResponse = $this->get(route('customer.portal', $tenant->slug));
        $portalResponse->assertOk();
        $portalResponse->assertSee('--tenant-brand: #10B981', false);
        $portalResponse->assertSee('--color-amber-500: var(--tenant-brand)', false);

        // 2. Pada halaman login khusus tenant
        $loginResponse = $this->get(route('tenant.login', $tenant->slug));
        $loginResponse->assertOk();
        $loginResponse->assertSee('--tenant-brand: #10B981', false);
        $loginResponse->assertSee('--color-amber-500: var(--tenant-brand)', false);

        // 3. Pada dashboard owner saat mengelola barbershop miliknya
        $ownerUser = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $ownerResponse = $this->actingAs($ownerUser)->get(route('owner.dashboard'));
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('--tenant-brand: #10B981', false);
        $ownerResponse->assertSee('--color-amber-500: var(--tenant-brand)', false);
    }

    public function test_unauthorized_customer_cannot_view_another_customer_ticket(): void
    {
        $victim = User::factory()->create(['phone' => '081299990001']);
        $attacker = User::factory()->create(['phone' => '081299990002']);

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($victim)->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Victim Customer',
            'customer_phone' => '081299990001',
            'service_ids' => [$service->id],
        ]);

        $ticket = QueueTicket::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();

        // Attacker mencoba mengakses detail tiket milik victim -> 403 Forbidden
        $response = $this->actingAs($attacker)->get(route('customer.tickets.show', $ticket->id));
        $response->assertForbidden();

        // Tamu anonim tanpa session tiket juga ditolak -> 403 Forbidden
        $guestResponse = $this->get(route('customer.tickets.show', $ticket->id));
        $guestResponse->assertForbidden();
    }

    public function test_unauthorized_customer_cannot_view_another_customer_booking(): void
    {
        $victim = User::factory()->create(['phone' => '081299990003']);
        $attacker = User::factory()->create(['phone' => '081299990004']);

        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($victim)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d H:i:s'),
        ]);

        $booking = Booking::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();

        // Attacker mencoba mengakses detail booking milik victim -> 403 Forbidden
        $response = $this->actingAs($attacker)->get(route('customer.bookings.show', $booking->id));
        $response->assertForbidden();

        // Tamu anonim tanpa session booking juga ditolak -> 403 Forbidden
        $guestResponse = $this->get(route('customer.bookings.show', $booking->id));
        $guestResponse->assertForbidden();
    }

    public function test_customer_cannot_take_queue_outside_branch_operational_hours(): void
    {
        $customer = User::factory()->create(['phone' => '081233445577']);
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // 1. Uji saat jam subuh (belum buka)
        Carbon::setTestNow(Carbon::parse('2026-11-04 04:00:00'));
        $earlyResponse = $this->actingAs($customer)->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Subuh Customer',
            'customer_phone' => '081233445577',
            'service_ids' => [$service->id],
        ]);
        $earlyResponse->assertSessionHasErrors(['queue']);

        // 2. Uji saat malam (sudah tutup)
        Carbon::setTestNow(Carbon::parse('2026-11-04 23:30:00'));
        $lateResponse = $this->actingAs($customer)->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Malam Customer',
            'customer_phone' => '081233445577',
            'service_ids' => [$service->id],
        ]);
        $lateResponse->assertSessionHasErrors(['queue']);

        Carbon::setTestNow();
    }

    public function test_booking_payment_proof_upload_and_cashier_approval_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00'));
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = User::factory()->create(['phone' => '081299998877']);
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // 1. Buat booking untuk jam 14:00
        $bookingResponse = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_at' => '2026-11-04 14:00:00',
        ]);

        $booking = Booking::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $bookingResponse->assertRedirect(route('customer.bookings.show', $booking));

        // 2. Customer membuka halaman detail booking: melihat kartu pembayaran QRIS resmi
        $detailView = $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id));
        $detailView->assertOk();
        $detailView->assertSee('Pembayaran Reservasi');
        $detailView->assertSee('QRIS');

        // 3. Customer mengunggah bukti transfer struk pembayaran
        $file = \Illuminate\Http\UploadedFile::fake()->image('bukti_transfer.jpg', 600, 600);
        $uploadResponse = $this->actingAs($customer)->post(route('customer.bookings.proof', $booking->id), [
            'payment_proof' => $file,
        ]);
        $uploadResponse->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertNotNull($booking->payment_proof_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($booking->payment_proof_path);

        // 4. Kasir / Owner memeriksa dan menyetujui (Approve) bukti pembayaran booking
        $ownerUser = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $approveResponse = $this->actingAs($ownerUser)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('cashier.bookings.approve-payment', $booking->id));
        $approveResponse->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertNotNull($booking->payment_approved_at);
        $this->assertNotNull($booking->queueTicket);

        // Tiket antrean langsung terbit sesuai jadwal jam 14:00 (priority_at) dan order lunas
        $ticket = $booking->queueTicket;
        $this->assertSame('booking', $ticket->type);
        $this->assertSame('2026-11-04 14:00:00', $ticket->priority_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($ticket->order);
        $this->assertSame(\App\Enums\PaymentStatus::Paid, $ticket->order->payment_status);

        // 5. Customer melihat status di booking detail telah lunas & nomor antrean terbit
        $verifiedView = $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id));
        $verifiedView->assertOk();
        $verifiedView->assertSee('Antrean Terdaftar di Sistem');
        $verifiedView->assertSee($ticket->number);

        Carbon::setTestNow();
    }

    public function test_customer_can_cancel_their_own_active_queue_ticket(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 14:00:00'));
        $customer = User::factory()->create(['phone' => '081233445588']);
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $queueResponse = $this->actingAs($customer)->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Cancel Queue Customer',
            'customer_phone' => '081233445588',
            'service_ids' => [$service->id],
        ]);

        $ticket = QueueTicket::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $queueResponse->assertRedirect(route('customer.tickets.show', $ticket->id));

        // Pelanggan membatalkan antrean
        $cancelResponse = $this->actingAs($customer)->post(route('customer.tickets.cancel', $ticket->id), [
            'reason' => 'Ada acara mendadak',
        ]);
        $cancelResponse->assertSessionHasNoErrors();
        $this->assertEquals(\App\Enums\QueueStatus::Cancelled, $ticket->fresh()->status);
        $this->assertStringContainsString('Ada acara mendadak', (string) $ticket->fresh()->notes);

        Carbon::setTestNow();
    }
}
