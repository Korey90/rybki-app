<?php

use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;

test('availability endpoint returns available spots with calculated price', function () {
    $venue = FishingVenue::factory()->create([
        'status' => FishingVenueStatus::Published,
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

    $spot->pricingRules()->create([
        'day_of_week' => 3,
        'price_amount' => 15000,
        'currency' => 'PLN',
        'priority' => 10,
    ]);

    $response = $this->getJson(route('api.fishing-venues.availability', [
        'fishingVenue' => $venue,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'available_spots')
        ->assertJsonPath('available_spots.0.uuid', $spot->uuid)
        ->assertJsonPath('available_spots.0.price.unit_amount', 15000)
        ->assertJsonPath('available_spots.0.price.total_amount', 15000);
});

test('availability endpoint does not return spots without matching rules', function () {
    $venue = FishingVenue::factory()->create([
        'status' => FishingVenueStatus::Published,
    ]);
    FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
    ]);

    $response = $this->getJson(route('api.fishing-venues.availability', [
        'fishingVenue' => $venue,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(0, 'available_spots');
});

test('availability endpoint does not return blocked spots', function () {
    $venue = FishingVenue::factory()->create([
        'status' => FishingVenueStatus::Published,
    ]);
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
    ]);

    $spot->availabilityRules()->create([
        'day_of_week' => 3,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'minimum_booking_minutes' => 60,
    ]);

    $spot->availabilityBlocks()->create([
        'starts_at' => '2026-08-12 11:00:00',
        'ends_at' => '2026-08-12 13:00:00',
        'reason' => 'Remont pomostu',
    ]);

    $response = $this->getJson(route('api.fishing-venues.availability', [
        'fishingVenue' => $venue,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(0, 'available_spots');
});
