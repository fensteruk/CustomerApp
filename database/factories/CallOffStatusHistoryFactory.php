<?php

namespace Database\Factories;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallOffStatusHistory>
 */
class CallOffStatusHistoryFactory extends Factory
{
    protected $model = CallOffStatusHistory::class;

    public function definition(): array
    {
        return [
            'call_off_request_id' => CallOffRequest::factory(),
            'call_off_batch_id' => CallOffBatch::factory(),
            'performed_by_user_id' => User::factory(),
            'sequence' => 1,
            'event_type' => CallOffHistoryEventType::Submitted,
            'previous_status' => null,
            'new_status' => CallOffRequestStatus::Submitted,
            'before_state' => null,
            'after_state' => null,
            'customer_response' => null,
            'internal_reason' => null,
            'performed_at' => now(),
        ];
    }
}
