<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Reviews\Actions\CreateFishingVenueReview;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use App\Modules\Reviews\Models\FishingVenueReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('angler can save and remove a fishing venue favorite', function () {
    $user = User::factory()->create();
    [$venue] = phase19VenueWithSpot('Lowisko Ulubione');

    $this->actingAs($user)
        ->post(route('marketplace.fishing-venues.favorites.store', $venue->slug))
        ->assertRedirect();

    $this->assertDatabaseHas('fishing_venue_favorites', [
        'user_id' => $user->id,
        'fishing_venue_id' => $venue->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('venue.is_favorited', true));

    $this->actingAs($user)
        ->get(route('account.favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/favorites/index')
            ->has('venues', 1)
            ->where('venues.0.slug', $venue->slug));

    $this->actingAs($user)
        ->delete(route('marketplace.fishing-venues.favorites.destroy', $venue->slug))
        ->assertRedirect();

    $this->assertDatabaseMissing('fishing_venue_favorites', [
        'user_id' => $user->id,
        'fishing_venue_id' => $venue->id,
    ]);
});

test('angler can review a venue only after a completed confirmed booking', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $user = User::factory()->create();
    $foreignUser = User::factory()->create();
    [$venue, $spot] = phase19VenueWithSpot('Lowisko Po Pobycie');
    $futureBooking = phase19Booking(
        user: $user,
        venue: $venue,
        spot: $spot,
        startsAt: '2026-08-13 10:00:00',
        endsAt: '2026-08-13 12:00:00',
    );
    $completedBooking = phase19Booking(
        user: $user,
        venue: $venue,
        spot: $spot,
        startsAt: '2026-08-11 10:00:00',
        endsAt: '2026-08-11 12:00:00',
    );

    $this->actingAs($user)
        ->post(route('marketplace.fishing-venues.reviews.store', $venue->slug), [
            'booking_uuid' => $futureBooking->uuid,
            'rating' => 5,
            'title' => 'Za wcześnie',
        ])
        ->assertSessionHasErrors('booking_uuid');

    $this->actingAs($foreignUser)
        ->post(route('marketplace.fishing-venues.reviews.store', $venue->slug), [
            'booking_uuid' => $completedBooking->uuid,
            'rating' => 5,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('marketplace.fishing-venues.reviews.store', $venue->slug), [
            'booking_uuid' => $completedBooking->uuid,
            'rating' => 5,
            'title' => 'Bardzo dobre miejsce',
            'body' => 'Sprawny dojazd, czyste stanowisko i spokojna obsługa.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('fishing_venue_reviews', [
        'booking_id' => $completedBooking->id,
        'fishing_venue_id' => $venue->id,
        'user_id' => $user->id,
        'rating' => 5,
        'status' => FishingVenueReviewStatus::Published->value,
    ]);

    $this->actingAs($user)
        ->post(route('marketplace.fishing-venues.reviews.store', $venue->slug), [
            'booking_uuid' => $completedBooking->uuid,
            'rating' => 4,
        ])
        ->assertSessionHasErrors('booking_uuid');

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('venue.rating_average', 5)
            ->where('venue.reviews_count', 1)
            ->has('venue.reviews', 1)
            ->where('venue.reviews.0.title', 'Bardzo dobre miejsce'));
});

test('operator can respond only to reviews from managed venues', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $angler = User::factory()->create();
    $operator = User::factory()->venueOwner()->create();
    $foreignOperator = User::factory()->venueOwner()->create();
    [$venue, $spot] = phase19VenueWithSpot('Lowisko Operatora');
    $foreignOrganization = Organization::factory()->create();
    $review = phase19Review($angler, $venue, $spot);

    OrganizationMember::create([
        'organization_id' => $venue->organization_id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);
    OrganizationMember::create([
        'organization_id' => $foreignOrganization->id,
        'user_id' => $foreignOperator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($foreignOperator)
        ->patch(route('operator.reviews.response', $review), [
            'operator_response' => 'Nie moje łowisko.',
        ])
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('operator.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/reviews/index')
            ->has('reviews', 1)
            ->where('reviews.0.uuid', $review->uuid));

    $this->actingAs($operator)
        ->patch(route('operator.reviews.response', $review), [
            'operator_response' => 'Dziękujemy za wizytę i opinię.',
        ])
        ->assertRedirect();

    expect($review->refresh()->operator_response)->toBe('Dziękujemy za wizytę i opinię.')
        ->and($review->operator_responded_by_user_id)->toBe($operator->id)
        ->and($review->operator_responded_at)->not->toBeNull();
});

test('admin can hide and republish a review from public venue ratings', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();
    $angler = User::factory()->create();
    [$venue, $spot] = phase19VenueWithSpot('Lowisko Moderowane');
    $review = phase19Review($angler, $venue, $spot, rating: 2);

    $this->actingAs($regularUser)
        ->get(route('admin.reviews.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reviews/index')
            ->has('reviews', 1)
            ->where('reviews.0.uuid', $review->uuid));

    $this->actingAs($admin)
        ->patch(route('admin.reviews.moderate', $review), [
            'status' => FishingVenueReviewStatus::Hidden->value,
            'hidden_reason' => 'Naruszenie zasad społeczności.',
        ])
        ->assertRedirect();

    expect($review->refresh()->status)->toBe(FishingVenueReviewStatus::Hidden)
        ->and($review->hidden_reason)->toBe('Naruszenie zasad społeczności.')
        ->and($review->moderated_by_user_id)->toBe($admin->id);

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('venue.rating_average', null)
            ->where('venue.reviews_count', 0)
            ->has('venue.reviews', 0));

    $this->actingAs($admin)
        ->patch(route('admin.reviews.moderate', $review), [
            'status' => FishingVenueReviewStatus::Published->value,
        ])
        ->assertRedirect();

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('venue.rating_average', 2)
            ->where('venue.reviews_count', 1));
});

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase19VenueWithSpot(string $name): array
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
    ]);

    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'name' => 'Stanowisko 1',
    ]);

    return [$venue, $spot];
}

function phase19Booking(
    User $user,
    FishingVenue $venue,
    FishingSpot $spot,
    string $startsAt,
    string $endsAt,
): Booking {
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
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'confirmed_at' => now(),
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'status' => ReservationStatus::Confirmed,
        'hold_expires_at' => null,
    ]);

    return $booking;
}

function phase19Review(User $user, FishingVenue $venue, FishingSpot $spot, int $rating = 4): FishingVenueReview
{
    $booking = phase19Booking(
        user: $user,
        venue: $venue,
        spot: $spot,
        startsAt: '2026-08-11 10:00:00',
        endsAt: '2026-08-11 12:00:00',
    );

    return app(CreateFishingVenueReview::class)->handle(
        user: $user,
        venue: $venue,
        booking: $booking,
        rating: $rating,
        title: 'Dobra wizyta',
        body: 'Krótka, prawdziwa opinia po pobycie.',
    );
}
