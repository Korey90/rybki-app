<?php

use App\Models\User;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('operator can upload and manage fishing venue images', function () {
    [$operator, $venue] = mediaManagedVenue();

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.media.store', $venue), [
            'images' => [
                UploadedFile::fake()->image('pierwsze.jpg', 1200, 800),
                UploadedFile::fake()->image('drugie.png', 1200, 800),
            ],
        ])
        ->assertRedirect();

    $media = $venue->media()->orderBy('sort_order')->get();

    expect($media)->toHaveCount(2)
        ->and($media[0]->sort_order)->toBe(1)
        ->and($media[1]->sort_order)->toBe(2);

    Storage::disk('public')->assertExists($media[0]->storage_path);
    Storage::disk('public')->assertExists($media[1]->storage_path);

    $this->actingAs($operator)
        ->patch(route('operator.fishing-venues.media.primary', [$venue, $media[1]]))
        ->assertRedirect();

    expect($venue->media()->orderBy('sort_order')->firstOrFail()->id)->toBe($media[1]->id);

    $this->actingAs($operator)
        ->delete(route('operator.fishing-venues.media.destroy', [$venue, $media[0]]))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($media[0]->storage_path);

    expect($venue->media()->count())->toBe(1);
});

test('operator can reorder fishing spot images', function () {
    [$operator, $venue, $spot] = mediaManagedSpot();

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.spots.media.store', [$venue, $spot]), [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
        ])
        ->assertRedirect();

    $media = $spot->media()->orderBy('sort_order')->get();

    $this->actingAs($operator)
        ->patch(route('operator.fishing-venues.spots.media.reorder', [$venue, $spot]), [
            'media_ids' => [$media[2]->id, $media[0]->id, $media[1]->id],
        ])
        ->assertRedirect();

    expect($spot->media()->orderBy('sort_order')->pluck('id')->all())->toBe([
        $media[2]->id,
        $media[0]->id,
        $media[1]->id,
    ]);
});

test('operator cannot manage media for foreign fishing venues', function () {
    $operator = User::factory()->venueOwner()->create();
    $foreignVenue = FishingVenue::factory()->create();

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.media.store', $foreignVenue), [
            'images' => [
                UploadedFile::fake()->image('foreign.jpg'),
            ],
        ])
        ->assertForbidden();

    expect($foreignVenue->media()->count())->toBe(0);
});

test('operator cannot manage media for foreign fishing spots', function () {
    [$operator, $venue] = mediaManagedVenue();
    $foreignSpot = FishingSpot::factory()->create();

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.spots.media.store', [$venue, $foreignSpot]), [
            'images' => [
                UploadedFile::fake()->image('foreign-spot.jpg'),
            ],
        ])
        ->assertNotFound();

    expect($foreignSpot->media()->count())->toBe(0);
});

test('venue image upload validates file type and gallery limit', function () {
    [$operator, $venue] = mediaManagedVenue();

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.media.store', $venue), [
            'images' => [
                UploadedFile::fake()->create('not-image.pdf', 32, 'application/pdf'),
            ],
        ])
        ->assertSessionHasErrors('images.0');

    for ($i = 1; $i <= 12; $i++) {
        $venue->media()->create([
            'storage_path' => "existing/{$i}.jpg",
            'sort_order' => $i,
        ]);
    }

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.media.store', $venue), [
            'images' => [
                UploadedFile::fake()->image('too-many.jpg'),
            ],
        ])
        ->assertSessionHasErrors('images');
});

test('marketplace payload exposes primary venue and spot media', function () {
    [$operator, $venue, $spot] = mediaManagedSpot();
    $venue->forceFill([
        'status' => 'PUBLISHED',
        'published_at' => now(),
    ])->save();

    $venueImage = UploadedFile::fake()->image('venue.jpg');
    $spotImage = UploadedFile::fake()->image('spot.jpg');

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.media.store', $venue), [
            'images' => [$venueImage],
        ]);
    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.spots.media.store', [$venue, $spot]), [
            'images' => [$spotImage],
        ]);

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('venue.primary_media.is_primary', true)
            ->where('venue.media.0.is_primary', true)
            ->where('venue.spots.0.media.0.is_primary', true));
});

/**
 * @return array{User, FishingVenue}
 */
function mediaManagedVenue(): array
{
    $operator = User::factory()->venueOwner()->create();
    $organization = Organization::factory()->create();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
    ]);

    return [$operator, $venue];
}

/**
 * @return array{User, FishingVenue, FishingSpot}
 */
function mediaManagedSpot(): array
{
    [$operator, $venue] = mediaManagedVenue();
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
    ]);

    return [$operator, $venue, $spot];
}
