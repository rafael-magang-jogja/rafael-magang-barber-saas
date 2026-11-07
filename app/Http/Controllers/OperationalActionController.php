<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
use App\Services\CustomerWalletService;
use App\Services\QueueService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class OperationalActionController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly BookingService $bookingService,
        private readonly CheckoutService $checkoutService,
        private readonly BranchRepository $branchRepository,
        private readonly StaffRepository $staffRepository,
        private readonly PlatformRepository $platformRepository,
        private readonly CustomerWalletService $customerWalletService,
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
                isCashierInput: true,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        if ($request->hasSession()) {
            $request->session()->push('guest_ticket_ids', $ticket->id);
        }

        $targetUrl = $request->boolean('pay_now', false)
            ? route('cashier.tickets.payment', $ticket)
            : route('cashier.dashboard');

        return redirect()
            ->to($targetUrl)
            ->with('status', "Tiket antrean {$ticket->number} berhasil dibuat. Silakan proses pembayaran pelanggan terlebih dahulu sebelum layanan dimulai.");
    }

    public function checkInBooking(Request $request, Booking $booking): RedirectResponse
    {
        $hairColorId = $request->filled('hair_color_id') ? $request->integer('hair_color_id') : null;

        try {
            $ticket = $this->queueService->checkInBooking($booking, $request->user()?->id, $hairColorId);
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        $targetUrl = $request->boolean('pay_now', false)
            ? route('cashier.tickets.payment', $ticket)
            : back();

        return redirect()
            ->to($targetUrl)
            ->with('status', "Booking {$booking->code} berhasil check-in dengan nomor antrean {$ticket->number}. Silakan proses pembayaran pelanggan terlebih dahulu.");
    }

    public function cancelBooking(Request $request, Booking $booking): RedirectResponse
    {
        $action = (string) $request->input('action', 'cancel');
        $targetStatus = $action === 'no_show' ? BookingStatus::NoShow : BookingStatus::Cancelled;
        $reason = $request->input('reason', $action === 'no_show' ? 'Pelanggan tidak hadir' : 'Dibatalkan oleh kasir');

        $isPaid = $booking->xendit_paid_at !== null
            || $booking->payment_approved_at !== null
            || in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed], true)
            || (! empty($booking->payment_proof_path) && in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true));

        $refundUpdates = [];
        if ($targetStatus === BookingStatus::Cancelled && $isPaid) {
            $customerUser = $booking->customerProfile?->user ?? $booking->user;
            $refundAmount = (int) $booking->estimated_total;

            if ($customerUser !== null && $refundAmount > 0) {
                $this->customerWalletService->credit(
                    user: $customerUser,
                    amount: $refundAmount,
                    category: 'refund_booking',
                    referenceId: $booking->code,
                    notes: "Pengembalian dana pembatalan booking {$booking->code} oleh kasir",
                    tenantId: $booking->tenant_id,
                    profile: $booking->customerProfile
                );

                $refundUpdates = [
                    'refund_status' => 'completed',
                    'refund_amount' => $refundAmount,
                    'refund_requested_at' => now(),
                    'refunded_at' => now(),
                    'refund_notes' => 'Otomatis masuk ke Saldo Dompet Akun Customer',
                ];
            }
        }

        $booking->update(array_merge([
            'status' => $targetStatus,
            'notes' => $booking->notes ? $booking->notes . " [{$reason}]" : $reason,
        ], $refundUpdates));

        if ($booking->queueTicket !== null && ! in_array($booking->queueTicket->status, [QueueStatus::Completed, QueueStatus::Cancelled, QueueStatus::NoShow], true)) {
            $this->queueService->transitionStatus(
                ticket: $booking->queueTicket,
                targetStatus: $targetStatus === BookingStatus::NoShow ? QueueStatus::NoShow : QueueStatus::Cancelled,
                actorUserId: $request->user()?->id,
                reason: $reason,
            );

            if (! empty($refundUpdates)) {
                $booking->queueTicket->update($refundUpdates);
            }
        }

        $msg = $targetStatus === BookingStatus::NoShow
            ? "Booking {$booking->code} berhasil ditandai Tidak Hadir (No-Show)."
            : "Booking {$booking->code} berhasil dibatalkan." . (! empty($refundUpdates) ? " Pengembalian dana otomatis masuk ke Saldo Dompet Customer." : "");

        return back()->with('status', $msg);
    }

    public function approveBookingPayment(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->queueTicket !== null) {
            return back()->with('status', "Booking {$booking->code} sudah memiliki tiket antrean {$booking->queueTicket->number}.");
        }

        $hairColorId = $request->filled('hair_color_id') ? $request->integer('hair_color_id') : null;
        $isToday = $booking->scheduled_at->isToday();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $booking, $hairColorId, $isToday): RedirectResponse {
            $booking->update([
                'status' => BookingStatus::Confirmed,
                'payment_approved_at' => now(),
                'payment_approved_by' => $request->user()?->id,
                'payment_rejected_reason' => null,
            ]);

            // Jika jadwal booking adalah HARI INI: Langsung terbitkan tiket antrean & Order lunas
            if ($isToday) {
                try {
                    $ticket = $this->queueService->checkInBooking($booking, $request->user()?->id, $hairColorId);
                } catch (DomainException $e) {
                    return back()->withErrors(['queue' => $e->getMessage()]);
                }

                // Buat Order Lunas di muka via Transfer Rekening Bank
                $orderTotal = (int) $ticket->items->sum('price');
                $order = \App\Models\Order::query()->create([
                    'tenant_id' => $booking->tenant_id,
                    'branch_id' => $booking->branch_id,
                    'queue_ticket_id' => $ticket->id,
                    'customer_profile_id' => $booking->customer_profile_id,
                    'barber_membership_id' => $booking->barber_membership_id,
                    'number' => 'ORD-' . strtoupper(\Illuminate\Support\Str::random(10)),
                    'subtotal' => $orderTotal,
                    'discount_total' => 0,
                    'tip_amount' => 0,
                    'grand_total' => $orderTotal,
                    'paid_total' => $orderTotal,
                    'refunded_total' => 0,
                    'payment_status' => \App\Enums\PaymentStatus::Paid,
                    'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                    'paid_at' => now(),
                ]);

                foreach ($ticket->items as $item) {
                    \App\Models\OrderItem::query()->create([
                        'tenant_id' => $booking->tenant_id,
                        'order_id' => $order->id,
                        'service_id' => $item->service_id,
                        'barber_membership_id' => $booking->barber_membership_id,
                        'name' => $item->service_name,
                        'type' => 'service',
                        'qty' => 1,
                        'unit_price' => $item->price,
                        'discount' => 0,
                        'line_total' => $item->price,
                    ]);
                }

                \App\Models\Payment::query()->create([
                    'tenant_id' => $booking->tenant_id,
                    'order_id' => $order->id,
                    'amount' => $orderTotal,
                    'method' => PaymentMethod::Transfer->value,
                    'reference' => 'TRANSFER-REK-' . $booking->code,
                    'status' => 'success',
                    'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                ]);

                return back()->with('status', "Pembayaran booking {$booking->code} disetujui! Karena jadwal hari ini, tiket antrean {$ticket->number} otomatis diterbitkan dan masuk antrean sesuai jam {$booking->scheduled_at->format('H:i')} WIB.");
            }

            // Jika jadwal booking adalah HARI LAIN / MENDATANG: Tetap tersimpan sebagai confirmed dan akan otomatis masuk antrean saat hari H tiba
            return back()->with('status', "Pembayaran booking {$booking->code} disetujui! Booking berstatus Lunas dan akan otomatis masuk ke antrean pada hari H ({$booking->scheduled_at->translatedFormat('d M Y')}) sesuai jam {$booking->scheduled_at->format('H:i')} WIB.");
        });
    }

    public function rejectBookingPayment(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Alasan penolakan bukti pembayaran wajib diisi.',
        ]);

        if (! empty($booking->payment_proof_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($booking->payment_proof_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($booking->payment_proof_path);
        }

        $booking->update([
            'payment_proof_path' => null,
            'payment_proof_uploaded_at' => null,
            'payment_approved_at' => null,
            'payment_approved_by' => null,
            'payment_rejected_reason' => $validated['reason'],
        ]);

        return back()->with('status', "Bukti transfer booking {$booking->code} telah ditolak dengan alasan: {$validated['reason']}. Pelanggan dapat mengunggah bukti baru.");
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

        // Validasi: Sebelum pengerjaan ('in_service') dimulai, pembayaran wajib sudah Lunas
        if ($target === QueueStatus::InService) {
            $ticket->load('order');
            $isPaid = $ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid;

            if (! $isPaid && ! $request->boolean('bypass_payment')) {
                return back()->withErrors([
                    'queue' => "Tiket {$ticket->number} belum lunas. Sesuai ketentuan operasional, pembayaran wajib diselesaikan di kasir atau via QRIS sebelum layanan cukur dimulai.",
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

        if (! $request->user()) {
            return redirect()
                ->to($tenant->loginUrl())
                ->with('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan reservasi booking jadwal.');
        }

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
            ->route('customer.bookings.show', $booking)
            ->with('status', "Booking {$booking->code} berhasil dikonfirmasi untuk ".$booking->scheduled_at->format('d M H:i').'.');
    }

    public function processTicketRefund(Request $request, QueueTicket $ticket): RedirectResponse
    {
        $ticket->load(['order.payments', 'tenant', 'branch']);

        if ($ticket->refund_status !== 'pending') {
            return back()->withErrors(['refund' => 'Antrean ini tidak memiliki permohonan refund yang menunggu persetujuan.']);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', Rule::in(['transfer', 'cash', 'qris', 'ewallet'])],
        ]);

        $refundMethod = $validated['method'] ?? 'transfer';
        $refundAmount = $ticket->refund_amount;

        DB::transaction(function () use ($ticket, $request, $validated, $refundMethod, $refundAmount): void {
            $ticket->update([
                'refund_status' => 'completed',
                'refunded_at' => now(),
                'refunded_by' => $request->user()?->id,
                'refund_notes' => $validated['notes'] ?? 'Pengembalian dana telah ditransfer ke rekening pelanggan.',
            ]);

            if ($ticket->order !== null) {
                $ticket->order->update([
                    'refunded_total' => $refundAmount,
                    'payment_status' => PaymentStatus::Refunded,
                    'notes' => trim(($ticket->order->notes ?? '') . ' [Refund berhasil diselesaikan]'),
                ]);

                \App\Models\Payment::query()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'order_id' => $ticket->order->id,
                    'type' => 'refund',
                    'method' => $refundMethod,
                    'amount' => $refundAmount,
                    'status' => 'success',
                    'reason' => 'Pengembalian dana pembatalan antrean ' . $ticket->number . (!empty($validated['notes']) ? ': ' . $validated['notes'] : ''),
                    'idempotency_key' => 'refund-ticket-' . $ticket->id . '-' . (string) \Illuminate\Support\Str::uuid(),
                    'received_by' => $request->user()?->id,
                    'created_at' => now(),
                ]);
            }

            if ($ticket->booking_id !== null) {
                Booking::query()
                    ->whereKey($ticket->booking_id)
                    ->update([
                        'refund_status' => 'completed',
                        'refunded_at' => now(),
                        'refunded_by' => $request->user()?->id,
                        'refund_notes' => $validated['notes'] ?? 'Pengembalian dana telah ditransfer ke rekening pelanggan.',
                    ]);
            }
        });

        return back()->with('status', "Pengembalian dana tiket {$ticket->number} sebesar Rp" . number_format($refundAmount, 0, ',', '.') . ' berhasil diselesaikan.');
    }

    public function processBookingRefund(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->refund_status !== 'pending') {
            return back()->withErrors(['refund' => 'Reservasi booking ini tidak memiliki permohonan refund yang menunggu persetujuan.']);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', Rule::in(['transfer', 'cash', 'qris', 'ewallet'])],
        ]);

        $refundMethod = $validated['method'] ?? 'transfer';
        $refundAmount = $booking->refund_amount;

        DB::transaction(function () use ($booking, $request, $validated, $refundMethod, $refundAmount): void {
            $booking->update([
                'refund_status' => 'completed',
                'refunded_at' => now(),
                'refunded_by' => $request->user()?->id,
                'refund_notes' => $validated['notes'] ?? 'Pengembalian dana telah ditransfer ke rekening pelanggan.',
            ]);

            if ($booking->queueTicket !== null) {
                $booking->queueTicket->update([
                    'refund_status' => 'completed',
                    'refunded_at' => now(),
                    'refunded_by' => $request->user()?->id,
                    'refund_notes' => $validated['notes'] ?? 'Pengembalian dana telah ditransfer ke rekening pelanggan.',
                ]);
            }
        });

        return back()->with('status', "Pengembalian dana reservasi booking {$booking->code} sebesar Rp" . number_format($refundAmount, 0, ',', '.') . ' berhasil diselesaikan.');
    }
}
