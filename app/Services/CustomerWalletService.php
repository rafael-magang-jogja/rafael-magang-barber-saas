<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\CustomerWalletTransaction;
use App\Models\CustomerWithdrawal;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerWalletService
{
    /**
     * Tambahkan saldo ke dompet customer secara atomic.
     */
    public function credit(
        User $user,
        int $amount,
        string $category,
        ?string $referenceId = null,
        ?string $notes = null,
        ?int $tenantId = null,
        ?CustomerProfile $profile = null
    ): CustomerWalletTransaction {
        if ($amount <= 0) {
            throw new DomainException('Nominal penambahan saldo harus lebih dari Rp0.');
        }

        return DB::transaction(function () use ($user, $amount, $category, $referenceId, $notes, $tenantId, $profile): CustomerWalletTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (int) $lockedUser->wallet_balance;
            $balanceAfter = $balanceBefore + $amount;

            $lockedUser->update(['wallet_balance' => $balanceAfter]);

            if ($profile !== null) {
                $profile->update(['wallet_balance' => $balanceAfter]);
            }

            return CustomerWalletTransaction::create([
                'user_id' => $lockedUser->id,
                'customer_profile_id' => $profile?->id,
                'tenant_id' => $tenantId,
                'type' => 'credit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Kurangi saldo dari dompet customer secara atomic.
     */
    public function debit(
        User $user,
        int $amount,
        string $category,
        ?string $referenceId = null,
        ?string $notes = null,
        ?int $tenantId = null,
        ?CustomerProfile $profile = null
    ): CustomerWalletTransaction {
        if ($amount <= 0) {
            throw new DomainException('Nominal pengurangan saldo harus lebih dari Rp0.');
        }

        return DB::transaction(function () use ($user, $amount, $category, $referenceId, $notes, $tenantId, $profile): CustomerWalletTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (int) $lockedUser->wallet_balance;

            if ($balanceBefore < $amount) {
                throw new DomainException("Saldo dompet tidak mencukupi (Saldo saat ini: Rp" . number_format($balanceBefore, 0, ',', '.') . ").");
            }

            $balanceAfter = $balanceBefore - $amount;

            $lockedUser->update(['wallet_balance' => $balanceAfter]);

            if ($profile !== null) {
                $profile->update(['wallet_balance' => $balanceAfter]);
            }

            return CustomerWalletTransaction::create([
                'user_id' => $lockedUser->id,
                'customer_profile_id' => $profile?->id,
                'tenant_id' => $tenantId,
                'type' => 'debit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Hitung sisa saldo dompet yang tersedia untuk digunakan atau ditarik
     * (setelah dikurangi permohonan penarikan yang berstatus pending/processing).
     */
    public function getAvailableBalance(User $user): int
    {
        $pendingAmount = (int) CustomerWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        return max(0, ((int) $user->wallet_balance) - $pendingAmount);
    }

    /**
     * Ajukan permohonan penarikan dana (Withdrawal) ke rekening / e-wallet.
     * Saldo TIDAK langsung didebit saat pengajuan, saldo tetap utuh di akun customer
     * sampai permohonan disetujui (ACC) & ditransfer oleh Superadmin.
     */
    public function createWithdrawalRequest(
        User $user,
        int $amount,
        string $bankName,
        string $accountNumber,
        string $accountHolder,
        ?CustomerProfile $profile = null
    ): CustomerWithdrawal {
        if ($amount < 10000) {
            throw new DomainException('Batas minimal penarikan saldo adalah Rp10.000.');
        }

        $availableBalance = $this->getAvailableBalance($user);
        if ($amount > $availableBalance) {
            throw new DomainException("Nominal penarikan (Rp" . number_format($amount, 0, ',', '.') . ") melebihi saldo yang tersedia untuk ditarik (Rp" . number_format($availableBalance, 0, ',', '.') . ").");
        }

        return DB::transaction(function () use ($user, $amount, $bankName, $accountNumber, $accountHolder, $profile): CustomerWithdrawal {
            $withdrawalCode = 'WD-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            return CustomerWithdrawal::create([
                'withdrawal_code' => $withdrawalCode,
                'user_id' => $user->id,
                'customer_profile_id' => $profile?->id,
                'amount' => $amount,
                'admin_fee' => 0, // Bebas biaya admin (Rp0)
                'net_amount' => $amount,
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_holder' => $accountHolder,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Superadmin menyetujui dan menandai penarikan selesai (dana ditransfer).
     * Saldo akun customer baru resmi dipotong (debit) pada tahap ini.
     */
    public function approveWithdrawal(
        CustomerWithdrawal $withdrawal,
        ?string $proofPath = null,
        ?int $processedByUserId = null
    ): CustomerWithdrawal {
        if ($withdrawal->status !== 'pending' && $withdrawal->status !== 'processing') {
            throw new DomainException("Permohonan penarikan ini sudah berstatus {$withdrawal->status}.");
        }

        return DB::transaction(function () use ($withdrawal, $proofPath, $processedByUserId): CustomerWithdrawal {
            // Debit saldo customer sekarang karena transfer telah disetujui
            $this->debit(
                user: $withdrawal->user,
                amount: $withdrawal->amount,
                category: 'withdrawal',
                referenceId: $withdrawal->withdrawal_code,
                notes: "Pencairan dana disetujui & ditransfer Superadmin ke {$withdrawal->bank_name} {$withdrawal->account_number} a.n {$withdrawal->account_holder}",
                profile: $withdrawal->customerProfile
            );

            $withdrawal->update([
                'status' => 'completed',
                'proof_path' => $proofPath ?? $withdrawal->proof_path,
                'processed_by' => $processedByUserId,
                'processed_at' => now(),
            ]);

            return $withdrawal;
        });
    }

    /**
     * Superadmin menolak penarikan dana.
     * Karena saldo belum pernah dipotong sebelumnya, tidak diperlukan pembalikan saldo.
     */
    public function rejectWithdrawal(
        CustomerWithdrawal $withdrawal,
        string $reason,
        ?int $processedByUserId = null
    ): CustomerWithdrawal {
        if ($withdrawal->status !== 'pending' && $withdrawal->status !== 'processing') {
            throw new DomainException("Permohonan penarikan ini sudah berstatus {$withdrawal->status}.");
        }

        return DB::transaction(function () use ($withdrawal, $reason, $processedByUserId): CustomerWithdrawal {
            $withdrawal->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'processed_by' => $processedByUserId,
                'processed_at' => now(),
            ]);

            return $withdrawal;
        });
    }
}
