<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Order;
use App\Models\OrderItem;

final class CommissionService
{
    /**
     * Resolve aturan komisi terbaik untuk sebuah item layanan & barber.
     *
     * @return array{type: string, value: int, rule_id: int|null}
     */
    public function resolveRuleSnapshot(int $tenantId, ?int $serviceId, ?int $categoryId, ?int $barberMembershipId): array
    {
        // 1. Cek aturan komisi per layanan atau kategori (komisi flat per kepala / layanan)
        $rules = CommissionRule::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        // 2. Cek aturan khusus individu staf terlebih dahulu (jika ada setting khusus per staf)
        if ($barberMembershipId !== null) {
            $memberRule = $rules->first(fn ($r) => $r->membership_id === $barberMembershipId);
            if ($memberRule !== null) {
                return [
                    'rule_id' => $memberRule->id,
                    'type' => $memberRule->type,
                    'value' => (int) $memberRule->value,
                ];
            }
        }

        // 3. Cek aturan komisi per layanan atau kategori
        foreach ($rules as $rule) {
            if ($rule->membership_id !== null) {
                continue;
            }

            if ($rule->scope === 'service' && $rule->service_id !== $serviceId) {
                continue;
            }

            if ($rule->scope === 'category' && $rule->service_category_id !== $categoryId) {
                continue;
            }

            return [
                'rule_id' => $rule->id,
                'type' => $rule->type,
                'value' => (int) $rule->value,
            ];
        }

        // 4. Jika tidak ada aturan spesifik layanan, cek apakah staf memakai skema persentase bagi hasil eksplisit
        if ($barberMembershipId !== null) {
            $barber = \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->with('salaryScheme')
                ->find($barberMembershipId);

            if ($barber?->salaryScheme && $barber->salaryScheme->type === 'commission_only' && !empty($barber->salaryScheme->commission_percentage)) {
                return [
                    'rule_id' => null,
                    'type' => 'percentage',
                    'value' => (int) $barber->salaryScheme->commission_percentage,
                ];
            }
        }

        // 5. Standar komisi per layanan: flat Rp5.000 per layanan pangkas
        return [
            'rule_id' => null,
            'type' => 'flat',
            'value' => 5_000,
        ];
    }

    /**
     * Hitung nominal komisi dari harga bersih item dan snapshot aturan.
     *
     * @param  array{type?: string, value?: int}|null  $snapshot
     */
    public function calculateAmount(int $lineTotal, ?array $snapshot): int
    {
        if (empty($snapshot)) {
            return 0;
        }

        $type = $snapshot['type'] ?? 'flat';
        $value = (int) ($snapshot['value'] ?? 0);

        if ($type === 'percentage') {
            return (int) round(($lineTotal * $value) / 100);
        }

        return max(0, $value);
    }

    /**
     * Catat komisi ke commission_entries setelah order berstatus Paid.
     */
    public function recordOrderCommissions(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            if ($item->barber_membership_id === null) {
                continue;
            }

            $amount = $this->calculateAmount($item->line_total, $item->commission_rule_snapshot);

            if ($amount <= 0) {
                continue;
            }

            CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->firstOrCreate(
                    [
                        'order_item_id' => $item->id,
                        'type' => 'commission',
                    ],
                    [
                        'tenant_id' => $order->tenant_id,
                        'branch_id' => $order->branch_id,
                        'membership_id' => $item->barber_membership_id,
                        'base_amount' => $item->line_total,
                        'amount' => $amount,
                        'earned_at' => $order->paid_at ?? now(),
                    ]
                );
        }
    }

    /**
     * Buat jurnal pembalik (reversal negatif) saat order di-refund.
     */
    public function reverseOrderCommissions(Order $order): void
    {
        $order->loadMissing('items.commissionEntries');

        foreach ($order->items as $item) {
            foreach ($item->commissionEntries as $entry) {
                if ($entry->type !== 'commission') {
                    continue;
                }

                CommissionEntry::query()
                    ->withoutGlobalScope('tenant')
                    ->firstOrCreate(
                        [
                            'order_item_id' => $item->id,
                            'type' => 'reversal',
                        ],
                        [
                            'tenant_id' => $order->tenant_id,
                            'branch_id' => $order->branch_id,
                            'membership_id' => $entry->membership_id,
                            'base_amount' => -$entry->base_amount,
                            'amount' => -$entry->amount,
                            'earned_at' => now(),
                        ]
                    );
            }
        }
    }
}
