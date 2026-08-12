<?php

namespace Database\Factories;

use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FishingSpot>
 */
class FishingSpotFactory extends Factory
{
    protected $model = FishingSpot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fishing_venue_id' => FishingVenue::factory(),
            'name' => 'Stanowisko '.$this->faker->unique()->numberBetween(1, 99),
            'description' => fake()->sentence(),
            'capacity' => 2,
            'base_price_amount' => 10000,
            'currency' => 'PLN',
            'status' => FishingSpotStatus::Active,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
