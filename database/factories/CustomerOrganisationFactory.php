<?php

namespace Database\Factories;

use App\Models\CustomerOrganisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrganisation>
 */
class CustomerOrganisationFactory extends Factory
{
    protected $model = CustomerOrganisation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
