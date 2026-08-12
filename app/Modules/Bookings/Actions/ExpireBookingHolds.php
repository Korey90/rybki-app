<?php

namespace App\Modules\Bookings\Actions;

use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use Carbon\CarbonInterface;

class ExpireBookingHolds
{
    public function handle(?FishingSpot $spot = null, ?CarbonInterface $now = null): int
    {
        $query = SpotReservation::query()
            ->where('status', ReservationStatus::Hold->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', ($now ?? now())->toDateTimeString());

        if ($spot instanceof FishingSpot) {
            $query->where('fishing_spot_id', $spot->id);
        }

        return $query->update([
            'status' => ReservationStatus::Expired->value,
            'updated_at' => now(),
        ]);
    }
}
