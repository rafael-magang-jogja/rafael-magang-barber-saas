<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\QueueStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\CustomerWithdrawal;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerWalletService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CustomerRefundWalletTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private User $customerUser;
    private CustomerProfile $profile;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');

        $this->tenant = Tenant::query()->firstOrFail();
        $this->branch = Branch::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->service = Service::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->customerUser = User::factory()->create([
            'phone' => '081299998888',
            'wallet_balance' => 0,
            'is_superadmin' => false,
        ]);

        $this->profile = CustomerProfile::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'phone' => $this->customerUser->phone],
            ['user_id' => $this->customerUser->id, 'name' => $this->customerUser->name, 'wallet_balance' => 0]
        );
    }

    public function test_cancelling_paid_booking_automatically_refunds_to_customer_wallet(): void
    {
        // 1. Buat booking berbayar QRIS
        $booking = Booking::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->customerUser->id,
            'customer_profile_id' => $this->profile->id,
            'customer_name' => $this->customerUser->name,
            'customer_phone' => $this->customerUser->phone,
            'code' => 'BK-REFUND-01',
            'status' => BookingStatus::Confirmed,
            'estimated_total' => 50000,
            'total_duration_minutes' => 30,
            'xendit_paid_at' => now(),
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
        ]);

        QueueTicket::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'booking_id' => $booking->id,
            'customer_profile_id' => $this->profile->id,
            'number' => 'B-001',
            'sequence' => 1,
            'priority_at' => now(),
            'date' => Carbon::now()->addDay()->toDateString(),
            'status' => QueueStatus::Waiting,
        ]);

        $this->actingAs($this->customerUser->fresh())
            ->post(route('customer.bookings.cancel', $booking), [
                'reason' => 'Ada keperluan mendadak',
            ])
            ->assertSessionHasNoErrors();

        // Saldo customer otomatis bertambah Rp50.000
        $this->assertEquals(50000, $this->customerUser->fresh()->wallet_balance);
        $this->assertEquals(50000, $this->profile->fresh()->wallet_balance);

        // Status refund booking otomatis completed
        $freshBooking = $booking->fresh();
        $this->assertEquals(BookingStatus::Cancelled, $freshBooking->status);
        $this->assertEquals('completed', $freshBooking->refund_status);
        $this->assertEquals(50000, $freshBooking->refund_amount);

        // Mutasi transaksi kredit tercatat
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'user_id' => $this->customerUser->id,
            'type' => 'credit',
            'category' => 'refund_booking',
            'amount' => 50000,
            'balance_after' => 50000,
        ]);
    }

    public function test_customer_can_pay_booking_using_wallet_balance(): void
    {
        // Beri saldo awal customer Rp60.000
        $walletService = app(CustomerWalletService::class);
        $walletService->credit($this->customerUser, 60000, 'adjustment', null, 'Saldo awal', $this->tenant->id, $this->profile);

        $booking = Booking::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->customerUser->id,
            'customer_profile_id' => $this->profile->id,
            'customer_name' => $this->customerUser->name,
            'customer_phone' => $this->customerUser->phone,
            'code' => 'BK-PAYWALLET-01',
            'status' => BookingStatus::Pending,
            'estimated_total' => 50000,
            'total_duration_minutes' => 30,
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
        ]);

        $this->actingAs($this->customerUser->fresh())
            ->post(route('customer.bookings.pay-wallet', $booking))
            ->assertRedirect(route('customer.bookings.show', $booking))
            ->assertSessionHas('status');

        // Saldo customer berkurang Rp50.000 sisa Rp10.000
        $this->assertEquals(10000, $this->customerUser->fresh()->wallet_balance);

        // Booking lunas terkonfirmasi
        $this->assertEquals(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->payment_approved_at);

        // Mutasi debit tercatat
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'user_id' => $this->customerUser->id,
            'type' => 'debit',
            'category' => 'booking_payment',
            'amount' => 50000,
            'balance_after' => 10000,
        ]);
    }

    public function test_customer_can_request_withdrawal_and_superadmin_can_approve(): void
    {
        // Saldo customer Rp50.000
        $walletService = app(CustomerWalletService::class);
        $walletService->credit($this->customerUser, 50000, 'refund_booking', null, 'Refund', $this->tenant->id, $this->profile);

        // 1. Customer ajukan penarikan dana
        $this->actingAs($this->customerUser->fresh())
            ->post(route('customer.wallet.withdraw'), [
                'amount' => 50000,
                'bank_name' => 'GoPay',
                'account_number' => '081299998888',
                'account_holder' => 'Customer Budi',
            ])
            ->assertSessionHas('status');

        // Saldo customer tetap Rp50.000 sebelum di-ACC Superadmin!
        $this->assertEquals(50000, $this->customerUser->fresh()->wallet_balance);

        $withdrawal = CustomerWithdrawal::where('user_id', $this->customerUser->id)->firstOrFail();
        $this->assertEquals('pending', $withdrawal->status);
        $this->assertEquals(0, $withdrawal->admin_fee); // Bebas biaya admin
        $this->assertEquals(50000, $withdrawal->net_amount);

        // Mutasi debit belum ada sebelum disetujui Superadmin
        $this->assertDatabaseMissing('customer_wallet_transactions', [
            'user_id' => $this->customerUser->id,
            'type' => 'debit',
            'category' => 'withdrawal',
        ]);

        // 2. Superadmin menyetujui penarikan
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();

        $this->actingAs($superadmin)
            ->post(route('platform.payouts.withdrawals.approve', $withdrawal))
            ->assertSessionHasNoErrors();

        $this->assertEquals('completed', $withdrawal->fresh()->status);
        $this->assertNotNull($withdrawal->fresh()->processed_at);
        $this->assertEquals($superadmin->id, $withdrawal->fresh()->processed_by);

        // Setelah di-ACC Superadmin, saldo resmi terpotong menjadi Rp0
        $this->assertEquals(0, $this->customerUser->fresh()->wallet_balance);

        // Mutasi debit tercatat setelah transfer disetujui
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'user_id' => $this->customerUser->id,
            'type' => 'debit',
            'category' => 'withdrawal',
            'amount' => 50000,
            'balance_after' => 0,
        ]);
    }

    public function test_superadmin_can_reject_withdrawal_and_balance_is_not_deducted(): void
    {
        // Saldo customer Rp50.000
        $walletService = app(CustomerWalletService::class);
        $walletService->credit($this->customerUser, 50000, 'refund_booking', null, 'Refund', $this->tenant->id, $this->profile);

        // Customer ajukan penarikan
        $this->actingAs($this->customerUser->fresh())
            ->post(route('customer.wallet.withdraw'), [
                'amount' => 50000,
                'bank_name' => 'BCA',
                'account_number' => '000000',
                'account_holder' => 'Salah Nama',
            ]);

        // Saldo tetap Rp50.000
        $this->assertEquals(50000, $this->customerUser->fresh()->wallet_balance);
        $withdrawal = CustomerWithdrawal::where('user_id', $this->customerUser->id)->firstOrFail();

        // Superadmin menolak
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();

        $this->actingAs($superadmin)
            ->post(route('platform.payouts.withdrawals.reject', $withdrawal), [
                'rejection_reason' => 'Nomor rekening tidak ditemukan di sistem BCA',
            ])
            ->assertSessionHasNoErrors();

        // Status ditolak
        $this->assertEquals('rejected', $withdrawal->fresh()->status);
        $this->assertEquals('Nomor rekening tidak ditemukan di sistem BCA', $withdrawal->fresh()->rejection_reason);

        // Saldo customer tetap utuh Rp50.000
        $this->assertEquals(50000, $this->customerUser->fresh()->wallet_balance);

        // Tidak ada transaksi debit pemotongan dana
        $this->assertDatabaseMissing('customer_wallet_transactions', [
            'user_id' => $this->customerUser->id,
            'type' => 'debit',
            'category' => 'withdrawal',
        ]);
    }
}
