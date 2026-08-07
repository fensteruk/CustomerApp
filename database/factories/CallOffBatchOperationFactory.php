<?php

namespace Database\Factories;

use App\Enums\CallOffOperationType;
use App\Models\CallOffBatch;
use App\Models\CallOffBatchOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallOffBatchOperation>
 */
class CallOffBatchOperationFactory extends Factory
{
    protected $model = CallOffBatchOperation::class;

    public function definition(): array
    {
        return [
            'call_off_batch_id' => CallOffBatch::factory(),
            'performed_by_user_id' => User::factory(),
            'operation_type' => CallOffOperationType::Trash,
            'performed_at' => now(),
            'undo_expires_at' => now()->addSeconds(5),
            'reversed_by_operation_id' => null,
        ];
    }
}
