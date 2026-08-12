<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(private readonly ManageableOrganizations $manageableOrganizations) {}

    /**
     * Show an operator reservation calendar.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Organization::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ]);

        $organizationIds = $this->manageableOrganizations->idsFor($user);
        $days = (int) ($validated['days'] ?? 14);
        $from = CarbonImmutable::parse((string) ($validated['from'] ?? now()->toDateString()))->startOfDay();
        $until = $from->addDays($days);

        $reservations = SpotReservation::query()
            ->with(['booking.venue', 'spot'])
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
            ->whereIn('status', [
                ReservationStatus::Hold->value,
                ReservationStatus::Confirmed->value,
            ])
            ->where('starts_at', '<', $until->toDateTimeString())
            ->where('ends_at', '>', $from->toDateTimeString())
            ->orderBy('starts_at')
            ->get();

        $dayRows = [];
        for ($index = 0; $index < $days; $index++) {
            $dayStart = $from->addDays($index);
            $dayEnd = $dayStart->addDay();

            $dayRows[] = [
                'date' => $dayStart->toDateString(),
                'reservations' => $reservations
                    ->filter(fn (SpotReservation $reservation): bool => $reservation->starts_at->lessThan($dayEnd) && $reservation->ends_at->greaterThan($dayStart))
                    ->map(fn (SpotReservation $reservation): array => $this->reservationPayload($reservation))
                    ->values()
                    ->all(),
            ];
        }

        return Inertia::render('operator/calendar', [
            'range' => [
                'from' => $from->toDateString(),
                'until' => $until->subDay()->toDateString(),
                'days' => $days,
            ],
            'days' => $dayRows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationPayload(SpotReservation $reservation): array
    {
        $booking = $reservation->booking;
        assert($booking instanceof Booking);

        $venue = $booking->venue;
        assert($venue instanceof FishingVenue);

        $spot = $reservation->spot;
        assert($spot instanceof FishingSpot);

        return [
            'uuid' => $reservation->uuid,
            'status' => $reservation->status->value,
            'starts_at' => $reservation->starts_at->toIso8601String(),
            'ends_at' => $reservation->ends_at->toIso8601String(),
            'booking' => [
                'uuid' => $booking->uuid,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
                'total_amount' => $booking->total_amount,
                'currency' => $booking->currency,
            ],
            'venue' => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
            ],
            'spot' => [
                'uuid' => $spot->uuid,
                'name' => $spot->name,
            ],
        ];
    }
}
