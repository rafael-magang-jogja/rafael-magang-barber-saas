<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Branch;
use App\Models\CommissionEntry;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payroll;
use App\Models\SalaryScheme;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OwnerManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PayrollWeeklyAndBaseSalaryRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private User $ownerUser;
    private User $barberUser;
    private Membership $barber;
    private User $adminUser;
    private Membership $fixedStaff;
    private SalaryScheme $fixedPlusCommScheme;
    private Service $service;
    private OwnerManagementService $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Barber King Test',
            'slug' => 'barber-king-test',
            'is_active' => true,
        ]);

        $this->branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabang Sudirman',
            'code' => 'SDR',
            'qr_token' => 'qr-token-sdr',
            'late_tolerance_minutes' => 15,
            'is_active' => true,
        ]);

        $this->ownerUser = User::query()->create([
            'name' => 'Owner Barber King',
            'email' => 'owner@barberking.id',
            'password' => bcrypt('password'),
        ]);

        Membership::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->ownerUser->id,
            'branch_id' => $this->branch->id,
            'role' => Role::Owner,
            'display_name' => 'Owner',
            'is_active' => true,
        ]);

        $this->fixedPlusCommScheme = SalaryScheme::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gaji Pokok + Komisi 30%',
            'type' => 'fixed_plus_commission',
            'base_salary' => 2000000,
            'commission_percentage' => 30,
            'is_active' => true,
        ]);

        $this->barberUser = User::query()->create([
            'name' => 'Budi Barber',
            'email' => 'budi@barberking.id',
            'password' => bcrypt('password'),
        ]);

        $this->barber = Membership::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->barberUser->id,
            'branch_id' => $this->branch->id,
            'role' => Role::Barber,
            'display_name' => 'Budi',
            'salary_scheme_id' => $this->fixedPlusCommScheme->id,
            'work_status' => WorkStatus::Active,
            'is_active' => true,
        ]);

        $this->adminUser = User::query()->create([
            'name' => 'Siti Kasir',
            'email' => 'siti@barberking.id',
            'password' => bcrypt('password'),
        ]);

        $this->fixedStaff = Membership::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'branch_id' => $this->branch->id,
            'role' => Role::Admin,
            'display_name' => 'Siti',
            'salary_scheme_id' => $this->fixedPlusCommScheme->id,
            'work_status' => WorkStatus::Active,
            'is_active' => true,
        ]);

        $this->service = Service::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gentleman Haircut',
            'price' => 50000,
            'duration_minutes' => 45,
            'is_commissionable' => true,
            'is_active' => true,
        ]);

        $this->serviceManager = app(OwnerManagementService::class);
    }

    private function createDummyOrder(string $number, string $paidAt): Order
    {
        return Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'number' => $number,
            'idempotency_key' => 'idemp-' . $number,
            'barber_membership_id' => $this->barber->id,
            'subtotal' => 50000,
            'discount_total' => 0,
            'tip_amount' => 0,
            'grand_total' => 50000,
            'paid_total' => 50000,
            'payment_status' => 'paid',
            'paid_at' => $paidAt,
        ]);
    }

    public function test_weekly_payroll_holds_base_salary_and_pays_commission(): void
    {
        // Set waktu pengujian di tanggal 28 Oktober 2026
        Carbon::setTestNow('2026-10-28 10:00:00');

        // Barber melayani order di Minggu 1 (3 Oktober 2026)
        $order1 = $this->createDummyOrder('ORD-001', '2026-10-03 14:00:00');
        $orderItem1 = OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'name' => 'Haircut 1',
            'service_id' => $this->service->id,
            'barber_membership_id' => $this->barber->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        $commEntry1 = CommissionEntry::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'membership_id' => $this->barber->id,
            'order_item_id' => $orderItem1->id,
            'type' => 'commission',
            'base_amount' => 50000,
            'amount' => 15000, // 30% dari 50.000
            'earned_at' => '2026-10-03 14:00:00',
        ]);

        // 1. Generate Payroll Minggu 1 (1 - 7 Oktober 2026)
        $payrollW1 = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-01',
            periodEnd: '2026-10-07',
            userId: $this->ownerUser->id,
        );

        $itemW1 = $payrollW1->items()->where('membership_id', $this->barber->id)->firstOrFail();
        $fixedItemW1 = $payrollW1->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();

        // Verifikasi Aturan Barber: Tanpa Gaji Pokok, Murni Komisi per Kepala (Rp15.000)
        $this->assertSame(0, $itemW1->base_salary);
        $this->assertSame('none', $itemW1->scheme_snapshot['base_salary_status']);
        $this->assertSame(0, $itemW1->scheme_snapshot['original_base_salary']);
        $this->assertSame(15000, $itemW1->commission_total);
        $this->assertSame(15000, $itemW1->net_total);

        // Verifikasi Aturan Staf Tetap: Gaji Pokok TIDAK dicairkan pada minggu 1 (Rp0, ditahan)
        $this->assertSame(0, $fixedItemW1->base_salary);
        $this->assertSame('held_period', $fixedItemW1->scheme_snapshot['base_salary_status']);
        $this->assertSame(2000000, $fixedItemW1->scheme_snapshot['original_base_salary']);
        $this->assertSame(0, $fixedItemW1->net_total);

        // 2. Tandai item Minggu 1 sebagai LUNAS
        $this->serviceManager->markPayrollItemPaid($itemW1, 'transfer', 'Gaji Komisi W1');
        $this->assertSame('paid', $itemW1->fresh()->payment_status);

        // Verifikasi bahwa commission entry sekarang tertaut ke payrollW1
        $this->assertSame($payrollW1->id, $commEntry1->fresh()->payroll_id);

        // Barber mendapatkan komisi lagi di Minggu 3 (18 Oktober) dan Minggu 4 (25 Oktober)
        $order2 = $this->createDummyOrder('ORD-002', '2026-10-18 14:00:00');
        $orderItem2 = OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'name' => 'Haircut 2',
            'service_id' => $this->service->id,
            'barber_membership_id' => $this->barber->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);
        CommissionEntry::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'membership_id' => $this->barber->id,
            'order_item_id' => $orderItem2->id,
            'type' => 'commission',
            'base_amount' => 50000,
            'amount' => 20000,
            'earned_at' => '2026-10-18 14:00:00',
        ]);

        $order3 = $this->createDummyOrder('ORD-003', '2026-10-25 14:00:00');
        $orderItem3 = OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order3->id,
            'name' => 'Haircut 3',
            'service_id' => $this->service->id,
            'barber_membership_id' => $this->barber->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);
        CommissionEntry::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'membership_id' => $this->barber->id,
            'order_item_id' => $orderItem3->id,
            'type' => 'commission',
            'base_amount' => 50000,
            'amount' => 25000,
            'earned_at' => '2026-10-25 14:00:00',
        ]);

        // 3. Generate Payroll 1 Bulan Penuh di Akhir Bulan (1 - 31 Oktober 2026)
        // Tanggal sekarang 28 Oktober (sudah >= 25 / menyentuh akhir bulan)
        $payrollFull = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-01',
            periodEnd: '2026-10-31',
            userId: $this->ownerUser->id,
        );

        $itemFull = $payrollFull->items()->where('membership_id', $this->barber->id)->firstOrFail();
        $fixedItemFull = $payrollFull->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();

        // Verifikasi Aturan Gaji Pokok Staf Tetap: Dicairkan penuh di akhir bulan (Rp2.000.000)
        $this->assertSame(2000000, $fixedItemFull->base_salary);
        $this->assertSame('disbursed', $fixedItemFull->scheme_snapshot['base_salary_status']);
        $this->assertSame(2000000, $fixedItemFull->net_total);

        // Verifikasi Aturan Barber: Gaji Pokok tetap 0, murni sisa komisi
        $this->assertSame(0, $itemFull->base_salary);
        $this->assertSame('none', $itemFull->scheme_snapshot['base_salary_status']);

        // Verifikasi Aturan Bagi Hasil / Komisi:
        // Total komisi bulan ini: 15.000 + 20.000 + 25.000 = 60.000.
        // Komisi Minggu 1 yang sudah dibayar: 15.000.
        // Sisa komisi yang dibayarkan: 60.000 - 15.000 = 45.000 (TIDAK DIBAYAR GANDA!)
        $this->assertSame(60000, $itemFull->scheme_snapshot['gross_commissions']);
        $this->assertSame(15000, $itemFull->scheme_snapshot['already_paid_commissions']);
        $this->assertSame(45000, $itemFull->commission_total);
        $this->assertSame(45000, $itemFull->net_total);

        // 4. Bayar payroll akhir bulan
        $this->serviceManager->markPayrollItemPaid($itemFull, 'transfer', 'Pelunasan Sisa Komisi Barber');
        $this->assertSame('paid', $itemFull->fresh()->payment_status);

        $this->serviceManager->markPayrollItemPaid($fixedItemFull, 'transfer', 'Pelunasan Gaji Pokok Staf Tetap');
        $this->assertSame('paid', $fixedItemFull->fresh()->payment_status);

        // 5. Coba generate lagi payroll di bulan Oktober
        $payrollExtra = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-25',
            periodEnd: '2026-10-31',
            userId: $this->ownerUser->id,
        );

        $itemExtra = $payrollExtra->items()->where('membership_id', $this->barber->id)->firstOrFail();
        $fixedItemExtra = $payrollExtra->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();

        // Verifikasi Aturan Staf Tetap: Gaji pokok hanya bisa 1x per bulan kalender.
        // Karena sudah dibayar, maka di payroll berikutnya dalam bulan yang sama menjadi Rp0
        $this->assertSame(0, $fixedItemExtra->base_salary);
        $this->assertSame('already_paid', $fixedItemExtra->scheme_snapshot['base_salary_status']);

        // Sisa komisi barber juga sudah 0 karena seluruh komisi telah lunas
        $this->assertSame(0, $itemExtra->commission_total);
        $this->assertSame(0, $itemExtra->net_total);
    }

    public function test_cannot_disburse_base_salary_before_25th(): void
    {
        // Set waktu pengujian di tanggal 10 Oktober 2026 (belum menyentuh akhir bulan)
        Carbon::setTestNow('2026-10-10 10:00:00');

        $payroll = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-01',
            periodEnd: '2026-10-31',
            userId: $this->ownerUser->id,
        );

        $item = $payroll->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();

        // Gaji pokok tetap dimasukkan di draf (tidak 0) dengan status unpaid, tetapi tidak bisa dicairkan sebelum akhir bulan
        $this->assertGreaterThan(0, $item->base_salary);
        $this->assertSame('unpaid', $item->payment_status);

        // Coba bayar gaji tetap sebelum H-7 akhir bulan -> harus Exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pembayaran gaji staf tetap terkunci hingga mendekati akhir bulan');
        $this->serviceManager->markPayrollItemPaid($item);
    }

    public function test_commission_only_barber_can_be_paid_anytime_with_remaining_balance(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        $commScheme = SalaryScheme::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mitra Komisi Murni 40%',
            'type' => 'commission_only',
            'base_salary' => 0,
            'commission_percentage' => 40,
            'is_active' => true,
        ]);

        $mitraUser = User::query()->create([
            'name' => 'Rian Mitra',
            'email' => 'rian@barberking.id',
            'password' => bcrypt('password'),
        ]);

        $mitra = Membership::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $mitraUser->id,
            'branch_id' => $this->branch->id,
            'role' => Role::Barber,
            'display_name' => 'Rian',
            'salary_scheme_id' => $commScheme->id,
            'work_status' => WorkStatus::Active,
            'is_active' => true,
        ]);

        // Order 1 di Minggu 1 (Tgl 2 Okt)
        $order1 = $this->createDummyOrder('ORD-M01', '2026-10-02 12:00:00');
        $item1 = OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'name' => 'Haircut',
            'service_id' => $this->service->id,
            'barber_membership_id' => $mitra->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);
        CommissionEntry::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'membership_id' => $mitra->id,
            'order_item_id' => $item1->id,
            'type' => 'commission',
            'base_amount' => 50000,
            'amount' => 20000,
            'earned_at' => '2026-10-02 12:00:00',
        ]);

        // Bayar Minggu 1 untuk Mitra
        $payrollW1 = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-01',
            periodEnd: '2026-10-07',
            userId: $this->ownerUser->id,
            membershipId: $mitra->id,
        );
        $mitraItemW1 = $payrollW1->items()->where('membership_id', $mitra->id)->firstOrFail();
        $this->assertSame(20000, $mitraItemW1->commission_total);
        $this->serviceManager->markPayrollItemPaid($mitraItemW1);

        // Order 2 di Minggu 2 (Tgl 10 Okt)
        $order2 = $this->createDummyOrder('ORD-M02', '2026-10-10 12:00:00');
        $item2 = OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'name' => 'Haircut 2',
            'service_id' => $this->service->id,
            'barber_membership_id' => $mitra->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);
        CommissionEntry::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'membership_id' => $mitra->id,
            'order_item_id' => $item2->id,
            'type' => 'commission',
            'base_amount' => 50000,
            'amount' => 30000,
            'earned_at' => '2026-10-10 12:00:00',
        ]);

        // Generate periode tanggal 1 - 15 Okt (kumulatif):
        $payrollW2 = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-01',
            periodEnd: '2026-10-15',
            userId: $this->ownerUser->id,
            membershipId: $mitra->id,
        );
        $mitraItemW2 = $payrollW2->items()->where('membership_id', $mitra->id)->firstOrFail();

        // Total didapat: 20k + 30k = 50k. Sudah dibayar: 20k. Sisa dibayar: 30k!
        $this->assertSame(50000, $mitraItemW2->scheme_snapshot['gross_commissions']);
        $this->assertSame(20000, $mitraItemW2->scheme_snapshot['already_paid_commissions']);
        $this->assertSame(30000, $mitraItemW2->commission_total);
    }

    public function test_owner_can_pay_past_month_forgotten_payroll_without_being_locked_and_without_disturbing_other_payments(): void
    {
        // 1. Buat payroll bulan Oktober 2026 (Bulan Kemarin)
        Carbon::setTestNow('2026-10-31 20:00:00');
        $octoberPayroll = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-10-01',
            periodEnd: '2026-10-31',
            userId: $this->ownerUser->id,
            membershipId: $this->fixedStaff->id,
        );
        $this->serviceManager->approvePayroll($octoberPayroll, $this->ownerUser->id);

        $octoberItem = $octoberPayroll->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();
        $this->assertSame(2000000, $octoberItem->base_salary);
        $this->assertSame('unpaid', $octoberItem->payment_status);

        // 2. Maju ke bulan November (Tgl 5 November 2026 - masih awal bulan, belum H-3)
        Carbon::setTestNow('2026-11-05 10:00:00');

        // Pembayaran gaji pokok bulan kemarin (Oktober) HARUS TETAP TERBUKA & BERHASIL DIBAYAR
        $this->serviceManager->markPayrollItemPaid($octoberItem);
        $octoberItem->refresh();
        $this->assertSame('paid', $octoberItem->payment_status);

        // 3. Pastikan pembayaran bulan Oktober tidak mengganggu perhitungan bulan November
        $novemberPayrollW1 = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-11-01',
            periodEnd: '2026-11-07',
            userId: $this->ownerUser->id,
            membershipId: $this->fixedStaff->id,
        );
        $novemberItemW1 = $novemberPayrollW1->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();

        // Gaji pokok November statusnya 'held_period' (bukan 'already_paid' karena Oktober dan November beda bulan kalender)
        $this->assertSame('held_period', $novemberItemW1->scheme_snapshot['base_salary_status']);
    }

    public function test_alpha_absent_does_not_deduct_salary_and_records_days_in_snapshot(): void
    {
        // 1. Waktu tes di akhir November 2026
        Carbon::setTestNow('2026-11-28 22:00:00');

        // Buat 1 absensi alpha di bulan November (tanggal 10 November 2026)
        \App\Models\Attendance::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'membership_id' => $this->fixedStaff->id,
            'date' => '2026-11-10',
            'status' => 'absent',
            'late_minutes' => 0,
        ]);

        // 2. Generate Payroll Bulan November (2026-11-01 s/d 2026-11-30)
        $payrollNovember = $this->serviceManager->generatePayroll(
            tenantId: $this->tenant->id,
            branchId: null,
            periodStart: '2026-11-01',
            periodEnd: '2026-11-30',
            userId: $this->ownerUser->id,
            membershipId: $this->fixedStaff->id,
        );

        $itemNov = $payrollNovember->items()->where('membership_id', $this->fixedStaff->id)->firstOrFail();
        
        // Verifikasi Aturan: Denda alpha ditiadakan (deduction_total = 0, net_total utuh)
        $this->assertSame(0, $itemNov->deduction_total);
        $this->assertEquals(1, $itemNov->scheme_snapshot['alpha_days'] ?? 0);
        $this->assertEquals($itemNov->gross_total, $itemNov->net_total);
    }
}
