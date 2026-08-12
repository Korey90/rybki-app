<?php

use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Queries\FishingVenueGeoQuery;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostGIS tests require DB_CONNECTION=pgsql.');
    }
});

test('postgis migration creates location points from venue coordinates', function () {
    $venue = phase15Venue('Olsztyn', '53.7784000', '20.4801000');

    $hasLocation = DB::table('fishing_venues')
        ->where('id', $venue->id)
        ->whereRaw('location IS NOT NULL')
        ->exists();

    expect($hasLocation)->toBeTrue();
});

test('marketplace filters venues by radius and sorts by distance', function () {
    phase15Venue('Olsztyn', '53.7784000', '20.4801000');
    phase15Venue('Gdansk', '54.3520000', '18.6466000');
    phase15Venue('Krakow', '50.0647000', '19.9450000');

    $this->get(route('marketplace.fishing-venues.index', [
        'latitude' => '53.7784',
        'longitude' => '20.4801',
        'radius_km' => 180,
        'sort' => 'distance',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('venues', 2)
            ->where('venues.0.name', 'Olsztyn')
            ->where('filters.sort', 'distance'));
});

test('public api exposes distance for radius search', function () {
    phase15Venue('Olsztyn', '53.7784000', '20.4801000');
    phase15Venue('Krakow', '50.0647000', '19.9450000');

    $this->getJson(route('api.fishing-venues.index', [
        'latitude' => '53.7784',
        'longitude' => '20.4801',
        'radius_km' => 100,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'venues')
        ->assertJsonPath('venues.0.name', 'Olsztyn');
});

test('geo query distance helper returns kilometers', function () {
    $venue = phase15Venue('Gdansk', '54.3520000', '18.6466000');

    $distance = app(FishingVenueGeoQuery::class)->distanceKm($venue, 53.7784, 20.4801);

    expect($distance)->toBeFloat()
        ->and($distance)->toBeGreaterThan(130.0)
        ->and($distance)->toBeLessThan(160.0);
});

function phase15Venue(string $name, string $latitude, string $longitude): FishingVenue
{
    return FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => $name,
        'slug' => str($name)->slug().'-phase15',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
    ]);
}
