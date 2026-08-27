<?php

namespace App\Actions\CallOff;

use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Locks the source-owned service before its portal request aggregate.
 *
 * Any date negotiation transition must use this order so it cannot race a
 * source projection update which already owns the service row.
 */
class LockCallOffDateNegotiationAggregateAction
{
    /**
     * @return array{0: CallOffRequest, 1: ProjectedPlotService|null}
     */
    public function handle(CallOffRequest $request): array
    {
        // Read the association again rather than trusting a model that may have been
        // loaded before this transaction began. The source service is deliberately
        // locked before the request, matching the source projection writer.
        $serviceId = CallOffRequest::query()
            ->whereKey($request->id)
            ->value('projected_plot_service_id');

        if ($serviceId === null && ! CallOffRequest::query()->whereKey($request->id)->exists()) {
            throw (new ModelNotFoundException)->setModel(CallOffRequest::class, [$request->id]);
        }

        if ($serviceId === null) {
            return [CallOffRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail(), null];
        }

        $service = ProjectedPlotService::query()->whereKey($serviceId)->lockForUpdate()->first();

        if ($service === null) {
            throw (new ModelNotFoundException)->setModel(ProjectedPlotService::class, [$serviceId]);
        }

        $lockedRequest = CallOffRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

        if ((int) $lockedRequest->projected_plot_service_id !== (int) $service->id) {
            throw new \LogicException('A call-off request cannot change its projected service during a date transition.');
        }

        $lockedRequest->setRelation('projectedPlotService', $service);

        return [$lockedRequest, $service];
    }
}
