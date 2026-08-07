<?php

namespace Database\Factories;

use App\Enums\CallOffRequestStatus;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallOffRequest>
 */
class CallOffRequestFactory extends Factory
{
    protected $model = CallOffRequest::class;

    public function definition(): array
    {
        return [
            'call_off_batch_id' => CallOffBatch::factory(),
            'projected_plot_id' => ProjectedPlot::factory(),
            'status' => CallOffRequestStatus::Submitted,
            'active_conflict_key' => null,
            'trashed_at' => null,
            'trash_expires_at' => null,
        ];
    }
}
