<?php

use App\Models\User;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;

test('operators can add availability rules to their fishing venue spots', function () {
    [$user, $venue, $spot] = ownedVenueWithSpot();

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.availability-rules.store', $venue), [
        'fishing_spot_id' => $spot->id,
        'day_of_week' => 3,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'minimum_booking_minutes' => 60,
        'maximum_booking_minutes' => 480,
    ]);

    $response->assertRedirect(route('operator.fishing-venues.show', $venue));

    $this->assertDatabaseHas('availability_rules', [
        'fishing_spot_id' => $spot->id,
        'day_of_week' => 3,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'minimum_booking_minutes' => 60,
        'maximum_booking_minutes' => 480,
    ]);
});

test('operators can add availability blocks to their fishing venue spots', function () {
    [$user, $venue, $spot] = ownedVenueWithSpot();

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.availability-blocks.store', $venue), [
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 11:00:00',
        'ends_at' => '2026-08-12 13:00:00',
        'reason' => 'Remont pomostu',
    ]);

    $response->assertRedirect(route('operator.fishing-venues.show', $venue));

    $this->assertDatabaseHas('availability_blocks', [
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 11:00:00',
        'ends_at' => '2026-08-12 13:00:00',
        'reason' => 'Remont pomostu',
        'created_by' => $user->id,
    ]);
});

test('operators can add pricing rules to their fishing venue spots', function () {
    [$user, $venue, $spot] = ownedVenueWithSpot();

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.pricing-rules.store', $venue), [
        'fishing_spot_id' => $spot->id,
        'day_of_week' => 6,
        'price_amount' => 18000,
        'currency' => 'pln',
        'minimum_duration_minutes' => 60,
        'priority' => 20,
    ]);

    $response->assertRedirect(route('operator.fishing-venues.show', $venue));

    $this->assertDatabaseHas('pricing_rules', [
        'fishing_spot_id' => $spot->id,
        'day_of_week' => 6,
        'price_amount' => 18000,
        'currency' => 'PLN',
        'minimum_duration_minutes' => 60,
        'priority' => 20,
    ]);
});

test('operators cannot manage availability for another organization venue', function () {
    $user = User::factory()->create();
    $foreignVenue = FishingVenue::factory()->create();
    $foreignSpot = FishingSpot::factory()->create([
        'fishing_venue_id' => $foreignVenue->id,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.availability-rules.store', $foreignVenue), [
        'fishing_spot_id' => $foreignSpot->id,
        'day_of_week' => 3,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'minimum_booking_minutes' => 60,
    ]);

    $response->assertForbidden();
});

/**
 * @return array{User, FishingVenue, FishingSpot}
 */
function ownedVenueWithSpot(): array
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
    ]);

    return [$user, $venue, $spot];
}
