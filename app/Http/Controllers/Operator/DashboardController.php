<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ManageableOrganizations $manageableOrganizations) {}

    /**
     * Show the operator dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Organization::class);

        $memberships = $this->manageableOrganizations->membershipsFor($user);
        $organizationIds = $memberships->pluck('organization_id')->map(fn (mixed $id): int => (int) $id)->all();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $upcomingReservations = SpotReservation::query()
            ->with(['booking.venue', 'spot'])
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
            ->whereIn('status', [
                ReservationStatus::Hold->value,
                ReservationStatus::Confirmed->value,
            ])
            ->where('ends_at', '>=', now()->toDateTimeString())
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $recentBookings = Booking::query()
            ->with(['venue', 'spotReservations.spot', 'payments' => fn ($query) => $query->latest()])
            ->whereIn('organization_id', $organizationIds)
            ->latest()
            ->limit(6)
            ->get();

        $dailyRows = [];
        for ($day = 0; $day < 7; $day++) {
            $date = now()->startOfDay()->addDays($day);
            $nextDate = $date->copy()->addDay();

            $dailyRows[] = [
                'date' => $date->toDateString(),
                'reservations_count' => SpotReservation::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->where('status', ReservationStatus::Confirmed->value)
                    ->where('starts_at', '<', $nextDate->toDateTimeString())
                    ->where('ends_at', '>', $date->toDateTimeString())
                    ->count(),
                'revenue_amount' => (int) Booking::query()
                    ->whereIn('organization_id', $organizationIds)
                    ->where('status', BookingStatus::Confirmed->value)
                    ->whereDate('confirmed_at', $date->toDateString())
                    ->sum('total_amount'),
            ];
        }

        return Inertia::render('operator/dashboard', [
            'organizations' => $memberships->map(fn (OrganizationMember $membership): array => $this->organizationPayload($membership))->all(),
            'stats' => [
                'organizations_count' => count($organizationIds),
                'venues_count' => FishingVenue::query()->whereIn('organization_id', $organizationIds)->count(),
                'spots_count' => FishingSpot::query()
                    ->whereHas('venue', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->count(),
                'today_reservations_count' => SpotReservation::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->where('status', ReservationStatus::Confirmed->value)
                    ->where('starts_at', '<=', $todayEnd->toDateTimeString())
                    ->where('ends_at', '>=', $todayStart->toDateTimeString())
                    ->count(),
                'active_holds_count' => SpotReservation::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->where('status', ReservationStatus::Hold->value)
                    ->where('hold_expires_at', '>', now()->toDateTimeString())
                    ->count(),
                'gross_revenue_amount' => (int) Booking::query()
                    ->whereIn('organization_id', $organizationIds)
                    ->where('status', BookingStatus::Confirmed->value)
                    ->sum('total_amount'),
                'platform_fee_amount' => (int) Booking::query()
                    ->whereIn('organization_id', $organizationIds)
                    ->where('status', BookingStatus::Confirmed->value)
                    ->sum('platform_fee_amount'),
                'operator_revenue_amount' => (int) Booking::query()
                    ->whereIn('organization_id', $organizationIds)
                    ->where('status', BookingStatus::Confirmed->value)
                    ->sum('operator_amount'),
                'pending_payments_count' => Payment::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->whereIn('status', [
                        PaymentStatus::Created->value,
                        PaymentStatus::Processing->value,
                    ])
                    ->count(),
            ],
            'upcoming_reservations' => $upcomingReservations->map(fn (SpotReservation $reservation): array => $this->reservationPayload($reservation))->all(),
            'recent_bookings' => $recentBookings->map(fn (Booking $booking): array => $this->bookingPayload($booking))->all(),
            'daily_schedule' => $dailyRows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationPayload(OrganizationMember $membership): array
    {
        $organization = $membership->organization;
        assert($organization instanceof Organization);

        return [
            'uuid' => $organization->uuid,
            'name' => $organization->name,
            'role' => $membership->role->value,
        ];
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
            'hold_expires_at' => $reservation->hold_expires_at?->toIso8601String(),
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

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        $venue = $booking->venue;
        assert($venue instanceof FishingVenue);

        $reservation = $booking->spotReservations->first();

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'customer_name' => $booking->customer_name,
            'total_amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'created_at' => $booking->created_at?->toIso8601String(),
            'venue' => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
            ],
            'reservation' => $reservation instanceof SpotReservation ? [
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
                'spot_name' => $reservation->spot?->name,
            ] : null,
        ];
    }
}
