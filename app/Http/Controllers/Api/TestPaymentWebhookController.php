<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTestPaymentWebhookRequest;
use App\Modules\Payments\Actions\HandlePaymentWebhook;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Exceptions\PaymentConfirmationFailed;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;

class TestPaymentWebhookController extends Controller
{
    /**
     * Process a local test payment webhook.
     */
    public function store(
        StoreTestPaymentWebhookRequest $request,
        PaymentGatewayManager $paymentGatewayManager,
        HandlePaymentWebhook $handlePaymentWebhook
    ): JsonResponse {
        try {
            $webhookEvent = $handlePaymentWebhook->handle(
                gateway: $paymentGatewayManager->for(PaymentProvider::Test->value),
                payload: $request->validated(),
            );
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
     * @return array<string, mixed>
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
