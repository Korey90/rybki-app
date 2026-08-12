<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateBookingPaymentRequest;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\Actions\CreateBookingPayment;
use App\Modules\Payments\Exceptions\PaymentSessionUnavailable;
use Illuminate\Http\JsonResponse;

class BookingPaymentController extends Controller
{
    /**
     * Create a payment session for a booking hold.
     */
    public function store(
        CreateBookingPaymentRequest $request,
        Booking $booking,
        CreateBookingPayment $createBookingPayment
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $paymentSession = $createBookingPayment->handle(
                booking: $booking,
                provider: isset($validated['provider']) ? (string) $validated['provider'] : null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
            );
        } catch (PaymentSessionUnavailable $exception) {
            return response()->json([
                'message' => 'Payment session cannot be created for this booking.',
                'reason' => $exception->reason,
            ], 409);
        }

        return response()->json([
            'payment_session' => $paymentSession->toArray(),
        ], 201);
    }
}
