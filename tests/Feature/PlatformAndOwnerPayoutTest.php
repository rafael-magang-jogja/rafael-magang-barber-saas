<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantPayout;
use App\Models\TenantPayoutItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlatformAndOwnerPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    private function getOrCreateCustomerProfileId(Tenant $tenant, string $phone = '081234567890', string $name = 'Customer Test'): int
    {
        $customer = User::query()->where('phone', $phone)->first()
            ?? User::factory()->create(['phone' => $phone, 'name' => $name, 'is_superadmin' => false]);

        return $customer->customerProfiles()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => $customer->phone],
            ['name' => $customer->name]
        )->id;
    }

    public function test_superadmin_can_access_payouts_page_and_view_metrics_and_tenant_balances(): void
    {
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profileId = $this->getOrCreateCustomerProfileId($tenant, '081233344455', 'John Doe');

        // Buat booking yang lunas via QRIS
        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profileId,
            'code' => 'BK-PAYOUT-01',
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => 60000,
            'status' => BookingStatus::Confirmed,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_test_payout_01',
            'xendit_payment_method' => 'QRIS',
            'xendit_paid_at' => Carbon::now(),
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => 60000,
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($superadmin)->get(route('platform.payouts'));

        $response->assertOk();
        $response->assertSee('Pencairan Dana Escrow Booking');
        $response->assertSee($tenant->name);
        $response->assertSee('Rp60.000');
    }

    public function test_superadmin_can_process_payout_with_proof_upload_for_bank_or_ewallet(): void
    {
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profileId1 = $this->getOrCreateCustomerProfileId($tenant, '081211111111', 'Customer A');
        $profileId2 = $this->getOrCreateCustomerProfileId($tenant, '081222222222', 'Customer B');

        // Rekening resmi sudah diatur oleh owner
        $tenant->update([
            'payout_bank_name' => 'DANA',
            'payout_account_number' => '081299998888',
            'payout_account_holder' => 'Rudi Hartono (DANA)',
        ]);

        // Buat 2 booking lunas
        $b1 = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profileId1,
            'code' => 'BK-PAYOUT-11',
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => 50000,
            'status' => BookingStatus::Confirmed,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_test_11',
            'xendit_paid_at' => Carbon::now(),
        ]);
        $b1->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'price' => 50000, 'duration_minutes' => 30]);

        $b2 = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profileId2,
            'code' => 'BK-PAYOUT-12',
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => 75000,
            'status' => BookingStatus::CheckedIn,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_test_12',
            'xendit_paid_at' => Carbon::now(),
        ]);
        $b2->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'price' => 75000, 'duration_minutes' => 30]);

        $fakeProof = UploadedFile::fake()->create('bukti-transfer-dana.png', 200, 'image/png');

        $response = $this->actingAs($superadmin)->post(route('platform.payouts.store'), [
            'tenant_id' => $tenant->id,
            'notes' => 'Transfer pencairan booking DANA',
            'transfer_proof' => $fakeProof,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Pastikan record payout dibuat dengan total 125.000 ke rekening terdaftar owner
        $this->assertDatabaseHas('tenant_payouts', [
            'tenant_id' => $tenant->id,
            'amount' => 125000,
            'status' => 'completed',
            'bank_name' => 'DANA',
            'account_number' => '081299998888',
            'account_holder' => 'Rudi Hartono (DANA)',
        ]);

        $payout = TenantPayout::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertStringStartsWith('PO-', $payout->reference_no);
        $this->assertCount(2, $payout->items);

        // Bukti transfer tersimpan di storage public
        Storage::disk('public')->assertExists($payout->transfer_proof_path);
    }

    public function test_superadmin_cannot_disburse_payout_when_owner_has_not_set_bank_account(): void
    {
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profileId = $this->getOrCreateCustomerProfileId($tenant, '081233344455', 'John Doe');

        // Pastikan rekening tenant kosong (belum diatur owner)
        $tenant->update([
            'payout_bank_name' => null,
            'payout_account_number' => null,
            'payout_account_holder' => null,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profileId,
            'code' => 'BK-PAYOUT-NOBANK',
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => 60000,
            'status' => BookingStatus::Confirmed,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_test_nobank',
            'xendit_paid_at' => Carbon::now(),
        ]);
        $booking->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'price' => 60000, 'duration_minutes' => 30]);

        $fakeProof = UploadedFile::fake()->create('proof.png', 100, 'image/png');

        $response = $this->actingAs($superadmin)->post(route('platform.payouts.store'), [
            'tenant_id' => $tenant->id,
            'transfer_proof' => $fakeProof,
        ]);

        $response->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('tenant_payouts', 0);
    }

    public function test_superadmin_cannot_disburse_payout_when_available_balance_is_zero(): void
    {
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();
        $tenant = Tenant::query()->firstOrFail();

        $tenant->update([
            'payout_bank_name' => 'BCA',
            'payout_account_number' => '1234567890',
            'payout_account_holder' => 'Test Owner',
        ]);

        $fakeProof = UploadedFile::fake()->create('proof.png', 100, 'image/png');

        $response = $this->actingAs($superadmin)->post(route('platform.payouts.store'), [
            'tenant_id' => $tenant->id,
            'transfer_proof' => $fakeProof,
        ]);

        $response->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('tenant_payouts', 0);
    }

    public function test_owner_can_view_payouts_page_and_pending_balance(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $tenant = $membership->tenant;
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $profileId = $this->getOrCreateCustomerProfileId($tenant, '081299990000', 'Customer C');

        // Buat booking lunas
        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $profileId,
            'code' => 'BK-OWNER-01',
            'scheduled_at' => Carbon::now()->addDay(),
            'estimated_end_at' => Carbon::now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => 45000,
            'status' => BookingStatus::Confirmed,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_test_owner_01',
            'xendit_paid_at' => Carbon::now(),
        ]);
        $booking->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'price' => 45000, 'duration_minutes' => 30]);

        $response = $this->actingAs($owner)->get(route('owner.payouts'));

        $response->assertOk();
        $response->assertSee('Pencairan Dana Booking');
        $response->assertSee('BK-OWNER-01');
        $response->assertSee('Rp45.000');
        $response->assertSee('Mengendap di Superadmin');
    }

    public function test_owner_can_update_payout_destination_bank_or_ewallet(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $tenant = $membership->tenant;

        $response = $this->actingAs($owner)->put(route('owner.payouts.update-bank-account'), [
            'payout_bank_name' => 'GoPay',
            'payout_account_number' => '081234567890',
            'payout_account_holder' => 'Rudi Hartono (GoPay)',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $tenant->refresh();
        $this->assertSame('GoPay', $tenant->payout_bank_name);
        $this->assertSame('081234567890', $tenant->payout_account_number);
        $this->assertSame('Rudi Hartono (GoPay)', $tenant->payout_account_holder);
    }

    public function test_owner_can_see_disbursed_payout_history_and_proof(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $tenant = $membership->tenant;
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();

        // Buat record TenantPayout yang sudah selesai
        $payout = TenantPayout::create([
            'tenant_id' => $tenant->id,
            'reference_no' => 'PO-20261105-ABC999',
            'amount' => 150000,
            'status' => 'completed',
            'bank_name' => 'OVO',
            'account_number' => '081277778888',
            'account_holder' => 'Rudi Hartono',
            'transfer_proof_path' => 'payout-proofs/sample-proof.png',
            'processed_by' => $superadmin->id,
            'processed_at' => Carbon::now(),
            'notes' => 'Pencairan via OVO berhasil',
        ]);

        $response = $this->actingAs($owner)->get(route('owner.payouts'));

        $response->assertOk();
        $response->assertSee('PO-20261105-ABC999');
        $response->assertSee('Rp150.000');
        $response->assertSee('OVO');
        $response->assertSee('Lihat Bukti');
    }
}
