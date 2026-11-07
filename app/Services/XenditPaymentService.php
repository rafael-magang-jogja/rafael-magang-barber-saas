<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\SubscriptionInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class XenditPaymentService
{
    private const BASE_URL = 'https://api.xendit.co';

    private string $secretKey;

    private string $webhookToken;

    public function __construct()
    {
        $this->secretKey = (string) config('services.xendit.secret_key', '');
        $this->webhookToken = (string) config('services.xendit.webhook_token', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey);
    }

    /**
     * Create or retrieve an existing valid Xendit QRIS invoice for a booking.
     *
     * @return array<string, mixed>
     */
    public function getOrCreateQrisInvoice(Booking $booking): array
    {
        // Jika sudah ada invoice dan belum kedaluwarsa, gunakan kembali
        if (! empty($booking->xendit_payment_url) && ! empty($booking->xendit_invoice_id)) {
            if ($booking->xendit_expired_at === null || $booking->xendit_expired_at->isFuture()) {
                return [
                    'id' => $booking->xendit_invoice_id,
                    'external_id' => $booking->xendit_external_id,
                    'invoice_url' => $booking->xendit_payment_url,
                    'status' => 'PENDING',
                    'expiry_date' => $booking->xendit_expired_at?->toIso8601String(),
                ];
            }
        }

        return $this->createQrisInvoice($booking);
    }

    /**
     * Create a new Xendit QRIS invoice for a booking.
     *
     * @return array<string, mixed>
     */
    public function createQrisInvoice(Booking $booking): array
    {
        $booking->loadMissing(['tenant', 'customerProfile', 'items']);

        $amount = (int) ($booking->estimated_total ?: $booking->items->sum('price'));
        if ($amount <= 0) {
            $amount = (int) $booking->items->sum('price');
        }

        $externalId = 'BOS-BK-'.$booking->code.'-'.time();
        $tenantName = $booking->tenant?->name ?? 'BarberOS';
        $customerName = $booking->customerProfile?->name ?? 'Pelanggan';
        $customerPhone = $booking->customerProfile?->phone ?? null;

        // Normalisasi format nomor telepon Indonesia (e.g. 0812 -> +62812)
        if (! empty($customerPhone)) {
            if (str_starts_with($customerPhone, '0')) {
                $customerPhone = '+62'.substr($customerPhone, 1);
            } elseif (! str_starts_with($customerPhone, '+')) {
                $customerPhone = '+62'.$customerPhone;
            }
        }

        $successUrl = route('customer.bookings.show', $booking);
        $failureUrl = route('customer.bookings.show', $booking);

        if (! $this->isConfigured()) {
            // Mode fallback/simulasi lokal jika API key belum diisi di .env
            Log::info('Xendit secret key not configured. Generating mock QRIS invoice.', [
                'booking_id' => $booking->id,
            ]);

            $mockInvoice = [
                'id' => 'mock_inv_'.bin2hex(random_bytes(8)),
                'external_id' => $externalId,
                'invoice_url' => $successUrl.'?mock_qris=1',
                'status' => 'PENDING',
                'expiry_date' => now()->addMinutes(30)->toIso8601String(),
                'payment_methods' => ['QRIS', 'OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA', 'ASTRAPAY'],
            ];

            $booking->update([
                'xendit_invoice_id' => $mockInvoice['id'],
                'xendit_external_id' => $mockInvoice['external_id'],
                'xendit_payment_url' => $mockInvoice['invoice_url'],
                'xendit_payment_method' => 'QRIS_EWALLET',
                'xendit_expired_at' => now()->addMinutes(30),
            ]);

            return $mockInvoice;
        }

        $payload = [
            'external_id' => $externalId,
            'amount' => $amount,
            'description' => "Reservasi Barbershop {$tenantName} (#{$booking->code})",
            'invoice_duration' => 1800, // 30 menit
            'customer' => array_filter([
                'given_names' => $customerName,
                'mobile_number' => $customerPhone,
            ]),
            'payment_methods' => ['QRIS', 'OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA', 'ASTRAPAY'],
            'currency' => 'IDR',
            'success_redirect_url' => $successUrl,
            'failure_redirect_url' => $failureUrl,
        ];

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(15)
            ->post(self::BASE_URL.'/v2/invoices', $payload);

        if (! $response->successful()) {
            Log::error('Xendit Create Invoice Failed', [
                'booking_id' => $booking->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Gagal membuat tagihan QRIS Xendit: '.($response->json('message') ?? $response->body()));
        }

        $data = $response->json();

        $booking->update([
            'xendit_invoice_id' => $data['id'] ?? null,
            'xendit_external_id' => $data['external_id'] ?? $externalId,
            'xendit_payment_url' => $data['invoice_url'] ?? null,
            'xendit_payment_method' => 'QRIS',
            'xendit_payment_channel' => 'QRIS',
            'xendit_expired_at' => ! empty($data['expiry_date']) ? Carbon::parse($data['expiry_date']) : now()->addMinutes(30),
        ]);

        return $data;
    }

    /**
     * Create or retrieve an existing valid Xendit invoice for a subscription invoice.
     *
     * @return array<string, mixed>
     */
    public function getOrCreateSubscriptionInvoice(SubscriptionInvoice $invoice): array
    {
        // Jika sudah ada invoice dan belum kedaluwarsa, gunakan kembali
        if (! empty($invoice->xendit_payment_url) && ! empty($invoice->xendit_invoice_id)) {
            if ($invoice->xendit_expired_at === null || $invoice->xendit_expired_at->isFuture()) {
                return [
                    'id' => $invoice->xendit_invoice_id,
                    'external_id' => $invoice->xendit_external_id,
                    'invoice_url' => $invoice->xendit_payment_url,
                    'status' => 'PENDING',
                    'expiry_date' => $invoice->xendit_expired_at?->toIso8601String(),
                ];
            }
        }

        return $this->createSubscriptionInvoice($invoice);
    }

    /**
     * Create a new Xendit invoice for a SaaS subscription invoice.
     *
     * @return array<string, mixed>
     */
    public function createSubscriptionInvoice(SubscriptionInvoice $invoice): array
    {
        $invoice->loadMissing(['tenant.owner', 'targetPlan']);

        $amount = (int) $invoice->amount;
        if ($amount <= 0) {
            throw new RuntimeException('Tidak dapat membuat tagihan Xendit untuk paket gratis (Rp0).');
        }

        $externalId = 'BOS-SUB-'.$invoice->number.'-'.$invoice->id;
        $tenantName = $invoice->tenant?->name ?? 'BarberOS Partner';
        $planName = $invoice->targetPlan?->name ?? 'SaaS Barbershop';
        $owner = $invoice->tenant?->owner ?? $invoice->tenant?->users()->where('role', 'owner')->first();

        $customerName = $owner?->name ?? $tenantName;
        $customerEmail = $owner?->email ?? null;
        $customerPhone = $owner?->phone ?? null;

        if (! empty($customerPhone)) {
            if (str_starts_with($customerPhone, '0')) {
                $customerPhone = '+62'.substr($customerPhone, 1);
            } elseif (! str_starts_with($customerPhone, '+')) {
                $customerPhone = '+62'.$customerPhone;
            }
        }

        $redirectUrl = route('owner.subscription.invoice', $invoice->id);

        if (! $this->isConfigured()) {
            Log::info('Xendit secret key not configured. Generating mock Subscription invoice.', [
                'invoice_id' => $invoice->id,
                'number' => $invoice->number,
            ]);

            $mockInvoice = [
                'id' => 'mock_sub_inv_'.bin2hex(random_bytes(8)),
                'external_id' => $externalId,
                'invoice_url' => $redirectUrl.'?mock_xendit_pay=1',
                'status' => 'PENDING',
                'expiry_date' => now()->addHours(24)->toIso8601String(),
                'payment_methods' => ['QRIS', 'BCA', 'BNI', 'BRI', 'MANDIRI'],
            ];

            $invoice->update([
                'gateway' => 'xendit',
                'gateway_ref' => $mockInvoice['id'],
                'xendit_invoice_id' => $mockInvoice['id'],
                'xendit_external_id' => $mockInvoice['external_id'],
                'xendit_payment_url' => $mockInvoice['invoice_url'],
                'xendit_payment_method' => 'XENDIT_ONLINE',
                'xendit_expired_at' => now()->addHours(24),
            ]);

            return $mockInvoice;
        }

        $payload = [
            'external_id' => $externalId,
            'amount' => $amount,
            'description' => "Langganan Paket {$planName} ({$tenantName})",
            'invoice_duration' => 86400, // 24 jam
            'customer' => array_filter([
                'given_names' => $customerName,
                'email' => $customerEmail,
                'mobile_number' => $customerPhone,
            ]),
            'payment_methods' => ['QRIS', 'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'OVO', 'DANA', 'SHOPEEPAY'],
            'currency' => 'IDR',
            'success_redirect_url' => $redirectUrl,
            'failure_redirect_url' => $redirectUrl,
        ];

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(15)
            ->post(self::BASE_URL.'/v2/invoices', $payload);

        if (! $response->successful()) {
            Log::error('Xendit Create Subscription Invoice Failed', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Gagal membuat tagihan Xendit: '.($response->json('message') ?? $response->body()));
        }

        $data = $response->json();

        $invoice->update([
            'gateway' => 'xendit',
            'gateway_ref' => $data['id'] ?? null,
            'xendit_invoice_id' => $data['id'] ?? null,
            'xendit_external_id' => $data['external_id'] ?? $externalId,
            'xendit_payment_url' => $data['invoice_url'] ?? null,
            'xendit_payment_method' => 'XENDIT_ONLINE',
            'xendit_expired_at' => ! empty($data['expiry_date']) ? Carbon::parse($data['expiry_date']) : now()->addHours(24),
        ]);

        return $data;
    }

    /**
     * Get invoice details from Xendit by invoice ID.
     *
     * @return array<string, mixed>|null
     */
    public function getInvoice(string $invoiceId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->get(self::BASE_URL."/v2/invoices/{$invoiceId}");

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Verify Xendit Webhook Token (x-callback-token).
     */
    public function verifyWebhookToken(?string $incomingToken): bool
    {
        if (empty($this->webhookToken) || empty($incomingToken)) {
            return false;
        }

        return hash_equals($this->webhookToken, $incomingToken);
    }
}
