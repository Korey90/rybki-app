<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected from operator phase six pages', function () {
    $this->get(route('operator.dashboard'))->assertRedirect(route('login'));
    $this->get(route('operator.bookings.index'))->assertRedirect(route('login'));
    $this->get(route('operator.calendar.index'))->assertRedirect(route('login'));
    $this->get(route('operator.settlements.index'))->assertRedirect(route('login'));
});

test('operator dashboard shows owned operational summary only', function () {
    Carbon::setTestNow('2026-08-11 10:00:00');
    [$user] = operatorPhaseSixScenario();

    $this->actingAs($user);

    $this->get(route('operator.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/dashboard')
            ->where('stats.organizations_count', 1)
            ->where('stats.venues_count', 1)
            ->where('stats.spots_count', 1)
            ->where('stats.today_reservations_count', 1)
            ->where('stats.active_holds_count', 1)
            ->where('stats.gross_revenue_amount', 10000)
            ->where('stats.platform_fee_amount', 1000)
            ->where('stats.operator_revenue_amount', 9000)
            ->where('stats.pending_payments_count', 1)
            ->has('upcoming_reservations', 2)
            ->has('recent_bookings', 2));
});

test('operator bookings index lists owned bookings and counts statuses', function () {
    Carbon::setTestNow('2026-08-11 10:00:00');
    [$user] = operatorPhaseSixScenario();

    $this->actingAs($user);

    $this->get(route('operator.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/bookings/index')
            ->has('bookings', 2)
            ->where('status_counts.CONFIRMED', 1)
            ->where('status_counts.PAYMENT_PENDING', 1));

    $this->get(route('operator.bookings.index', ['status' => BookingStatus::Confirmed->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', BookingStatus::Confirmed->value)
            ->has('bookings', 1)
            ->where('bookings.0.status', BookingStatus::Confirmed->value));
});

test('operator can view owned booking details but not foreign booking details', function () {
    Carbon::setTestNow('2026-08-11 10:00:00');
    [$user, $ownedBooking, $foreignBooking] = operatorPhaseSixScenario();

    $this->actingAs($user);

    $this->get(route('operator.bookings.show', $ownedBooking))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/bookings/show')
            ->where('booking.uuid', $ownedBooking->uuid)
            ->where('booking.total_amount', 10000)
            ->has('booking.reservations', 1)
            ->has('booking.price_lines', 1)
            ->has('booking.payments', 1));

    $this->get(route('operator.bookings.show', $foreignBooking))
        ->assertForbidden();
});

test('operator calendar includes only owned reservations', function () {
    Carbon::setTestNow('2026-08-11 10:00:00');
    [$user] = operatorPhaseSixScenario();

    $this->actingAs($user);

    $this->get(route('operator.calendar.index', [
        'from' => '2026-08-11',
        'days' => 3,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/calendar')
            ->where('range.from', '2026-08-11')
            ->where('range.days', 3)
            ->has('days', 3)
            ->has('days.0.reservations', 2));
});

test('operator settlements summarize owned payments only', function () {
    Carbon::setTestNow('2026-08-11 10:00:00');
    [$user] = operatorPhaseSixScenario();

    $this->actingAs($user);

    $this->get(route('operator.settlements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/settlements')
            ->where('summary.paid_gross_amount', 10000)
            ->where('summary.platform_fee_amount', 1000)
            ->where('summary.operator_amount', 9000)
            ->where('summary.estimated_payout_amount', 9000)
            ->where('summary.succeeded_payments_count', 1)
            ->where('summary.pending_payments_count', 1)
            ->has('organizations', 1)
            ->has('payments', 2));
});

/**
 * @return array{User, Booking, Booking}
 */
function operatorPhaseSixScenario(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'name' => 'Stanowisko Testowe',
    ]);

    $ownedBooking = createOperatorPhaseSixBooking(
        organization: $organization,
        venue: $venue,
        spot: $spot,
        bookingStatus: BookingStatus::Confirmed,
        reservationStatus: ReservationStatus::Confirmed,
        paymentStatus: PaymentStatus::Succeeded,
        totalAmount: 10000,
        startsAt: '2026-08-11 12:00:00',
        endsAt: '2026-08-11 14:00:00',
    );

    createOperatorPhaseSixBooking(
        organization: $organization,
        venue: $venue,
        spot: $spot,
        bookingStatus: BookingStatus::PaymentPending,
        reservationStatus: ReservationStatus::Hold,
        paymentStatus: PaymentStatus::Created,
        totalAmount: 20000,
        startsAt: '2026-08-11 15:00:00',
        endsAt: '2026-08-11 17:00:00',
    );

    $foreignOrganization = Organization::factory()->create();
    $foreignVenue = FishingVenue::factory()->create([
        'organization_id' => $foreignOrganization->id,
    ]);
    $foreignSpot = FishingSpot::factory()->create([
        'fishing_venue_id' => $foreignVenue->id,
    ]);
    $foreignBooking = createOperatorPhaseSixBooking(
        organization: $foreignOrganization,
        venue: $foreignVenue,
        spot: $foreignSpot,
        bookingStatus: BookingStatus::Confirmed,
        reservationStatus: ReservationStatus::Confirmed,
        paymentStatus: PaymentStatus::Succeeded,
        totalAmount: 50000,
        startsAt: '2026-08-11 12:00:00',
        endsAt: '2026-08-11 14:00:00',
    );

    return [$user, $ownedBooking, $foreignBooking];
}

function createOperatorPhaseSixBooking(
    Organization $organization,
    FishingVenue $venue,
    FishingSpot $spot,
    BookingStatus $bookingStatus,
    ReservationStatus $reservationStatus,
    PaymentStatus $paymentStatus,
    int $totalAmount,
    string $startsAt,
    string $endsAt,
): Booking {
    $platformFeeAmount = (int) floor($totalAmount * 1000 / 10000);

    $booking = Booking::create([
        'organization_id' => $organization->id,
        'fishing_venue_id' => $venue->id,
        'status' => $bookingStatus,
        'currency' => 'PLN',
        'subtotal_amount' => $totalAmount,
        'discount_amount' => 0,
        'total_amount' => $totalAmount,
        'commission_bps' => 1000,
        'platform_fee_amount' => $platformFeeAmount,
        'operator_amount' => $totalAmount - $platformFeeAmount,
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'customer_phone' => '+48123123123',
        'confirmed_at' => $bookingStatus === BookingStatus::Confirmed ? now() : null,
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'status' => $reservationStatus,
        'hold_expires_at' => $reservationStatus === ReservationStatus::Hold ? now()->addMinutes(10) : null,
    ]);

    $booking->priceLines()->create([
        'type' => BookingPriceLineType::Spot,
        'description' => $spot->name.' x 1 dni',
        'quantity' => 1,
        'unit_amount' => $totalAmount,
        'total_amount' => $totalAmount,
    ]);

    $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => $paymentStatus,
        'amount' => $totalAmount,
        'currency' => 'PLN',
        'idempotency_key' => 'operator-phase-six-'.Str::uuid(),
        'paid_at' => $paymentStatus === PaymentStatus::Succeeded ? now() : null,
    ]);

    return $booking;
}
