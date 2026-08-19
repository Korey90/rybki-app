<?php

namespace App\Modules\Reviews\Actions;

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use App\Modules\Reviews\Models\FishingVenueReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFishingVenueReview
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        User $user,
        FishingVenue $venue,
        Booking $booking,
        int $rating,
        ?string $title,
        ?string $body,
    ): FishingVenueReview {
        $booking->loadMissing('spotReservations');

        if ($booking->user_id !== $user->id || $booking->fishing_venue_id !== $venue->id) {
            throw new AuthorizationException;
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            throw ValidationException::withMessages([
                'booking_uuid' => 'Opinię można dodać tylko do potwierdzonej rezerwacji.',
            ]);
        }

        $hasCompletedReservation = $booking->spotReservations
            ->contains(fn (SpotReservation $reservation): bool => $reservation->status === ReservationStatus::Confirmed
                && $reservation->ends_at->lessThanOrEqualTo(now()));

        if (! $hasCompletedReservation) {
            throw ValidationException::withMessages([
                'booking_uuid' => 'Opinię można dodać dopiero po zakończonym pobycie.',
            ]);
        }

        if ($booking->review()->exists()) {
            throw ValidationException::withMessages([
                'booking_uuid' => 'Ta rezerwacja ma już dodaną opinię.',
            ]);
        }

        return DB::transaction(fn (): FishingVenueReview => FishingVenueReview::create([
            'user_id' => $user->id,
            'fishing_venue_id' => $venue->id,
            'booking_id' => $booking->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => FishingVenueReviewStatus::Published,
        ]));
    }
}
