<?php

namespace App\Actions\CallOff;

use App\Models\CallOffRequest;

class UpdateConflictKeyAction
{
    public function handle(CallOffRequest $request): CallOffRequest
    {
        $request->loadMissing('batch', 'projectedPlotService');

        $service = $request->effectiveServiceIdentifier();

        $request->active_conflict_key = $request->status->isConflictActive() && $service !== null
            ? self::keyFor((int) $request->projected_plot_id, $service->value)
            : null;

        $request->save();

        return $request;
    }

    public static function keyFor(int $projectedPlotId, string $serviceIdentifier): string
    {
        return "projected_plot:{$projectedPlotId}:service:{$serviceIdentifier}";
    }
}
