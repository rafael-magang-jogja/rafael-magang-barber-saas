<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SubscriptionInvoice;
use App\Services\QueueService;
use App\Services\SubscriptionActivationService;
use App\Services\XenditPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class XenditWebhookController extends Controller
{
    public function __construct(
        private readonly XenditPaymentService $xenditPaymentService,
        private readonly QueueService $queueService,
        private readonly SubscriptionActivationService $subscriptionActivationService,
    ) {}

    public function handleInvoice(Request $request): JsonResponse
    {
        $callbackToken = $request->header('x-callback-token');

        // Verifikasi token keamanan webhook jika token dikonfigurasi di environment
        if (config('services.xendit.webhook_token') && ! $this->xenditPaymentService->verifyWebhookToken($callbackToken)) {
            Log::warning('Xendit webhook unauthorized token attempt', [
                'ip' => $request->ip(),
                'token' => $callbackToken,
            ]);

            return response()->json(['message' => 'Unauthorized webhook token'], 401);
        }

        $payload = $request->all();
        $invoiceId = $payload['id'] ?? null;
        $externalId = $payload['external_id'] ?? null;
        $status = strtoupper((string) ($payload['status'] ?? ''));

        Log::info('Xendit Webhook Received', [
            'invoice_id' => $invoiceId,
            'external_id' => $externalId,
            'status' => $status,
        ]);

        if (empty($invoiceId) && empty($externalId)) {
            return response()->json(['message' => 'Invalid webhook payload'], 400);
        }

        // 1. Cek apakah webhook ini untuk Tagihan Langganan SaaS (SubscriptionInvoice)
        /** @var SubscriptionInvoice|null $subInvoice */
        $subInvoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where(function ($query) use ($invoiceId, $externalId): void {
                if (! empty($invoiceId)) {
                    $query->where('xendit_invoice_id', $invoiceId);
                }
                if (! empty($externalId)) {
                    $query->orWhere('xendit_external_id', $externalId)
                        ->orWhere('number', $externalId);
                }
            })
            ->first();

        if ($subInvoice === null && ! empty($externalId) && preg_match('/(?:BOS-SUB-|INV-SUB-)([A-Z0-9-]+)/', (string) $externalId, $matches)) {
            $subInvoice = SubscriptionInvoice::query()
                ->withoutGlobalScope('tenant')
                ->where('number', 'like', "%{$matches[1]}%")
                ->first();
        }

        if ($subInvoice !== null) {
            return $this->handleSubscriptionPayment($subInvoice, $status, $payload);
        }

        // 2. Cari booking berdasarkan xendit_invoice_id atau xendit_external_id
        /** @var Booking|null $booking */
        $booking = Booking::query()
            ->withoutGlobalScope('tenant')
            ->where(function ($query) use ($invoiceId, $externalId): void {
                if (! empty($invoiceId)) {
                    $query->where('xendit_invoice_id', $invoiceId);
                }
                if (! empty($externalId)) {
                    $query->orWhere('xendit_external_id', $externalId);
                }
            })
            ->first();

        if ($booking === null && ! empty($externalId) && preg_match('/BOS-BK-([A-Z0-9-]+)-\d+/', (string) $externalId, $matches)) {
            $booking = Booking::query()
                ->withoutGlobalScope('tenant')
                ->where('code', $matches[1])
                ->first();
        }

        if ($booking === null) {
            Log::info('Xendit webhook acknowledged (test ping or unlinked booking)', [
                'invoice_id' => $invoiceId,
                'external_id' => $externalId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received and acknowledged',
            ], 200);
        }

        // Tangani Status PAID (Pembayaran QRIS Berhasil)
        if ($status === 'PAID') {
            // Idempotensi: jika sudah memiliki tiket antrean atau sudah lunas/checked-in
            if ($booking->queueTicket !== null || in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Booking already processed',
                    'booking_id' => $booking->id,
                    'ticket_number' => $booking->queueTicket?->number,
                ]);
            }

            $paidAt = ! empty($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now();
            $paymentChannel = $payload['payment_channel'] ?? $payload['payment_method'] ?? 'QRIS';

            DB::transaction(function () use ($booking, $invoiceId, $externalId, $paymentChannel, $paidAt): void {
                $booking->update([
                    'xendit_invoice_id' => $invoiceId ?: $booking->xendit_invoice_id,
                    'xendit_external_id' => $externalId ?: $booking->xendit_external_id,
                    'xendit_payment_method' => 'QRIS',
                    'xendit_payment_channel' => (string) $paymentChannel,
                    'xendit_paid_at' => $paidAt,
                    'payment_approved_at' => $paidAt,
                    'status' => BookingStatus::Confirmed,
                ]);

                // Terbitkan tiket antrean B-xxx dan buat rekaman Order & Payment QRIS lunas
                $ticket = $this->queueService->checkInBooking($booking);
                $this->queueService->createPaidOrderForBookingTicket($booking, $ticket);
            });

            $booking->refresh()->load('queueTicket');

            Log::info("Booking #{$booking->code} auto-confirmed via Xendit QRIS webhook", [
                'booking_id' => $booking->id,
                'ticket_number' => $booking->queueTicket?->number,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed and booking confirmed',
                'booking_id' => $booking->id,
                'ticket_number' => $booking->queueTicket?->number,
            ]);
        }

        // Tangani Status EXPIRED
        if ($status === 'EXPIRED') {
            if ($booking->status === BookingStatus::Pending && $booking->queueTicket === null) {
                $booking->update([
                    'status' => BookingStatus::Expired,
                    'cancel_reason' => 'Batas waktu pembayaran QRIS habis (Expired)',
                    'cancelled_at' => now(),
                ]);

                Log::info("Booking #{$booking->code} expired via Xendit webhook", [
                    'booking_id' => $booking->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking expired',
                'booking_id' => $booking->id,
            ]);
        }

        return response()->json(['message' => 'Webhook received, no action required']);
    }

    /**
     * Handle Xendit Webhook specifically for SaaS subscription invoices.
     *
     * @param array<string, mixed> $payload
     */
    private function handleSubscriptionPayment(SubscriptionInvoice $invoice, string $status, array $payload): JsonResponse
    {
        if ($status === 'PAID') {
            // Idempotensi: jika sudah lunas sebelumnya
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription invoice already paid',
                    'invoice_id' => $invoice->id,
                    'number' => $invoice->number,
                ]);
            }

            $activatedInvoice = $this->subscriptionActivationService->activateFromInvoice(
                invoice: $invoice,
                payload: [
                    'gateway' => 'xendit',
                    'gateway_ref' => $payload['id'] ?? $invoice->xendit_invoice_id,
                    'payment_method' => $payload['payment_method'] ?? 'XENDIT_ONLINE',
                    'payment_channel' => $payload['payment_channel'] ?? $payload['payment_method'] ?? 'ONLINE',
                    'paid_at' => ! empty($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now(),
                ],
                actor: null,
                defaultGateway: 'xendit',
                defaultGatewayRef: $payload['id'] ?? 'XENDIT-WEBHOOK-PAID'
            );

            Log::info("Subscription invoice #{$invoice->number} marked PAID via Xendit webhook", [
                'invoice_id' => $activatedInvoice->id,
                'tenant_id' => $activatedInvoice->tenant_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'invoice_id' => $activatedInvoice->id,
                'number' => $activatedInvoice->number,
                'status' => $activatedInvoice->status,
                'period_end' => $activatedInvoice->period_end?->toDateString(),
            ]);
        }

        if ($status === 'EXPIRED') {
            if ($invoice->status === 'unpaid') {
                $invoice->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Batas waktu pembayaran tagihan via Xendit kedaluwarsa.',
                    'cancelled_at' => now(),
                ]);

                Log::info("Subscription invoice #{$invoice->number} expired via Xendit webhook", [
                    'invoice_id' => $invoice->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription invoice expired',
                'invoice_id' => $invoice->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Subscription invoice status {$status} acknowledged",
            'invoice_id' => $invoice->id,
        ]);
    }
}
