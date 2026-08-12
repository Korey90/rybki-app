<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingHoldRequest;
use App\Modules\Bookings\Actions\CreateBookingHold;
use App\Modules\Bookings\Exceptions\BookingHoldUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingPriceLine;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class BookingHoldController extends Controller
{
    /**
     * Create a temporary booking hold.
     */
    public function store(StoreBookingHoldRequest $request, CreateBookingHold $createBookingHold): JsonResponse
    {
        $validated = $request->validated();
        $spot = FishingSpot::query()
            ->where('uuid', $validated['fishing_spot_uuid'])
            ->firstOrFail();

        try {
            $booking = $createBookingHold->handle(
                spot: $spot,
                startsAt: CarbonImmutable::parse((string) $validated['starts_at']),
                endsAt: CarbonImmutable::parse((string) $validated['ends_at']),
                customer: $validated,
                user: $request->user(),
            );
        } catch (BookingHoldUnavailable $exception) {
            return response()->json([
                'message' => 'Selected spot is not available for this range.',
                'reason' => $exception->reason,
            ], 409);
        }

        return response()->json([
            'booking' => $this->bookingPayload($booking),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        $reservation = $booking->spotReservations->first();
        assert($reservation instanceof SpotReservation);

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'currency' => $booking->currency,
            'subtotal_amount' => $booking->subtotal_amount,
            'discount_amount' => $booking->discount_amount,
            'total_amount' => $booking->total_amount,
            'commission_bps' => $booking->commission_bps,
            'platform_fee_amount' => $booking->platform_fee_amount,
            'operator_amount' => $booking->operator_amount,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'reservation' => [
                'uuid' => $reservation->uuid,
                'status' => $reservation->status->value,
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
                'hold_expires_at' => $reservation->hold_expires_at?->toIso8601String(),
                'spot' => [
                    'uuid' => $reservation->spot->uuid,
                    'name' => $reservation->spot->name,
                ],
            ],
            'price_lines' => $booking->priceLines->map(fn (BookingPriceLine $line): array => [
                'type' => $line->type->value,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_amount' => $line->unit_amount,
                'total_amount' => $line->total_amount,
            ])->values()->all(),
        ];
    }
}
