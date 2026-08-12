<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Bookings\Actions\DispatchBookingNotification;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\Contracts\MarketplacePaymentGateway;
use App\Modules\Payments\DTO\PaymentEvent;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentConfirmationFailed;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Throwable;

class HandlePaymentWebhook
{
    public function __construct(
        private readonly ConfirmBookingAfterPayment $confirmBookingAfterPayment,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly DispatchBookingNotification $dispatchBookingNotification,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $headers
     */
    public function handle(
        MarketplacePaymentGateway $gateway,
        array $payload,
        array $headers = [],
        ?string $rawPayload = null
    ): PaymentWebhookEvent {
        $paymentEvent = $gateway->parseWebhook($payload, $headers, $rawPayload);
        $existingEvent = PaymentWebhookEvent::query()
            ->where('provider', $paymentEvent->provider)
            ->where('provider_event_id', $paymentEvent->providerEventId)
            ->first();

        if ($existingEvent instanceof PaymentWebhookEvent) {
            return $existingEvent;
        }

        try {
            return $this->process($paymentEvent);
        } catch (QueryException $exception) {
            $existingEvent = PaymentWebhookEvent::query()
                ->where('provider', $paymentEvent->provider)
                ->where('provider_event_id', $paymentEvent->providerEventId)
                ->first();

            if ($existingEvent instanceof PaymentWebhookEvent) {
                return $existingEvent;
            }

            throw $exception;
        }
    }

    private function process(PaymentEvent $paymentEvent): PaymentWebhookEvent
    {
        $webhookEvent = PaymentWebhookEvent::create([
            'provider' => $paymentEvent->provider,
            'provider_event_id' => $paymentEvent->providerEventId,
            'event_type' => $paymentEvent->eventType,
            'payload' => $paymentEvent->payload,
        ]);

        try {
            $payment = Payment::query()
                ->where('provider', $paymentEvent->provider)
                ->where('provider_payment_id', $paymentEvent->providerPaymentId)
                ->firstOrFail();

            if ($paymentEvent->status === PaymentStatus::Succeeded->value) {
                $payment->update([
                    'status' => PaymentStatus::Succeeded,
                    'paid_at' => $payment->paid_at ?? now(),
                    'provider_payload' => $paymentEvent->payload,
                ]);

                $booking = $payment->booking;
                assert($booking instanceof Booking);

                $this->confirmBookingAfterPayment->handle($booking);
            } elseif ($paymentEvent->status === PaymentStatus::Failed->value) {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'failed_at' => now(),
                    'provider_payload' => $paymentEvent->payload,
                ]);

                $booking = $payment->booking;
                assert($booking instanceof Booking);

                $this->dispatchBookingNotification->paymentFailed($booking);
            } elseif ($paymentEvent->status === PaymentStatus::Processing->value) {
                $payment->update([
                    'status' => PaymentStatus::Processing,
                    'provider_payload' => $paymentEvent->payload,
                ]);
            } elseif ($paymentEvent->status === PaymentStatus::Cancelled->value) {
                $payment->update([
                    'status' => PaymentStatus::Cancelled,
                    'provider_payload' => $paymentEvent->payload,
                ]);
            }

            $webhookEvent->update([
                'processed_at' => now(),
            ]);

            $this->recordAuditLog->handle(
                action: 'payment.webhook_processed',
                subject: $webhookEvent,
                after: [
                    'provider' => $paymentEvent->provider,
                    'provider_event_id' => $paymentEvent->providerEventId,
                    'event_type' => $paymentEvent->eventType,
                    'payment_status' => $payment->status->value,
                    'provider_payment_id' => $payment->provider_payment_id,
                ],
            );
        } catch (PaymentConfirmationFailed $exception) {
            $webhookEvent->update([
                'failed_at' => now(),
                'error' => $exception->reason,
            ]);

            $this->recordAuditLog->handle(
                action: 'payment.webhook_failed',
                subject: $webhookEvent,
                after: [
                    'provider' => $paymentEvent->provider,
                    'provider_event_id' => $paymentEvent->providerEventId,
                    'event_type' => $paymentEvent->eventType,
                    'error' => $exception->reason,
                ],
            );

            throw $exception;
        } catch (Throwable $exception) {
            $webhookEvent->update([
                'failed_at' => now(),
                'error' => $exception->getMessage(),
            ]);

            $this->recordAuditLog->handle(
                action: 'payment.webhook_failed',
                subject: $webhookEvent,
                after: [
                    'provider' => $paymentEvent->provider,
                    'provider_event_id' => $paymentEvent->providerEventId,
                    'event_type' => $paymentEvent->eventType,
                    'error' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }

        return $webhookEvent->refresh();
    }
}
