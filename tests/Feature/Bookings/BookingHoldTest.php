<?php

use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Support\Carbon;

test('customers can create booking holds', function () {
    [$venue, $spot] = bookableSpotForBookingHold();

    $response = $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot));

    $response
        ->assertCreated()
        ->assertJsonPath('booking.status', BookingStatus::PaymentPending->value)
        ->assertJsonPath('booking.total_amount', 10000)
        ->assertJsonPath('booking.reservation.status', ReservationStatus::Hold->value)
        ->assertJsonPath('booking.reservation.spot.uuid', $spot->uuid)
        ->assertJsonPath('booking.price_lines.0.type', BookingPriceLineType::Spot->value);

    expect(Booking::query()->count())->toBe(1)
        ->and(SpotReservation::query()->count())->toBe(1)
        ->and($venue->bookings()->count())->toBe(1);
});

test('overlapping active holds are rejected', function () {
    [, $spot] = bookableSpotForBookingHold();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertCreated();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertConflict()
        ->assertJsonPath('reason', 'reserved');

    expect(SpotReservation::query()->where('status', ReservationStatus::Hold->value)->count())->toBe(1);
});

test('adjacent holds are allowed', function () {
    [, $spot] = bookableSpotForBookingHold();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot, [
        'starts_at' => '2026-08-12T08:00:00Z',
        'ends_at' => '2026-08-12T10:00:00Z',
    ]))->assertCreated();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot, [
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
    ]))->assertCreated();

    expect(SpotReservation::query()->where('status', ReservationStatus::Hold->value)->count())->toBe(2);
});

test('expired holds do not block a new hold', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [, $spot] = bookableSpotForBookingHold();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertCreated();

    Carbon::setTestNow('2026-08-11 12:11:00');

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertCreated();

    expect(SpotReservation::query()->where('status', ReservationStatus::Expired->value)->count())->toBe(1)
        ->and(SpotReservation::query()->where('status', ReservationStatus::Hold->value)->count())->toBe(1);

    Carbon::setTestNow();
});

test('expired holds can be cleared without checkout flow', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [, $spot] = bookableSpotForBookingHold();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertCreated();

    Carbon::setTestNow('2026-08-11 12:11:00');

    $this->artisan('bookings:expire-holds')
        ->expectsOutput('Wygaszono 1 holdow.')
        ->assertSuccessful();

    expect(SpotReservation::query()->where('status', ReservationStatus::Expired->value)->count())->toBe(1)
        ->and(SpotReservation::query()->where('status', ReservationStatus::Hold->value)->count())->toBe(0);

    Carbon::setTestNow();
});

test('booking hold keeps a price snapshot after spot price changes', function () {
    [, $spot] = bookableSpotForBookingHold();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertCreated();

    $spot->update(['base_price_amount' => 25000]);

    $booking = Booking::query()->with('priceLines')->firstOrFail();
    $line = $booking->priceLines->firstOrFail();

    expect($booking->total_amount)->toBe(10000)
        ->and($line->unit_amount)->toBe(10000)
        ->and($line->total_amount)->toBe(10000);
});

test('booking hold is rejected when spot is outside availability rules', function () {
    $spot = FishingSpot::factory()->create([
        'base_price_amount' => 10000,
    ]);

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertConflict()
        ->assertJsonPath('reason', 'outside_availability_rules');
});

test('active holds remove spots from public availability results', function () {
    [$venue, $spot] = bookableSpotForBookingHold();

    $this->postJson(route('api.booking-holds.store'), bookingHoldPayload($spot))
        ->assertCreated();

    $this->getJson(route('api.fishing-venues.availability', [
        'fishingVenue' => $venue,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'available_spots');
});

/**
 * @return array{FishingVenue, FishingSpot}
 */
function bookableSpotForBookingHold(): array
{
    $venue = FishingVenue::factory()->create();
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
        'currency' => 'PLN',
    ]);

    $spot->availabilityRules()->create([
        'day_of_week' => 3,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'minimum_booking_minutes' => 60,
        'maximum_booking_minutes' => 480,
    ]);

    return [$venue, $spot];
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function bookingHoldPayload(FishingSpot $spot, array $overrides = []): array
{
    return [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'customer_phone' => '+48123123123',
        ...$overrides,
    ];
}
