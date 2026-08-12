<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use App\Modules\Payments\Exceptions\RefundFailed;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\PaymentGatewayManager;

class RefundPayment
{
    public function __construct(private readonly PaymentGatewayManager $paymentGatewayManager) {}

    public function handle(Payment $payment, Refund $refund): Refund
    {
        try {
            $result = $this->paymentGatewayManager
                ->for($payment->provider->value)
                ->refund($payment, $refund);
        } catch (PaymentProviderRequestFailed $exception) {
            $refund->update(['status' => RefundStatus::Failed]);

            throw new RefundFailed($exception->reason);
        }

        $refund->update([
            'provider_refund_id' => $result->providerRefundId,
            'status' => RefundStatus::from($result->status),
        ]);

        if ($result->status === RefundStatus::Failed->value) {
            throw new RefundFailed('provider_refund_failed');
        }

        $payment->update([
            'status' => $refund->amount >= $payment->amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded,
        ]);

        return $refund->refresh();
    }
}
