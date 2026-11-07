<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CustomerWalletTransaction;
use App\Models\CustomerWithdrawal;
use App\Models\HairColor;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\Review;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\QueueRepository;
use App\Services\BookingService;
use App\Services\CheckoutService;
use App\Services\CustomerWalletService;
use App\Services\QueueService;
use App\Services\XenditPaymentService;
use App\Support\Tenancy\CurrentTenant;
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
        private readonly QueueRepository $queueRepository,
        private readonly QueueService $queueService,
        private readonly XenditPaymentService $xenditPaymentService,
        private readonly CustomerWalletService $customerWalletService,
    ) {}

    public function dashboard(Request $request): View|RedirectResponse
    {
        $this->queueRepository->cancelExpiredQueuesAndBookings();

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            $guestTicketIds = (array) $request->session()->get('guest_ticket_ids', []);
            if (! empty($guestTicketIds)) {
                $lastTicket = QueueTicket::query()
                    ->withoutGlobalScope('tenant')
                    ->with('tenant')
                    ->find(end($guestTicketIds));
                if ($lastTicket?->tenant) {
                    return redirect()->to($lastTicket->tenant->customerPortalUrl());
                }
            }

            return redirect()->route('login');
        }

        // 1. Ambil antrean aktif (jika ada yang sedang menunggu / dipanggil / dilayani)
        $activeTicket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order.payments', 'booking'])
            ->where(function ($query) use ($user): void {
                $query->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                if (! empty($user->phone)) {
                    $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                }
            })
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->latest('id')
            ->first();

        // 1b. Ambil reservasi booking aktif / mendatang (jika ada yang pending / confirmed / checked-in)
        $activeBooking = Booking::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'queueTicket.order.payments'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                if (! empty($user->phone)) {
                    $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                }
            })
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->where(function ($query): void {
                $query->where('scheduled_at', '>=', now()->subHours(4))
                    ->orWhereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed]);
            })
            ->orderBy('scheduled_at', 'asc')
            ->first();

        // 2. Jika tidak ada antrean aktif dan tidak ada booking aktif, tampilkan HANYA 1 riwayat terbaru saja (sesuai request)
        $latestHistory = null;
        if ($activeTicket === null && $activeBooking === null) {
            $latestHistory = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order.payments', 'booking'])
                ->where(function ($query) use ($user): void {
                    $query->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                    if (! empty($user->phone)) {
                        $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                    }
                })
                ->latest('id')
                ->first();
        }

        $tenant = app(CurrentTenant::class)->get();
        $tenantRatings = [];
        $branchRatings = [];

        if ($tenant !== null) {
            $tenant->loadMissing(['branches' => fn ($b) => $b->where('is_active', true)]);
            $tenants = collect();

            // Hitung rata-rata rating staf per cabang milik tenant ini
            $branchBarberStats = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('role', Role::Barber)
                ->where('is_active', true)
                ->whereNotNull('branch_id')
                ->selectRaw('branch_id, AVG(rating_avg) as avg_rating, COUNT(*) as staff_count')
                ->groupBy('branch_id')
                ->get();

            foreach ($branchBarberStats as $stat) {
                $branchRatings[$stat->branch_id] = [
                    'avg' => round((float) $stat->avg_rating, 1),
                    'staff_count' => (int) $stat->staff_count,
                ];
            }
        } else {
            // 3. Daftar Barbershop yang aktif untuk pilihan cepat (hanya dimuat pada mode netral platform)
            $tenants = Tenant::query()
                ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
                ->with(['branches' => fn ($b) => $b->where('is_active', true)])
                ->get();

            // Hitung rata-rata rating dari total seluruh staf barber per tenant
            $tenantBarberStats = Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('role', Role::Barber)
                ->where('is_active', true)
                ->selectRaw('tenant_id, AVG(rating_avg) as avg_rating, COUNT(*) as staff_count')
                ->groupBy('tenant_id')
                ->get();

            foreach ($tenantBarberStats as $stat) {
                $tenantRatings[$stat->tenant_id] = [
                    'avg' => round((float) $stat->avg_rating, 1),
                    'staff_count' => (int) $stat->staff_count,
                ];
            }
        }

        return view('customer.dashboard', [
            'user' => $user,
            'activeTicket' => $activeTicket,
            'activeBooking' => $activeBooking,
            'latestHistory' => $latestHistory,
            'tenants' => $tenants,
            'tenant' => $tenant,
            'tenantRatings' => $tenantRatings,
            'branchRatings' => $branchRatings,
        ]);
    }

    public function handleQrScan(Request $request): RedirectResponse
    {
        $rawQr = trim((string) $request->query('qr', ''));
        if ($rawQr === '') {
            return redirect()->route('customer.dashboard');
        }

        $tenant = null;
        $queryParams = [];

        if (str_contains($rawQr, '?')) {
            $queryString = (string) parse_url($rawQr, PHP_URL_QUERY);
            if ($queryString !== '') {
                parse_str($queryString, $queryParams);
            }
        }

        if (preg_match('#/b/([a-zA-Z0-9\-_]+)#', $rawQr, $matches)) {
            $slug = strtolower($matches[1]);
            $tenant = Tenant::query()
                ->where('slug', $slug)
                ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
                ->first();
        } elseif (str_starts_with($rawQr, 'http://') || str_starts_with($rawQr, 'https://')) {
            $host = strtolower((string) parse_url($rawQr, PHP_URL_HOST));
            if ($host !== '') {
                $tenant = Tenant::query()
                    ->where('settings->custom_domain', $host)
                    ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
                    ->first();

                if ($tenant === null) {
                    $subdomain = explode('.', $host)[0] ?? '';
                    if ($subdomain !== '' && ! in_array($subdomain, ['www', 'localhost', '127'], true)) {
                        $tenant = Tenant::query()
                            ->where(function ($q) use ($subdomain): void {
                                $q->where('settings->custom_subdomain', $subdomain)
                                    ->orWhere('slug', $subdomain);
                            })
                            ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
                            ->first();
                    }
                }
            }
        } else {
            $cleanSlug = strtolower(trim($rawQr, '/'));
            $tenant = Tenant::query()
                ->where(function ($q) use ($cleanSlug): void {
                    $q->where('slug', $cleanSlug)
                        ->orWhere('settings->custom_subdomain', $cleanSlug);
                })
                ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
                ->first();
        }

        if ($tenant === null) {
            return redirect()
                ->route('customer.dashboard')
                ->withErrors(['qr' => 'QR Code barbershop tidak dikenali atau barbershop sedang tidak aktif.']);
        }

        $request->session()->put('scanned_customer_tenant_id', $tenant->id);
        $request->session()->put('scanned_customer_tenant_slug', $tenant->slug);

        $branchCode = isset($queryParams['branch']) ? (string) $queryParams['branch'] : null;
        unset($queryParams['branch'], $queryParams['sso_token']);

        $targetUrl = $tenant->customerPortalUrl($branchCode, $queryParams);

        return redirect()->to($targetUrl);
    }

    public function booking(Request $request): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            $tenantId = $request->integer('tenant_id');
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    return redirect()->to($tenant->loginUrl())
                        ->with('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan reservasi booking jadwal.');
                }
            }

            return redirect()->route('login')
                ->with('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan reservasi booking jadwal.');
        }

        $currentTenant = app(CurrentTenant::class)->get();

        // Jika customer SUDAH melakukan scan QR (memiliki $currentTenant aktif) dan masuk ke menu Booking
        // tanpa memilih tenant lain secara spesifik, arahkan ke halaman booking milik tenant yang di-scan tersebut.
        if ($currentTenant !== null && ! $request->filled('tenant_id')) {
            $branchCode = null;
            if ($request->filled('branch_id')) {
                $branchCode = Branch::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $currentTenant->id)
                    ->where('id', $request->integer('branch_id'))
                    ->value('code');
            }

            return redirect()->to($currentTenant->customerPortalUrl($branchCode, ['tab' => 'booking']));
        }

        $tenants = Tenant::query()
            ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
            ->with(['branches' => fn ($b) => $b->where('is_active', true)])
            ->get();

        $selectedTenantId = $request->integer('tenant_id') ?: ($currentTenant?->id ?: $tenants->first()?->id);
        $selectedTenant = $tenants->firstWhere('id', $selectedTenantId) ?? $currentTenant ?? $tenants->first();

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

        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
        $initialDate = CarbonImmutable::now()->addDay();
        $initialSlots = [];
        if ($selectedBranch !== null) {
            $initialSlots = $this->bookingService->getAvailableSlots(
                branch: $selectedBranch,
                date: $initialDate,
                barberMembershipId: null,
                durationMinutes: 30,
            );
        }

        return view('customer.booking', [
            'user' => $user,
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'tenant' => $currentTenant,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'selectedBranch' => $selectedBranch,
            'services' => $services,
            'hairColors' => $hairColors,
            'barbers' => $barbers,
            'initialDate' => $initialDate->toDateString(),
            'initialSlots' => $initialSlots,
        ]);
    }

    public function storeBooking(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login')
                ->with('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan reservasi booking jadwal.');
        }

        if ($request->filled('scheduled_date') && $request->filled('scheduled_time')) {
            $request->merge([
                'scheduled_at' => trim((string) $request->input('scheduled_date')).' '.trim((string) $request->input('scheduled_time')),
            ]);
        }

        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'hair_color_id' => ['nullable', 'integer', 'exists:hair_colors,id'],
            'barber_membership_id' => ['nullable', 'integer', 'exists:memberships,id'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'string'],
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

            // Jika pelanggan mencentang bayar dengan Saldo Dompet dan saldo mencukupi
            $availableBalance = $this->customerWalletService->getAvailableBalance($user);
            if ($request->boolean('use_wallet') && $availableBalance >= $booking->estimated_total) {
                $amountToPay = (int) $booking->estimated_total;
                \Illuminate\Support\Facades\DB::transaction(function () use ($user, $booking, $amountToPay): void {
                    $this->customerWalletService->debit(
                        user: $user,
                        amount: $amountToPay,
                        category: 'booking_payment',
                        referenceId: $booking->code,
                        notes: "Pembayaran booking {$booking->code} menggunakan Saldo Dompet",
                        tenantId: $booking->tenant_id,
                        profile: $booking->customerProfile
                    );

                    $booking->update([
                        'status' => BookingStatus::Confirmed,
                        'payment_approved_at' => now(),
                        'payment_rejected_reason' => null,
                        'notes' => trim(($booking->notes ?? '') . ' [Lunas dibayar dengan Saldo Dompet Customer]'),
                    ]);

                    if ($booking->scheduled_at->isToday()) {
                        $ticket = $this->queueService->checkInBooking($booking);
                        $this->queueService->createPaidOrderForBookingTicket($booking, $ticket);
                    }
                });

                return redirect()->route('customer.bookings.show', $booking)->with('status', "Booking berhasil dibuat dan otomatis LUNAS menggunakan Saldo Dompet Anda! Kode Booking: {$booking->code}.");
            }

            // Buat invoice QRIS Xendit secara otomatis
            try {
                $this->xenditPaymentService->createQrisInvoice($booking);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-create Xendit QRIS invoice failed: '.$e->getMessage(), [
                    'booking_id' => $booking->id,
                ]);
            }

            return redirect()->route('customer.bookings.show', $booking)->with('status', "Booking berhasil dibuat! Kode Booking: {$booking->code}. Silakan selesaikan pembayaran via QRIS atau E-Wallet.");
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }
    }

    public function history(Request $request): View|RedirectResponse
    {
        $this->queueRepository->cancelExpiredQueuesAndBookings();

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login')
                ->with('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan reservasi booking jadwal.');
        }

        $tickets = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'order.payments', 'booking'])
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
            ->with(['tenant', 'branch', 'barber.user', 'hairColor', 'items', 'queueTicket.order.payments'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('customerProfile', fn ($cp) => $cp->where('user_id', $user->id));
                if (! empty($user->phone)) {
                    $query->orWhereHas('customerProfile', fn ($cp) => $cp->where('phone', $user->phone));
                }
            })
            ->latest('scheduled_at')
            ->get();

        $tenant = app(CurrentTenant::class)->get();

        return view('customer.history', [
            'user' => $user,
            'tickets' => $tickets,
            'bookings' => $bookings,
            'tenant' => $tenant,
        ]);
    }

    public function profile(Request $request): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $tenant = app(CurrentTenant::class)->get();

        $walletTransactions = CustomerWalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->take(15)
            ->get();

        $withdrawals = CustomerWithdrawal::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->take(10)
            ->get();

        $availableBalance = $this->customerWalletService->getAvailableBalance($user);

        return view('customer.profile', [
            'user' => $user,
            'tenant' => $tenant,
            'walletTransactions' => $walletTransactions,
            'withdrawals' => $withdrawals,
            'availableBalance' => $availableBalance,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login')
                ->with('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan reservasi booking jadwal.');
        }

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

    public function getAvailableSlots(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id');
        $dateStr = (string) ($request->input('date') ?: now()->addDay()->toDateString());
        $barberMembershipId = $request->filled('barber_id') ? $request->integer('barber_id') : null;
        $serviceIds = (array) $request->input('service_ids', []);

        $branch = Branch::query()->withoutGlobalScope('tenant')->find($branchId);
        if (! $branch) {
            return response()->json(['success' => false, 'message' => 'Cabang tidak ditemukan.'], 404);
        }

        $duration = 30;
        if (! empty($serviceIds)) {
            $duration = (int) Service::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $branch->tenant_id)
                ->whereIn('id', array_map('intval', $serviceIds))
                ->sum('duration_minutes');
            if ($duration <= 0) {
                $duration = 30;
            }
        }

        try {
            $date = CarbonImmutable::parse($dateStr);
        } catch (\Throwable) {
            $date = CarbonImmutable::now()->addDay();
        }

        $slotData = $this->bookingService->getAvailableSlots(
            branch: $branch,
            date: $date,
            barberMembershipId: $barberMembershipId,
            durationMinutes: $duration,
        );

        return response()->json([
            'success' => true,
            ...$slotData,
        ]);
    }

    public function showTicket(Request $request, int|string $ticketId): View|JsonResponse
    {
        $this->queueRepository->cancelExpiredQueuesAndBookings();

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
            'tenant' => $request->user() ? app(CurrentTenant::class)->get() : $ticket->tenant,
            'branch' => $ticket->branch,
            'barber' => $ticket->barber,
            'order' => $ticket->order,
            'review' => $ticket->order?->review,
            'totalPrice' => (int) $ticket->items->sum('price'),
        ]);
    }

    public function showBooking(Request $request, int|string $bookingId): View
    {
        $this->queueRepository->cancelExpiredQueuesAndBookings();

        $booking = $this->findBookingBySlugOrId($bookingId, [
            'tenant', 'branch', 'barber.user', 'hairColor', 'items', 'queueTicket.order',
        ]);

        $this->authorizeCustomerAccessToBooking($request, $booking);

        // Jika booking sudah upload bukti pembayaran tapi belum terbit tiket antreannya (otomatis terkonfirmasi tanpa kasir),
        // langsung terbitkan nomor antrean & order lunas sekarang juga agar pemesan langsung melihat nomor antreannya!
        if (! empty($booking->payment_proof_path) && $booking->queueTicket === null && ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
            $booking->update([
                'payment_approved_at' => $booking->payment_approved_at ?? now(),
                'status' => BookingStatus::Confirmed,
            ]);
            $ticket = $this->queueService->checkInBooking($booking);
            $this->queueService->createPaidOrderForBookingTicket($booking, $ticket);
            $booking->refresh()->load(['queueTicket.order']);
        }

        // Pastikan invoice QRIS Xendit siap jika booking masih pending
        if ($booking->status === BookingStatus::Pending && $booking->queueTicket === null) {
            try {
                $this->xenditPaymentService->getOrCreateQrisInvoice($booking);
                $booking->refresh();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Generate Xendit QRIS on showBooking failed: '.$e->getMessage());
            }
        }

        $todayTickets = collect();
        if ($booking->scheduled_at->isToday() && $booking->branch !== null) {
            $todayTickets = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $booking->branch_id)
                ->whereDate('date', now()->toDateString())
                ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
                ->with(['customerProfile', 'barber.user', 'items'])
                ->orderByRaw('COALESCE(priority_at, created_at) ASC')
                ->orderBy('sequence')
                ->get();
        }

        return view('customer.booking-detail', [
            'booking' => $booking,
            'tenant' => $request->user() ? app(CurrentTenant::class)->get() : $booking->tenant,
            'branch' => $booking->branch,
            'barber' => $booking->barber,
            'totalPrice' => (int) $booking->items->sum('price'),
            'todayTickets' => $todayTickets,
        ]);
    }

    public function uploadPaymentProof(Request $request, int|string $bookingId): RedirectResponse
    {
        $booking = $this->findBookingBySlugOrId($bookingId);

        $this->authorizeCustomerAccessToBooking($request, $booking);

        if ($booking->status === BookingStatus::Cancelled || $booking->status === BookingStatus::NoShow) {
            return back()->withErrors(['proof' => 'Booking ini telah dibatalkan sehingga tidak dapat mengunggah bukti pembayaran.']);
        }

        if ($booking->queueTicket !== null) {
            return back()->with('status', 'Pembayaran booking ini sudah disetujui sebelumnya.');
        }

        $validated = $request->validate([
            'payment_proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'payment_proof.required' => 'Silakan pilih berkas bukti transfer.',
            'payment_proof.file' => 'Berkas bukti transfer tidak valid.',
            'payment_proof.image' => 'Berkas bukti transfer harus berupa gambar foto atau tangkapan layar.',
            'payment_proof.mimes' => 'Format gambar harus JPG, JPEG, PNG, atau WEBP.',
            'payment_proof.max' => 'Ukuran berkas gambar maksimal 2 MB.',
        ]);

        if (! empty($booking->payment_proof_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($booking->payment_proof_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($booking->payment_proof_path);
        }

        $path = $request->file('payment_proof')->store('booking-proofs', 'public');

        \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $path): void {
            $booking->update([
                'payment_proof_path' => $path,
                'payment_proof_uploaded_at' => now(),
                'payment_approved_at' => now(),
                'payment_rejected_reason' => null,
                'status' => BookingStatus::Confirmed,
            ]);

            // Otomatis terbitkan tiket antrean booking & Order/Payment Lunas tanpa konfirmasi manual kasir
            $ticket = $this->queueService->checkInBooking($booking);
            $this->queueService->createPaidOrderForBookingTicket($booking, $ticket);
        });

        $booking->refresh()->load('queueTicket');
        $ticketNumber = $booking->queueTicket?->number ?? 'B-001';

        return back()->with('status', "Pembayaran transfer berhasil diterima dan otomatis terkonfirmasi! Nomor antrean booking resmi Anda: {$ticketNumber}. Antrean akan aktif di sistem kasir 15 menit sebelum jam jadwal Anda.");
    }

    public function payQris(Request $request, int|string $bookingId): RedirectResponse
    {
        $booking = $this->findBookingBySlugOrId($bookingId);

        $this->authorizeCustomerAccessToBooking($request, $booking);

        if ($booking->status === BookingStatus::Cancelled || $booking->status === BookingStatus::NoShow) {
            return back()->withErrors(['booking' => 'Booking ini telah dibatalkan.']);
        }

        if ($booking->queueTicket !== null || $booking->status === BookingStatus::Confirmed) {
            return redirect()->route('customer.bookings.show', $booking)->with('status', 'Booking ini sudah lunas.');
        }

        try {
            $invoice = $this->xenditPaymentService->getOrCreateQrisInvoice($booking);

            if (! empty($invoice['invoice_url'])) {
                return redirect()->away($invoice['invoice_url']);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => 'Gagal membuka halaman pembayaran QRIS: '.$e->getMessage()]);
        }

        return redirect()->route('customer.bookings.show', $booking);
    }

    public function mockPayQris(Request $request, int|string $bookingId): RedirectResponse
    {
        $booking = $this->findBookingBySlugOrId($bookingId);

        $this->authorizeCustomerAccessToBooking($request, $booking);

        if ($booking->queueTicket === null && ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
            $paidAt = now();

            $booking->update([
                'status' => BookingStatus::Confirmed,
                'xendit_paid_at' => $paidAt,
                'payment_approved_at' => $paidAt,
                'xendit_payment_method' => 'QRIS',
                'xendit_payment_channel' => 'QRIS-SIMULATOR',
            ]);

            $ticket = $this->queueService->checkInBooking($booking);
            $this->queueService->createPaidOrderForBookingTicket($booking, $ticket);
        }

        return redirect()->route('customer.bookings.show', $booking)
            ->with('status', 'Simulasi pembayaran QRIS berhasil! Tiket antrean resmi booking Anda telah diterbitkan secara otomatis.');
    }

    public function cancelTicket(Request $request, int|string $ticketId): RedirectResponse
    {
        $ticket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['customerProfile', 'booking', 'order.payments'])
            ->findOrFail($ticketId);

        $this->authorizeCustomerAccessToTicket($request, $ticket);

        if (! in_array($ticket->status, [QueueStatus::Waiting, QueueStatus::Called], true)) {
            return back()->withErrors([
                'queue' => 'Antrean ini sedang dilayani atau sudah selesai sehingga tidak dapat dibatalkan.',
            ]);
        }

        $isPaid = ($ticket->order !== null && $ticket->order->payment_status === PaymentStatus::Paid)
            || ($ticket->booking !== null && (
                $ticket->booking->xendit_paid_at !== null
                || $ticket->booking->payment_approved_at !== null
                || in_array($ticket->booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed], true)
                || ! empty($ticket->booking->payment_proof_path)
            ));

        $paidPayment = $ticket->order?->payments?->firstWhere('status', 'success')
            ?? $ticket->order?->payments?->first();
        $paidMethod = $paidPayment?->method;
        $isQrisPaid = $isPaid && (
            $ticket->booking_id !== null
            || $paidMethod === PaymentMethod::Qris
            || (is_object($paidMethod) && $paidMethod->value === 'qris')
            || $paidMethod === 'qris'
            || $paidMethod === PaymentMethod::Transfer
            || (is_object($paidMethod) && $paidMethod->value === 'transfer')
            || $paidMethod === 'transfer'
        );
        $isCashPaid = $isPaid && ! $isQrisPaid;

        $validationRules = [
            'reason' => ['nullable', 'string', 'max:255'],
        ];

        // Hanya pembayaran non-tunai (QRIS / Transfer) yang mewajibkan input rekening transfer pengembalian dana
        if ($isQrisPaid) {
            $validationRules['refund_bank_name'] = ['nullable', 'string', 'max:60'];
            $validationRules['refund_account_number'] = ['nullable', 'string', 'max:60'];
            $validationRules['refund_account_name'] = ['nullable', 'string', 'max:100'];
        }

        $validated = $request->validate($validationRules);

        $reasonInput = trim((string) ($validated['reason'] ?? ''));
        $reason = $reasonInput !== ''
            ? "Dibatalkan oleh pelanggan: {$reasonInput}"
            : 'Dibatalkan oleh pelanggan';

        try {
            $this->queueService->transitionStatus(
                ticket: $ticket,
                targetStatus: QueueStatus::Cancelled,
                actorUserId: $request->user()?->id,
                reason: $reason,
            );

            if ($isQrisPaid) {
                // Untuk QRIS / Transfer: Refund otomatis dikreditkan ke Saldo Dompet Customer
                $refundAmount = $ticket->order?->paid_total ?? (int) ($ticket->booking?->estimated_total ?? $ticket->items->sum('price'));
                $customerUser = $ticket->customerProfile?->user ?? $request->user();

                if ($customerUser !== null && $refundAmount > 0) {
                    $this->customerWalletService->credit(
                        user: $customerUser,
                        amount: $refundAmount,
                        category: 'refund_booking',
                        referenceId: $ticket->number,
                        notes: "Pengembalian dana pembatalan antrean {$ticket->number}",
                        tenantId: $ticket->tenant_id,
                        profile: $ticket->customerProfile
                    );

                    $refundUpdates = [
                        'refund_status' => 'completed',
                        'refund_amount' => $refundAmount,
                        'refund_bank_name' => !empty($validated['refund_bank_name']) ? trim((string) $validated['refund_bank_name']) : 'Dompet Akun Customer',
                        'refund_account_number' => !empty($validated['refund_account_number']) ? trim((string) $validated['refund_account_number']) : ($customerUser->phone ?? '-'),
                        'refund_account_name' => !empty($validated['refund_account_name']) ? trim((string) $validated['refund_account_name']) : $customerUser->name,
                        'refund_requested_at' => now(),
                        'refunded_at' => now(),
                        'refund_notes' => 'Otomatis masuk ke Saldo Dompet Akun Customer',
                    ];
                    $message = "Nomor antrean {$ticket->number} telah dibatalkan. Pengembalian dana sebesar Rp" . number_format($refundAmount, 0, ',', '.') . " telah otomatis masuk ke Saldo Dompet Akun Anda dan siap digunakan kembali atau ditarik tunai!";
                } else {
                    $refundUpdates = [
                        'refund_status' => 'pending',
                        'refund_amount' => $refundAmount,
                        'refund_bank_name' => trim((string) ($validated['refund_bank_name'] ?? '')),
                        'refund_account_number' => trim((string) ($validated['refund_account_number'] ?? '')),
                        'refund_account_name' => trim((string) ($validated['refund_account_name'] ?? '')),
                        'refund_requested_at' => now(),
                    ];
                    $message = "Nomor antrean {$ticket->number} telah dibatalkan. Pengajuan pengembalian dana QRIS sebesar Rp" . number_format($refundAmount, 0, ',', '.') . " telah tercatat dan sedang menunggu konfirmasi transfer dari kasir atau owner.";
                }

                $ticket->update($refundUpdates);

                if ($ticket->booking_id !== null) {
                    \App\Models\Booking::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($ticket->booking_id)
                        ->update(array_merge($refundUpdates, [
                            'status' => BookingStatus::Cancelled,
                            'notes' => $ticket->notes,
                        ]));
                }

                if ($ticket->order !== null) {
                    // Batalkan catatan komisi barber agar tidak masuk payroll
                    \App\Models\CommissionEntry::query()
                        ->withoutGlobalScope('tenant')
                        ->whereHas('orderItem', fn ($q) => $q->where('order_id', $ticket->order->id))
                        ->delete();

                    $ticket->order->update([
                        'notes' => trim(($ticket->order->notes ?? '') . ' [Dibatalkan: Refund diproses]'),
                    ]);
                }
            } elseif ($isCashPaid && $ticket->order !== null) {
                // Untuk Pembayaran Tunai (Cash): Tidak perlu konfirmasi transfer pending, uang tunai langsung dikembalikan di tempat
                $refundAmount = $ticket->order->paid_total;

                $ticket->update([
                    'refund_status' => 'completed',
                    'refund_amount' => $refundAmount,
                    'refunded_at' => now(),
                    'refund_notes' => 'Pengembalian uang tunai langsung di kasir outlet.',
                ]);

                // Batalkan komisi barber agar tidak masuk payroll
                \App\Models\CommissionEntry::query()
                    ->withoutGlobalScope('tenant')
                    ->whereHas('orderItem', fn ($q) => $q->where('order_id', $ticket->order->id))
                    ->delete();

                $ticket->order->update([
                    'payment_status' => PaymentStatus::Refunded,
                    'refunded_total' => $refundAmount,
                    'notes' => trim(($ticket->order->notes ?? '') . ' [Dibatalkan: Refund tunai langsung]'),
                ]);

                \App\Models\Payment::query()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'order_id' => $ticket->order->id,
                    'type' => 'refund',
                    'method' => PaymentMethod::Cash,
                    'amount' => $refundAmount,
                    'status' => 'success',
                    'reason' => 'Pengembalian uang tunai langsung pembatalan antrean ' . $ticket->number,
                    'idempotency_key' => 'refund-cash-' . $ticket->id . '-' . (string) \Illuminate\Support\Str::uuid(),
                    'created_at' => now(),
                ]);

                $message = "Nomor antrean {$ticket->number} telah dibatalkan. Karena pembayaran dilakukan secara tunai, silakan ambil langsung pengembalian uang Anda sebesar Rp" . number_format($refundAmount, 0, ',', '.') . " di meja kasir outlet.";
            } else {
                $message = "Nomor antrean {$ticket->number} telah berhasil dibatalkan.";
            }

            return back()->with('status', $message);
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }
    }

    public function cancelBooking(Request $request, int|string $bookingId): RedirectResponse
    {
        $booking = $this->findBookingBySlugOrId($bookingId, ['customerProfile', 'queueTicket']);

        $this->authorizeCustomerAccessToBooking($request, $booking);

        $canCancelPendingOrConfirmed = in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
        $canCancelCheckedIn = $booking->status === BookingStatus::CheckedIn
            && $booking->queueTicket !== null
            && in_array($booking->queueTicket->status, [QueueStatus::Waiting, QueueStatus::Called], true);

        if (! $canCancelPendingOrConfirmed && ! $canCancelCheckedIn) {
            return back()->withErrors([
                'booking' => 'Reservasi booking ini sudah diproses atau selesai sehingga tidak dapat dibatalkan.',
            ]);
        }

        $isPaid = $booking->xendit_paid_at !== null
            || $booking->payment_approved_at !== null
            || in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed], true)
            || (! empty($booking->payment_proof_path) && in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true));

        $validationRules = [
            'reason' => ['nullable', 'string', 'max:255'],
        ];

        if ($isPaid) {
            $validationRules['refund_bank_name'] = ['nullable', 'string', 'max:60'];
            $validationRules['refund_account_number'] = ['nullable', 'string', 'max:60'];
            $validationRules['refund_account_name'] = ['nullable', 'string', 'max:100'];
        }

        $validated = $request->validate($validationRules);

        $reasonInput = trim((string) ($validated['reason'] ?? ''));
        $reason = $reasonInput !== ''
            ? "Dibatalkan oleh pelanggan: {$reasonInput}"
            : 'Dibatalkan oleh pelanggan';

        try {
            $refundUpdates = [];
            if ($isPaid) {
                $customerUser = $booking->customerProfile?->user ?? $request->user();
                $refundAmount = (int) $booking->estimated_total;

                if ($customerUser !== null && $refundAmount > 0) {
                    $this->customerWalletService->credit(
                        user: $customerUser,
                        amount: $refundAmount,
                        category: 'refund_booking',
                        referenceId: $booking->code,
                        notes: "Pengembalian dana pembatalan booking {$booking->code}",
                        tenantId: $booking->tenant_id,
                        profile: $booking->customerProfile
                    );

                    $refundUpdates = [
                        'refund_status' => 'completed',
                        'refund_amount' => $refundAmount,
                        'refund_bank_name' => !empty($validated['refund_bank_name']) ? trim((string) $validated['refund_bank_name']) : 'Dompet Akun Customer',
                        'refund_account_number' => !empty($validated['refund_account_number']) ? trim((string) $validated['refund_account_number']) : ($customerUser->phone ?? '-'),
                        'refund_account_name' => !empty($validated['refund_account_name']) ? trim((string) $validated['refund_account_name']) : $customerUser->name,
                        'refund_requested_at' => now(),
                        'refunded_at' => now(),
                        'refund_notes' => 'Otomatis masuk ke Saldo Dompet Akun Customer',
                    ];
                } else {
                    $refundUpdates = [
                        'refund_status' => 'pending',
                        'refund_amount' => $refundAmount,
                        'refund_bank_name' => trim((string) ($validated['refund_bank_name'] ?? '')),
                        'refund_account_number' => trim((string) ($validated['refund_account_number'] ?? '')),
                        'refund_account_name' => trim((string) ($validated['refund_account_name'] ?? '')),
                        'refund_requested_at' => now(),
                    ];
                }
            }

            if ($booking->queueTicket !== null && in_array($booking->queueTicket->status, [QueueStatus::Waiting, QueueStatus::Called], true)) {
                $this->queueService->transitionStatus(
                    ticket: $booking->queueTicket,
                    targetStatus: QueueStatus::Cancelled,
                    actorUserId: $request->user()?->id,
                    reason: $reason,
                );

                $bookingUpdates = array_merge([
                    'status' => BookingStatus::Cancelled,
                    'cancel_reason' => $reason,
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()?->id,
                ], $refundUpdates);

                if ($isPaid) {
                    $booking->queueTicket->update($refundUpdates);
                }
                $booking->update($bookingUpdates);
            } else {
                if ($booking->queueTicket !== null && ! in_array($booking->queueTicket->status, [QueueStatus::Cancelled, QueueStatus::Completed, QueueStatus::NoShow], true)) {
                    $booking->queueTicket->update([
                        'status' => QueueStatus::Cancelled,
                        'notes' => $booking->queueTicket->notes ? $booking->queueTicket->notes . " (Batal: {$reason})" : "Batal: {$reason}",
                    ]);
                }

                $bookingUpdates = array_merge([
                    'status' => BookingStatus::Cancelled,
                    'cancel_reason' => $reason,
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()?->id,
                ], $refundUpdates);

                $booking->update($bookingUpdates);
            }

            $message = $isPaid
                ? "Reservasi booking {$booking->code} telah dibatalkan. Pengembalian dana sebesar Rp" . number_format($booking->estimated_total, 0, ',', '.') . " telah otomatis masuk ke Saldo Dompet Akun Anda dan siap digunakan kembali atau ditarik tunai!"
                : "Reservasi booking {$booking->code} telah berhasil dibatalkan.";

            return back()->with('status', $message);
        } catch (DomainException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }
    }

    public function payBookingWithWallet(Request $request, int|string $bookingId): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $booking = $this->findBookingBySlugOrId($bookingId);
        $this->authorizeCustomerAccessToBooking($request, $booking);

        if ($booking->status === BookingStatus::Cancelled || $booking->status === BookingStatus::NoShow) {
            return back()->withErrors(['wallet' => 'Booking ini sudah dibatalkan.']);
        }

        if ($booking->queueTicket !== null || $booking->status === BookingStatus::Confirmed) {
            return back()->with('status', 'Booking ini sudah terkonfirmasi lunas.');
        }

        $amountToPay = (int) $booking->estimated_total;
        $availableBalance = $this->customerWalletService->getAvailableBalance($user);
        if ($availableBalance < $amountToPay) {
            return back()->withErrors([
                'wallet' => "Saldo Dompet Anda yang tersedia (Rp" . number_format($availableBalance, 0, ',', '.') . ") tidak mencukupi untuk membayar tagihan Rp" . number_format($amountToPay, 0, ',', '.') . ".",
            ]);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $booking, $amountToPay): void {
                $this->customerWalletService->debit(
                    user: $user,
                    amount: $amountToPay,
                    category: 'booking_payment',
                    referenceId: $booking->code,
                    notes: "Pembayaran booking {$booking->code} menggunakan Saldo Dompet",
                    tenantId: $booking->tenant_id,
                    profile: $booking->customerProfile
                );

                $booking->update([
                    'status' => BookingStatus::Confirmed,
                    'payment_approved_at' => now(),
                    'payment_rejected_reason' => null,
                    'notes' => trim(($booking->notes ?? '') . ' [Lunas dibayar dengan Saldo Dompet Customer]'),
                ]);

                // Terbitkan tiket antrean jika jadwal hari ini
                if ($booking->scheduled_at->isToday()) {
                    $ticket = $this->queueService->checkInBooking($booking);
                    $this->queueService->createPaidOrderForBookingTicket($booking, $ticket);
                }
            });

            return redirect()->route('customer.bookings.show', $booking)
                ->with('status', "Pembayaran booking sebesar Rp" . number_format($amountToPay, 0, ',', '.') . " menggunakan Saldo Dompet berhasil! Reservasi telah dikonfirmasi.");
        } catch (\Throwable $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $availableBalance = $this->customerWalletService->getAvailableBalance($user);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:' . max(10000, $availableBalance)],
            'bank_name' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
        ], [
            'amount.required' => 'Nominal penarikan wajib diisi.',
            'amount.min' => 'Batas minimal penarikan saldo adalah Rp10.000.',
            'amount.max' => 'Nominal penarikan melebihi saldo dompet yang tersedia untuk ditarik.',
            'bank_name.required' => 'Pilih atau isi nama bank / e-wallet tujuan.',
            'account_number.required' => 'Nomor rekening atau nomor HP e-wallet tujuan wajib diisi.',
            'account_holder.required' => 'Nama pemilik rekening atau akun e-wallet wajib diisi.',
        ]);

        try {
            $withdrawal = $this->customerWalletService->createWithdrawalRequest(
                user: $user,
                amount: (int) $validated['amount'],
                bankName: trim((string) $validated['bank_name']),
                accountNumber: trim((string) $validated['account_number']),
                accountHolder: trim((string) $validated['account_holder']),
                profile: $user->customerProfiles()->first()
            );

            return back()->with('status', "Permohonan penarikan dana {$withdrawal->withdrawal_code} sebesar Rp" . number_format($withdrawal->amount, 0, ',', '.') . " berhasil diajukan! Admin Platform akan memproses transfer ke {$withdrawal->bank_name} tanpa biaya admin (Gratis Rp0) dalam 1x24 jam.");
        } catch (\Throwable $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }
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

        if (in_array($ticket->status, [QueueStatus::Cancelled, QueueStatus::NoShow], true)) {
            return redirect()
                ->route('customer.tickets.show', $ticket->id)
                ->withErrors(['checkout' => 'Antrean ini telah dibatalkan atau ditandai tidak hadir.']);
        }

        return view('customer.checkout-review', [
            'ticket' => $ticket,
            'tenant' => $request->user() ? app(CurrentTenant::class)->get() : $ticket->tenant,
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

        $isCompleted = ($ticket->status === QueueStatus::Completed);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['qris', 'cash'])],
            'rating' => [$isCompleted ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
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

        if ($isCompleted && ! empty($validated['rating'])) {
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
        }

        return redirect()
            ->route('customer.tickets.waiting-payment', $ticket->id)
            ->with('status', 'Pilihan pembayaran telah tersimpan! Silakan tunggu kasir mengonfirmasi penerimaan pembayaran.');
    }

    public function submitReview(Request $request, int|string $ticketId): RedirectResponse
    {
        $ticket = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->with(['order'])
            ->findOrFail($ticketId);

        $this->authorizeCustomerAccessToTicket($request, $ticket);

        if ($ticket->status !== QueueStatus::Completed) {
            return back()->withErrors(['review' => 'Penilaian hanya dapat diberikan setelah layanan pangkas rambut selesai dikerjakan staff.']);
        }

        if ($ticket->order === null) {
            return back()->withErrors(['review' => 'Pesanan belum ditemukan untuk antrean ini.']);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => 'Silakan berikan rating bintang.',
            'rating.between' => 'Rating bintang harus antara 1 sampai 5.',
        ]);

        Review::query()
            ->withoutGlobalScope('tenant')
            ->updateOrCreate(
                ['order_id' => $ticket->order->id],
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

        return back()->with('status', 'Terima kasih! Penilaian Anda untuk staff telah berhasil disimpan.');
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
            'tenant' => $request->user() ? app(CurrentTenant::class)->get() : $ticket->tenant,
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

    private function findBookingBySlugOrId(int|string $bookingId, array $relations = []): Booking
    {
        return Booking::query()
            ->withoutGlobalScope('tenant')
            ->when(! empty($relations), fn ($q) => $q->with($relations))
            ->where(function ($query) use ($bookingId): void {
                $query->where('code', $bookingId);
                if (is_numeric($bookingId)) {
                    $query->orWhere('id', (int) $bookingId);
                }
            })
            ->firstOrFail();
    }
}
