<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Service;
use App\Repositories\BookingRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\CustomerRepository;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Cache;
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
                    'status' => BookingStatus::Confirmed,
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
}
