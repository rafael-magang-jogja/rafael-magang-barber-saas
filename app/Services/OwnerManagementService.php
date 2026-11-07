<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Enums\WorkStatus;
use App\Models\Attendance;
use App\Models\BarberSchedule;
use App\Models\BarberTimeOff;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommissionEntry;
use App\Models\CustomerProfile;
use App\Models\HairColor;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\QueueTicket;
use App\Models\Review;
use App\Models\Service;
use App\Models\Tenant;
use App\Repositories\BranchRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\StaffRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class OwnerManagementService
{
    public function __construct(
        private readonly BranchRepository $branchRepository,
        private readonly StaffRepository $staffRepository,
        private readonly CatalogRepository $catalogRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createBranch(array $validated): Branch
    {
        $branch = $this->branchRepository->create([
            'name' => $validated['name'],
            'code' => strtoupper((string) $validated['code']),
            'address' => $validated['address'] ?? null,
            'maps_url' => $validated['maps_url'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'late_tolerance_minutes' => (int) $validated['late_tolerance_minutes'],
            'qr_token' => Str::slug($validated['code'].'-'.Str::random(12)),
            'is_active' => true,
        ]);

        $this->auditLogger->log('branch.created', $branch, null, $branch->only(['name', 'code', 'maps_url']));

        return $branch;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBranch(Branch $branch, array $validated): Branch
    {
        $old = $branch->only(['name', 'code', 'address', 'maps_url', 'phone', 'late_tolerance_minutes', 'is_active']);

        $updates = [
            'name' => $validated['name'],
            'code' => strtoupper((string) $validated['code']),
            'address' => $validated['address'] ?? null,
            'maps_url' => $validated['maps_url'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'late_tolerance_minutes' => (int) $validated['late_tolerance_minutes'],
        ];

        if (array_key_exists('is_active', $validated)) {
            $updates['is_active'] = (bool) $validated['is_active'];
        }

        $branch->update($updates);

        $this->auditLogger->log('branch.updated', $branch, $old, $branch->only(array_keys($updates)));

        return $branch;
    }

    public function deleteBranch(Branch $branch): void
    {
        $old = $branch->only(['name', 'code', 'address', 'phone']);
        $branch->delete();
        $this->auditLogger->log('branch.deleted', $branch, $old, null);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createStaff(int $tenantId, array $validated): Membership
    {
        return DB::transaction(function () use ($tenantId, $validated): Membership {
            $user = $this->staffRepository->createUser([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            $membership = $this->staffRepository->createMembership([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'branch_id' => (int) $validated['branch_id'],
                'role' => $validated['role'],
                'display_name' => $validated['display_name'],
                'specialty' => $validated['specialty'] ?? null,
                'salary_scheme_id' => (int) $validated['salary_scheme_id'],
                'employment_type' => 'permanent',
                'work_status' => WorkStatus::Active,
                'is_active' => true,
                'joined_at' => now()->toDateString(),
            ]);

            $this->auditLogger->log(
                action: 'staff.created',
                auditable: $membership,
                newValues: ['name' => $user->name, 'role' => $validated['role']],
            );

            return $membership;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateStaff(Membership $membership, array $validated): Membership
    {
        return DB::transaction(function () use ($membership, $validated): Membership {
            $oldValues = [
                'name' => $membership->user?->name,
                'display_name' => $membership->display_name,
                'role' => $membership->role->value,
                'branch_id' => $membership->branch_id,
                'specialty' => $membership->specialty,
                'salary_scheme_id' => $membership->salary_scheme_id,
                'work_status' => $membership->work_status->value,
            ];

            if ($membership->user !== null) {
                $userUpdates = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                ];

                if (! empty($validated['password'])) {
                    $userUpdates['password'] = Hash::make($validated['password']);
                }

                $this->staffRepository->updateUser($membership->user, $userUpdates);
            }

            $updated = $this->staffRepository->updateMembership($membership, [
                'branch_id' => (int) $validated['branch_id'],
                'role' => $validated['role'],
                'display_name' => $validated['display_name'],
                'specialty' => $validated['specialty'] ?? null,
                'salary_scheme_id' => (int) $validated['salary_scheme_id'],
                'work_status' => $validated['work_status'] ?? $membership->work_status->value,
            ]);

            $this->auditLogger->log(
                action: 'staff.updated',
                auditable: $updated,
                oldValues: $oldValues,
                newValues: [
                    'name' => $validated['name'],
                    'display_name' => $updated->display_name,
                    'role' => $updated->role->value,
                    'branch_id' => $updated->branch_id,
                    'specialty' => $updated->specialty,
                    'salary_scheme_id' => $updated->salary_scheme_id,
                    'work_status' => $updated->work_status->value,
                ],
            );

            return $updated;
        });
    }

    public function deleteStaff(Membership $membership): void
    {
        if ($membership->role === \App\Enums\Role::Owner) {
            throw new \DomainException('Akun Owner tidak dapat dihapus.');
        }

        DB::transaction(function () use ($membership): void {
            $staffName = $membership->display_name ?? $membership->user?->name;

            $this->auditLogger->log(
                action: 'staff.deleted',
                auditable: $membership,
                oldValues: [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'name' => $staffName,
                    'role' => $membership->role->value,
                    'branch_id' => $membership->branch_id,
                ],
                newValues: null,
            );

            $membership->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createService(array $validated): Service
    {
        $service = $this->catalogRepository->createService($validated + [
            'is_commissionable' => true,
            'is_active' => true,
        ]);

        $this->auditLogger->log(
            action: 'service.created',
            auditable: $service,
            newValues: $service->only(['name', 'price', 'duration_minutes']),
        );

        return $service;
    }

    public function updateServicePrice(Service $service, int $newPrice): Service
    {
        $oldPrice = $service->price;
        $updated = $this->catalogRepository->updateServicePrice($service, $newPrice);

        $this->auditLogger->log(
            action: 'service.price_updated',
            auditable: $updated,
            oldValues: ['price' => $oldPrice],
            newValues: ['price' => $newPrice],
        );

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateService(Service $service, array $validated): Service
    {
        $oldValues = $service->only(['name', 'service_category_id', 'type', 'price', 'duration_minutes', 'description']);
        $updated = $this->catalogRepository->updateService($service, $validated);

        $this->auditLogger->log(
            action: 'service.updated',
            auditable: $updated,
            oldValues: $oldValues,
            newValues: $updated->only(['name', 'service_category_id', 'type', 'price', 'duration_minutes', 'description']),
        );

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createHairColor(array $validated): HairColor
    {
        $color = $this->catalogRepository->createHairColor([
            'name' => $validated['name'],
            'hex_code' => $validated['hex_code'],
            'stock' => (int) $validated['stock'],
            'min_stock' => (int) $validated['min_stock'],
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
        ]);

        $this->auditLogger->log(
            action: 'hair_color.created',
            auditable: $color,
            newValues: $color->only(['name', 'stock', 'min_stock', 'is_active']),
        );

        return $color;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateHairColor(HairColor $hairColor, array $validated): HairColor
    {
        $oldValues = $hairColor->only(['name', 'hex_code', 'stock', 'min_stock', 'is_active']);
        $updated = $this->catalogRepository->updateHairColor($hairColor, [
            'name' => $validated['name'],
            'hex_code' => $validated['hex_code'],
            'stock' => (int) $validated['stock'],
            'min_stock' => (int) $validated['min_stock'],
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
        ]);

        $this->auditLogger->log(
            action: 'hair_color.updated',
            auditable: $updated,
            oldValues: $oldValues,
            newValues: $updated->only(['name', 'hex_code', 'stock', 'min_stock', 'is_active']),
        );

        return $updated;
    }

    /**
     * Generate draf payroll untuk periode tertentu (bisa seluruh staf atau perorangan).
     */
    public function generatePayroll(int $tenantId, ?int $branchId, string $periodStart, string $periodEnd, int $userId, ?int $membershipId = null, ?int $payrollId = null): Payroll
    {
        return DB::transaction(function () use ($tenantId, $branchId, $periodStart, $periodEnd, $userId, $membershipId, $payrollId): Payroll {
            $payroll = null;

            if ($payrollId !== null && $payrollId > 0) {
                $target = Payroll::query()->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->find($payrollId);

                // Gunakan payroll ini jika tanggal periodenya sama persis
                if ($target && $target->period_start->format('Y-m-d') === $periodStart && $target->period_end->format('Y-m-d') === $periodEnd) {
                    $payroll = $target;
                }
            }

            if (! $payroll) {
                // Cari payroll yang ada untuk tenant dan rentang tanggal yang sama
                $payroll = Payroll::query()->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->whereDate('period_start', $periodStart)
                    ->whereDate('period_end', $periodEnd)
                    ->first();
            }

            if (! $payroll) {
                $payroll = Payroll::query()->withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenantId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'branch_id' => null,
                    'status' => PayrollStatus::Draft,
                    'notes' => 'Generated secara otomatis oleh owner pada ' . now()->translatedFormat('d M Y H:i'),
                ]);
            } else {
                $payroll->update([
                    'notes' => 'Generated secara otomatis oleh owner pada ' . now()->translatedFormat('d M Y H:i'),
                ]);
            }

            // Sinkronkan auto-checkout dan absensi alpa untuk rentang tanggal payroll ini
            $this->autoCloseDailyAttendances($tenantId, $periodStart, $periodEnd);

            $periodStartDt = \Illuminate\Support\Carbon::parse($periodStart)->startOfDay();
            $periodEndDt = \Illuminate\Support\Carbon::parse($periodEnd)->endOfDay();
            $lastDayOfPeriodMonth = $periodEndDt->copy()->endOfMonth();

            // Syarat Kelayakan Gaji Pokok:
            // 1. Periode adalah 1 bulan penuh (tgl 1 s/d tgl terakhir) ATAU periode akhir bulan (berakhir di hari terakhir bulan tersebut)
            $isFullMonth = ($periodStartDt->day === 1 && $periodEndDt->isSameDay($lastDayOfPeriodMonth) && $periodStartDt->isSameMonth($periodEndDt));
            $isEndingAtLastDayOfMonth = $periodEndDt->isSameDay($lastDayOfPeriodMonth);
            $isPeriodEligibleForBaseSalary = ($isFullMonth || $isEndingAtLastDayOfMonth);

            $staffMembers = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('role', '!=', \App\Enums\Role::Owner)
                ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
                ->when($membershipId !== null && $membershipId > 0, fn ($q) => $q->where('id', $membershipId))
                ->with(['salaryScheme', 'user'])
                ->get();

            foreach ($staffMembers as $staff) {
                // 1. Komisi Kotor yang dihasilkan staf pada rentang tanggal periode ini
                $grossCommissions = (int) CommissionEntry::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('membership_id', $staff->id)
                    ->whereBetween('earned_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
                    ->sum('amount');

                // 2. Komisi yang SUDAH PERNAH DIBAYAR (status 'paid') di payroll lain yang beririsan dengan periode ini
                $paidCommissionEntriesSum = (int) CommissionEntry::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('membership_id', $staff->id)
                    ->whereBetween('earned_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
                    ->whereNotNull('payroll_id')
                    ->when($payroll && $payroll->id, fn ($q) => $q->where('payroll_id', '!=', $payroll->id))
                    ->whereHas('payroll.items', function ($q) use ($staff) {
                        $q->where('membership_id', $staff->id)->where('payment_status', 'paid');
                    })
                    ->sum('amount');

                $otherPaidItemsCommissionSum = (int) PayrollItem::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('membership_id', $staff->id)
                    ->where('payment_status', 'paid')
                    ->when($payroll && $payroll->id, fn ($q) => $q->where('payroll_id', '!=', $payroll->id))
                    ->whereHas('payroll', function ($q) use ($periodStart, $periodEnd) {
                        $q->where(function ($sub) use ($periodStart, $periodEnd) {
                            $sub->whereBetween('period_start', [$periodStart, $periodEnd])
                                ->orWhereBetween('period_end', [$periodStart, $periodEnd])
                                ->orWhere(function ($nested) use ($periodStart, $periodEnd) {
                                    $nested->where('period_start', '<=', $periodStart)
                                           ->where('period_end', '>=', $periodEnd);
                                });
                        });
                    })
                    ->sum('commission_total');

                $alreadyPaidCommissions = max($paidCommissionEntriesSum, $otherPaidItemsCommissionSum);
                $netCommissions = max(0, $grossCommissions - $alreadyPaidCommissions);

                // 3. Jumlah Layanan (Services Count)
                $grossServicesCount = (int) Order::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('barber_membership_id', $staff->id)
                    ->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
                    ->count();

                $alreadyPaidServicesCount = (int) PayrollItem::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('membership_id', $staff->id)
                    ->where('payment_status', 'paid')
                    ->when($payroll && $payroll->id, fn ($q) => $q->where('payroll_id', '!=', $payroll->id))
                    ->whereHas('payroll', function ($q) use ($periodStart, $periodEnd) {
                        $q->where(function ($sub) use ($periodStart, $periodEnd) {
                            $sub->whereBetween('period_start', [$periodStart, $periodEnd])
                                ->orWhereBetween('period_end', [$periodStart, $periodEnd])
                                ->orWhere(function ($nested) use ($periodStart, $periodEnd) {
                                    $nested->where('period_start', '<=', $periodStart)
                                           ->where('period_end', '>=', $periodEnd);
                                });
                        });
                    })
                    ->sum('services_count');

                $netServicesCount = max(0, $grossServicesCount - $alreadyPaidServicesCount);

                // Tip telah ditiadakan
                $grossTips = 0;
                $alreadyPaidTips = 0;
                $netTips = 0;

                $scheme = $staff->salaryScheme;
                $isBarber = $staff->role === \App\Enums\Role::Barber || (is_object($staff->role) && $staff->role->value === 'barber') || $staff->role === 'barber';
                $nominalBaseSalary = $isBarber ? 0 : (int) $staff->effective_base_salary;

                // 4. Logika Gaji Pokok (Hanya untuk Staf Tetap / Kasir, 1x per bulan kalender di akhir bulan)
                $alreadyPaidBaseSalary = PayrollItem::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('membership_id', $staff->id)
                    ->where('payment_status', 'paid')
                    ->where('base_salary', '>', 0)
                    ->when($payroll && $payroll->id, fn ($q) => $q->where('payroll_id', '!=', $payroll->id))
                    ->whereHas('payroll', function ($q) use ($periodEndDt) {
                        $q->whereYear('period_end', $periodEndDt->year)
                          ->whereMonth('period_end', $periodEndDt->month);
                    })
                    ->exists();

                $baseSalaryDisbursed = 0;
                $baseSalaryStatus = 'none';
                $baseSalaryNote = $isBarber ? 'Staff Barber murni komisi per kepala (tanpa gaji pokok).' : '';

                if (! $isBarber && $nominalBaseSalary > 0) {
                    if ($alreadyPaidBaseSalary) {
                        $baseSalaryDisbursed = 0;
                        $baseSalaryStatus = 'already_paid';
                        $baseSalaryNote = 'Gaji pokok bulan ini sudah pernah dicairkan pada payroll sebelumnya.';
                    } elseif (! $isPeriodEligibleForBaseSalary) {
                        $baseSalaryDisbursed = 0;
                        $baseSalaryStatus = 'held_period';
                        $baseSalaryNote = 'Gaji pokok hanya berlaku pada periode bulanan.';
                    } else {
                        $baseSalaryDisbursed = $nominalBaseSalary;
                        $baseSalaryStatus = 'disbursed';
                        $baseSalaryNote = 'Gaji pokok bulanan tetap.';
                    }
                }

                // Hitung bonus target layanan jika skema memiliki target bonus berjenjang
                $bonusTotal = 0;
                if ($scheme && !empty($scheme->bonus_tiers) && is_array($scheme->bonus_tiers)) {
                    foreach ($scheme->bonus_tiers as $tier) {
                        $min = (int) ($tier['min_services'] ?? 0);
                        $tierBonus = (int) ($tier['bonus'] ?? 0);
                        if ($grossServicesCount >= $min && $tierBonus > $bonusTotal) {
                            $bonusTotal = $tierBonus;
                        }
                    }

                    if ($bonusTotal > 0) {
                        $alreadyPaidBonus = (int) PayrollItem::query()
                            ->withoutGlobalScope('tenant')
                            ->where('tenant_id', $tenantId)
                            ->where('membership_id', $staff->id)
                            ->where('payment_status', 'paid')
                            ->when($payroll && $payroll->id, fn ($q) => $q->where('payroll_id', '!=', $payroll->id))
                            ->whereHas('payroll', function ($q) use ($periodEndDt) {
                                $q->whereYear('period_end', $periodEndDt->year)
                                  ->whereMonth('period_end', $periodEndDt->month);
                            })
                            ->sum('bonus_total');
                        $bonusTotal = max(0, $bonusTotal - $alreadyPaidBonus);
                    }
                }

                // Cek item yang sudah ada di payroll ini
                $existingItem = PayrollItem::query()->withoutGlobalScope('tenant')
                    ->where('payroll_id', $payroll->id)
                    ->where('membership_id', $staff->id)
                    ->first();

                // Fallback: Cari di item payroll manapun untuk tenant ini pada rentang periode yang sama
                if (! $existingItem) {
                    $existingItem = PayrollItem::query()->withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenantId)
                        ->where('membership_id', $staff->id)
                        ->whereHas('payroll', function ($q) use ($periodStart, $periodEnd) {
                            $q->whereDate('period_start', $periodStart)
                              ->whereDate('period_end', $periodEnd);
                        })
                        ->first();
                }

                // 5. Pencatatan Hari Alpha (Potongan denda ditiadakan sesuai kebijakan baru)
                $alphaDays = 0;
                $alphaPenaltyNominal = 0;
                $alphaDeduction = 0;

                // Hitung hari alpha hanya untuk catatan statistik kehadiran
                $calculationEndDt = $periodEndDt->lt(now()) ? $periodEndDt : now()->endOfDay();
                if ($periodStartDt->lte($calculationEndDt)) {
                    $alphaDays = Attendance::query()->withoutGlobalScope('tenant')
                        ->where('membership_id', $staff->id)
                        ->whereBetween('date', [$periodStartDt->format('Y-m-d'), $calculationEndDt->format('Y-m-d')])
                        ->where('status', 'absent')
                        ->count();
                }

                // PENTING: Pertahankan status dan angka jika item staf ini sudah dibayar sebelumnya
                $isPaid = $existingItem && $existingItem->payment_status === 'paid';
                $paymentStatus = $isPaid ? 'paid' : ($existingItem?->payment_status ?? 'unpaid');
                $paidAt = $isPaid ? ($existingItem->paid_at ?? now()) : ($existingItem?->paid_at);
                $paymentMethod = $existingItem?->payment_method;
                $paymentReference = $existingItem?->payment_reference;
                $deductionTotal = 0;

                $baseSalary = $isPaid ? (int) $existingItem->base_salary : $baseSalaryDisbursed;
                $commissions = $isPaid ? (int) $existingItem->commission_total : $netCommissions;
                $servicesCount = $isPaid ? (int) $existingItem->services_count : $netServicesCount;
                if ($isPaid) {
                    $bonusTotal = (int) $existingItem->bonus_total;
                }

                $tips = 0;
                $grossTotal = $baseSalary + $commissions + $bonusTotal;
                $netTotal = max(0, $grossTotal - $deductionTotal);

                PayrollItem::query()->withoutGlobalScope('tenant')->updateOrCreate(
                    [
                        'payroll_id' => $payroll->id,
                        'membership_id' => $staff->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'services_count' => $servicesCount,
                        'base_salary' => $baseSalary,
                        'commission_total' => $commissions,
                        'bonus_total' => $bonusTotal,
                        'deduction_total' => $deductionTotal,
                        'tip_total' => $tips,
                        'gross_total' => $grossTotal,
                        'net_total' => $netTotal,
                        'scheme_snapshot' => [
                            'name' => $scheme?->name ?? 'Skema Khusus',
                            'type' => $scheme?->type ?? 'custom',
                            'base_salary' => $baseSalary,
                            'original_base_salary' => $nominalBaseSalary,
                            'base_salary_status' => $baseSalaryStatus,
                            'base_salary_note' => $baseSalaryNote,
                            'tip_total' => $tips,
                            'gross_tips' => $grossTips,
                            'already_paid_tips' => $alreadyPaidTips,
                            'is_custom_salary' => $staff->custom_base_salary !== null,
                            'commission_percentage' => $scheme?->effective_commission_percentage,
                            'gross_commissions' => $grossCommissions,
                            'already_paid_commissions' => $alreadyPaidCommissions,
                            'net_commissions' => $commissions,
                            'gross_services_count' => $grossServicesCount,
                            'already_paid_services_count' => $alreadyPaidServicesCount,
                            'net_services_count' => $servicesCount,
                            'alpha_days' => $alphaDays,
                            'alpha_penalty_nominal' => $alphaPenaltyNominal,
                            'alpha_deduction' => $alphaDeduction,
                        ],
                        'payment_status' => $paymentStatus,
                        'paid_at' => $paidAt,
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                    ]
                );
            }

            // Hitung ulang total amount dari seluruh item yang ada di payroll ini
            $grandTotal = (int) PayrollItem::query()
                ->withoutGlobalScope('tenant')
                ->where('payroll_id', $payroll->id)
                ->sum('net_total');

            $payroll->update(['total_amount' => $grandTotal]);

            // Cek jika seluruh item non-owner dalam payroll ini sudah berstatus paid
            $unpaidCount = $payroll->items()
                ->where('payment_status', '!=', 'paid')
                ->whereHas('membership', fn ($q) => $q->where('role', '!=', \App\Enums\Role::Owner))
                ->count();

            if ($unpaidCount === 0 && $payroll->items()->count() > 0) {
                $payroll->update([
                    'status' => PayrollStatus::Paid,
                    'paid_at' => $payroll->paid_at ?? now(),
                ]);
            }

            $this->auditLogger->log('payroll.generated', $payroll, null, [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_amount' => $grandTotal,
                'membership_id' => $membershipId,
                'branch_id' => $branchId,
            ]);

            return $payroll;
        });
    }

    public function markPayrollItemPaid(PayrollItem $item, ?string $paymentMethod = 'transfer', ?string $notes = null): PayrollItem
    {
        return DB::transaction(function () use ($item, $paymentMethod, $notes): PayrollItem {
            $payroll = $item->payroll;
            if ($payroll) {
                // 1. Validasi Persetujuan (Approve): Pembayaran hanya bisa dilakukan jika payroll sudah di-approve
                if (! in_array($payroll->status, [PayrollStatus::Approved, PayrollStatus::Paid], true)) {
                    throw new \DomainException("Pembayaran gaji belum dapat diproses karena Payroll belum disetujui (Approved). Silakan klik 'Setujui Payroll' terlebih dahulu.");
                }

                $endOfMonth = $payroll->period_end->copy()->endOfMonth();
                $cutoffDay = $endOfMonth->day - 7;
                
                // Jika periode adalah bulan kemarin yang sudah lewat, pembayaran tetap terbuka
                $isPastMonth = now()->year > $payroll->period_end->year
                    || (now()->year === $payroll->period_end->year && now()->month > $payroll->period_end->month);

                // Untuk periode bulan berjalan, seluruh pembayaran dibuka mendekati akhir bulan (H-7 akhir bulan)
                $isNearEndOfMonth = now()->isSameMonth($payroll->period_end)
                    && now()->isSameYear($payroll->period_end)
                    && now()->day >= $cutoffDay;

                if (! $isPastMonth && ! $isNearEndOfMonth) {
                    throw new \DomainException("Pembayaran gaji seluruh karyawan terkunci hingga mendekati akhir bulan (minimal H-7 akhir bulan, mulai tanggal {$cutoffDay}).");
                }
            }

            $item->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $paymentMethod ?? 'transfer',
                'payment_reference' => $notes,
            ]);

            if ($payroll) {
                // Tautkan komisi yang diperoleh staf dalam rentang tanggal payroll ini agar tidak dibayar ganda
                CommissionEntry::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $item->tenant_id)
                    ->where('membership_id', $item->membership_id)
                    ->whereBetween('earned_at', [
                        $payroll->period_start->format('Y-m-d') . ' 00:00:00',
                        $payroll->period_end->format('Y-m-d') . ' 23:59:59',
                    ])
                    ->where(function ($q) use ($payroll) {
                        $q->whereNull('payroll_id')
                          ->orWhere('payroll_id', $payroll->id);
                    })
                    ->update(['payroll_id' => $payroll->id]);

                // Cek jika seluruh item non-owner dalam payroll ini sudah paid
                $unpaidCount = $payroll->items()
                    ->where('payment_status', '!=', 'paid')
                    ->whereHas('membership', fn ($q) => $q->where('role', '!=', \App\Enums\Role::Owner))
                    ->count();

                if ($unpaidCount === 0) {
                    $payroll->update([
                        'status' => PayrollStatus::Paid,
                        'paid_at' => now(),
                    ]);
                }
            }

            $this->auditLogger->log('payroll_item.paid', $item, null, [
                'membership_id' => $item->membership_id,
                'net_total' => $item->net_total,
                'payment_method' => $paymentMethod,
            ]);

            return $item;
        });
    }

    public function approvePayroll(Payroll $payroll, int $userId): Payroll
    {
        $payroll->update([
            'status' => PayrollStatus::Approved,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->auditLogger->log('payroll.approved', $payroll, null, ['status' => 'approved']);

        return $payroll;
    }

    public function markPayrollPaid(Payroll $payroll): Payroll
    {
        // 1. Validasi Persetujuan (Approve): Pelunasan hanya bisa dilakukan jika payroll sudah di-approve
        if (! in_array($payroll->status, [PayrollStatus::Approved, PayrollStatus::Paid], true)) {
            throw new \DomainException("Pelunasan payroll belum dapat diproses karena Payroll belum disetujui (Approved). Silakan klik 'Setujui Payroll' terlebih dahulu.");
        }

        DB::transaction(function () use ($payroll): void {
            $endOfMonth = $payroll->period_end->copy()->endOfMonth();
            $cutoffDay = $endOfMonth->day - 7;
            
            // Jika periode adalah bulan kemarin yang sudah lewat, pelunasan tetap terbuka
            $isPastMonth = now()->year > $payroll->period_end->year
                || (now()->year === $payroll->period_end->year && now()->month > $payroll->period_end->month);

            // Untuk periode bulan berjalan, pelunasan seluruh payroll hanya dibuka mulai H-7 akhir bulan
            $isNearEndOfMonth = now()->isSameMonth($payroll->period_end)
                && now()->isSameYear($payroll->period_end)
                && now()->day >= $cutoffDay;

            if (! $isPastMonth && ! $isNearEndOfMonth) {
                throw new \DomainException("Pelunasan seluruh payroll terkunci hingga mendekati akhir bulan (minimal H-7 akhir bulan, mulai tanggal {$cutoffDay}).");
            }

            $payroll->update([
                'status' => PayrollStatus::Paid,
                'paid_at' => now(),
            ]);

            $payroll->items()->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            // Tautkan seluruh commission_entries untuk tenant ini dalam rentang periode payroll
            CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $payroll->tenant_id)
                ->whereBetween('earned_at', [
                    $payroll->period_start->format('Y-m-d') . ' 00:00:00',
                    $payroll->period_end->format('Y-m-d') . ' 23:59:59',
                ])
                ->where(function ($q) use ($payroll) {
                    $q->whereNull('payroll_id')
                      ->orWhere('payroll_id', $payroll->id);
                })
                ->update(['payroll_id' => $payroll->id]);
        });

        $this->auditLogger->log('payroll.paid', $payroll, null, ['status' => 'paid']);

        return $payroll;
    }

    /**
     * @param array<int, array{day_of_week: int, start_time: string, end_time: string, break_start?: string|null, break_end?: string|null, is_off: bool}> $days
     */
    public function saveBarberSchedules(int $tenantId, int $membershipId, int $branchId, array $days): void
    {
        DB::transaction(function () use ($tenantId, $membershipId, $branchId, $days): void {
            foreach ($days as $d) {
                $isOff = (bool) ($d['is_off'] ?? false);
                $breakStart = !empty($d['break_start']) ? $d['break_start'] : null;
                $breakEnd = !empty($d['break_end']) ? $d['break_end'] : null;

                BarberSchedule::query()->withoutGlobalScope('tenant')->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'membership_id' => $membershipId,
                        'day_of_week' => (int) $d['day_of_week'],
                    ],
                    [
                        'branch_id' => $branchId,
                        'start_time' => $d['start_time'] ?? '10:00:00',
                        'end_time' => $d['end_time'] ?? '21:00:00',
                        'break_start' => $isOff ? null : $breakStart,
                        'break_end' => $isOff ? null : $breakEnd,
                        'is_off' => $isOff,
                    ]
                );
            }
        });
    }

    /**
     * Ekstrak koordinat Latitude & Longitude presisi dari berbagai format Google Maps URL atau string koordinat.
     * Prioritas utama: Titik Pin Lokasi Asli (!3d/!4d, /place/lat,lng, query q=),
     * bukan sekadar titik tengah kamera viewport (@lat,lng).
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function extractCoordinatesFromMapsUrl(?string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);

        // Jika merupakan shortlink (maps.app.goo.gl, goo.gl/maps, bit.ly, dll), ikuti redirect untuk dapat URL target
        if (preg_match('#^https?://(maps\.app\.goo\.gl|goo\.gl/maps|bit\.ly|t\.co)/#i', $url)) {
            $resolved = self::resolveMapsShortUrl($url);
            if (!empty($resolved['lat']) && !empty($resolved['lng'])) {
                return ['lat' => (float) $resolved['lat'], 'lng' => (float) $resolved['lng']];
            }
            if (!empty($resolved['url']) && $resolved['url'] !== $url) {
                $url = $resolved['url'];
            }
        }

        // Lakukan URL Decode agar parameter terenkripsi (%213d, %2C, dll) dapat dibaca
        $decodedUrl = urldecode($url);

        // 1. PRIORITAS TERTINGGI: Exact Pin Data Protobuf (!3dlat!4dlng atau !4dlng!3dlat)
        // Ini adalah titik pin fisik tempat usaha/bangunan outlet di Google Maps
        if (preg_match('/!3d(-?\d{1,2}\.\d+).*?!4d(-?\d{1,3}\.\d+)/', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[1], 7), 'lng' => round((float) $m[2], 7)];
        }
        if (preg_match('/!4d(-?\d{1,3}\.\d+).*?!3d(-?\d{1,2}\.\d+)/', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[2], 7), 'lng' => round((float) $m[1], 7)];
        }

        // 2. PRIORITAS 2: Koordinat Pin Tempat di path /place/lat,lng/
        if (preg_match('#/place/(-?\d{1,2}\.\d+)(?:,|%2C|\+)(-?\d{1,3}\.\d+)#i', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[1], 7), 'lng' => round((float) $m[2], 7)];
        }

        // 3. PRIORITAS 3: Query Parameter Pencarian / Pin (q=lat,lng, query=lat,lng, ll=lat,lng, daddr=lat,lng)
        if (preg_match('/[?&](?:q|query|ll|daddr|destination)=(-?\d{1,2}\.\d+)(?:,|%2C|\+)(-?\d{1,3}\.\d+)/i', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[1], 7), 'lng' => round((float) $m[2], 7)];
        }

        // 4. PRIORITAS 4: Parameter static map markers (markers=lat,lng atau center=lat,lng)
        if (preg_match('/(?:markers|center)=(?:color:[^%|&]+%7C)?(-?\d{1,2}\.\d+)(?:,|%2C)(-?\d{1,3}\.\d+)/i', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[1], 7), 'lng' => round((float) $m[2], 7)];
        }

        // 5. PRIORITAS 5: Raw Coordinates: "-6.260718, 106.813305" atau "geo:-6.260718,106.813305"
        if (preg_match('/(?:geo:)?(-?\d{1,2}\.\d{4,})\s*,\s*(-?\d{1,3}\.\d{4,})/i', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[1], 7), 'lng' => round((float) $m[2], 7)];
        }

        // 6. PRIORITAS TERAKHIR (FALLBACK): Titik Tengah Kamera Viewport (@lat,lng)
        if (preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', $decodedUrl, $m)) {
            return ['lat' => round((float) $m[1], 7), 'lng' => round((float) $m[2], 7)];
        }

        return null;
    }

    /**
     * Resolve shortlink Google Maps (maps.app.goo.gl) ke target URL panjang tanpa download body
     *
     * @return array{url: ?string, lat: ?float, lng: ?float}|null
     */
    private static function resolveMapsShortUrl(string $url): ?array
    {
        $currentUrl = $url;
        $maxHops = 5;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            // Validasi URL dan Host agar kebal dari SSRF (Server-Side Request Forgery)
            $parsed = parse_url($currentUrl);
            $scheme = strtolower($parsed['scheme'] ?? '');
            $host = strtolower($parsed['host'] ?? '');

            if (!in_array($scheme, ['http', 'https'], true)) {
                break;
            }

            // Izinkan hanya domain Maps yang valid
            $isAllowedDomain = preg_match('/^(?:[a-z0-9-]+\.)*(?:google\.(?:com|[a-z]{2,3}(?:\.[a-z]{2})?)|goo\.gl|bit\.ly|t\.co)$/i', $host);
            if (!$isAllowedDomain) {
                break;
            }

            // Blokir IP lokal / internal / loopback
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    break;
                }
            }

            // Jika currentUrl sudah mengandung koordinat, langsung selesai
            $decoded = urldecode($currentUrl);
            if (preg_match('/(?:!3d|-?\d{1,2}\.\d{4,}\s*,\s*-?\d{1,3}\.\d{4,}|\/place\/|@)/', $decoded)) {
                return ['url' => $currentUrl, 'lat' => null, 'lng' => null];
            }

            $nextUrl = null;

            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $currentUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_NOBODY => true, // HEAD request agar super cepat
                    CURLOPT_FOLLOWLOCATION => false, // Tangkap header Location di hop ini
                    CURLOPT_TIMEOUT => 4,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_USERAGENT => 'curl/8.0.0',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]);
                $headerResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // Jika server tidak mengizinkan HEAD (405), coba GET tanpa follow location
                if ($httpCode === 405 || empty($headerResponse)) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $currentUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HEADER => true,
                        CURLOPT_FOLLOWLOCATION => false,
                        CURLOPT_TIMEOUT => 4,
                        CURLOPT_CONNECTTIMEOUT => 2,
                        CURLOPT_USERAGENT => 'curl/8.0.0',
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]);
                    $headerResponse = curl_exec($ch);
                    curl_close($ch);
                }

                if (is_string($headerResponse)) {
                    if (preg_match('/^location:\s*(.+)$/im', $headerResponse, $locMatch)) {
                        $nextUrl = trim($locMatch[1]);
                    }
                }
            }

            // Fallback via stream context jika curl belum dapat
            if (empty($nextUrl)) {
                try {
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'follow_location' => 0,
                            'timeout' => 4,
                            'user_agent' => 'curl/8.0.0',
                        ],
                    ]);
                    $headers = @get_headers($currentUrl, false, $context);
                    if ($headers) {
                        foreach ($headers as $h) {
                            if (stripos($h, 'Location:') === 0) {
                                $nextUrl = trim(substr($h, 9));
                                break;
                            }
                        }
                    }
                } catch (\Throwable) {}
            }

            if (!empty($nextUrl)) {
                // Periksa apakah ada param url/continue/q terenkripsi (misal consent.google.com)
                if (preg_match('/[?&](?:continue|url|q)=([^&]+)/i', $nextUrl, $paramMatch)) {
                    $extracted = urldecode($paramMatch[1]);
                    if (str_starts_with($extracted, 'http')) {
                        $nextUrl = $extracted;
                    }
                }

                $currentUrl = $nextUrl;
            } else {
                break;
            }
        }

        return [
            'url' => $currentUrl,
            'lat' => null,
            'lng' => null,
        ];
    }

    public function approveTimeOff(BarberTimeOff $timeOff, int $userId): void
    {
        $timeOff->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);

        $this->syncTimeOffToAttendance($timeOff);

        $this->auditLogger->log('time_off.approved', $timeOff, null, ['approved_by' => $userId]);
    }

    public function rejectTimeOff(BarberTimeOff $timeOff, int $userId, ?string $notes = null): void
    {
        $timeOff->update([
            'status' => 'rejected',
            'notes' => $notes,
            'approved_by' => $userId,
        ]);

        $this->rollbackTimeOffAttendance($timeOff);

        $this->auditLogger->log('time_off.rejected', $timeOff, null, ['notes' => $notes]);
    }

    public function syncTimeOffToAttendance(BarberTimeOff $timeOff): void
    {
        $membership = $timeOff->membership ?? Membership::query()->withoutGlobalScope('tenant')->find($timeOff->membership_id);
        $branchId = $membership?->branch_id ?? Branch::query()->withoutGlobalScope('tenant')->where('tenant_id', $timeOff->tenant_id)->value('id');

        $start = \Illuminate\Support\Carbon::parse($timeOff->date_from);
        $end = \Illuminate\Support\Carbon::parse($timeOff->date_to);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            $existing = Attendance::query()->withoutGlobalScope('tenant')
                ->where('membership_id', $timeOff->membership_id)
                ->whereDate('date', $dateStr)
                ->first();

            $attendanceData = [
                'tenant_id' => $timeOff->tenant_id,
                'branch_id' => $branchId,
                'check_in_at' => null,
                'check_out_at' => null,
                'status' => $timeOff->type,
                'late_minutes' => 0,
                'photo_path' => $timeOff->attachment_path,
            ];

            if ($existing !== null) {
                $existing->update($attendanceData);
            } else {
                $attendanceData['membership_id'] = $timeOff->membership_id;
                $attendanceData['date'] = $dateStr;
                Attendance::query()->withoutGlobalScope('tenant')->create($attendanceData);
            }
        }
    }

    public function rollbackTimeOffAttendance(BarberTimeOff $timeOff): void
    {
        $start = \Illuminate\Support\Carbon::parse($timeOff->date_from);
        $end = \Illuminate\Support\Carbon::parse($timeOff->date_to);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            Attendance::query()->withoutGlobalScope('tenant')
                ->where('tenant_id', $timeOff->tenant_id)
                ->where('membership_id', $timeOff->membership_id)
                ->where('date', $dateStr)
                ->whereIn('status', ['leave', 'sick', 'holiday'])
                ->whereNull('check_in_lat')
                ->delete();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTimeOff(int $tenantId, array $data, ?int $approvedBy = null): BarberTimeOff
    {
        $status = $data['status'] ?? ($approvedBy ? 'approved' : 'pending');

        $timeOff = BarberTimeOff::query()->create([
            'tenant_id' => $tenantId,
            'membership_id' => (int) $data['membership_id'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'type' => $data['type'] ?? 'leave',
            'status' => $status,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'notes' => $data['notes'] ?? null,
            'approved_by' => $approvedBy,
        ]);

        if ($status === 'approved') {
            $this->syncTimeOffToAttendance($timeOff);
        }

        return $timeOff;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function recordAttendance(int $tenantId, array $data): Attendance
    {
        $date = $data['date'];
        $checkInAt = !empty($data['check_in_time']) ? "{$date} {$data['check_in_time']}:00" : null;
        $checkOutAt = !empty($data['check_out_time']) ? "{$date} {$data['check_out_time']}:00" : null;

        return Attendance::query()->withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'membership_id' => (int) $data['membership_id'],
                'date' => $date,
            ],
            [
                'branch_id' => (int) $data['branch_id'],
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'status' => $data['status'] ?? 'present',
                'late_minutes' => (int) ($data['late_minutes'] ?? 0),
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAttendance(Attendance $attendance, array $data): Attendance
    {
        $date = $attendance->date->format('Y-m-d');
        $checkInAt = !empty($data['check_in_time']) ? "{$date} {$data['check_in_time']}:00" : $attendance->check_in_at;
        $checkOutAt = !empty($data['check_out_time']) ? "{$date} {$data['check_out_time']}:00" : $attendance->check_out_at;

        $attendance->update([
            'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : $attendance->branch_id,
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'status' => $data['status'] ?? $attendance->status,
            'late_minutes' => isset($data['late_minutes']) ? (int) $data['late_minutes'] : $attendance->late_minutes,
        ]);

        return $attendance;
    }

    /**
     * Otomatisasi Absensi Harian:
     * 1. Jika staf sudah absen masuk tetapi lupa absen pulang, saat shift berakhir / lewat 23:59
     *    otomatis dicatat checkout (pulang) sesuai jam berakhir shiftnya.
     * 2. Jika lewat 23:59 staf memiliki jadwal kerja tetapi sama sekali tidak absen (dan tidak izin/cuti/libur),
     *    otomatis dicatat sebagai Alpa (status = 'absent').
     * 3. Staf yang terlambat (status = 'late') tetap berstatus terlambat (tidak diubah menjadi alpa).
     *
     * @return array{auto_checked_out: int, marked_absent: int}
     */
    public function autoCloseDailyAttendances(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $startDate = $dateFrom ? \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay() : now()->subDays(14)->startOfDay();
        $endDate = $dateTo ? \Illuminate\Support\Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();
        $now = now();

        $autoCheckedOutCount = 0;
        $markedAbsentCount = 0;

        // 1. AUTO CHECK-OUT: Untuk staf yang sudah check-in tapi belum check-out
        $unclosedAttendances = Attendance::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereNotIn('status', ['sick', 'leave', 'holiday', 'absent'])
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['branch', 'membership'])
            ->get();

        foreach ($unclosedAttendances as $attendance) {
            $attDate = \Illuminate\Support\Carbon::parse($attendance->date);
            $dayOfWeek = $attDate->dayOfWeek;

            $schedule = BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $attendance->membership_id)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            $shiftEndStr = '21:00';
            if ($schedule && $schedule->end_time) {
                $shiftEndStr = substr((string) $schedule->end_time, 0, 5);
            } elseif ($attendance->branch && !empty($attendance->branch->opening_hours[$dayOfWeek]['close'])) {
                $shiftEndStr = $attendance->branch->opening_hours[$dayOfWeek]['close'];
            }

            $shiftEndDateTime = \Illuminate\Support\Carbon::parse("{$attDate->format('Y-m-d')} {$shiftEndStr}:00");

            // Jika tanggal sudah lewat (kemarin/sebelumnya) ATAU hari ini sudah melewati jam shift berakhir
            if ($attDate->lt(now()->startOfDay()) || $now->greaterThanOrEqualTo($shiftEndDateTime)) {
                $attendance->update([
                    'check_out_at' => $shiftEndDateTime,
                ]);
                $autoCheckedOutCount++;
            }
        }

        // 2. MARK ABSENT (ALPA): Untuk hari yang sudah lewat 23:59 (tanggal < hari ini)
        $pastEnd = $endDate->lt(now()->startOfDay()) ? $endDate : now()->subDay()->endOfDay();

        if ($startDate->lte($pastEnd)) {
            $staffMemberships = Membership::query()
                ->withoutGlobalScope('tenant')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->whereIn('role', [\App\Enums\Role::Barber, \App\Enums\Role::Admin])
                ->where('is_active', true)
                ->with(['branch'])
                ->get();

            $allSchedules = BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->whereIn('membership_id', $staffMemberships->pluck('id'))
                ->get()
                ->groupBy('membership_id');

            for ($cursor = $startDate->copy(); $cursor->lte($pastEnd); $cursor->addDay()) {
                $dateStr = $cursor->format('Y-m-d');
                $dayOfWeek = $cursor->dayOfWeek;

                foreach ($staffMemberships as $membership) {
                    // Abaikan jika staf belum bergabung pada tanggal tersebut
                    if ($membership->joined_at && \Illuminate\Support\Carbon::parse($membership->joined_at)->gt($cursor)) {
                        continue;
                    }

                    // Cek jadwal kerja staf pada hari tersebut
                    $staffScheds = $allSchedules->get($membership->id, collect());
                    $sched = $staffScheds->firstWhere('day_of_week', $dayOfWeek);
                    $isOff = false;

                    if ($sched !== null) {
                        $isOff = (bool) $sched->is_off;
                    } else {
                        $branch = $membership->branch;
                        $opening = $branch?->opening_hours[$dayOfWeek] ?? null;
                        if ($opening !== null) {
                            $isOff = empty($opening['is_open']);
                        } else {
                            $isOff = true;
                        }
                    }

                    if ($isOff) {
                        continue; // Hari libur/off, tidak dianggap alpa
                    }

                    // Cek permohonan izin/cuti/sakit yang disetujui
                    $hasApprovedTimeOff = BarberTimeOff::query()
                        ->withoutGlobalScope('tenant')
                        ->where('membership_id', $membership->id)
                        ->where('status', 'approved')
                        ->whereDate('date_from', '<=', $dateStr)
                        ->whereDate('date_to', '>=', $dateStr)
                        ->exists();

                    if ($hasApprovedTimeOff) {
                        continue; // Memiliki izin resmi yang disetujui, tidak dianggap alpa
                    }

                    // Cek keberadaan record absensi
                    $att = Attendance::query()
                        ->withoutGlobalScope('tenant')
                        ->where('membership_id', $membership->id)
                        ->whereDate('date', $dateStr)
                        ->first();

                    if ($att !== null) {
                        // Jika sudah ada check-in (hadir tepat waktu atau terlambat) atau izin, jangan ubah ke alpa
                        if ($att->check_in_at !== null || in_array($att->status, ['present', 'late', 'sick', 'leave', 'holiday', 'absent'], true)) {
                            continue;
                        }

                        $att->update([
                            'status' => 'absent',
                            'late_minutes' => 0,
                        ]);
                        $markedAbsentCount++;
                    } else {
                        $branchId = $membership->branch_id ?? Branch::query()->withoutGlobalScope('tenant')->where('tenant_id', $membership->tenant_id)->value('id');
                        if ($branchId) {
                            Attendance::query()
                                ->withoutGlobalScope('tenant')
                                ->create([
                                    'tenant_id' => $membership->tenant_id,
                                    'branch_id' => $branchId,
                                    'membership_id' => $membership->id,
                                    'date' => $dateStr,
                                    'check_in_at' => null,
                                    'check_out_at' => null,
                                    'status' => 'absent',
                                    'late_minutes' => 0,
                                ]);
                            $markedAbsentCount++;
                        }
                    }
                }
            }
        }

        return [
            'auto_checked_out' => $autoCheckedOutCount,
            'marked_absent' => $markedAbsentCount,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateBranchScheduleSettings(Branch $branch, array $data): Branch
    {
        $branch->update([
            'late_tolerance_minutes' => (int) ($data['late_tolerance_minutes'] ?? 15),
            'booking_slot_minutes' => (int) ($data['booking_slot_minutes'] ?? 30),
            'radius_meters' => (int) ($data['radius_meters'] ?? 50),
            'maps_url' => $data['maps_url'] ?? $branch->maps_url,
            'lat' => isset($data['lat']) && $data['lat'] !== null && $data['lat'] !== '' ? (float) $data['lat'] : $branch->lat,
            'lng' => isset($data['lng']) && $data['lng'] !== null && $data['lng'] !== '' ? (float) $data['lng'] : $branch->lng,
            'opening_hours' => $data['opening_hours'] ?? $branch->opening_hours,
        ]);

        return $branch;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCustomer(int $tenantId, array $data): CustomerProfile
    {
        return CustomerProfile::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'notes' => $data['notes'] ?? null,
            'visit_count' => 0,
            'total_spent' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCustomer(CustomerProfile $customer, array $data): CustomerProfile
    {
        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $customer;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTenantSettings(Tenant $tenant, array $data): Tenant
    {
        $settings = $tenant->settings ?? [];
        if (isset($data['qris_account'])) {
            $settings['qris_account'] = $data['qris_account'];
        }
        if (array_key_exists('bank_name', $data)) {
            $settings['bank_name'] = $data['bank_name'];
        }
        if (array_key_exists('bank_account_number', $data)) {
            $settings['bank_account_number'] = $data['bank_account_number'];
        }
        if (array_key_exists('bank_account_holder', $data)) {
            $settings['bank_account_holder'] = $data['bank_account_holder'];
        }
        if (array_key_exists('qris_image_path', $data)) {
            $settings['qris_image_path'] = $data['qris_image_path'];
        }
        if (isset($data['booking_advance_days'])) {
            $settings['booking_advance_days'] = (int) $data['booking_advance_days'];
        }
        if (isset($data['default_duration_minutes'])) {
            $settings['default_duration_minutes'] = (int) $data['default_duration_minutes'];
        }
        if (isset($data['announcement'])) {
            $settings['announcement'] = $data['announcement'];
        }
        if (array_key_exists('favicon_path', $data)) {
            $settings['favicon_path'] = $data['favicon_path'];
        }

        $tenantData = [
            'name' => $data['name'] ?? $tenant->name,
            'phone' => $data['phone'] ?? $tenant->phone,
            'email' => $data['email'] ?? $tenant->email,
            'address' => $data['address'] ?? $tenant->address,
            'brand_color' => $data['brand_color'] ?? $tenant->brand_color,
            'settings' => $settings,
        ];

        if (array_key_exists('logo_path', $data)) {
            $tenantData['logo_path'] = $data['logo_path'];
        }

        $tenant->update($tenantData);

        $this->auditLogger->log('tenant.settings_updated', $tenant, null, $data);

        return $tenant;
    }

    /**
     * Data analitik operasional, efektivitas harian/bulanan, dan kinerja staff untuk Pro Feature.
     * Tidak menyertakan metrik keuangan/omzet (keuangan dikhususkan pada transaksi).
     *
     * @return array<string, mixed>
     */
    public function getOperationalAnalytics(?int $branchId = null, ?int $barberId = null): array
    {
        // 1. Efektivitas & Beban Antrean Harian (Hourly Peak Hours 10:00 - 21:00)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $hourExpr = $isSqlite ? "CAST(strftime('%H', created_at) AS INTEGER)" : 'HOUR(created_at)';

        $hours = range(10, 21);
        $ticketHours = QueueTicket::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->selectRaw("{$hourExpr} as hr, count(*) as total")
            ->groupBy('hr')
            ->pluck('total', 'hr')
            ->all();

        $orderHours = Order::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->selectRaw("{$hourExpr} as hr, count(*) as total")
            ->groupBy('hr')
            ->pluck('total', 'hr')
            ->all();

        $maxHourlyCount = 0;
        $hourlyList = [];
        $peakHourLabel = '-';
        $peakCount = 0;

        foreach ($hours as $h) {
            $count = max((int) ($ticketHours[$h] ?? 0), (int) ($orderHours[$h] ?? 0));
            if ($count > $maxHourlyCount) {
                $maxHourlyCount = $count;
            }
            $label = sprintf('%02d:00', $h);
            if ($count > $peakCount) {
                $peakCount = $count;
                $peakHourLabel = $label . ' - ' . sprintf('%02d:00', $h + 1);
            }
            $hourlyList[] = [
                'hour' => $h,
                'label' => $label,
                'count' => $count,
            ];
        }

        foreach ($hourlyList as &$hItem) {
            $hItem['percentage'] = $maxHourlyCount > 0 ? (int) round(($hItem['count'] / $maxHourlyCount) * 100) : 0;
            $hItem['is_peak'] = $hItem['count'] > 0 && $hItem['count'] === $maxHourlyCount;
        }
        unset($hItem);

        // 2. Efektivitas & Tren Volume Bulanan (6 Bulan Terakhir - Optimized Agregat 1x Query)
        $monthlyTrend = [];
        $totalCompletedRecent = 0;
        $totalCancelledRecent = 0;

        $startDate6Months = now()->subMonths(5)->startOfMonth();
        $monthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        $completedTicketsByMonth = QueueTicket::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where('created_at', '>=', $startDate6Months)
            ->whereIn('status', ['completed', 'in_service'])
            ->selectRaw("{$monthExpr} as ym, count(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        $completedOrdersByMonth = Order::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where('created_at', '>=', $startDate6Months)
            ->whereIn('payment_status', ['paid', 'completed'])
            ->selectRaw("{$monthExpr} as ym, count(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        $cancelledTicketsByMonth = QueueTicket::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where('created_at', '>=', $startDate6Months)
            ->whereIn('status', ['cancelled', 'skipped'])
            ->selectRaw("{$monthExpr} as ym, count(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $ymKey = $date->format('Y-m');
            $shortName = $date->translatedFormat('M');
            $fullName = $date->translatedFormat('F Y');

            $completed = (int) ($completedTicketsByMonth[$ymKey] ?? 0);
            if ($completed === 0) {
                $completed = (int) ($completedOrdersByMonth[$ymKey] ?? 0);
            }

            $cancelled = (int) ($cancelledTicketsByMonth[$ymKey] ?? 0);

            $totalMonth = $completed + $cancelled;
            $rate = $totalMonth > 0 ? (int) round(($completed / $totalMonth) * 100) : 100;

            $totalCompletedRecent += $completed;
            $totalCancelledRecent += $cancelled;

            $monthlyTrend[] = [
                'month' => $shortName,
                'full_month' => $fullName,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'rate' => $rate,
            ];
        }

        $overallMonthTotal = $totalCompletedRecent + $totalCancelledRecent;
        $overallCompletionRate = $overallMonthTotal > 0 ? (int) round(($totalCompletedRecent / $overallMonthTotal) * 100) : 98;

        // 3. Kinerja & Produktivitas Staff / Barber (Optimized Batch Aggregations Tanpa N+1 Query)
        $activeBarbers = Membership::query()
            ->with(['user', 'branch'])
            ->where('role', \App\Enums\Role::Barber)
            ->where('is_active', true)
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('id', $barberId))
            ->get();

        $barberIds = $activeBarbers->pluck('id')->all();

        $durationExpr = $isSqlite
            ? 'AVG((julianday(completed_at) - julianday(started_at)) * 1440)'
            : 'AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at))';

        $ticketCountsByBarber = !empty($barberIds) ? QueueTicket::query()
            ->whereIn('barber_membership_id', $barberIds)
            ->whereIn('status', ['completed', 'in_service'])
            ->selectRaw('barber_membership_id, count(*) as total')
            ->groupBy('barber_membership_id')
            ->pluck('total', 'barber_membership_id')
            ->all() : [];

        $orderCountsByBarber = !empty($barberIds) ? Order::query()
            ->whereIn('barber_membership_id', $barberIds)
            ->selectRaw('barber_membership_id, count(*) as total')
            ->groupBy('barber_membership_id')
            ->pluck('total', 'barber_membership_id')
            ->all() : [];

        $requestedCountsByBarber = !empty($barberIds) ? QueueTicket::query()
            ->whereIn('barber_membership_id', $barberIds)
            ->whereColumn('requested_barber_id', 'barber_membership_id')
            ->selectRaw('barber_membership_id, count(*) as total')
            ->groupBy('barber_membership_id')
            ->pluck('total', 'barber_membership_id')
            ->all() : [];

        $durationsByBarber = !empty($barberIds) ? QueueTicket::query()
            ->whereIn('barber_membership_id', $barberIds)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw("barber_membership_id, {$durationExpr} as duration")
            ->groupBy('barber_membership_id')
            ->pluck('duration', 'barber_membership_id')
            ->all() : [];

        $reviewStatsByBarber = !empty($barberIds) ? Review::query()
            ->whereIn('barber_membership_id', $barberIds)
            ->selectRaw('barber_membership_id, AVG(rating) as avg_rating, COUNT(*) as rating_count')
            ->groupBy('barber_membership_id')
            ->get()
            ->keyBy('barber_membership_id') : collect();

        $staffPerformance = [];
        $totalBarberServicesAll = 0;

        foreach ($activeBarbers as $barber) {
            $tCount = (int) ($ticketCountsByBarber[$barber->id] ?? 0);
            $oCount = (int) ($orderCountsByBarber[$barber->id] ?? 0);

            $servicesDone = max($tCount, $oCount);
            $totalBarberServicesAll += $servicesDone;

            $requestedCount = (int) ($requestedCountsByBarber[$barber->id] ?? 0);
            $reqRate = $servicesDone > 0 ? (int) round(($requestedCount / $servicesDone) * 100) : 0;

            $avgDur = $durationsByBarber[$barber->id] ?? null;
            $avgMin = $avgDur ? (int) round((float) $avgDur) : 30;
            if ($avgMin < 15 || $avgMin > 120) {
                $avgMin = 30;
            }

            $rev = $reviewStatsByBarber->get($barber->id);
            $avgRating = $rev?->avg_rating !== null ? (float) $rev->avg_rating : null;
            $ratingCount = (int) ($rev?->rating_count ?? 0);

            if ($avgRating !== null) {
                $ratingFormatted = number_format($avgRating, 1);
            } elseif ($servicesDone > 0) {
                $ratingFormatted = '5.0';
            } else {
                $ratingFormatted = '-';
            }

            $staffPerformance[] = [
                'id' => $barber->id,
                'name' => $barber->display_name ?? $barber->user?->name ?? 'Barber',
                'branch' => $barber->branch?->name ?? 'Semua Cabang',
                'completed_count' => $servicesDone,
                'avg_duration' => $avgMin,
                'request_rate' => $reqRate,
                'rating' => $ratingFormatted,
                'rating_count' => $ratingCount,
            ];
        }

        usort($staffPerformance, fn ($a, $b) => $b['completed_count'] <=> $a['completed_count']);
        $maxServices = !empty($staffPerformance) ? max(1, $staffPerformance[0]['completed_count']) : 1;

        foreach ($staffPerformance as &$sp) {
            $sp['share_rate'] = $totalBarberServicesAll > 0 ? (int) round(($sp['completed_count'] / $totalBarberServicesAll) * 100) : 0;
            $sp['bar_width'] = (int) round(($sp['completed_count'] / $maxServices) * 100);
        }
        unset($sp);

        // 4. Distribusi Metode Kedatangan: Walk-in vs Booking Online
        $walkInCount = QueueTicket::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where(function ($q) {
                $q->where('type', 'walk_in')->orWhereNull('booking_id');
            })
            ->count();

        $bookingCount = QueueTicket::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where(function ($q) {
                $q->where('type', 'booking')->orWhereNotNull('booking_id');
            })
            ->count();

        $totalArrivals = $walkInCount + $bookingCount;
        $walkInPct = $totalArrivals > 0 ? (int) round(($walkInCount / $totalArrivals) * 100) : 65;
        $bookingPct = $totalArrivals > 0 ? (100 - $walkInPct) : 35;

        // 5. Distribusi Layanan Terpopuler (Top 5 Services by Frequency)
        $topServicesQuery = OrderItem::query()
            ->whereHas('order', function ($q) use ($branchId, $barberId) {
                $q->when($branchId !== null && $branchId > 0, fn ($sq) => $sq->where('branch_id', $branchId))
                  ->when($barberId !== null && $barberId > 0, fn ($sq) => $sq->where('barber_membership_id', $barberId));
            })
            ->selectRaw('name, SUM(qty) as total_qty')
            ->groupBy('name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $totalServiceQty = (int) $topServicesQuery->sum('total_qty');
        $topServices = [];

        foreach ($topServicesQuery as $item) {
            $qty = (int) $item->total_qty;
            $topServices[] = [
                'name' => $item->name,
                'qty' => $qty,
                'percentage' => $totalServiceQty > 0 ? (int) round(($qty / $totalServiceQty) * 100) : 0,
            ];
        }

        if (empty($topServices)) {
            $defaultServices = Service::query()->where('is_active', true)->limit(4)->get();
            foreach ($defaultServices as $ds) {
                $topServices[] = [
                    'name' => $ds->name,
                    'qty' => 0,
                    'percentage' => 25,
                ];
            }
        }

        // 6. Ketepatan Waktu Pelanggan (Customer Punctuality Rate)
        $totalBookings = Booking::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->count();

        $checkedInBookings = Booking::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where('status', 'checked_in')
            ->count();

        $punctualityRate = $totalBookings > 0 ? (int) round(($checkedInBookings / $totalBookings) * 100) : 95;

        // 7. Hari Tersibuk Mingguan (Senin - Minggu)
        $dayNames = [
            0 => 'Sen',
            1 => 'Sel',
            2 => 'Rab',
            3 => 'Kam',
            4 => 'Jum',
            5 => 'Sab',
            6 => 'Min',
        ];

        $dayFullNames = [
            0 => 'Senin',
            1 => 'Selasa',
            2 => 'Rabu',
            3 => 'Kamis',
            4 => 'Jumat',
            5 => 'Sabtu',
            6 => 'Minggu',
        ];

        $weekdayExpr = $isSqlite
            ? "((CAST(strftime('%w', created_at) AS INTEGER) + 6) % 7)"
            : 'WEEKDAY(created_at)';

        $ticketDays = QueueTicket::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->selectRaw("{$weekdayExpr} as wd, count(*) as total")
            ->groupBy('wd')
            ->pluck('total', 'wd')
            ->all();

        $orderDays = Order::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->selectRaw("{$weekdayExpr} as wd, count(*) as total")
            ->groupBy('wd')
            ->pluck('total', 'wd')
            ->all();

        $maxDayCount = 0;
        $weeklyList = [];
        $peakDayLabel = '-';
        $peakDayCount = 0;

        foreach ($dayNames as $dIdx => $dLabel) {
            $dCount = max((int) ($ticketDays[$dIdx] ?? 0), (int) ($orderDays[$dIdx] ?? 0));
            if ($dCount > $maxDayCount) {
                $maxDayCount = $dCount;
            }
            if ($dCount > $peakDayCount) {
                $peakDayCount = $dCount;
                $peakDayLabel = $dayFullNames[$dIdx];
            }
            $weeklyList[] = [
                'day_index' => $dIdx,
                'label' => $dLabel,
                'full_name' => $dayFullNames[$dIdx],
                'count' => $dCount,
            ];
        }

        foreach ($weeklyList as &$wItem) {
            $wItem['percentage'] = $maxDayCount > 0 ? (int) round(($wItem['count'] / $maxDayCount) * 100) : 0;
            $wItem['is_peak'] = $wItem['count'] > 0 && $wItem['count'] === $maxDayCount;
        }
        unset($wItem);

        // 8. Retensi & Loyalitas Pelanggan (Pelanggan Baru vs Pelanggan Langganan)
        $newCustomersCount = CustomerProfile::query()
            ->where('visit_count', '<=', 1)
            ->count();

        $returningCustomersCount = CustomerProfile::query()
            ->where('visit_count', '>', 1)
            ->count();

        $totalProfiles = $newCustomersCount + $returningCustomersCount;
        $returningPct = $totalProfiles > 0 ? (int) round(($returningCustomersCount / $totalProfiles) * 100) : 60;
        $newPct = $totalProfiles > 0 ? (100 - $returningPct) : 40;

        return [
            'hourly' => [
                'list' => $hourlyList,
                'peak_hour' => $peakHourLabel,
                'peak_count' => $peakCount,
                'max_count' => $maxHourlyCount,
            ],
            'monthly' => [
                'trend' => $monthlyTrend,
                'overall_rate' => $overallCompletionRate,
                'total_completed' => $totalCompletedRecent,
            ],
            'weekly' => [
                'list' => $weeklyList,
                'peak_day' => $peakDayLabel,
                'peak_count' => $peakDayCount,
            ],
            'retention' => [
                'new_count' => $newCustomersCount,
                'returning_count' => $returningCustomersCount,
                'new_pct' => $newPct,
                'returning_pct' => $returningPct,
            ],
            'staff' => [
                'list' => $staffPerformance,
                'total_services' => $totalBarberServicesAll,
            ],
            'arrival' => [
                'walk_in_count' => $walkInCount,
                'booking_count' => $bookingCount,
                'walk_in_pct' => $walkInPct,
                'booking_pct' => $bookingPct,
            ],
            'top_services' => $topServices,
            'punctuality' => [
                'rate' => $punctualityRate,
                'total_bookings' => $totalBookings,
            ],
        ];
    }

    /**
     * Ringkasan analitik finansial & tren transaksi untuk Pro Owner.
     *
     * @return array<string, mixed>
     */
    public function getFinancialAnalytics(
        ?int $branchId = null,
        ?int $barberId = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array
    {
        // 1. Tren Omzet 7 Hari Terakhir (Optimized 1x Group By Query)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $dateExpr = $isSqlite ? "strftime('%Y-%m-%d', created_at)" : 'DATE(created_at)';
        $start7Days = now()->subDays(6)->startOfDay();

        $dailyStats = Order::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->where('created_at', '>=', $start7Days)
            ->where('payment_status', 'paid')
            ->selectRaw("{$dateExpr} as dt, SUM(grand_total) as total_rev, COUNT(*) as total_cnt")
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $days = [];
        $maxDailyRevenue = 0;
        $total7DaysRevenue = 0;
        $total7DaysOrders = 0;
        $peakDayLabel = '-';
        $peakDayRevenue = 0;

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();
            $dayName = $date->translatedFormat('D');
            $dayLabel = $date->translatedFormat('d M');

            $stat = $dailyStats->get($dateStr);
            $dayRevenue = (int) ($stat?->total_rev ?? 0);
            $dayCount = (int) ($stat?->total_cnt ?? 0);

            $total7DaysRevenue += $dayRevenue;
            $total7DaysOrders += $dayCount;

            if ($dayRevenue > $maxDailyRevenue) {
                $maxDailyRevenue = $dayRevenue;
            }

            if ($dayRevenue > $peakDayRevenue) {
                $peakDayRevenue = $dayRevenue;
                $peakDayLabel = $dayName . ', ' . $dayLabel;
            }

            $days[] = [
                'date' => $dateStr,
                'day_name' => $dayName,
                'label' => $dayLabel,
                'is_today' => $i === 0,
                'revenue' => $dayRevenue,
                'count' => $dayCount,
            ];
        }

        foreach ($days as &$d) {
            $d['percentage'] = $maxDailyRevenue > 0 ? (int) round(($d['revenue'] / $maxDailyRevenue) * 100) : 0;
            $d['is_peak'] = $d['revenue'] > 0 && $d['revenue'] === $maxDailyRevenue;
        }
        unset($d);

        $avgDailyRevenue = (int) round($total7DaysRevenue / 7);

        // 2. Komposisi Metode Pembayaran (QRIS vs Tunai)
        // Konsisten 100% dengan metode pembayaran transaksi yang tampil di daftar tabel order
        $paidOrdersQuery = Order::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->when($startDate !== null, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate !== null, fn ($q) => $q->where('created_at', '<=', $endDate))
            ->where('payment_status', 'paid')
            ->with(['payments' => fn ($p) => $p->where('type', '!=', 'refund')->latest('id')]);

        $qrisTotal = 0;
        $qrisCount = 0;
        $cashTotal = 0;
        $cashCount = 0;

        foreach ($paidOrdersQuery->cursor() as $order) {
            $payment = $order->payments->first();
            $paymentMethod = $payment?->method;
            $methodVal = $paymentMethod instanceof PaymentMethod ? $paymentMethod->value : (string) ($paymentMethod ?? '');

            $isCash = str_contains(strtolower($methodVal), 'cash') || strtolower($methodVal) === 'tunai';
            $amount = (int) ($order->grand_total > 0 ? $order->grand_total : ($order->paid_total ?? 0));

            if ($isCash) {
                $cashTotal += $amount;
                $cashCount++;
            } else {
                $qrisTotal += $amount;
                $qrisCount++;
            }
        }

        $totalMethodRevenue = $qrisTotal + $cashTotal;
        $totalMethodCount = $qrisCount + $cashCount;

        $qrisPct = $totalMethodRevenue > 0 ? (int) round(($qrisTotal / $totalMethodRevenue) * 100) : 0;
        $cashPct = $totalMethodRevenue > 0 ? (100 - $qrisPct) : 0;

        return [
            'trend_7days' => [
                'days' => $days,
                'total_revenue' => $total7DaysRevenue,
                'avg_revenue' => $avgDailyRevenue,
                'total_orders' => $total7DaysOrders,
                'peak_day' => $peakDayLabel,
                'peak_revenue' => $peakDayRevenue,
            ],
            'payment_methods' => [
                'qris_total' => $qrisTotal,
                'qris_count' => $qrisCount,
                'qris_pct' => $qrisPct,
                'cash_total' => $cashTotal,
                'cash_count' => $cashCount,
                'cash_pct' => $cashPct,
                'total_revenue' => $totalMethodRevenue,
                'total_count' => $totalMethodCount,
            ],
        ];
    }
}
