<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\HairColor;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Review;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CheckoutService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class CustomerPortalController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function dashboard(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Ambil antrean aktif (jika ada yang sedang menunggu / dipanggil / dilayani)
        $activeTicket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items'])
            ->where(function ($query) use ($user): void {
                $query->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                if (! empty($user->phone)) {
                    $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                }
            })
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->latest('id')
            ->first();

        // 2. Jika tidak ada antrean aktif, tampilkan HANYA 1 riwayat terbaru saja (sesuai request)
        $latestHistory = null;
        if ($activeTicket === null) {
            $latestHistory = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order'])
                ->where(function ($query) use ($user): void {
                    $query->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                    if (! empty($user->phone)) {
                        $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                    }
                })
                ->latest('id')
                ->first();
        }

        // 3. Daftar Barbershop yang aktif untuk pilihan cepat / referensi
        $tenants = Tenant::query()
            ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
            ->with(['branches' => fn ($b) => $b->where('is_active', true)])
            ->get();

        return view('customer.dashboard', [
            'user' => $user,
            'activeTicket' => $activeTicket,
            'latestHistory' => $latestHistory,
            'tenants' => $tenants,
        ]);
    }

    public function booking(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $tenants = Tenant::query()
            ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
            ->with(['branches' => fn ($b) => $b->where('is_active', true)])
            ->get();

        $selectedTenantId = $request->integer('tenant_id') ?: $tenants->first()?->id;
        $selectedTenant = $tenants->firstWhere('id', $selectedTenantId) ?? $tenants->first();

        $branches = $selectedTenant ? $selectedTenant->branches : collect();
        $selectedBranchId = $request->integer('branch_id');
        if (! $branches->contains('id', $selectedBranchId)) {
            $selectedBranchId = $branches->first()?->id;
        }

        $services = collect();
        $hairColors = collect();
        $barbers = collect();

        if ($selectedTenant !== null) {
            $services = Service::query()
                ->withoutGlobalScope('tenant')
                ->with('category')
                ->where('tenant_id', $selectedTenant->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $hairColors = HairColor::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $selectedTenant->id)
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->orderBy('name')
                ->get();

            $barbersQuery = Membership::query()
                ->withoutGlobalScope('tenant')
                ->with(['user', 'branch'])
                ->where('tenant_id', $selectedTenant->id)
                ->where('role', Role::Barber)
                ->where('is_active', true);

            if ($selectedBranchId !== null) {
                $barbersQuery->where(function ($q) use ($selectedBranchId): void {
                    $q->where('branch_id', $selectedBranchId)
                        ->orWhereNull('branch_id');
                });
            }

            $barbers = $barbersQuery->get();
        }

        return view('customer.booking', [
            'user' => $user,
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'services' => $services,
            'hairColors' => $hairColors,
            'barbers' => $barbers,
        ]);
    }

    public function storeBooking(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'hair_color_id' => ['nullable', 'integer', 'exists:hair_colors,id'],
            'barber_membership_id' => ['nullable', 'integer', 'exists:memberships,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'tenant_id.required' => 'Pilih barbershop terlebih dahulu.',
            'branch_id.required' => 'Pilih cabang barbershop terlebih dahulu.',
            'service_ids.required' => 'Pilih minimal satu layanan.',
            'scheduled_at.required' => 'Tentukan jadwal kedatangan booking.',
            'scheduled_at.after' => 'Jadwal booking harus di masa depan.',
        ]);

        $branch = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('id', $validated['branch_id'])
            ->where('tenant_id', $validated['tenant_id'])
            ->first();

        if ($branch === null) {
            return back()->withInput()->withErrors([
                'branch_id' => 'Cabang yang dipilih tidak sesuai dengan barbershop ini.',
            ]);
        }

        // Validasi layanan harus milik tenant yang dipilih
        $validServiceCount = Service::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $validated['tenant_id'])
            ->whereIn('id', $validated['service_ids'])
            ->count();

        if ($validServiceCount !== count($validated['service_ids'])) {
            return back()->withInput()->withErrors([
                'service_ids' => 'Layanan yang dipilih tidak sesuai dengan barbershop ini.',
            ]);
        }

        // Validasi warna jika diisi
        if (! empty($validated['hair_color_id'])) {
            $validColor = HairColor::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $validated['tenant_id'])
                ->where('id', $validated['hair_color_id'])
                ->exists();

            if (! $validColor) {
                return back()->withInput()->withErrors([
                    'hair_color_id' => 'Pilihan warna tidak sesuai dengan barbershop ini.',
                ]);
            }
        }

        // Validasi barber jika diisi
        if (! empty($validated['barber_membership_id'])) {
            $validBarber = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $validated['tenant_id'])
                ->where('id', $validated['barber_membership_id'])
                ->where('role', Role::Barber)
                ->where(function ($q) use ($branch): void {
                    $q->where('branch_id', $branch->id)
                        ->orWhereNull('branch_id');
                })
                ->exists();

            if (! $validBarber) {
                return back()->withInput()->withErrors([
                    'barber_membership_id' => 'Barber yang dipilih tidak bertugas di cabang ini.',
                ]);
            }
        }

        try {
            $booking = $this->bookingService->createBooking(
                branch: $branch,
                customerName: $user->name,
                customerPhone: $user->phone ?? '0800000000',
                serviceIds: array_map('intval', $validated['service_ids']),
                scheduledAt: CarbonImmutable::parse($validated['scheduled_at']),
                barberMembershipId: ! empty($validated['barber_membership_id']) ? (int) $validated['barber_membership_id'] : null,
                notes: $validated['notes'] ?? null,
                userId: $user->id,
                hairColorId: ! empty($validated['hair_color_id']) ? (int) $validated['hair_color_id'] : null,
            );

            return redirect()->route('customer.bookings.show', $booking->id)->with('status', "Booking berhasil dikonfirmasi! Kode Booking: {$booking->code}");
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }
    }

    public function history(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $tickets = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order'])
            ->where(function ($query) use ($user): void {
                $query->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                if (! empty($user->phone)) {
                    $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                }
            })
            ->latest('id')
            ->get();

        $bookings = Booking::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'queueTicket.order'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                if (! empty($user->phone)) {
                    $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                }
            })
            ->latest('scheduled_at')
            ->get();

        return view('customer.history', [
            'user' => $user,
            'tickets' => $tickets,
            'bookings' => $bookings,
        ]);
    }

    public function profile(Request $request): View
    {
        return view('customer.profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:25', Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'phone.unique' => 'Nomor telepon sudah digunakan akun lain.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $user->name = trim($validated['name']);
        $user->phone = trim($validated['phone']);
        $user->email = ! empty($validated['email']) ? trim($validated['email']) : null;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('status', 'Profil Anda berhasil diperbarui.');
    }

    public function showTicket(Request $request, int|string $ticketId): View|JsonResponse
    {
        $ticket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order.payments', 'order.review', 'customerProfile'])
            ->findOrFail($ticketId);

        $this->authorizeCustomerAccessToTicket($request, $ticket);

        if ($request->wantsJson() || $request->query('poll') === '1') {
            return response()->json([
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'barber_name' => $ticket->barber?->display_name,
                'barber_specialty' => $ticket->barber?->specialty,
                'is_completed' => $ticket->status === QueueStatus::Completed,
                'is_paid' => $ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid,
                'invoice_url' => $ticket->order ? route('invoices.show', $ticket->order->number) : null,
                'checkout_url' => route('customer.tickets.checkout', $ticket->id),
            ]);
        }

        return view('customer.ticket-detail', [
            'ticket' => $ticket,
            'tenant' => $ticket->tenant,
            'branch' => $ticket->branch,
            'barber' => $ticket->barber,
            'order' => $ticket->order,
            'review' => $ticket->order?->review,
            'totalPrice' => (int) $ticket->items->sum('price'),
        ]);
    }

    public function showBooking(Request $request, int|string $bookingId): View
    {
        $booking = Booking::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'queueTicket.order'])
            ->findOrFail($bookingId);

        $this->authorizeCustomerAccessToBooking($request, $booking);

        return view('customer.booking-detail', [
            'booking' => $booking,
            'tenant' => $booking->tenant,
            'branch' => $booking->branch,
            'barber' => $booking->barber,
            'totalPrice' => (int) $booking->items->sum('price'),
        ]);
    }

    public function showCheckout(Request $request, int|string $ticketId): View|RedirectResponse
    {
        $ticket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order.payments', 'order.review', 'customerProfile'])
            ->findOrFail($ticketId);

        $this->authorizeCustomerAccessToTicket($request, $ticket);

        if ($ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid) {
            return redirect()->route('invoices.show', $ticket->order->number);
        }

        if ($ticket->status !== QueueStatus::Completed) {
            return redirect()
                ->route('customer.tickets.show', $ticket->id)
                ->withErrors(['checkout' => 'Staff belum menyelesaikan layanan potong rambut Anda. Silakan tunggu hingga staff menekan tombol selesai.']);
        }

        return view('customer.checkout-review', [
            'ticket' => $ticket,
            'tenant' => $ticket->tenant,
            'branch' => $ticket->branch,
            'barber' => $ticket->barber,
            'totalPrice' => (int) $ticket->items->sum('price'),
        ]);
    }

    public function processCheckout(
        Request $request,
        int|string $ticketId,
        CheckoutService $checkoutService,
    ): RedirectResponse {
        $ticket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order.payments', 'customerProfile'])
            ->findOrFail($ticketId);

        $this->authorizeCustomerAccessToTicket($request, $ticket);

        if ($ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid) {
            return redirect()->route('invoices.show', $ticket->order->number);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['qris', 'cash'])],
        ], [
            'rating.required' => 'Silakan berikan penilaian bintang untuk staff.',
            'rating.between' => 'Rating bintang harus antara 1 sampai 5.',
            'payment_method.required' => 'Pilih metode pembayaran (QRIS atau Tunai).',
            'payment_method.in' => 'Metode pembayaran hanya mendukung QRIS atau Tunai di kasir.',
        ]);

        $method = PaymentMethod::from($validated['payment_method']);

        $order = $checkoutService->checkoutQueueTicket(
            ticket: $ticket,
            method: $method,
            cashierUserId: $request->user()?->id,
            markAsPaid: false, // Menunggu konfirmasi kasir terlebih dahulu
        );

        Review::query()
            ->withoutGlobalScope('tenant')
            ->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'tenant_id' => $ticket->tenant_id,
                    'branch_id' => $ticket->branch_id,
                    'customer_profile_id' => $ticket->customer_profile_id,
                    'barber_membership_id' => $ticket->barber_membership_id,
                    'rating' => (int) $validated['rating'],
                    'comment' => $validated['comment'] ?? null,
                    'is_published' => true,
                ]
            );

        if ($ticket->barber_membership_id !== null) {
            $avgRating = Review::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $ticket->barber_membership_id)
                ->avg('rating');

            if ($avgRating !== null) {
                Membership::query()
                    ->withoutGlobalScope('tenant')
                    ->where('id', $ticket->barber_membership_id)
                    ->update(['rating_avg' => round((float) $avgRating, 1)]);
            }
        }

        return redirect()
            ->route('customer.tickets.waiting-payment', $ticket->id)
            ->with('status', 'Penilaian Anda telah tersimpan! Silakan tunggu kasir mengonfirmasi penerimaan pembayaran.');
    }

    public function waitingPayment(Request $request, int|string $ticketId): View|RedirectResponse
    {
        $ticket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'items', 'order.payments', 'order.review', 'customerProfile'])
            ->findOrFail($ticketId);

        $this->authorizeCustomerAccessToTicket($request, $ticket);

        if ($ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid) {
            return redirect()
                ->route('invoices.show', $ticket->order->number)
                ->with('status', 'Pembayaran Anda telah dikonfirmasi Lunas oleh kasir.');
        }

        return view('customer.waiting-payment', [
            'ticket' => $ticket,
            'order' => $ticket->order,
            'tenant' => $ticket->tenant,
            'branch' => $ticket->branch,
            'barber' => $ticket->barber,
            'totalPrice' => (int) $ticket->items->sum('price'),
            'selectedMethod' => $ticket->order?->payments->first()?->method ?? PaymentMethod::Qris,
        ]);
    }

    private function authorizeCustomerAccessToTicket(Request $request, QueueTicket $ticket): void
    {
        /** @var User|null $user */
        $user = $request->user();

        // 1. Jika tiket terikat pada akun User tertentu
        if ($ticket->customerProfile?->user_id !== null) {
            if ($user === null) {
                $sessionTickets = (array) $request->session()->get('guest_ticket_ids', []);
                if (! in_array($ticket->id, $sessionTickets, true)) {
                    abort(403, 'Silakan login dengan akun Anda untuk mengakses tiket ini.');
                }

                return;
            }

            if ($ticket->customerProfile->user_id !== $user->id) {
                abort(403, 'Anda tidak memiliki hak akses untuk tiket antrean ini.');
            }

            return;
        }

        // 2. Jika tiket tamu / walk-in tanpa user_id, izinkan jika nomor telepon cocok dengan user login
        if ($user !== null && ! empty($user->phone)) {
            if (! empty($ticket->customerProfile?->phone) && $ticket->customerProfile->phone === $user->phone) {
                return;
            }
        }

        // 3. Pastikan ID tiket tercatat dalam session tamu
        $sessionTickets = (array) $request->session()->get('guest_ticket_ids', []);
        if (! in_array($ticket->id, $sessionTickets, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk tiket antrean ini.');
        }
    }

    private function authorizeCustomerAccessToBooking(Request $request, Booking $booking): void
    {
        /** @var User|null $user */
        $user = $request->user();

        $bookingUserId = $booking->user_id ?? $booking->customerProfile?->user_id;

        // 1. Jika booking terikat pada akun User tertentu
        if ($bookingUserId !== null) {
            if ($user === null) {
                $sessionBookings = (array) $request->session()->get('guest_booking_ids', []);
                if (! in_array($booking->id, $sessionBookings, true)) {
                    abort(403, 'Silakan login dengan akun Anda untuk melihat rincian booking ini.');
                }

                return;
            }

            if ($bookingUserId !== $user->id) {
                abort(403, 'Anda tidak memiliki hak akses untuk booking ini.');
            }

            return;
        }

        // 2. Jika booking tamu tanpa user_id, izinkan jika nomor telepon cocok dengan user login
        if ($user !== null && ! empty($user->phone)) {
            if (! empty($booking->customerProfile?->phone) && $booking->customerProfile->phone === $user->phone) {
                return;
            }
        }

        // 3. Pastikan ID booking tercatat dalam session tamu
        $sessionBookings = (array) $request->session()->get('guest_booking_ids', []);
        if (! in_array($booking->id, $sessionBookings, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk booking ini.');
        }
    }
}
