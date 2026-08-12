<?php

use App\Modules\Availability\DTO\AvailabilityCheckResult;
use App\Modules\Availability\Services\AvailabilityService;
use App\Modules\Bookings\Actions\CreateBookingHold;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Exceptions\BookingHoldUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL reservation constraint tests require DB_CONNECTION=pgsql.');
    }
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('postgresql rejects direct overlapping active reservations', function () {
    [$venue, $spot] = phase10PostgresqlBookableVenueWithSpot();

    phase10PostgresqlBooking($venue, $spot)->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 10:00:00',
        'ends_at' => '2026-08-12 12:00:00',
        'status' => ReservationStatus::Hold,
        'hold_expires_at' => '2026-08-12 09:30:00',
    ]);

    $secondBooking = phase10PostgresqlBooking($venue, $spot);

    expect(fn () => $secondBooking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 11:00:00',
        'ends_at' => '2026-08-12 13:00:00',
        'status' => ReservationStatus::Hold,
        'hold_expires_at' => '2026-08-12 09:30:00',
    ]))->toThrow(QueryException::class);
});

test('booking hold maps postgresql overlap constraint violation to reserved reason', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [$venue, $spot] = phase10PostgresqlBookableVenueWithSpot();

    phase10PostgresqlBooking($venue, $spot)->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 10:00:00',
        'ends_at' => '2026-08-12 12:00:00',
        'status' => ReservationStatus::Hold,
        'hold_expires_at' => '2026-08-12 09:30:00',
    ]);

    $this->mock(AvailabilityService::class, function ($mock): void {
        $mock->shouldReceive('check')
            ->once()
            ->andReturn(new AvailabilityCheckResult(true));
    });

    try {
        app(CreateBookingHold::class)->handle(
            spot: $spot,
            startsAt: Carbon::parse('2026-08-12 11:00:00'),
            endsAt: Carbon::parse('2026-08-12 13:00:00'),
            customer: [
                'customer_name' => 'Jan Kowalski',
                'customer_email' => 'jan@example.com',
                'customer_phone' => null,
            ],
        );

        $this->fail('Expected booking hold to be rejected by the PostgreSQL overlap constraint.');
    } catch (BookingHoldUnavailable $exception) {
        expect($exception->reason)->toBe('reserved');
    }
});

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase10PostgresqlBookableVenueWithSpot(): array
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Lowisko PostgreSQL '.Str::uuid(),
        'slug' => 'lowisko-postgresql-'.Str::uuid(),
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
    ]);

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

function phase10PostgresqlBooking(FishingVenue $venue, FishingSpot $spot): Booking
{
    return Booking::create([
        'organization_id' => $venue->organization_id,
        'fishing_venue_id' => $venue->id,
        'status' => BookingStatus::PaymentPending,
        'currency' => 'PLN',
        'subtotal_amount' => $spot->base_price_amount,
        'discount_amount' => 0,
        'total_amount' => $spot->base_price_amount,
        'commission_bps' => 0,
        'platform_fee_amount' => 0,
        'operator_amount' => $spot->base_price_amount,
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
    ]);
}
