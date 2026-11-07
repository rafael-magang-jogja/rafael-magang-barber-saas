<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BarberSchedule;
use App\Models\BarberTimeOff;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingScheduleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_booking_fails_when_branch_is_closed_on_selected_day(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Atur cabang tutup pada hari Minggu (day 0)
        $branch->update([
            'opening_hours' => [
                0 => ['is_open' => '0', 'open' => '10:00', 'close' => '21:00'],
                1 => ['is_open' => '1', 'open' => '10:00', 'close' => '21:00'],
            ],
        ]);

        // Cari tanggal hari Minggu berikutnya
        $nextSunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY)->setTime(14, 0);

        $response = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $nextSunday->format('Y-m-d'),
            'scheduled_time' => '14:00',
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertStringContainsString('tutup pada hari Minggu', session('errors')->first('booking'));
    }

    public function test_booking_fails_earlier_than_15_minutes_after_branch_opens(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Cabang buka jam 09:00
        $branch->update([
            'opening_hours' => [
                'mon' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'tue' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'wed' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'thu' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'fri' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'sat' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'sun' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
            ],
        ]);

        $targetDate = CarbonImmutable::now()->addDay();

        // Coba booking tepat jam 09:00 (kurang dari 15 menit setelah buka)
        $response = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '09:00',
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertStringContainsString('minimal 15 menit setelah cabang buka', session('errors')->first('booking'));
    }

    public function test_booking_fails_later_than_15_minutes_before_branch_closes(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Cabang tutup jam 21:00
        $branch->update([
            'opening_hours' => [
                'mon' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'tue' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'wed' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'thu' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'fri' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'sat' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
                'sun' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00'],
            ],
        ]);

        $targetDate = CarbonImmutable::now()->addDay();

        // Coba booking jam 20:50 (kurang dari 15 menit sebelum tutup)
        $response = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '20:50',
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertStringContainsString('15 menit', session('errors')->first('booking'));
    }

    public function test_booking_fails_during_break_and_1_hour_before_break(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Cabang istirahat jam 12:00 - 13:00
        $branch->update([
            'opening_hours' => [
                'mon' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
                'tue' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
                'wed' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
                'thu' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
                'fri' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
                'sat' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
                'sun' => ['is_open' => '1', 'open' => '09:00', 'close' => '21:00', 'break_start' => '12:00', 'break_end' => '13:00'],
            ],
        ]);

        $targetDate = CarbonImmutable::now()->addDay();

        // 1. Coba booking jam 11:30 (kurang dari 1 jam sebelum istirahat 12:00) -> Ditolak
        $response1 = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '11:30',
        ]);
        $response1->assertSessionHasErrors('booking');
        $this->assertStringContainsString('istirahat', session('errors')->first('booking'));

        // 2. Coba booking jam 12:15 (tepat saat jam istirahat) -> Ditolak
        $response2 = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '12:15',
        ]);
        $response2->assertSessionHasErrors('booking');
        $this->assertStringContainsString('istirahat', session('errors')->first('booking'));

        // 3. Coba booking jam 13:15 (dalam batas 15 menit setelah istirahat selesai 13:00) -> Ditolak
        $response3 = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '13:15',
        ]);
        $response3->assertSessionHasErrors('booking');
        $this->assertStringContainsString('istirahat', session('errors')->first('booking'));

        // 4. Coba booking jam 13:45 (setelah batas 15 menit pasca-istirahat) -> Berhasil
        $response4 = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '13:45',
        ]);
        $response4->assertSessionDoesntHaveErrors();
    }

    public function test_booking_fails_within_1_hour_before_shift_ends(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', \App\Enums\Role::Barber)->firstOrFail();

        $targetDate = CarbonImmutable::now()->addDay();

        // Barber shift sampai jam 20:00
        BarberSchedule::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'membership_id' => $barber->id,
                'day_of_week' => $targetDate->dayOfWeek,
            ],
            [
                'branch_id' => $branch->id,
                'start_time' => '09:00:00',
                'end_time' => '20:00:00',
                'is_off' => false,
            ]
        );

        // Coba booking jam 19:15 (kurang dari 1 jam sebelum shift berakhir 20:00)
        $response = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'barber_membership_id' => $barber->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '19:15',
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertStringContainsString('1 jam sebelum shift', session('errors')->first('booking'));
    }

    public function test_booking_fails_when_selected_barber_is_on_approved_time_off(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', \App\Enums\Role::Barber)->firstOrFail();

        $targetDate = CarbonImmutable::now()->addDay();

        // Berikan izin libur cuti yang disetujui untuk barber
        BarberTimeOff::query()->create([
            'tenant_id' => $tenant->id,
            'membership_id' => $barber->id,
            'date_from' => $targetDate->toDateString(),
            'date_to' => $targetDate->toDateString(),
            'type' => 'leave',
            'status' => 'approved',
            'reason' => 'Keperluan keluarga',
        ]);

        $response = $this->actingAs($customer)->post(route('customer.booking.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'barber_membership_id' => $barber->id,
            'service_ids' => [$service->id],
            'scheduled_date' => $targetDate->format('Y-m-d'),
            'scheduled_time' => '14:00',
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertStringContainsString('cuti atau izin libur', session('errors')->first('booking'));
    }

    public function test_available_slots_endpoint_returns_json_with_correct_availability(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $targetDate = CarbonImmutable::now()->addDay()->toDateString();

        $response = $this->actingAs($customer)->getJson(
            route('customer.booking.slots', [
                'branch_id' => $branch->id,
                'date' => $targetDate,
            ])
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'date',
            'day_name',
            'is_open',
            'slots' => [
                '*' => ['time', 'is_available', 'reason'],
            ],
        ]);
    }
}
