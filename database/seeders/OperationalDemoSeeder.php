<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\CommissionEntry;
use App\Models\CustomerProfile;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\QueueTicketItem;
use App\Models\Review;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class OperationalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $sudirman = Branch::query()->where('tenant_id', $barberKing->id)->where('code', 'SDR')->firstOrFail();
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $barbers = Membership::query()
            ->where('tenant_id', $barberKing->id)
            ->where('role', Role::Barber)
            ->get()
            ->keyBy('display_name');

        $services = Service::query()
            ->where('tenant_id', $barberKing->id)
            ->get()
            ->keyBy('name');

        // Customer yang memiliki akun login (Rafael)
        $customerRafaelUser = User::query()->updateOrCreate(
            ['email' => 'rafael@customer.id'],
            [
                'name' => 'Rafael Pratama',
                'phone' => '081510000001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $customersData = [
            ['name' => 'Rafael', 'phone' => '081510000001', 'user_id' => $customerRafaelUser->id, 'notes' => 'Tipis samping, atas jangan terlalu pendek'],
            ['name' => 'Budi Santoso', 'phone' => '081510000002', 'user_id' => null, 'notes' => 'Low fade'],
            ['name' => 'Fajar Nugroho', 'phone' => '081510000003', 'user_id' => null, 'notes' => null],
            ['name' => 'Dimas Anggara', 'phone' => '081510000004', 'user_id' => null, 'notes' => null],
            ['name' => 'Gilang Ramadhan', 'phone' => '081510000005', 'user_id' => null, 'notes' => null],
            ['name' => 'Hendra Kusuma', 'phone' => '081510000006', 'user_id' => null, 'notes' => null],
        ];

        $customers = [];
        foreach ($customersData as $c) {
            $customers[$c['name']] = CustomerProfile::query()->updateOrCreate(
                ['tenant_id' => $barberKing->id, 'phone' => $c['phone']],
                [
                    'user_id' => $c['user_id'],
                    'name' => $c['name'],
                    'notes' => $c['notes'],
                    'visit_count' => 4,
                    'total_spent' => 250_000,
                    'last_visit_at' => now()->subWeeks(2),
                    'points' => 25,
                ]
            );
        }

        // Shift kasir aktif hari ini
        $today = now()->toDateString();
        $shift = CashShift::query()->updateOrCreate(
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'cashier_user_id' => $cashierUser->id,
                'opened_at' => now()->setTime(9, 0),
            ],
            [
                'opening_cash' => 300_000,
                'expected_cash' => 1_240_000,
            ]
        );

        // Counter antrean hari ini
        QueueCounter::query()->updateOrCreate(
            ['branch_id' => $sudirman->id, 'date' => $today, 'prefix' => 'A'],
            ['tenant_id' => $barberKing->id, 'last_number' => 14]
        );
        QueueCounter::query()->updateOrCreate(
            ['branch_id' => $sudirman->id, 'date' => $today, 'prefix' => 'B'],
            ['tenant_id' => $barberKing->id, 'last_number' => 16]
        );

        // Booking yang belum check-in (Gilang 12:00 & Hendra 10:30)
        $bookingGilang = Booking::query()->updateOrCreate(
            ['code' => 'BK-SDR-0001'],
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'customer_profile_id' => $customers['Gilang Ramadhan']->id,
                'barber_membership_id' => $barbers['Bima']->id,
                'scheduled_at' => now()->setTime(12, 0),
                'estimated_end_at' => now()->setTime(12, 45),
                'total_duration_minutes' => 45,
                'estimated_total' => 50_000,
                'status' => BookingStatus::Confirmed,
                'source' => 'customer_app',
            ]
        );

        BookingItem::query()->updateOrCreate(
            ['booking_id' => $bookingGilang->id, 'service_id' => $services['Haircut']->id],
            [
                'service_name' => 'Haircut',
                'price' => 50_000,
                'duration_minutes' => 45,
            ]
        );

        $bookingHendra = Booking::query()->updateOrCreate(
            ['code' => 'BK-SDR-0002'],
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'customer_profile_id' => $customers['Hendra Kusuma']->id,
                'barber_membership_id' => $barbers['Andi']->id,
                'scheduled_at' => now()->setTime(10, 30),
                'estimated_end_at' => now()->setTime(10, 50),
                'total_duration_minutes' => 20,
                'estimated_total' => 30_000,
                'status' => BookingStatus::Confirmed,
                'source' => 'customer_app',
            ]
        );

        BookingItem::query()->updateOrCreate(
            ['booking_id' => $bookingHendra->id, 'service_id' => $services['Shave']->id],
            [
                'service_name' => 'Shave',
                'price' => 30_000,
                'duration_minutes' => 20,
            ]
        );

        // Tiket antrean hari ini (sesuai tampilan mockup)
        $ticketsData = [
            [
                'number' => 'A-008',
                'sequence' => 8,
                'customer' => $customers['Rafael'],
                'barber' => $barbers['Andi'],
                'type' => 'walk_in',
                'status' => QueueStatus::Completed,
                'services' => [$services['Haircut'], $services['Hair Wash']],
                'notes' => 'Tipis samping, atas jangan terlalu pendek',
            ],
            [
                'number' => 'A-009',
                'sequence' => 9,
                'customer' => $customers['Budi Santoso'],
                'barber' => $barbers['Bima'],
                'type' => 'walk_in',
                'status' => QueueStatus::Completed,
                'services' => [$services['Haircut']],
                'notes' => null,
            ],
            [
                'number' => 'A-010',
                'sequence' => 10,
                'customer' => $customers['Dimas Anggara'],
                'barber' => $barbers['Andi'],
                'type' => 'walk_in',
                'status' => QueueStatus::InService,
                'services' => [$services['Haircut']],
                'notes' => null,
            ],
            [
                'number' => 'A-011',
                'sequence' => 11,
                'customer' => $customers['Gilang Ramadhan'],
                'barber' => $barbers['Bima'],
                'type' => 'walk_in',
                'status' => QueueStatus::InService,
                'services' => [$services['Shave']],
                'notes' => null,
            ],
            [
                'number' => 'A-012',
                'sequence' => 12,
                'customer' => $customers['Fajar Nugroho'],
                'barber' => $barbers['Doni'],
                'type' => 'walk_in',
                'status' => QueueStatus::Called,
                'services' => [$services['Haircut']],
                'notes' => null,
            ],
            [
                'number' => 'A-013',
                'sequence' => 13,
                'customer' => $customers['Rafael'],
                'barber' => $barbers['Andi'],
                'type' => 'booking',
                'status' => QueueStatus::Waiting,
                'services' => [$services['Haircut'], $services['Hair Wash']],
                'notes' => 'Tipis samping, atas jangan terlalu pendek',
            ],
            [
                'number' => 'A-014',
                'sequence' => 14,
                'customer' => $customers['Budi Santoso'],
                'barber' => null,
                'type' => 'walk_in',
                'status' => QueueStatus::Waiting,
                'services' => [$services['Haircut']],
                'notes' => null,
            ],
        ];

        $createdTickets = [];
        foreach ($ticketsData as $t) {
            $duration = collect($t['services'])->sum('duration_minutes');
            $ticket = QueueTicket::query()->updateOrCreate(
                ['branch_id' => $sudirman->id, 'date' => $today, 'number' => $t['number']],
                [
                    'tenant_id' => $barberKing->id,
                    'sequence' => $t['sequence'],
                    'customer_profile_id' => $t['customer']->id,
                    'barber_membership_id' => $t['barber']?->id,
                    'requested_barber_id' => $t['barber']?->id,
                    'type' => $t['type'],
                    'status' => $t['status'],
                    'priority_at' => now()->subMinutes(100 - ($t['sequence'] * 5)),
                    'estimated_duration_minutes' => $duration,
                    'notes' => $t['notes'],
                    'called_at' => in_array($t['status'], [QueueStatus::Called, QueueStatus::InService, QueueStatus::Completed], true) ? now()->subMinutes(30) : null,
                    'started_at' => in_array($t['status'], [QueueStatus::InService, QueueStatus::Completed], true) ? now()->subMinutes(20) : null,
                    'completed_at' => $t['status'] === QueueStatus::Completed ? now()->subMinutes(5) : null,
                ]
            );

            foreach ($t['services'] as $srv) {
                QueueTicketItem::query()->updateOrCreate(
                    ['queue_ticket_id' => $ticket->id, 'service_id' => $srv->id],
                    [
                        'service_name' => $srv->name,
                        'price' => $srv->price,
                        'duration_minutes' => $srv->duration_minutes,
                    ]
                );
            }

            QueueTicketEvent::query()->firstOrCreate(
                ['queue_ticket_id' => $ticket->id, 'to_status' => $t['status']->value],
                [
                    'from_status' => null,
                    'actor_user_id' => $cashierUser->id,
                    'created_at' => now(),
                ]
            );

            $createdTickets[$t['number']] = $ticket;
        }

        // Order Lunas untuk tiket A-008 + Komisi + Review
        $ticketA008 = $createdTickets['A-008'];
        $order = Order::query()->updateOrCreate(
            ['number' => 'INV-SDR-20261027-0038'],
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'queue_ticket_id' => $ticketA008->id,
                'customer_profile_id' => $customers['Rafael']->id,
                'barber_membership_id' => $barbers['Andi']->id,
                'cashier_user_id' => $cashierUser->id,
                'cash_shift_id' => $shift->id,
                'subtotal' => 75_000,
                'discount_total' => 5_000,
                'tip_amount' => 10_000,
                'grand_total' => 80_000,
                'paid_total' => 80_000,
                'payment_status' => PaymentStatus::Paid,
                'idempotency_key' => 'demo-order-a008',
                'paid_at' => now()->subMinutes(3),
            ]
        );

        $orderItem1 = OrderItem::query()->updateOrCreate(
            ['order_id' => $order->id, 'service_id' => $services['Haircut']->id],
            [
                'tenant_id' => $barberKing->id,
                'barber_membership_id' => $barbers['Andi']->id,
                'name' => 'Haircut',
                'type' => 'service',
                'qty' => 1,
                'unit_price' => 50_000,
                'discount' => 5_000,
                'line_total' => 45_000,
                'commission_rule_snapshot' => ['type' => 'flat', 'value' => 5_000],
            ]
        );

        $orderItem2 = OrderItem::query()->updateOrCreate(
            ['order_id' => $order->id, 'service_id' => $services['Hair Wash']->id],
            [
                'tenant_id' => $barberKing->id,
                'barber_membership_id' => $barbers['Andi']->id,
                'name' => 'Hair Wash',
                'type' => 'service',
                'qty' => 1,
                'unit_price' => 25_000,
                'discount' => 0,
                'line_total' => 25_000,
                'commission_rule_snapshot' => ['type' => 'flat', 'value' => 3_000],
            ]
        );

        Payment::query()->updateOrCreate(
            ['idempotency_key' => 'demo-payment-a008'],
            [
                'tenant_id' => $barberKing->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'method' => PaymentMethod::Qris,
                'amount' => 80_000,
                'reference' => 'QRIS-20261027-0038',
                'status' => 'success',
                'received_by' => $cashierUser->id,
            ]
        );

        CommissionEntry::query()->updateOrCreate(
            ['order_item_id' => $orderItem1->id, 'type' => 'commission'],
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'membership_id' => $barbers['Andi']->id,
                'base_amount' => 45_000,
                'amount' => 5_000,
                'earned_at' => now()->subMinutes(3),
            ]
        );

        CommissionEntry::query()->updateOrCreate(
            ['order_item_id' => $orderItem2->id, 'type' => 'commission'],
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'membership_id' => $barbers['Andi']->id,
                'base_amount' => 25_000,
                'amount' => 3_000,
                'earned_at' => now()->subMinutes(3),
            ]
        );

        Review::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'tenant_id' => $barberKing->id,
                'branch_id' => $sudirman->id,
                'customer_profile_id' => $customers['Rafael']->id,
                'barber_membership_id' => $barbers['Andi']->id,
                'rating' => 5,
                'comment' => 'Potongan Mas Andi rapi banget, sesuai request!',
                'is_published' => true,
            ]
        );

        // Payroll Draft Oktober (sesuai simulasi 300 layanan Andi = Rp3.800.000)
        $payroll = Payroll::query()->updateOrCreate(
            [
                'tenant_id' => $barberKing->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ],
            [
                'branch_id' => null,
                'status' => PayrollStatus::Draft,
                'total_amount' => 10_300_000,
                'notes' => 'Draft payroll periode berjalan.',
            ]
        );

        $andiPayroll = PayrollItem::query()->updateOrCreate(
            ['payroll_id' => $payroll->id, 'membership_id' => $barbers['Andi']->id],
            [
                'tenant_id' => $barberKing->id,
                'services_count' => 300,
                'base_salary' => 2_000_000,
                'commission_total' => 1_500_000,
                'bonus_total' => 300_000,
                'deduction_total' => 0,
                'tip_total' => 120_000,
                'gross_total' => 3_800_000,
                'net_total' => 3_920_000,
                'scheme_snapshot' => ['type' => 'base_plus_commission', 'base_salary' => 2_000_000],
                'payment_status' => 'unpaid',
            ]
        );

        PayrollAdjustment::query()->updateOrCreate(
            ['payroll_item_id' => $andiPayroll->id, 'label' => 'Bonus Target 300 Layanan'],
            [
                'type' => 'bonus',
                'amount' => 300_000,
                'notes' => 'Mencapai target >= 300 layanan selesai.',
            ]
        );

        $bimaPayroll = PayrollItem::query()->updateOrCreate(
            ['payroll_id' => $payroll->id, 'membership_id' => $barbers['Bima']->id],
            [
                'tenant_id' => $barberKing->id,
                'services_count' => 260,
                'base_salary' => 2_000_000,
                'commission_total' => 1_300_000,
                'bonus_total' => 200_000,
                'deduction_total' => 150_000,
                'tip_total' => 80_000,
                'gross_total' => 3_500_000,
                'net_total' => 3_430_000,
                'scheme_snapshot' => ['type' => 'base_plus_commission', 'base_salary' => 2_000_000],
                'payment_status' => 'unpaid',
            ]
        );

        PayrollAdjustment::query()->updateOrCreate(
            ['payroll_item_id' => $bimaPayroll->id, 'label' => 'Potongan Kasbon'],
            [
                'type' => 'deduction',
                'amount' => 150_000,
                'notes' => 'Cicilan kasbon Oktober.',
            ]
        );

        PayrollItem::query()->updateOrCreate(
            ['payroll_id' => $payroll->id, 'membership_id' => $barbers['Rizky']->id],
            [
                'tenant_id' => $barberKing->id,
                'services_count' => 210,
                'base_salary' => 0,
                'commission_total' => 3_150_000,
                'bonus_total' => 0,
                'deduction_total' => 0,
                'tip_total' => 95_000,
                'gross_total' => 3_150_000,
                'net_total' => 3_245_000,
                'scheme_snapshot' => ['type' => 'commission_only', 'percentage' => 30],
                'payment_status' => 'unpaid',
            ]
        );
    }
}
