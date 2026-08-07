<?php

namespace Database\Factories;

use App\Enums\CallOffServiceType;
use App\Models\CallOffBatch;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallOffBatch>
 */
class CallOffBatchFactory extends Factory
{
    protected $model = CallOffBatch::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'submitted_by_user_id' => User::factory(),
            'service_identifier' => CallOffServiceType::Windows,
            'requested_date' => now()->addWeek()->toDateString(),
            'customer_response' => null,
            'submitted_at' => now(),
        ];
    }
}
