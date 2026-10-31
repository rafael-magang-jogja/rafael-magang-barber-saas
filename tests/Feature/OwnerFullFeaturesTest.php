<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Models\CustomerProfile;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OwnerFullFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $this->tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
    }

    public function test_owner_sidebar_renders_all_eleven_menus(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Antrean & Booking');
        $response->assertSee('Transaksi');
        $response->assertSee('Layanan & Harga');
        $response->assertSee('Cabang');
        $response->assertSee('Karyawan');
        $response->assertSee('Gaji & Komisi');
        $response->assertSee('Jadwal & Absensi');
        $response->assertSee('Customer');
        $response->assertSee('Laporan');
        $response->assertSee('Pengaturan Barbershop');
    }

    public function test_owner_can_view_transactions_page(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.transactions'));

        $response->assertOk();
        $response->assertSee('Transaksi & Riwayat Pembayaran');
        $response->assertSee('Total Omzet Lunas');
        $response->assertSee('Daftar Transaksi Pembayaran');
    }

    public function test_owner_can_view_and_manage_payroll(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.payroll'));

        $response->assertOk();
        $response->assertSee('Gaji & Komisi');

        // Test generate payroll baru
        $generateResponse = $this->actingAs($this->owner)->post(route('owner.payroll.generate'), [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        $generateResponse->assertSessionHas('status');

        $payroll = Payroll::query()->latest('id')->firstOrFail();

        // Test approve payroll
        $approveResponse = $this->actingAs($this->owner)->post(route('owner.payroll.approve', $payroll));
        $approveResponse->assertSessionHas('status');
        $this->assertEquals(PayrollStatus::Approved, $payroll->fresh()->status);

        // Test mark paid
        $paidResponse = $this->actingAs($this->owner)->post(route('owner.payroll.mark-paid', $payroll));
        $paidResponse->assertSessionHas('status');
        $this->assertEquals(PayrollStatus::Paid, $payroll->fresh()->status);
    }

    public function test_owner_can_view_and_manage_schedules_and_attendances(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.schedules'));

        $response->assertOk();
        $response->assertSee('Jadwal Shift Kerja Barber');
        $response->assertSee('Matriks Jadwal Shift');

        $barber = \App\Models\Membership::query()
            ->where('role', \App\Enums\Role::Barber)
            ->firstOrFail();

        // Test halaman edit schedule per staff
        $editResponse = $this->actingAs($this->owner)->get(route('owner.schedules.edit', $barber));
        $editResponse->assertOk();
        $editResponse->assertSee('Atur Jadwal Shift &amp; Istirahat', false);
        $editResponse->assertSee($barber->display_name ?? $barber->user->name);

        // Test simpan schedule per staff
        $updateResponse = $this->actingAs($this->owner)->put(route('owner.schedules.update', $barber), [
            'branch_id' => $barber->branch_id ?? 1,
            'days' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'break_start' => '12:00',
                    'break_end' => '13:00',
                    'is_off' => 0,
                ],
                [
                    'day_of_week' => 2,
                    'start_time' => '10:00',
                    'end_time' => '21:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00',
                    'is_off' => 0,
                ],
                [
                    'day_of_week' => 3,
                    'start_time' => '10:00',
                    'end_time' => '21:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00',
                    'is_off' => 0,
                ],
                [
                    'day_of_week' => 4,
                    'start_time' => '10:00',
                    'end_time' => '21:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00',
                    'is_off' => 0,
                ],
                [
                    'day_of_week' => 5,
                    'start_time' => '10:00',
                    'end_time' => '21:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00',
                    'is_off' => 0,
                ],
                [
                    'day_of_week' => 6,
                    'start_time' => '10:00',
                    'end_time' => '21:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00',
                    'is_off' => 0,
                ],
                [
                    'day_of_week' => 0,
                    'start_time' => '10:00',
                    'end_time' => '21:00',
                    'break_start' => null,
                    'break_end' => null,
                    'is_off' => 1,
                ],
            ],
        ]);

        $updateResponse->assertRedirect(route('owner.schedules'));
        $updateResponse->assertSessionHas('status');
    }

    public function test_owner_can_view_and_manage_customers(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.customers'));

        $response->assertOk();
        $response->assertSee('Database & Loyalitas Customer');
        $response->assertSee('Total Pelanggan Cukur');

        // Test tambah customer
        $storeResponse = $this->actingAs($this->owner)->post(route('owner.customers.store'), [
            'name' => 'John Doe Testing',
            'phone' => '081299887766',
            'notes' => 'Suka gaya taper fade',
        ]);

        $storeResponse->assertSessionHas('status');

        $customer = CustomerProfile::query()->where('phone', '081299887766')->firstOrFail();
        $this->assertEquals('John Doe Testing', $customer->name);

        // Test update customer
        $updateResponse = $this->actingAs($this->owner)->put(route('owner.customers.update', $customer), [
            'name' => 'John Doe Updated',
            'phone' => '081299887766',
            'notes' => 'Update catatan preferensi',
        ]);

        $updateResponse->assertSessionHas('status');
        $this->assertEquals('John Doe Updated', $customer->fresh()->name);
        $this->assertEquals('Update catatan preferensi', $customer->fresh()->notes);
    }

    public function test_owner_can_view_and_update_barbershop_settings(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.settings'));

        $response->assertOk();
        $response->assertSee('Pengaturan Barbershop');
        $response->assertSee('Profil & Identitas Usaha Barbershop');
        $response->assertSee('Daftar Cabang Aktif');

        // Test update settings
        $updateResponse = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => 'Barber King Updated',
            'phone' => '081288889999',
            'email' => 'contact@barberking.id',
            'address' => 'Jl. Jenderal Sudirman No. 99, Jakarta',
            'brand_color' => '#f59e0b',
            'qris_account' => 'QRIS Barber King Pusat - BCA',
            'booking_advance_days' => 14,
            'default_duration_minutes' => 35,
            'announcement' => 'Promo Diskon Kemerdekaan!',
        ]);

        $updateResponse->assertSessionHas('status');
        $this->tenant->refresh();
        $this->assertEquals('Barber King Updated', $this->tenant->name);
        $this->assertEquals('QRIS Barber King Pusat - BCA', $this->tenant->settings['qris_account']);
        $this->assertEquals(14, $this->tenant->settings['booking_advance_days']);
    }

    public function test_owner_can_upload_and_remove_barbershop_logo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->image('barber-logo.png', 200, 200);

        $response = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => $this->tenant->name,
            'brand_color' => '#10B981',
            'logo' => $file,
        ]);

        $response->assertSessionHas('status');
        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($this->tenant->logo_path);

        // Test tampil di halaman pengaturan owner & portal customer publik
        $settingsResponse = $this->actingAs($this->owner)->get(route('owner.settings'));
        $settingsResponse->assertOk();
        $settingsResponse->assertSee($this->tenant->logo_url);

        auth()->logout();
        $portalResponse = $this->get(route('customer.portal', $this->tenant->slug));
        $portalResponse->assertOk();
        $portalResponse->assertSee($this->tenant->logo_url);

        // Test hapus logo
        $removeResponse = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => $this->tenant->name,
            'brand_color' => '#10B981',
            'remove_logo' => 1,
        ]);

        $removeResponse->assertSessionHas('status');
        $this->tenant->refresh();
        $this->assertNull($this->tenant->logo_path);

        // Test tolak jika file lebih dari 2MB
        $oversizedFile = \Illuminate\Http\UploadedFile::fake()->create('huge-logo.png', 2500); // 2.5 MB
        $oversizedResponse = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => $this->tenant->name,
            'brand_color' => '#10B981',
            'logo' => $oversizedFile,
        ]);
        $oversizedResponse->assertSessionHasErrors(['logo']);

        // Test tolak jika format tidak didukung (contoh: .pdf / .txt)
        $invalidFile = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');
        $invalidResponse = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => $this->tenant->name,
            'brand_color' => '#10B981',
            'logo' => $invalidFile,
        ]);
        $invalidResponse->assertSessionHasErrors(['logo']);
    }

    public function test_owner_can_create_staff_with_required_password_and_update_with_optional_password(): void
    {
        $branch = $this->tenant->branches()->firstOrFail();
        $scheme = \App\Models\SalaryScheme::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        // 1. Gagal jika password tidak diisi saat tambah staff
        $failResponse = $this->actingAs($this->owner)->post(route('owner.staff.store'), [
            'name' => 'Barber Baru Testing',
            'display_name' => 'Barber Test',
            'email' => 'barbertest@barberking.id',
            'phone' => '089911223344',
            'role' => 'barber',
            'branch_id' => $branch->id,
            'salary_scheme_id' => $scheme->id,
        ]);
        $failResponse->assertSessionHasErrors(['password']);

        // 2. Berhasil jika password dan confirmation diisi
        $successResponse = $this->actingAs($this->owner)->post(route('owner.staff.store'), [
            'name' => 'Barber Baru Testing',
            'display_name' => 'Barber Test',
            'email' => 'barbertest@barberking.id',
            'phone' => '089911223344',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'barber',
            'branch_id' => $branch->id,
            'salary_scheme_id' => $scheme->id,
        ]);
        $successResponse->assertSessionHas('status');

        $user = User::query()->where('email', 'barbertest@barberking.id')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret1234', $user->password));

        $membership = $user->memberships()->firstOrFail();

        // 3. Update staff tanpa password: password lama tetap tidak berubah
        $updateNoPwResponse = $this->actingAs($this->owner)->put(route('owner.staff.update', $membership), [
            'name' => 'Barber Baru Testing Edit',
            'display_name' => 'Barber Edit',
            'email' => 'barbertest@barberking.id',
            'phone' => '089911223344',
            'role' => 'barber',
            'branch_id' => $branch->id,
            'salary_scheme_id' => $scheme->id,
        ]);
        $updateNoPwResponse->assertSessionHas('status');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret1234', $user->fresh()->password));

        // 4. Update staff dengan password baru: password terupdate
        $updateWithPwResponse = $this->actingAs($this->owner)->put(route('owner.staff.update', $membership), [
            'name' => 'Barber Baru Testing Edit',
            'display_name' => 'Barber Edit',
            'email' => 'barbertest@barberking.id',
            'phone' => '089911223344',
            'password' => 'newsecret999',
            'password_confirmation' => 'newsecret999',
            'role' => 'barber',
            'branch_id' => $branch->id,
            'salary_scheme_id' => $scheme->id,
        ]);
        $updateWithPwResponse->assertSessionHas('status');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newsecret999', $user->fresh()->password));
    }

    public function test_owner_can_view_and_manage_individual_staff_salaries_and_raises(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.payroll.staff'));
        $response->assertOk();
        $response->assertSee('Gaji per Karyawan & Kenaikan Gaji');
        $response->assertSee('Total Beban Gaji Pokok');

        $barber = $this->tenant->memberships()->where('role', 'barber')->firstOrFail();
        $scheme = \App\Models\SalaryScheme::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        // Test update gaji khusus / kenaikan gaji per orangan
        $updateSalaryResponse = $this->actingAs($this->owner)->post(route('owner.payroll.update-staff-salary', $barber), [
            'salary_scheme_id' => $scheme->id,
            'salary_mode' => 'custom',
            'custom_base_salary' => 3_200_000,
            'notes' => 'Kenaikan gaji tahunan',
        ]);

        $updateSalaryResponse->assertSessionHas('status');
        $this->assertEquals(3_200_000, $barber->fresh()->custom_base_salary);
        $this->assertEquals(3_200_000, $barber->fresh()->effective_base_salary);
    }

    public function test_owner_can_view_and_manage_job_salary_schemes_and_service_commission_rules(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.payroll.rules'));
        $response->assertOk();
        $response->assertSee('Standar Gaji Jabatan & Komisi Layanan');
        $response->assertSee('Skema Jabatan & Standar Penghasilan');
        $response->assertSee('Daftar Komisi Layanan Barbershop');

        // Test tambah skema jabatan baru (misal: Default Gaji Petugas Kasir)
        $storeSchemeResponse = $this->actingAs($this->owner)->post(route('owner.payroll.store-scheme'), [
            'name' => 'Petugas Kasir Outlet',
            'type' => 'fixed',
            'base_salary' => 2_600_000,
        ]);
        $storeSchemeResponse->assertSessionHas('status');

        $createdScheme = \App\Models\SalaryScheme::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('name', 'Petugas Kasir Outlet')
            ->firstOrFail();
        $this->assertEquals(2_600_000, $createdScheme->base_salary);

        // Test update komisi layanan default
        $service = $this->tenant->services()->firstOrFail();
        $updateCommResponse = $this->actingAs($this->owner)->post(route('owner.payroll.store-commission-rule'), [
            'service_id' => $service->id,
            'type' => 'flat',
            'value' => 12_500,
        ]);
        $updateCommResponse->assertSessionHas('status');

        $serviceRule = \App\Models\CommissionRule::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('service_id', $service->id)
            ->whereNull('membership_id')
            ->firstOrFail();
        $this->assertEquals(12_500, $serviceRule->value);
    }

    public function test_owner_can_view_reports_with_pro_operational_analytics_and_audit_logs(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.reports'));

        $response->assertOk();
        $response->assertSee('Efektivitas & Kinerja Barbershop');
        $response->assertSee('Jam Ramai Kunjungan');
        $response->assertSee('Tren Pangkas Bulanan');
        $response->assertSee('Cara Masuk Pelanggan');
        $response->assertSee('Layanan Terlaris');
        $response->assertSee('Performa & Kinerja Barber');
        $response->assertSee('Riwayat Transaksi & Refund');
        $response->assertSee('Audit Log Keamanan (50 Aktivitas Terakhir)');

        // Test export CSV laporan
        $exportResponse = $this->actingAs($this->owner)->get(route('owner.reports.export'));
        $exportResponse->assertOk();
        $exportResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_owner_can_view_transactions_with_pro_financial_charts(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.transactions'));

        $response->assertOk();
        $response->assertSee('Daftar Transaksi Pembayaran');
        $response->assertSee('Tren Omzet (7 Hari Terakhir)');
        $response->assertSee('Metode Pembayaran (QRIS vs Tunai)');
        $response->assertSee('QRIS / Non-Tunai');
        $response->assertSee('Uang Tunai (Cash)');
        $response->assertSee('Total Omzet Lunas');
    }

    public function test_owner_can_view_subscription_page_and_upgrade_plan(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.subscription'));

        $response->assertOk();
        $response->assertSee('Langganan &amp; Paket Fitur', false);
        $response->assertSee('Pilihan Paket Langganan &amp; Upgrade Fitur', false);
        $response->assertSee('Starter');
        $response->assertSee('Pro');
        $response->assertSee('Business');

        $businessPlan = \App\Models\Plan::query()->where('code', 'business')->firstOrFail();

        $upgradeResponse = $this->actingAs($this->owner)->post(route('owner.subscription.upgrade'), [
            'plan_id' => $businessPlan->id,
        ]);

        $invoice = \App\Models\SubscriptionInvoice::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertEquals($businessPlan->id, $invoice->target_plan_id);
        $this->assertEquals('upgrade', $invoice->type);
        $this->assertEquals('unpaid', $invoice->status);

        $upgradeResponse->assertRedirect(route('owner.subscription.invoice', $invoice->id));
        $upgradeResponse->assertSessionHas('status');

        // Buka halaman invoice pembayaran
        $invoicePage = $this->actingAs($this->owner)->get(route('owner.subscription.invoice', $invoice->id));
        $invoicePage->assertOk();
        $invoicePage->assertSee('Rincian Tagihan Berlangganan');
        $invoicePage->assertSee('Transfer Bank');

        // Upload bukti transfer
        \Illuminate\Support\Facades\Storage::fake('public');
        $fakeProof = \Illuminate\Http\UploadedFile::fake()->image('bukti_transfer.jpg', 600, 400);

        $payResponse = $this->actingAs($this->owner)->post(route('owner.subscription.submit-payment', $invoice->id), [
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'proof' => $fakeProof,
            'notes' => 'Transfer via BCA Anton H',
        ]);

        $payResponse->assertRedirect(route('owner.subscription.invoice', $invoice->id));
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals('BCA', $invoice->bank_name);
        $this->assertNotNull($invoice->proof_path);

        // Superadmin mengonfirmasi pembayaran
        $superadmin = \App\Models\User::factory()->create(['is_superadmin' => true]);
        $confirmResponse = $this->actingAs($superadmin)->patch(route('platform.subscriptions.confirm', $invoice->id));
        $confirmResponse->assertSessionHas('status');

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);

        $this->tenant->refresh();
        $sub = $this->tenant->subscriptions()->latest('id')->first();
        $this->assertNotNull($sub);
        $this->assertEquals($businessPlan->id, $sub->plan_id);
        $this->assertEquals(\App\Enums\SubscriptionStatus::Active, $sub->status);
    }

    public function test_owner_can_cancel_subscription_invoice_before_approval(): void
    {
        $plan = \App\Models\Plan::query()->where('code', 'pro')->firstOrFail();

        $this->actingAs($this->owner)->post(route('owner.subscription.upgrade'), [
            'plan_id' => $plan->id,
        ]);

        $invoice = \App\Models\SubscriptionInvoice::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertEquals('unpaid', $invoice->status);

        // Owner membatalkan tagihan dengan alasan
        $cancelResponse = $this->actingAs($this->owner)->post(route('owner.subscription.cancel-invoice', $invoice->id), [
            'cancellation_reason' => 'Salah memilih paket langganan',
        ]);
        $cancelResponse->assertRedirect(route('owner.subscription'));
        $cancelResponse->assertSessionHas('status');

        $invoice->refresh();
        $this->assertEquals('cancelled', $invoice->status);
        $this->assertEquals('owner', $invoice->cancelled_by);
        $this->assertEquals('Salah memilih paket langganan', $invoice->cancellation_reason);
        $this->assertNotNull($invoice->cancelled_at);
    }

    public function test_renewal_adds_days_and_upgrade_resets_days(): void
    {
        $starterPlan = \App\Models\Plan::query()->where('code', 'starter')->firstOrFail();
        $proPlan = \App\Models\Plan::query()->where('code', 'pro')->firstOrFail();

        // 1. Subscription aktif paket Starter dengan sisa 5 hari (memenuhi syarat perpanjangan <= 7 hari)
        $sub = $this->tenant->subscriptions()->latest('id')->first();
        $sub->update([
            'plan_id' => $starterPlan->id,
            'status' => \App\Enums\SubscriptionStatus::Active,
            'ends_at' => now()->addDays(5),
        ]);

        // Perpanjang Starter (+30 hari)
        $this->actingAs($this->owner)->post(route('owner.subscription.upgrade'), [
            'plan_id' => $starterPlan->id,
        ]);

        $renewalInvoice = \App\Models\SubscriptionInvoice::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertEquals('renewal', $renewalInvoice->type);

        $superadmin = \App\Models\User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($superadmin)->patch(route('platform.subscriptions.confirm', $renewalInvoice->id));

        $sub->refresh();
        // Masa aktif harus ditumpuk (+30 hari dari 5 hari sisa = 35 hari dari now())
        $this->assertEquals(now()->addDays(35)->toDateString(), $sub->ends_at->toDateString());

        // 2. Sekarang Upgrade ke Pro (sisa masa aktif 35 hari TIDAK ditumpuk, direset murni 30 hari ke depan)
        $this->actingAs($this->owner)->post(route('owner.subscription.upgrade'), [
            'plan_id' => $proPlan->id,
        ]);

        $upgradeInvoice = \App\Models\SubscriptionInvoice::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertEquals('upgrade', $upgradeInvoice->type);

        $this->actingAs($superadmin)->patch(route('platform.subscriptions.confirm', $upgradeInvoice->id));

        $sub->refresh();
        $this->assertEquals($proPlan->id, $sub->plan_id);
        // Sisa 35 hari hangus, direset 30 hari dari now()
        $this->assertEquals(now()->addDays(30)->toDateString(), $sub->ends_at->toDateString());
    }

    public function test_superadmin_can_reject_subscription_invoice_with_reason(): void
    {
        $plan = \App\Models\Plan::query()->where('code', 'pro')->firstOrFail();

        $this->actingAs($this->owner)->post(route('owner.subscription.upgrade'), [
            'plan_id' => $plan->id,
        ]);

        $invoice = \App\Models\SubscriptionInvoice::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();

        $superadmin = \App\Models\User::factory()->create(['is_superadmin' => true]);

        // Superadmin menolak tagihan dengan alasan
        $rejectResponse = $this->actingAs($superadmin)->patch(route('platform.subscriptions.cancel', $invoice->id), [
            'cancellation_reason' => 'Bukti transfer tidak valid atau foto buram',
        ]);

        $rejectResponse->assertSessionHas('status');

        $invoice->refresh();
        $this->assertEquals('cancelled', $invoice->status);
        $this->assertEquals('superadmin', $invoice->cancelled_by);
        $this->assertEquals('Bukti transfer tidak valid atau foto buram', $invoice->cancellation_reason);
        $this->assertNotNull($invoice->cancelled_at);

        // Halaman invoice owner menampilkan alasan penolakan dari Superadmin
        $pageResponse = $this->actingAs($this->owner)->get(route('owner.subscription.invoice', $invoice->id));
        $pageResponse->assertOk();
        $pageResponse->assertSee('Ditolak Superadmin');
        $pageResponse->assertSee('Bukti transfer tidak valid atau foto buram');
    }

    public function test_downgrade_to_starter_resets_custom_logo_color_and_whitelabel(): void
    {
        $businessPlan = \App\Models\Plan::query()->where('code', 'business')->firstOrFail();
        $starterPlan = \App\Models\Plan::query()->where('code', 'starter')->firstOrFail();

        // Siapkan tenant dengan kustomisasi logo, tema, dan white-label di paket Business
        $this->tenant->update([
            'logo_path' => 'logos/custom_logo.png',
            'brand_color' => '#10B981',
            'settings' => [
                'white_label' => true,
                'custom_subdomain' => 'barberking-vip',
            ],
        ]);

        $sub = $this->tenant->subscriptions()->latest('id')->first();
        $sub->update([
            'plan_id' => $businessPlan->id,
            'status' => \App\Enums\SubscriptionStatus::Active,
        ]);

        $this->tenant->refresh();
        $this->assertTrue($this->tenant->hasFeature('custom_logo'));
        $this->assertTrue($this->tenant->hasFeature('white_label'));
        $this->assertNotNull($this->tenant->logo_url);

        // Downgrade ke Starter melalui konfirmasi Superadmin
        $downgradeInvoice = \App\Models\SubscriptionInvoice::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $sub->id,
            'target_plan_id' => $starterPlan->id,
            'type' => 'downgrade',
            'number' => 'INV-SUB-TEST-DOWNGRADE',
            'amount' => $starterPlan->price_monthly,
            'status' => 'pending',
            'due_at' => now()->addDays(2),
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
        ]);

        $superadmin = \App\Models\User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($superadmin)->patch(route('platform.subscriptions.confirm', $downgradeInvoice->id));

        $this->tenant->refresh();
        $sub->refresh();

        // 1. Plan berubah ke Starter
        $this->assertEquals($starterPlan->id, $sub->plan_id);

        // 2. Logo dan brand color direset ke default
        $this->assertNull($this->tenant->logo_path);
        $this->assertNull($this->tenant->logo_url);
        $this->assertEquals('#f59e0b', $this->tenant->brand_color);

        // 3. White-label dan subdomain di-reset
        $this->assertFalse($this->tenant->hasFeature('white_label'));
        $this->assertFalse($this->tenant->hasFeature('custom_logo'));
        $this->assertFalse($this->tenant->settings['white_label'] ?? false);
        $this->assertNull($this->tenant->settings['custom_subdomain'] ?? null);
    }
}
