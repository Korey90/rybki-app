<?php

use App\Models\User;
use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated operators can visit their fishing venues page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('operator.fishing-venues.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/fishing-venues/index')
            ->has('organizations', 1)
            ->has('venues', 0));
});

test('operators can create private fishing venues for their organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.store'), [
        'organization_id' => $organization->id,
        'name' => 'Lowisko Zielona Dolina',
        'description' => 'Kameralne lowisko karpiowe.',
        'venue_type' => FishingVenueType::Private->value,
        'city' => 'Olsztyn',
        'voivodeship' => 'warminsko-mazurskie',
        'country' => 'pl',
    ]);

    $venue = FishingVenue::query()->firstOrFail();

    $response->assertRedirect(route('operator.fishing-venues.show', $venue));

    expect($venue->organization_id)->toBe($organization->id)
        ->and($venue->slug)->toBe('lowisko-zielona-dolina')
        ->and($venue->country)->toBe('PL')
        ->and($venue->status)->toBe(FishingVenueStatus::Draft)
        ->and($venue->commercial_status)->toBe(FishingVenueCommercialStatus::Bookable);
});

test('public water fishing venues are information only by default', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($user);

    $this->post(route('operator.fishing-venues.store'), [
        'organization_id' => $organization->id,
        'name' => 'Obwod Rzeki Testowej',
        'venue_type' => FishingVenueType::WodyPolskie->value,
        'country' => 'PL',
    ]);

    $venue = FishingVenue::query()->firstOrFail();

    expect($venue->commercial_status)->toBe(FishingVenueCommercialStatus::InformationOnly);
});

test('operators cannot open fishing venues from another organization', function () {
    $user = User::factory()->create();
    $foreignVenue = FishingVenue::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('operator.fishing-venues.show', $foreignVenue));

    $response->assertForbidden();
});

test('operators can publish their fishing venue', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);
    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user);

    $response = $this->patch(route('operator.fishing-venues.publish', $venue));

    $response->assertRedirect(route('operator.fishing-venues.show', $venue));

    $venue->refresh();

    expect($venue->status)->toBe(FishingVenueStatus::Published)
        ->and($venue->published_at)->not->toBeNull();
});

test('operators can add spots to their fishing venue', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);
    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.spots.store', $venue), [
        'name' => 'Stanowisko 1',
        'description' => 'Pomost przy trzcinach.',
        'capacity' => 2,
        'base_price_amount' => 15000,
        'currency' => 'pln',
        'status' => FishingSpotStatus::Active->value,
    ]);

    $response->assertRedirect(route('operator.fishing-venues.show', $venue));

    $spot = FishingSpot::query()->firstOrFail();

    expect($spot->fishing_venue_id)->toBe($venue->id)
        ->and($spot->name)->toBe('Stanowisko 1')
        ->and($spot->currency)->toBe('PLN')
        ->and($spot->sort_order)->toBe(1);
});

test('operators cannot add spots to another organization fishing venue', function () {
    $user = User::factory()->create();
    $foreignVenue = FishingVenue::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('operator.fishing-venues.spots.store', $foreignVenue), [
        'name' => 'Stanowisko 1',
        'capacity' => 2,
        'base_price_amount' => 15000,
        'currency' => 'PLN',
        'status' => FishingSpotStatus::Active->value,
    ]);

    $response->assertForbidden();
});
