<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\BarberSchedule;
use App\Models\BarberTimeOff;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Service;
use App\Repositories\BookingRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\CustomerRepository;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly CatalogRepository $catalogRepository,
        private readonly CustomerRepository $customerRepository,
    ) {}

    /**
     * Create a customer booking with overlap validation per barber.
     *
     * @param  array<int, int>  $serviceIds
     */
    public function createBooking(
        Branch $branch,
        string $customerName,
        string $customerPhone,
        array $serviceIds,
        CarbonImmutable $scheduledAt,
        ?int $barberMembershipId = null,
        ?string $notes = null,
        ?int $userId = null,
        ?int $hairColorId = null,
    ): Booking {
        return DB::transaction(function () use (
            $branch,
            $customerName,
            $customerPhone,
            $serviceIds,
            $scheduledAt,
            $barberMembershipId,
            $notes,
            $userId,
            $hairColorId,
        ): Booking {
            $services = $this->catalogRepository->getActiveServicesByIdsForTenant($branch->tenant_id, $serviceIds);

            if ($services->isEmpty()) {
                throw new DomainException('Pilih minimal 1 layanan untuk dibooking.');
            }

            $hasColoringService = $services->contains(
                fn (Service $s): bool => stripos($s->name, 'color') !== false
                    || stripos($s->name, 'warna') !== false
                    || ($s->category && (stripos($s->category->name, 'warna') !== false || stripos($s->category->name, 'color') !== false))
            );

            $selectedColor = null;
            if ($hasColoringService && $hairColorId !== null) {
                $selectedColor = $this->catalogRepository->findAvailableHairColorForTenant(
                    tenantId: $branch->tenant_id,
                    hairColorId: $hairColorId,
                );
            }

            $duration = (int) $services->sum('duration_minutes');
            if ($duration <= 0) {
                $duration = 30;
            }

            // Validasi jadwal operasional cabang, shift barber, jam istirahat, dan batasan waktu
            $this->validateBookingSchedule(
                branch: $branch,
                scheduledAt: $scheduledAt,
                durationMinutes: $duration,
                barberMembershipId: $barberMembershipId,
            );

            $endsAt = $scheduledAt->addMinutes($duration);

            if ($barberMembershipId !== null) {
                $conflict = $this->bookingRepository->hasOverlappingSlotForBarber(
                    tenantId: $branch->tenant_id,
                    barberMembershipId: $barberMembershipId,
                    startsAt: $scheduledAt,
                    endsAt: $endsAt,
                );

                if ($conflict) {
                    throw new DomainException('Slot waktu barber tersebut sudah terisi. Silakan pilih jam atau barber lain.');
                }
            }

            $customerProfile = $this->customerRepository->firstOrCreateByPhone(
                tenantId: $branch->tenant_id,
                phone: $customerPhone,
                name: $customerName,
                userId: $userId,
            );

            $code = sprintf('BK-%s-%s', $branch->code, strtoupper(Str::random(4)));
            $subtotal = (int) $services->sum('price');

            $booking = Booking::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'customer_profile_id' => $customerProfile->id,
                    'user_id' => $userId,
                    'barber_membership_id' => $barberMembershipId,
                    'hair_color_id' => $selectedColor?->id,
                    'code' => $code,
                    'scheduled_at' => $scheduledAt,
                    'estimated_end_at' => $endsAt,
                    'total_duration_minutes' => $duration,
                    'estimated_total' => $subtotal,
                    'status' => BookingStatus::Pending,
                    'source' => 'online',
                    'notes' => $notes,
                ]);

            /** @var Service $service */
            foreach ($services as $service) {
                $isColorService = stripos($service->name, 'color') !== false
                    || stripos($service->name, 'warna') !== false
                    || ($service->category && (stripos($service->category->name, 'warna') !== false || stripos($service->category->name, 'color') !== false));
                $itemName = ($isColorService && $selectedColor !== null)
                    ? "{$service->name} ({$selectedColor->name})"
                    : $service->name;

                BookingItem::query()->create([
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'service_name' => $itemName,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                ]);
            }

            return $booking->load(['items', 'customerProfile', 'barber']);
        });
    }

    /**
     * Memvalidasi tanggal & jam booking berdasarkan:
     * 1. Hari libur/tutup cabang.
     * 2. Jam buka/tutup cabang (+15 menit setelah buka, -15 menit sebelum tutup).
     * 3. Shift kerja barber (tidak boleh di luar shift).
     * 4. Batasan 1 jam sebelum shift tutup.
     * 5. Jam istirahat barber & cabang serta batasan 1 jam sebelum istirahat.
     * 6. Cuti/libur barber (BarberTimeOff & BarberSchedule::is_off).
     *
     * @throws DomainException
     */
    public function validateBookingSchedule(
        Branch $branch,
        CarbonImmutable $scheduledAt,
        int $durationMinutes,
        ?int $barberMembershipId = null,
    ): void {
        $dayName = $this->getDayName($scheduledAt->dayOfWeek);
        $branchHours = $this->getBranchDayHours($branch, $scheduledAt);

        // 1. Pengecekan apakah cabang tutup pada hari tersebut
        if (! $branchHours['is_open']) {
            throw new DomainException("Cabang {$branch->name} tutup pada hari {$dayName} ({$scheduledAt->translatedFormat('d F Y')}).");
        }

        $dateStr = $scheduledAt->format('Y-m-d');
        $branchOpenAt = CarbonImmutable::parse($dateStr.' '.$branchHours['open']);
        $branchCloseAt = CarbonImmutable::parse($dateStr.' '.$branchHours['close']);

        // 2. Pembatasan 15 menit setelah cabang buka
        $earliestBookingAt = $branchOpenAt->addMinutes(15);
        if ($scheduledAt->lt($earliestBookingAt)) {
            throw new DomainException(
                "Booking paling awal adalah pukul {$earliestBookingAt->format('H:i')} (minimal 15 menit setelah cabang buka pukul {$branchHours['open']})."
            );
        }

        // 3. Pembatasan 15 menit sebelum cabang tutup
        $latestBookingAt = $branchCloseAt->subMinutes(15);
        if ($scheduledAt->gt($latestBookingAt)) {
            throw new DomainException(
                "Booking paling lambat adalah pukul {$latestBookingAt->format('H:i')} (minimal 15 menit sebelum cabang tutup pukul {$branchHours['close']})."
            );
        }

        // 4. Durasi layanan tidak boleh melebihi jam tutup cabang
        $serviceEndAt = $scheduledAt->addMinutes($durationMinutes);
        if ($serviceEndAt->gt($branchCloseAt)) {
            throw new DomainException(
                "Estimasi selesai layanan (pukul {$serviceEndAt->format('H:i')}) melebihi jam tutup cabang (pukul {$branchHours['close']})."
            );
        }

        // 5. Pengecekan jam istirahat cabang & batasan 1 jam sebelum serta 15 menit setelah istirahat
        if (! empty($branchHours['break_start']) && ! empty($branchHours['break_end'])) {
            $branchBreakStart = CarbonImmutable::parse($dateStr.' '.$branchHours['break_start']);
            $branchBreakEnd = CarbonImmutable::parse($dateStr.' '.$branchHours['break_end']);

            $restrictedBreakStart = $branchBreakStart->subHour();
            $restrictedBreakEnd = $branchBreakEnd->addMinutes(15);
            if ($scheduledAt->gte($restrictedBreakStart) && $scheduledAt->lte($restrictedBreakEnd)) {
                throw new DomainException(
                    "Waktu booking pukul {$scheduledAt->format('H:i')} tidak tersedia karena jam istirahat cabang ({$branchHours['break_start']} - {$branchHours['break_end']}) atau berada dalam batas 1 jam sebelum dan 15 menit setelah istirahat."
                );
            }
        }

        // 6. Pengecekan jika barber spesifik dipilih
        if ($barberMembershipId !== null) {
            $barberMembership = Membership::query()
                ->withoutGlobalScope('tenant')
                ->with('user')
                ->find($barberMembershipId);

            $barberName = $barberMembership?->display_name ?? $barberMembership?->user?->name ?? 'Barber';

            // Cek izin libur / cuti barber yang sudah diapprove
            if ($this->isBarberOnTimeOff($barberMembershipId, $scheduledAt)) {
                throw new DomainException("Barber {$barberName} sedang cuti atau izin libur pada tanggal {$scheduledAt->translatedFormat('d M Y')}.");
            }

            // Cek jadwal shift kerja & libur rutin barber
            $schedule = $this->getBarberDaySchedule($barberMembershipId, $scheduledAt, $branch);
            if ($schedule['is_off']) {
                $reason = $schedule['reason'] ?? 'Barber libur';
                throw new DomainException("Barber {$barberName} libur pada hari {$dayName} ({$reason}).");
            }

            $shiftStartAt = CarbonImmutable::parse($dateStr.' '.$schedule['start_time']);
            $shiftEndAt = CarbonImmutable::parse($dateStr.' '.$schedule['end_time']);

            // Melebihi shift: sebelum shift mulai
            if ($scheduledAt->lt($shiftStartAt)) {
                throw new DomainException(
                    "Jam booking ({$scheduledAt->format('H:i')}) berada di luar jam shift kerja {$barberName} (shift mulai pukul {$schedule['start_time']})."
                );
            }

            // Melebihi shift atau pembatasan 1 jam sebelum shift tutup
            $restrictedShiftEnd = $shiftEndAt->subHour();
            if ($scheduledAt->gte($restrictedShiftEnd) || $scheduledAt->gt($shiftEndAt)) {
                throw new DomainException(
                    "Jam booking ({$scheduledAt->format('H:i')}) tidak tersedia karena berada dalam batas 1 jam sebelum shift kerja {$barberName} berakhir (shift berakhir pukul {$schedule['end_time']})."
                );
            }

            if ($serviceEndAt->gt($shiftEndAt)) {
                throw new DomainException(
                    "Estimasi selesai layanan (pukul {$serviceEndAt->format('H:i')}) melebihi akhir shift kerja {$barberName} (pukul {$schedule['end_time']})."
                );
            }

            // Jam istirahat barber & pembatasan 1 jam sebelum serta 15 menit setelah istirahat barber
            if (! empty($schedule['break_start']) && ! empty($schedule['break_end'])) {
                $barberBreakStart = CarbonImmutable::parse($dateStr.' '.$schedule['break_start']);
                $barberBreakEnd = CarbonImmutable::parse($dateStr.' '.$schedule['break_end']);

                $restrictedBarberBreak = $barberBreakStart->subHour();
                $restrictedBarberBreakEnd = $barberBreakEnd->addMinutes(15);
                if ($scheduledAt->gte($restrictedBarberBreak) && $scheduledAt->lte($restrictedBarberBreakEnd)) {
                    throw new DomainException(
                        "Jam booking ({$scheduledAt->format('H:i')}) tidak tersedia karena jam istirahat {$barberName} ({$schedule['break_start']} - {$schedule['break_end']}) atau berada dalam batas 1 jam sebelum dan 15 menit setelah istirahat."
                    );
                }
            }
        } else {
            // 7. Jika tidak memilih barber tertentu ("Siapa Saja"), pastikan slot tidak 1 jam sebelum cabang tutup
            // dan setidaknya ada 1 barber aktif yang bertugas dan slotnya valid
            if ($scheduledAt->gte($branchCloseAt->subHour())) {
                throw new DomainException(
                    "Waktu booking pukul {$scheduledAt->format('H:i')} tidak tersedia karena berada dalam batas 1 jam sebelum operasional cabang berakhir (tutup pukul {$branchHours['close']})."
                );
            }

            $barbers = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $branch->tenant_id)
                ->where('role', Role::Barber)
                ->where('is_active', true)
                ->where(function ($q) use ($branch): void {
                    $q->where('branch_id', $branch->id)
                        ->orWhereNull('branch_id');
                })
                ->get();

            if ($barbers->isNotEmpty()) {
                $anyAvailable = false;
                foreach ($barbers as $b) {
                    try {
                        $this->validateBookingSchedule($branch, $scheduledAt, $durationMinutes, $b->id);
                        $hasConflict = $this->bookingRepository->hasOverlappingSlotForBarber(
                            tenantId: $branch->tenant_id,
                            barberMembershipId: $b->id,
                            startsAt: $scheduledAt,
                            endsAt: $serviceEndAt,
                        );
                        if (! $hasConflict) {
                            $anyAvailable = true;
                            break;
                        }
                    } catch (DomainException) {
                        // Barber ini sedang tidak tersedia pada slot ini, lanjutkan cek barber lain
                    }
                }

                if (! $anyAvailable) {
                    throw new DomainException(
                        "Tidak ada barber yang tersedia pada jam {$scheduledAt->format('H:i')} (seluruh barber sedang istirahat, di luar shift, atau slot waktu sudah penuh)."
                    );
                }
            }
        }
    }

    /**
     * Menghasilkan daftar slot jam yang tersedia dan terformat untuk dipilih di UI.
     *
     * @return array{
     *     date: string,
     *     day_name: string,
     *     is_open: bool,
     *     branch_open?: string,
     *     branch_close?: string,
     *     branch_break_start?: string|null,
     *     branch_break_end?: string|null,
     *     message: string,
     *     slots: array<int, array{time: string, is_available: bool, reason: string|null}>
     * }
     */
    public function getAvailableSlots(
        Branch $branch,
        CarbonImmutable $date,
        ?int $barberMembershipId = null,
        int $durationMinutes = 30,
    ): array {
        $branchHours = $this->getBranchDayHours($branch, $date);
        $dayName = $this->getDayName($date->dayOfWeek);

        if (! $branchHours['is_open']) {
            return [
                'date' => $date->toDateString(),
                'day_name' => $dayName,
                'is_open' => false,
                'message' => "Cabang {$branch->name} tutup pada hari {$dayName}.",
                'slots' => [],
            ];
        }

        if ($barberMembershipId !== null) {
            $barberSched = $this->getBarberDaySchedule($barberMembershipId, $date, $branch);
            if ($barberSched['is_off']) {
                $reason = $barberSched['reason'] ?? 'Barber libur';

                return [
                    'date' => $date->toDateString(),
                    'day_name' => $dayName,
                    'is_open' => false,
                    'message' => "Barber tidak bertugas pada hari {$dayName} ({$reason}).",
                    'slots' => [],
                ];
            }
        }

        $dateStr = $date->format('Y-m-d');
        $openAt = CarbonImmutable::parse($dateStr.' '.$branchHours['open']);
        $closeAt = CarbonImmutable::parse($dateStr.' '.$branchHours['close']);

        // Mulai dari 15 menit setelah buka
        $current = $openAt->addMinutes(15);
        $now = now();
        $slots = [];

        // Generate slot setiap 30 menit
        $intervalMinutes = 30;

        while ($current->lt($closeAt)) {
            $timeStr = $current->format('H:i');
            $isAvailable = true;
            $reason = null;

            try {
                $this->validateBookingSchedule($branch, $current, $durationMinutes, $barberMembershipId);

                if ($barberMembershipId !== null) {
                    $hasConflict = $this->bookingRepository->hasOverlappingSlotForBarber(
                        tenantId: $branch->tenant_id,
                        barberMembershipId: $barberMembershipId,
                        startsAt: $current,
                        endsAt: $current->addMinutes($durationMinutes),
                    );
                    if ($hasConflict) {
                        $isAvailable = false;
                        $reason = 'Sudah terisi';
                    }
                }

                if ($date->isToday() && $current->lte($now)) {
                    $isAvailable = false;
                    $reason = 'Waktu telah lewat';
                }
            } catch (DomainException $e) {
                $isAvailable = false;
                $reason = $e->getMessage();
            }

            $slots[] = [
                'time' => $timeStr,
                'is_available' => $isAvailable,
                'reason' => $reason,
            ];

            $current = $current->addMinutes($intervalMinutes);
        }

        return [
            'date' => $date->toDateString(),
            'day_name' => $dayName,
            'is_open' => true,
            'branch_open' => $branchHours['open'],
            'branch_close' => $branchHours['close'],
            'branch_break_start' => $branchHours['break_start'] ?? null,
            'branch_break_end' => $branchHours['break_end'] ?? null,
            'message' => "Jam operasional: {$branchHours['open']} - {$branchHours['close']}".(! empty($branchHours['break_start']) ? " (Istirahat: {$branchHours['break_start']} - {$branchHours['break_end']})" : ''),
            'slots' => $slots,
        ];
    }

    /**
     * Mendapatkan jam operasional cabang pada hari tertentu.
     *
     * @return array{is_open: bool, open: string, close: string, break_start?: string|null, break_end?: string|null}
     */
    public function getBranchDayHours(Branch $branch, CarbonImmutable $date): array
    {
        $openingHours = $branch->opening_hours ?? [];
        if (empty($openingHours)) {
            return [
                'is_open' => true,
                'open' => '09:00',
                'close' => '21:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
            ];
        }

        $dayNum = $date->dayOfWeek; // 0 (Sun) .. 6 (Sat)
        $dayCode = strtolower($date->format('D')); // 'sun' .. 'sat'

        $dayConfig = $openingHours[$dayNum]
            ?? $openingHours[(string) $dayNum]
            ?? $openingHours[$dayCode]
            ?? null;

        if ($dayConfig === null) {
            return [
                'is_open' => true,
                'open' => '09:00',
                'close' => '21:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
            ];
        }

        $isOpen = true;
        if (isset($dayConfig['is_open'])) {
            $isOpen = filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN) || (string) $dayConfig['is_open'] === '1';
        }

        return [
            'is_open' => $isOpen,
            'open' => ! empty($dayConfig['open']) ? substr((string) $dayConfig['open'], 0, 5) : '09:00',
            'close' => ! empty($dayConfig['close']) ? substr((string) $dayConfig['close'], 0, 5) : '21:00',
            'break_start' => ! empty($dayConfig['break_start']) ? substr((string) $dayConfig['break_start'], 0, 5) : '12:00',
            'break_end' => ! empty($dayConfig['break_end']) ? substr((string) $dayConfig['break_end'], 0, 5) : '13:00',
        ];
    }

    /**
     * Mendapatkan jadwal shift kerja & libur barber pada hari tertentu.
     *
     * @return array{is_off: bool, reason?: string|null, start_time?: string, end_time?: string, break_start?: string|null, break_end?: string|null}
     */
    public function getBarberDaySchedule(int $barberMembershipId, CarbonImmutable $date, Branch $branch): array
    {
        // 1. Cek izin libur / cuti / sakit yang sudah disetujui
        if ($this->isBarberOnTimeOff($barberMembershipId, $date)) {
            return [
                'is_off' => true,
                'reason' => 'Barber sedang cuti atau izin libur',
            ];
        }

        // 2. Cek BarberSchedule mingguan
        $dayNum = $date->dayOfWeek; // 0..6
        $sched = BarberSchedule::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $barberMembershipId)
            ->where('day_of_week', $dayNum)
            ->first();

        if ($sched !== null) {
            if ($sched->is_off) {
                return [
                    'is_off' => true,
                    'reason' => 'Barber libur rutin',
                ];
            }

            return [
                'is_off' => false,
                'start_time' => substr((string) ($sched->start_time ?? '09:00'), 0, 5),
                'end_time' => substr((string) ($sched->end_time ?? '21:00'), 0, 5),
                'break_start' => ! empty($sched->break_start) ? substr((string) $sched->break_start, 0, 5) : null,
                'break_end' => ! empty($sched->break_end) ? substr((string) $sched->break_end, 0, 5) : null,
            ];
        }

        // 3. Fallback jika tidak ada schedule spesifik, ikuti jam cabang
        $branchHours = $this->getBranchDayHours($branch, $date);
        if (! $branchHours['is_open']) {
            return [
                'is_off' => true,
                'reason' => 'Cabang tutup',
            ];
        }

        return [
            'is_off' => false,
            'start_time' => $branchHours['open'],
            'end_time' => $branchHours['close'],
            'break_start' => $branchHours['break_start'] ?? null,
            'break_end' => $branchHours['break_end'] ?? null,
        ];
    }

    /**
     * Memeriksa apakah barber sedang cuti / izin libur yang sudah disetujui.
     */
    public function isBarberOnTimeOff(int $barberMembershipId, CarbonImmutable $date): bool
    {
        $dateStr = $date->toDateString();

        return BarberTimeOff::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $barberMembershipId)
            ->where('status', 'approved')
            ->whereDate('date_from', '<=', $dateStr)
            ->whereDate('date_to', '>=', $dateStr)
            ->exists();
    }

    /**
     * Nama hari dalam bahasa Indonesia berdasarkan dayOfWeek (0 = Minggu ... 6 = Sabtu).
     */
    public function getDayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };
    }
}
