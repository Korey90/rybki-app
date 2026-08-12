<?php

namespace Database\Factories;

use App\Modules\Organizations\Enums\OrganizationVerificationStatus;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' Sp. z o.o.',
            'tax_id' => fake()->numerify('##########'),
            'regon' => fake()->numerify('#########'),
            'country' => 'PL',
            'address' => fake()->address(),
            'verification_status' => OrganizationVerificationStatus::Pending,
        ];
    }
}
