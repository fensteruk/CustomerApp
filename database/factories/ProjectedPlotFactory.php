<?php

namespace Database\Factories;

use App\Models\ProjectedPlot;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectedPlot>
 */
class ProjectedPlotFactory extends Factory
{
    protected $model = ProjectedPlot::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'external_source' => 'siteapp',
            'external_identifier' => fake()->unique()->bothify('plot-####'),
            'plot_reference' => fake()->unique()->bothify('Plot ###'),
            'is_completed' => false,
            'source_updated_at' => now(),
            'synchronised_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_completed' => true,
        ]);
    }
}
