<?php

namespace Database\Factories;

use App\Models\CallOffBatchOperation;
use App\Models\CallOffBatchOperationItem;
use App\Models\CallOffRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallOffBatchOperationItem>
 */
class CallOffBatchOperationItemFactory extends Factory
{
    protected $model = CallOffBatchOperationItem::class;

    public function definition(): array
    {
        return [
            'call_off_batch_operation_id' => CallOffBatchOperation::factory(),
            'call_off_request_id' => CallOffRequest::factory(),
            'before_state' => [],
            'after_state' => [],
        ];
    }
}
