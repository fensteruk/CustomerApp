<?php

namespace Database\Factories;

use App\Models\CustomerOrganisation;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_organisation_id' => CustomerOrganisation::factory(),
            'name' => fake()->unique()->words(2, true),
            'location' => fake()->city(),
        ];
    }
}
