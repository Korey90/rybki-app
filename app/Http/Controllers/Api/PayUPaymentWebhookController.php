<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Actions\HandlePaymentWebhook;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Exceptions\PaymentConfirmationFailed;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use App\Modules\Payments\Exceptions\PaymentWebhookSignatureInvalid;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class PayUPaymentWebhookController extends Controller
{
    /**
     * Process a PayU Marketplace notification.
     */
    public function __invoke(
        Request $request,
        PaymentGatewayManager $paymentGatewayManager,
        HandlePaymentWebhook $handlePaymentWebhook
    ): JsonResponse {
        $rawPayload = $request->getContent();

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        try {
            $webhookEvent = $handlePaymentWebhook->handle(
                gateway: $paymentGatewayManager->for(PaymentProvider::PayUMarketplace->value),
                payload: $payload,
                headers: [
                    'openpayu-signature' => $request->header('OpenPayu-Signature'),
                ],
                rawPayload: $rawPayload,
            );
        } catch (PaymentWebhookSignatureInvalid $exception) {
            return response()->json([
                'message' => 'Webhook signature is invalid.',
                'reason' => $exception->reason,
            ], 400);
        } catch (PaymentProviderRequestFailed $exception) {
            return response()->json([
                'message' => 'Webhook payload could not be processed.',
                'reason' => $exception->reason,
            ], 422);
        } catch (PaymentConfirmationFailed $exception) {
            return response()->json([
                'message' => 'Payment succeeded but booking could not be confirmed.',
                'reason' => $exception->reason,
            ], 409);
        }

        return response()->json([
            'webhook_event' => $this->webhookEventPayload($webhookEvent),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function webhookEventPayload(PaymentWebhookEvent $webhookEvent): array
    {
        return [
            'provider' => $webhookEvent->provider->value,
            'provider_event_id' => $webhookEvent->provider_event_id,
            'event_type' => $webhookEvent->event_type,
            'processed_at' => $webhookEvent->processed_at?->toIso8601String(),
            'failed_at' => $webhookEvent->failed_at?->toIso8601String(),
            'error' => $webhookEvent->error,
        ];
    }
}
