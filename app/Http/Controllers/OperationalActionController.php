<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\QueueStatus;
use App\Enums\WorkStatus;
use App\Http\Requests\Customer\CreateBookingRequest;
use App\Http\Requests\Customer\TakeQueueRequest;
use App\Http\Requests\Operational\CheckoutTicketRequest;
use App\Http\Requests\Operational\StoreWalkInRequest;
use App\Http\Requests\Operational\ToggleWorkStatusRequest;
use App\Http\Requests\Operational\TransitionTicketRequest;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Repositories\BranchRepository;
use App\Repositories\PlatformRepository;
use App\Repositories\StaffRepository;
use App\Services\BookingService;
use App\Services\CheckoutService;
use App\Services\QueueService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OperationalActionController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly BookingService $bookingService,
        private readonly CheckoutService $checkoutService,
        private readonly BranchRepository $branchRepository,
        private readonly StaffRepository $staffRepository,
        private readonly PlatformRepository $platformRepository,
    ) {}

    public function storeWalkIn(StoreWalkInRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $branch = ! empty($validated['branch_id'])
            ? $this->branchRepository->findByIdOrFail((int) $validated['branch_id'])
            : $this->branchRepository->getActiveBranches()->firstOrFail();

        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        if ($tenant !== null && $tenant->isBranchLocked($branch)) {
            return back()->withInput()->withErrors(['queue' => "Cabang {$branch->name} saat ini sedang dikunci karena melebihi kuota paket langganan Anda."]);
        }

        try {
            $ticket = $this->queueService->createWalkInTicket(
                branch: $branch,
                customerName: $validated['customer_name'],
                customerPhone: $validated['customer_phone'] ?? null,
                serviceIds: isset($validated['service_ids']) ? array_map('intval', $validated['service_ids']) : [],
                barberMembershipId: ! empty($validated['barber_membership_id']) ? (int) $validated['barber_membership_id'] : null,
                notes: $validated['notes'] ?? null,
                actorUserId: $request->user()?->id,
                hairColorId: ! empty($validated['hair_color_id']) ? (int) $validated['hair_color_id'] : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        if ($request->hasSession()) {
            $request->session()->push('guest_ticket_ids', $ticket->id);
        }

        return redirect()
            ->route('cashier.dashboard')
            ->with('status', "Tiket antrean {$ticket->number} berhasil dibuat untuk {$validated['customer_name']}.");
    }

    public function checkInBooking(Request $request, Booking $booking): RedirectResponse
    {
        $hairColorId = $request->filled('hair_color_id') ? $request->integer('hair_color_id') : null;

        try {
            $ticket = $this->queueService->checkInBooking($booking, $request->user()?->id, $hairColorId);
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->code} berhasil check-in dengan nomor antrean {$ticket->number}.");
    }

    public function cancelBooking(Request $request, Booking $booking): RedirectResponse
    {
        $action = (string) $request->input('action', 'cancel');
        $targetStatus = $action === 'no_show' ? BookingStatus::NoShow : BookingStatus::Cancelled;
        $reason = $request->input('reason', $action === 'no_show' ? 'Pelanggan tidak hadir' : 'Dibatalkan oleh kasir');

        $booking->update([
            'status' => $targetStatus,
            'notes' => $booking->notes ? $booking->notes . " [{$reason}]" : $reason,
        ]);

        $msg = $targetStatus === BookingStatus::NoShow
            ? "Booking {$booking->code} berhasil ditandai Tidak Hadir (No-Show)."
            : "Booking {$booking->code} berhasil dibatalkan.";

        return back()->with('status', $msg);
    }

    public function transitionTicket(TransitionTicketRequest $request, QueueTicket $ticket): RedirectResponse
    {
        $validated = $request->validated();
        $target = QueueStatus::from($validated['status']);
        $user = $request->user();

        // Validasi: Pemanggilan ('called') dan Mulai Cukur ('in_service') hanya boleh dilakukan oleh Staff Barber
        if (in_array($target, [QueueStatus::Called, QueueStatus::InService], true)) {
            $barberMembership = $this->staffRepository->findBarberMembershipForUser($user?->id);

            if ($barberMembership === null) {
                return back()->withErrors([
                    'queue' => 'Pemanggilan antrean dan proses pengerjaan layanan hanya dapat dilakukan oleh staff barber.',
                ]);
            }
        }

        $reason = $validated['cancel_reason'] ?? $validated['notes'] ?? null;

        try {
            $updated = $this->queueService->transitionStatus(
                ticket: $ticket,
                targetStatus: $target,
                barberMembershipId: isset($validated['barber_membership_id']) ? (int) $validated['barber_membership_id'] : null,
                actorUserId: $user?->id,
                reason: $reason,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return back()->with('status', "Tiket {$updated->number} diperbarui ke status {$updated->status->value}.");
    }

    public function recallTicket(Request $request, QueueTicket $ticket): RedirectResponse
    {
        try {
            $updated = $this->queueService->recallTicket(
                ticket: $ticket,
                actorUserId: $request->user()?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return back()->with('status', "Panggilan antrean {$updated->number} berhasil diulang ke layar TV display.");
    }

    public function skipTicket(Request $request, QueueTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->queueService->skipTicket(
                ticket: $ticket,
                actorUserId: $request->user()?->id,
                reason: $validated['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        $ticket = $result['ticket'];
        if ($result['is_cancelled']) {
            return back()->with('status', "Antrean {$ticket->number} dibatalkan otomatis karena 2x tidak hadir saat dipanggil. Kursi Anda siap untuk antrean berikutnya.");
        }

        return back()->with('status', "Antrean {$ticket->number} telah dimundurkan 1 posisi antrean (dilewati 1x). Kursi Anda siap memanggil antrean berikutnya.");
    }

    public function rescheduleTicket(Request $request, QueueTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'string'],
            'barber_membership_id' => ['nullable', 'exists:memberships,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $newDateTime = CarbonImmutable::parse($validated['date'] . ' ' . $validated['time']);
        } catch (\Exception $e) {
            return back()->withErrors(['queue' => 'Format tanggal atau jam tidak valid.']);
        }

        $user = $request->user();

        try {
            $updated = $this->queueService->rescheduleTicket(
                ticket: $ticket,
                newScheduledAt: $newDateTime,
                barberMembershipId: !empty($validated['barber_membership_id']) ? (int) $validated['barber_membership_id'] : null,
                reason: $validated['notes'] ?? null,
                actorUserId: $user?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        $customerName = $ticket->customerProfile?->name ?? 'Pelanggan';
        $formattedTime = $newDateTime->locale('id')->isoFormat('dddd, D MMMM Y [jam] HH:mm') . ' WIB';
        return back()->with('status', "Antrean {$ticket->number} ({$customerName}) berhasil di-reschedule ke {$formattedTime}. Urutan antrean otomatis diperbarui.");
    }

    public function callNextTicket(Request $request): RedirectResponse
    {
        $user = $request->user();
        $barberMembership = $this->staffRepository->findBarberMembershipForUser($user?->id);

        if ($barberMembership === null) {
            return back()->withErrors([
                'queue' => 'Pemanggilan antrean hanya dapat dilakukan oleh staff barber dari kursinya masing-masing.',
            ]);
        }

        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $today = now()->toDateString();

        $query = QueueTicket::query()
            ->whereDate('date', $today)
            ->where('status', QueueStatus::Waiting)
            ->orderBy('priority_at')
            ->orderBy('sequence');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $nextTicket = $query->first();

        if ($nextTicket === null) {
            return back()->withErrors(['queue' => 'Tidak ada antrean menunggu untuk dipanggil saat ini.']);
        }

        try {
            $updated = $this->queueService->transitionStatus(
                ticket: $nextTicket,
                targetStatus: QueueStatus::Called,
                barberMembershipId: null, // Otomatis dipilihkan staff barber yang ready
                actorUserId: $request->user()?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        $barberName = $updated->barber?->display_name ?? 'Barber';
        return back()->with('status', "Antrean {$updated->number} berhasil dipanggil dan otomatis ditugaskan ke Kursi {$barberName}.");
    }

    public function showPaymentPage(Request $request, QueueTicket $ticket): \Illuminate\View\View|RedirectResponse
    {
        $ticket->load([
            'tenant',
            'branch',
            'barber.user',
            'customerProfile',
            'items.service',
            'order.payments',
        ]);

        if ($ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid) {
            return redirect()->route('invoices.show', $ticket->order->number)
                ->with('status', "Tiket {$ticket->number} sudah lunas.");
        }

        $subtotal = (int) $ticket->items->sum('price');
        $grandTotal = $ticket->order !== null ? (int) $ticket->order->grand_total : $subtotal;

        return view('dashboards.cashier-payment', [
            'ticket' => $ticket,
            'tenant' => $ticket->tenant,
            'branch' => $ticket->branch,
            'barber' => $ticket->barber,
            'subtotal' => $subtotal,
            'grandTotal' => $grandTotal,
            'order' => $ticket->order,
        ]);
    }

    public function checkoutTicket(CheckoutTicketRequest $request, QueueTicket $ticket): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $order = $this->checkoutService->checkoutQueueTicket(
                ticket: $ticket,
                method: PaymentMethod::from($validated['method']),
                discountTotal: (int) ($validated['discount_total'] ?? 0),
                tipAmount: (int) ($validated['tip_amount'] ?? 0),
                cashReceived: isset($validated['cash_received']) ? (int) $validated['cash_received'] : null,
                reference: $validated['reference'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
                cashierUserId: $request->user()?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $order->number)
            ->with('status', "Pembayaran tiket {$ticket->number} berhasil diselesaikan (Invoice {$order->number}).");
    }

    public function confirmPayment(Request $request, QueueTicket $ticket): RedirectResponse
    {
        $ticket->load('order.payments');

        if ($ticket->order === null) {
            return back()->withErrors(['checkout' => 'Tiket ini belum memiliki rincian pesanan.']);
        }

        try {
            $order = $this->checkoutService->confirmOrderPayment(
                order: $ticket->order,
                cashierUserId: $request->user()?->id,
                cashReceived: $request->filled('cash_received')
                    ? (int) str_replace(['.', ',', ' '], '', (string) $request->input('cash_received'))
                    : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        $methodName = $order->payments->first()?->method->value === 'qris' ? 'QRIS' : 'Tunai';
        return redirect()->route('invoices.show', $order->number)
            ->with('status', "Pembayaran {$methodName} untuk tiket {$ticket->number} berhasil dikonfirmasi Lunas (Invoice {$order->number}).");
    }

    public function toggleBarberWorkStatus(ToggleWorkStatusRequest $request): RedirectResponse
    {
        $this->staffRepository->updateBarberWorkStatus(
            userId: $request->user()?->id,
            status: WorkStatus::from((string) $request->validated('work_status')),
        );

        return back()->with('status', 'Status kerja Anda berhasil diperbarui.');
    }

    public function updateChairWorkStatus(ToggleWorkStatusRequest $request, Membership $membership): RedirectResponse
    {
        $membership->update([
            'work_status' => WorkStatus::from((string) $request->validated('work_status')),
        ]);

        return back()->with('status', "Status kursi barber {$membership->display_name} berhasil diperbarui.");
    }

    public function customerTakeQueue(TakeQueueRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->platformRepository->findTenantBySlugOrFail($tenantSlug);

        if ($tenant->isSubscriptionLocked()) {
            return back()->withInput()->withErrors(['queue' => 'Layanan antrean online barbershop ini sedang dinonaktifkan sementara.']);
        }

        $validated = $request->validated();
        $branch = \App\Models\Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('id', $validated['branch_id'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($branch === null || $tenant->isBranchLocked($branch)) {
            return back()->withInput()->withErrors(['queue' => 'Cabang yang dipilih sedang tidak aktif atau dinonaktifkan sementara karena kuota paket.']);
        }

        if (! empty($validated['barber_membership_id'])) {
            $isBarberInBranch = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('id', $validated['barber_membership_id'])
                ->where('tenant_id', $tenant->id)
                ->where('role', \App\Enums\Role::Barber)
                ->where(function ($q) use ($branch): void {
                    $q->where('branch_id', $branch->id)
                        ->orWhereNull('branch_id');
                })
                ->exists();

            if (! $isBarberInBranch) {
                return back()->withInput()->withErrors(['queue' => 'Barber yang dipilih tidak bertugas di cabang ini.']);
            }
        }

        try {
            $ticket = $this->queueService->createWalkInTicket(
                branch: $branch,
                customerName: $validated['customer_name'],
                customerPhone: $validated['customer_phone'],
                serviceIds: array_map('intval', $validated['service_ids']),
                barberMembershipId: isset($validated['barber_membership_id']) ? (int) $validated['barber_membership_id'] : null,
                notes: $validated['notes'] ?? null,
                actorUserId: $request->user()?->id,
                hairColorId: ! empty($validated['hair_color_id']) ? (int) $validated['hair_color_id'] : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        if ($request->hasSession()) {
            $request->session()->push('guest_ticket_ids', $ticket->id);
        }

        return redirect()
            ->route('customer.tickets.show', $ticket->id)
            ->with('status', "Nomor antrean Anda adalah {$ticket->number}.");
    }

    public function customerCreateBooking(CreateBookingRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->platformRepository->findTenantBySlugOrFail($tenantSlug);

        if ($tenant->isSubscriptionLocked()) {
            return back()->withInput()->withErrors(['booking' => 'Layanan booking online barbershop ini sedang dinonaktifkan sementara.']);
        }

        $validated = $request->validated();
        $branch = \App\Models\Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('id', $validated['branch_id'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($branch === null || $tenant->isBranchLocked($branch)) {
            return back()->withInput()->withErrors(['booking' => 'Cabang yang dipilih sedang tidak aktif atau dinonaktifkan sementara karena kuota paket.']);
        }

        if (! empty($validated['barber_membership_id'])) {
            $isBarberInBranch = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('id', $validated['barber_membership_id'])
                ->where('tenant_id', $tenant->id)
                ->where('role', \App\Enums\Role::Barber)
                ->where(function ($q) use ($branch): void {
                    $q->where('branch_id', $branch->id)
                        ->orWhereNull('branch_id');
                })
                ->exists();

            if (! $isBarberInBranch) {
                return back()->withInput()->withErrors(['booking' => 'Barber yang dipilih tidak bertugas di cabang ini.']);
            }
        }

        try {
            $booking = $this->bookingService->createBooking(
                branch: $branch,
                customerName: $validated['customer_name'],
                customerPhone: $validated['customer_phone'],
                serviceIds: array_map('intval', $validated['service_ids']),
                scheduledAt: CarbonImmutable::parse($validated['scheduled_at']),
                barberMembershipId: isset($validated['barber_membership_id']) ? (int) $validated['barber_membership_id'] : null,
                notes: $validated['notes'] ?? null,
                userId: $request->user()?->id,
                hairColorId: ! empty($validated['hair_color_id']) ? (int) $validated['hair_color_id'] : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        if ($request->hasSession()) {
            $request->session()->push('guest_booking_ids', $booking->id);
        }

        return redirect()
            ->route('customer.bookings.show', $booking->id)
            ->with('status', "Booking {$booking->code} berhasil dikonfirmasi untuk ".$booking->scheduled_at->format('d M H:i').'.');
    }
}
