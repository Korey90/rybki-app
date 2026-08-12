<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('marketplace home and index show published public venues and hide drafts', function () {
    marketplaceVenueWithSpot('Lowisko Publiczne');
    marketplaceVenueWithSpot('Lowisko Szkic', [
        'status' => FishingVenueStatus::Draft,
    ]);
    marketplaceVenueWithSpot('Lowisko Informacyjne', [
        'commercial_status' => FishingVenueCommercialStatus::InformationOnly,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/home')
            ->has('featured_venues', 2));

    $this->get(route('marketplace.fishing-venues.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/fishing-venues/index')
            ->has('venues', 2));

    $this->getJson(route('api.fishing-venues.index'))
        ->assertOk()
        ->assertJsonCount(2, 'venues');
});

test('marketplace availability filter returns venues with free spots', function () {
    [$availableVenue] = marketplaceVenueWithSpot('Lowisko Wolne');
    marketplaceVenueWithSpot('Lowisko Bez Reguly', [], addAvailability: false);

    $this->get(route('marketplace.fishing-venues.index', [
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'available_only' => 1,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('venues', 1)
            ->where('venues.0.slug', $availableVenue->slug)
            ->where('venues.0.available_spots_count', 1));
});

test('public venue page shows availability and pricing for selected range', function () {
    [$venue] = marketplaceVenueWithSpot('Lowisko Karp');

    $this->get(route('marketplace.fishing-venues.show', $venue->slug).'?starts_at=2026-08-12T10:00:00Z&ends_at=2026-08-12T12:00:00Z')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/fishing-venues/show')
            ->where('venue.slug', $venue->slug)
            ->has('venue.spots', 1)
            ->where('venue.spots.0.availability.available', true)
            ->where('venue.spots.0.price.total_amount', 10000));

    $draftVenue = FishingVenue::factory()->create([
        'status' => FishingVenueStatus::Draft,
    ]);

    $this->get(route('marketplace.fishing-venues.show', $draftVenue->slug))
        ->assertNotFound();
});

test('angler can checkout a booking and confirm a test payment', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $user = User::factory()->create();
    [, $spot] = marketplaceVenueWithSpot('Lowisko Checkout');

    $this->actingAs($user);

    $response = $this->post(route('checkout.store'), [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'customer_phone' => '+48123123123',
    ]);

    $booking = Booking::query()->with(['spotReservations', 'payments'])->firstOrFail();

    $response->assertRedirect(route('checkout.show', $booking));

    expect($booking->user_id)->toBe($user->id)
        ->and($booking->status)->toBe(BookingStatus::PaymentPending)
        ->and($booking->spotReservations->firstOrFail()->status)->toBe(ReservationStatus::Hold)
        ->and($booking->payments->firstOrFail()->status)->toBe(PaymentStatus::Created);

    $this->get(route('checkout.show', $booking))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/checkout/show')
            ->where('booking.uuid', $booking->uuid)
            ->where('payment_session.provider', PaymentProvider::Test->value));

    $this->post(route('checkout.test-payment', $booking))
        ->assertRedirect(route('account.bookings.show', $booking));

    $booking->refresh();
    $reservation = $booking->spotReservations()->firstOrFail();
    $payment = $booking->payments()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded);

    $this->get(route('account.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/bookings/index')
            ->has('bookings', 1)
            ->where('bookings.0.uuid', $booking->uuid));
});

test('angler booking history is private', function () {
    $user = User::factory()->create();
    $foreignUser = User::factory()->create();
    [$venue, $spot] = marketplaceVenueWithSpot('Lowisko Prywatnosc');
    $foreignBooking = createMarketplaceAccountBooking($foreignUser, $venue, $spot);

    $this->actingAs($user);

    $this->get(route('account.bookings.show', $foreignBooking))
        ->assertForbidden();
});

/**
 * @param  array<string, mixed>  $venueOverrides
 * @return array{FishingVenue, FishingSpot}
 */
function marketplaceVenueWithSpot(string $name, array $venueOverrides = [], bool $addAvailability = true): array
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => $name,
        'slug' => Str::slug($name),
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
        'city' => 'Olsztyn',
        'voivodeship' => 'warminsko-mazurskie',
        'latitude' => '53.7784',
        'longitude' => '20.4801',
        ...$venueOverrides,
    ]);

    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'name' => 'Stanowisko 1',
        'base_price_amount' => 10000,
        'currency' => 'PLN',
    ]);

    if ($addAvailability) {
        $spot->availabilityRules()->create([
            'day_of_week' => 3,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'minimum_booking_minutes' => 60,
            'maximum_booking_minutes' => 480,
        ]);
    }

    return [$venue, $spot];
}

function createMarketplaceAccountBooking(User $user, FishingVenue $venue, FishingSpot $spot): Booking
{
    $booking = Booking::create([
        'user_id' => $user->id,
        'organization_id' => $venue->organization_id,
        'fishing_venue_id' => $venue->id,
        'status' => BookingStatus::Confirmed,
        'currency' => 'PLN',
        'subtotal_amount' => 10000,
        'discount_amount' => 0,
        'total_amount' => 10000,
        'commission_bps' => 0,
        'platform_fee_amount' => 0,
        'operator_amount' => 10000,
        'customer_name' => 'Adam Nowak',
        'customer_email' => 'adam@example.com',
        'confirmed_at' => now(),
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 10:00:00',
        'ends_at' => '2026-08-12 12:00:00',
        'status' => ReservationStatus::Confirmed,
        'hold_expires_at' => null,
    ]);

    $booking->priceLines()->create([
        'type' => BookingPriceLineType::Spot,
        'description' => 'Stanowisko 1 x 1 dni',
        'quantity' => 1,
        'unit_amount' => 10000,
        'total_amount' => 10000,
    ]);

    $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => PaymentStatus::Succeeded,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'marketplace-account-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    return $booking;
}
