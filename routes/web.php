<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TenantAuthController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\OperationalActionController;
use App\Http\Controllers\OwnerManagementController;
use App\Http\Controllers\PlatformPlanController;
use App\Http\Controllers\PlatformReportController;
use App\Http\Controllers\PlatformSettingController;
use App\Http\Controllers\PlatformSubscriptionController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\PlatformTransactionController;
use App\Http\Controllers\RoleDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'redirectAuthenticated'])->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [AuthenticatedSessionController::class, 'redirectAuthenticated'])->name('dashboard');

    // Superadmin Platform
    Route::middleware('role:superadmin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/', [RoleDashboardController::class, 'platform'])->name('dashboard');

        // Tenant Management
        Route::get('/tenants/create', [PlatformTenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->name('tenants.show');
        Route::put('/tenants/{tenant}', [PlatformTenantController::class, 'update'])->name('tenants.update');
        Route::patch('/tenants/{tenant}/toggle', [PlatformTenantController::class, 'toggleStatus'])->name('tenants.toggle');
        Route::post('/tenants/{tenant}/subscription', [PlatformTenantController::class, 'updateSubscription'])->name('tenants.subscription');
        Route::post('/tenants/{tenant}/reset-password', [PlatformTenantController::class, 'resetOwnerPassword'])->name('tenants.reset-password');
        Route::delete('/tenants/{tenant}', [PlatformTenantController::class, 'destroy'])->name('tenants.destroy');

        // Plans & Subscriptions
        Route::get('/plans', [PlatformPlanController::class, 'index'])->name('plans');
        Route::get('/plans/{plan}/edit', [PlatformPlanController::class, 'edit'])->name('plans.edit');
        Route::post('/plans', [PlatformPlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}', [PlatformPlanController::class, 'update'])->name('plans.update');
        Route::patch('/plans/{plan}/toggle', [PlatformPlanController::class, 'toggleStatus'])->name('plans.toggle');
        Route::delete('/plans/{plan}', [PlatformPlanController::class, 'destroy'])->name('plans.destroy');

        // Tagihan & Konfirmasi Langganan SaaS
        Route::get('/subscriptions', [PlatformSubscriptionController::class, 'index'])->name('subscriptions');
        Route::patch('/subscriptions/{id}/confirm', [PlatformSubscriptionController::class, 'confirmPayment'])->name('subscriptions.confirm');
        Route::patch('/subscriptions/{id}/cancel', [PlatformSubscriptionController::class, 'cancelInvoice'])->name('subscriptions.cancel');
        Route::get('/subscriptions/{id}', [PlatformSubscriptionController::class, 'show'])->name('subscriptions.show');

        // Monitoring Transaksi Global Platform
        Route::get('/transactions', [PlatformTransactionController::class, 'index'])->name('transactions');
        Route::get('/transactions/export', [PlatformTransactionController::class, 'exportCsv'])->name('transactions.export');

        // Laporan Pertumbuhan & Pendapatan Platform
        Route::get('/reports', [PlatformReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [PlatformReportController::class, 'exportCsv'])->name('reports.export');

        // Konfigurasi Global Platform SaaS
        Route::get('/settings', [PlatformSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [PlatformSettingController::class, 'update'])->name('settings.update');
    });

    // Owner Management
    Route::middleware(['role:owner', 'subscription.active'])->prefix('owner')->name('owner.')->group(function (): void {
        Route::get('/', [RoleDashboardController::class, 'owner'])->name('dashboard');
        Route::get('/branches', [OwnerManagementController::class, 'branches'])->name('branches');
        Route::get('/branches/create', [OwnerManagementController::class, 'createBranch'])->name('branches.create');
        Route::post('/branches', [OwnerManagementController::class, 'storeBranch'])->name('branches.store');
        Route::get('/branches/{branch}/edit', [OwnerManagementController::class, 'editBranch'])->name('branches.edit');
        Route::put('/branches/{branch}', [OwnerManagementController::class, 'updateBranch'])->name('branches.update');
        Route::delete('/branches/{branch}', [OwnerManagementController::class, 'destroyBranch'])->name('branches.destroy');
        Route::get('/staff', [OwnerManagementController::class, 'staff'])->name('staff');
        Route::post('/staff', [OwnerManagementController::class, 'storeStaff'])->name('staff.store');
        Route::put('/staff/{membership}', [OwnerManagementController::class, 'updateStaff'])->name('staff.update');
        Route::delete('/staff/{membership}', [OwnerManagementController::class, 'destroyStaff'])->name('staff.destroy');
        Route::get('/services', [OwnerManagementController::class, 'services'])->name('services');
        Route::post('/services', [OwnerManagementController::class, 'storeService'])->name('services.store');
        Route::put('/services/{service}', [OwnerManagementController::class, 'updateService'])->name('services.update');
        Route::patch('/services/{service}/price', [OwnerManagementController::class, 'updateServicePrice'])->name('services.update-price');
        Route::get('/colors', [OwnerManagementController::class, 'colors'])->name('colors');
        Route::post('/colors', [OwnerManagementController::class, 'storeColor'])->name('colors.store');
        Route::put('/colors/{hairColor}', [OwnerManagementController::class, 'updateColor'])->name('colors.update');
        Route::get('/reports', [OwnerManagementController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [OwnerManagementController::class, 'exportReportsCsv'])->name('reports.export');
        Route::patch('/reports/reviews/{review}/toggle-visibility', [OwnerManagementController::class, 'toggleReviewVisibility'])->name('reports.reviews.toggle-visibility');
        Route::post('/reports/reviews/{review}/reply', [OwnerManagementController::class, 'replyReview'])->name('reports.reviews.reply');
        Route::post('/orders/{order}/refund', [OwnerManagementController::class, 'refundOrder'])->name('orders.refund');

        // Transaksi
        Route::get('/transactions', [OwnerManagementController::class, 'transactions'])->name('transactions');

        // Gaji & Komisi (Halaman Utama & Pecahan)
        Route::get('/payroll', [OwnerManagementController::class, 'payroll'])->name('payroll');
        Route::post('/payroll/generate', [OwnerManagementController::class, 'generatePayroll'])->name('payroll.generate');
        Route::post('/payroll/{payroll}/approve', [OwnerManagementController::class, 'approvePayroll'])->name('payroll.approve');
        Route::post('/payroll/{payroll}/mark-paid', [OwnerManagementController::class, 'markPayrollPaid'])->name('payroll.mark-paid');
        Route::post('/payroll/items/{item}/mark-paid', [OwnerManagementController::class, 'markPayrollItemPaid'])->name('payroll.items.mark-paid');

        // Pecahan 1: Gaji & Kenaikan Per Orangan
        Route::get('/payroll/staff', [OwnerManagementController::class, 'payrollStaff'])->name('payroll.staff');
        Route::post('/payroll/staff/{membership}/salary', [OwnerManagementController::class, 'updateStaffSalary'])->name('payroll.update-staff-salary');

        // Pecahan 2: Standar Gaji Jabatan & Komisi Layanan
        Route::get('/payroll/rules', [OwnerManagementController::class, 'payrollRules'])->name('payroll.rules');
        Route::post('/payroll/schemes', [OwnerManagementController::class, 'storeSalaryScheme'])->name('payroll.store-scheme');
        Route::put('/payroll/schemes/{scheme}', [OwnerManagementController::class, 'updateSalaryScheme'])->name('payroll.update-scheme');
        Route::delete('/payroll/schemes/{scheme}', [OwnerManagementController::class, 'destroySalaryScheme'])->name('payroll.destroy-scheme');
        Route::post('/payroll/commission-rules', [OwnerManagementController::class, 'storeServiceCommissionRule'])->name('payroll.store-commission-rule');

        // 1. Jadwal Shift Kerja Barber
        Route::get('/schedules', [OwnerManagementController::class, 'schedules'])->name('schedules');
        Route::get('/schedules/{membership}/edit', [OwnerManagementController::class, 'editSchedule'])->name('schedules.edit');
        Route::post('/schedules', [OwnerManagementController::class, 'storeSchedule'])->name('schedules.store');
        Route::put('/schedules/{membership}', [OwnerManagementController::class, 'updateSchedule'])->name('schedules.update');
        Route::post('/schedules/time-off/{timeOff}/approve', [OwnerManagementController::class, 'approveTimeOff'])->name('schedules.approve-time-off');

        // 2. Pengajuan & Izin Libur (Time Off)
        Route::get('/time-offs', [OwnerManagementController::class, 'timeOffs'])->name('time-offs');
        Route::post('/time-offs', [OwnerManagementController::class, 'storeTimeOff'])->name('time-offs.store');
        Route::post('/time-offs/{timeOff}/approve', [OwnerManagementController::class, 'approveTimeOff'])->name('time-offs.approve');
        Route::post('/time-offs/{timeOff}/reject', [OwnerManagementController::class, 'rejectTimeOff'])->name('time-offs.reject');

        // 3. Rekap & Log Absensi
        Route::get('/attendances', [OwnerManagementController::class, 'attendances'])->name('attendances');
        Route::post('/attendances', [OwnerManagementController::class, 'storeAttendance'])->name('attendances.store');
        Route::put('/attendances/{attendance}', [OwnerManagementController::class, 'updateAttendance'])->name('attendances.update');

        // 4. Pengaturan Jam Operasional & Toleransi Absen
        Route::get('/schedule-settings', [OwnerManagementController::class, 'scheduleSettings'])->name('schedule-settings');
        Route::put('/schedule-settings/{branch}', [OwnerManagementController::class, 'updateScheduleSettings'])->name('schedule-settings.update');
        Route::post('/schedule-settings/{branch}/regenerate-qr', [OwnerManagementController::class, 'regenerateBranchQrToken'])->name('schedule-settings.regenerate-qr');
        Route::get('/resolve-maps-coords', [OwnerManagementController::class, 'resolveMapsCoords'])->name('resolve-maps-coords');

        // Customer CRM
        Route::get('/customers', [OwnerManagementController::class, 'customers'])->name('customers');
        Route::post('/customers', [OwnerManagementController::class, 'storeCustomer'])->name('customers.store');
        Route::put('/customers/{customer}', [OwnerManagementController::class, 'updateCustomer'])->name('customers.update');

        // Pengaturan Barbershop
        Route::get('/settings', [OwnerManagementController::class, 'settings'])->name('settings');
        Route::put('/settings', [OwnerManagementController::class, 'updateSettings'])->name('settings.update');
        Route::put('/profile', [OwnerManagementController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [OwnerManagementController::class, 'updatePassword'])->name('password.update');

        // Langganan & Upgrade Paket Fitur Pro
        Route::get('/subscription', [OwnerManagementController::class, 'subscription'])->name('subscription');
        Route::post('/subscription/upgrade', [OwnerManagementController::class, 'upgradeSubscription'])->name('subscription.upgrade');
        Route::get('/subscription/invoices/{id}', [OwnerManagementController::class, 'showSubscriptionInvoice'])->name('subscription.invoice');
        Route::post('/subscription/invoices/{id}/pay', [OwnerManagementController::class, 'submitPaymentProof'])->name('subscription.submit-payment');
        Route::post('/subscription/invoices/{id}/cancel', [OwnerManagementController::class, 'cancelSubscriptionInvoice'])->name('subscription.cancel-invoice');
    });

    // Admin / Cashier & Operational Actions
    Route::middleware(['role:owner,admin,barber', 'subscription.active'])->prefix('cashier')->name('cashier.')->group(function (): void {
        Route::get('/', [RoleDashboardController::class, 'cashier'])
            ->middleware('role:owner,admin')
            ->name('dashboard');

        Route::get('/walk-in', [RoleDashboardController::class, 'cashierWalkIn'])
            ->middleware('role:owner,admin')
            ->name('walk-in');

        Route::get('/bookings', [RoleDashboardController::class, 'cashierBookings'])
            ->middleware('role:owner,admin')
            ->name('bookings');

        Route::get('/chairs', [RoleDashboardController::class, 'cashierChairs'])
            ->middleware('role:owner,admin')
            ->name('chairs');

        Route::get('/attendance', [RoleDashboardController::class, 'cashierAttendance'])
            ->middleware('role:owner,admin')
            ->name('attendance');

        Route::post('/time-off', [RoleDashboardController::class, 'requestTimeOff'])
            ->middleware('role:owner,admin')
            ->name('time-off.store');

        Route::post('/walk-in', [OperationalActionController::class, 'storeWalkIn'])
            ->middleware('role:owner,admin')
            ->name('walk-in.store');

        Route::post('/bookings/{booking}/check-in', [OperationalActionController::class, 'checkInBooking'])
            ->middleware('role:owner,admin')
            ->name('bookings.check-in');

        Route::post('/bookings/{booking}/cancel', [OperationalActionController::class, 'cancelBooking'])
            ->middleware('role:owner,admin')
            ->name('bookings.cancel');

        Route::post('/tickets/call-next', [OperationalActionController::class, 'callNextTicket'])
            ->middleware('role:owner,admin')
            ->name('tickets.call-next');

        Route::post('/tickets/{ticket}/transition', [OperationalActionController::class, 'transitionTicket'])
            ->name('tickets.transition');

        Route::post('/tickets/{ticket}/recall', [OperationalActionController::class, 'recallTicket'])
            ->name('tickets.recall');

        Route::post('/tickets/{ticket}/skip', [OperationalActionController::class, 'skipTicket'])
            ->name('tickets.skip');

        Route::post('/tickets/{ticket}/reschedule', [OperationalActionController::class, 'rescheduleTicket'])
            ->middleware('role:owner,admin')
            ->name('tickets.reschedule');

        Route::get('/tickets/{ticket}/payment', [OperationalActionController::class, 'showPaymentPage'])
            ->middleware('role:owner,admin')
            ->name('tickets.payment');

        Route::post('/tickets/{ticket}/checkout', [OperationalActionController::class, 'checkoutTicket'])
            ->middleware('role:owner,admin')
            ->name('tickets.checkout');

        Route::post('/tickets/{ticket}/confirm-payment', [OperationalActionController::class, 'confirmPayment'])
            ->middleware('role:owner,admin')
            ->name('tickets.confirm-payment');

        Route::patch('/chairs/{membership}/work-status', [OperationalActionController::class, 'updateChairWorkStatus'])
            ->middleware('role:owner,admin')
            ->name('chairs.work-status');
    });

    // Barber App
    Route::middleware(['role:barber', 'subscription.active'])->prefix('barber')->name('barber.')->group(function (): void {
        Route::get('/', [RoleDashboardController::class, 'barber'])->name('dashboard');
        Route::get('/earnings', [RoleDashboardController::class, 'barberEarnings'])->name('earnings');
        Route::get('/attendance', [RoleDashboardController::class, 'barberAttendance'])->name('attendance');
        Route::post('/time-off', [RoleDashboardController::class, 'requestTimeOff'])->name('time-off.store');
        Route::post('/work-status', [OperationalActionController::class, 'toggleBarberWorkStatus'])->name('work-status');
        Route::get('/profile', [RoleDashboardController::class, 'barberProfile'])->name('profile');
        Route::get('/profile/edit', [RoleDashboardController::class, 'editBarberProfile'])->name('profile.edit');
        Route::put('/profile', [RoleDashboardController::class, 'updateBarberProfile'])->name('profile.update');
        Route::put('/profile/password', [RoleDashboardController::class, 'updateBarberPassword'])->name('profile.password');
    });

    // Customer Portal (Personal Dashboard, Booking, History, Profile)
    Route::middleware('role:customer')->prefix('customer')->name('customer.')->group(function (): void {
        Route::get('/', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/booking', [CustomerPortalController::class, 'booking'])->name('booking');
        Route::post('/booking', [CustomerPortalController::class, 'storeBooking'])->name('booking.store');
        Route::get('/history', [CustomerPortalController::class, 'history'])->name('history');
        Route::get('/profile', [CustomerPortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [CustomerPortalController::class, 'updateProfile'])->name('profile.update');
    });
});

// Tenant-Scoped Public & Customer Portal (Guest OK & Customer, blocks Staff from customer portal)
Route::middleware('role:customer')->prefix('b/{tenant}')->group(function (): void {
    Route::get('/', [RoleDashboardController::class, 'customerPortal'])->name('customer.portal');

    // Tenant-Branded Auth (Guest only)
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('tenant.login');
        Route::post('/login', [TenantAuthController::class, 'login'])
            ->middleware('throttle:6,1')
            ->name('tenant.login.store');
        Route::get('/register', [TenantAuthController::class, 'showRegister'])->name('tenant.register');
        Route::post('/register', [TenantAuthController::class, 'register'])
            ->middleware('throttle:6,1')
            ->name('tenant.register.store');
    });

    // Ambil Antrean & Booking Langsung per Tenant
    Route::post('/queue', [OperationalActionController::class, 'customerTakeQueue'])
        ->middleware('throttle:15,1')
        ->name('customer.queue.store');
    Route::post('/booking', [OperationalActionController::class, 'customerCreateBooking'])
        ->middleware('throttle:15,1')
        ->name('customer.tenant-booking.store');
});

// TV Display Antrean (Publik)
Route::get('/b/{tenant}/tv/{branchCode}', [RoleDashboardController::class, 'tvDisplay'])->name('display.queue');

// Customer Ticket Tracking, Booking Detail, Selesai & Penilaian/Pembayaran (Guest & Customer)
Route::middleware('role:customer')->prefix('customer')->name('customer.')->group(function (): void {
    Route::get('/tickets/{ticket}', [CustomerPortalController::class, 'showTicket'])->name('tickets.show');
    Route::get('/bookings/{booking}', [CustomerPortalController::class, 'showBooking'])->name('bookings.show');
    Route::get('/tickets/{ticket}/checkout', [CustomerPortalController::class, 'showCheckout'])->name('tickets.checkout');
    Route::post('/tickets/{ticket}/checkout', [CustomerPortalController::class, 'processCheckout'])->name('tickets.process-checkout');
    Route::get('/tickets/{ticket}/waiting-payment', [CustomerPortalController::class, 'waitingPayment'])->name('tickets.waiting-payment');
});

// Absensi Scan QR Code Cabang (Semua Staf & Kasir & Barber)
Route::middleware(['auth', 'subscription.active'])->prefix('attend')->name('attend.')->group(function (): void {
    Route::get('/{token}', [AttendanceScanController::class, 'show'])->name('scan');
    Route::post('/{token}', [AttendanceScanController::class, 'record'])->name('record');
});

Route::get('/invoices/{orderNumber}', [RoleDashboardController::class, 'showInvoice'])->name('invoices.show');
