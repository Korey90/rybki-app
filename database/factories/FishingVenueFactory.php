<?php

namespace Database\Factories;

use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FishingVenue>
 */
class FishingVenueFactory extends Factory
{
    protected $model = FishingVenue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Lowisko';

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'venue_type' => FishingVenueType::Private,
            'commercial_status' => FishingVenueCommercialStatus::Bookable,
            'latitude' => fake()->latitude(49, 54),
            'longitude' => fake()->longitude(14, 24),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'voivodeship' => 'mazowieckie',
            'country' => 'PL',
            'phone' => null,
            'email' => fake()->safeEmail(),
            'website' => null,
            'rules' => null,
            'check_in_instructions' => null,
            'status' => FishingVenueStatus::Draft,
            'published_at' => null,
        ];
    }
}
