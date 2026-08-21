<?php

namespace App\Services;

use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Data\SourceRecord;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\SourceProjectionIssueType;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionEvent;
use App\Models\SourceProjectionIssue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SourceProjectionImportService
{
    public function __construct(private readonly SourceCallTypeMapper $callTypes, private readonly SourceProjectionIssueService $issues, private readonly UpdateConflictKeyAction $conflicts) {}

    /** @param iterable<SourceRecord> $records */
    public function import(string $sourceName, iterable $records, ?string $sourceVersion = null): SourceImportRun
    {
        $run = SourceImportRun::query()->create(['source_name' => $sourceName, 'source_version' => $sourceVersion, 'status' => 'running', 'started_at' => now()]);

        try {
            $records = Collection::make($records)
                ->map(fn (SourceRecord $record): SourceRecord => new SourceRecord(
                    trim($record->callNumber),
                    trim($record->siteIdentifier),
                    trim($record->plotReference),
                    trim($record->callType),
                    $record->jobStage === null ? null : trim($record->jobStage),
                    $record->completedDate,
                    $record->products,
                    $record->sourceUpdatedAt,
                ))
                ->values();
            $callNumbers = $records->pluck('callNumber')->filter();
            $duplicateNumbers = $callNumbers->countBy()->filter(fn (int $count) => $count > 1)->keys();
            $counts = ['records_seen' => $records->count(), 'records_applied' => 0, 'records_created' => 0, 'records_updated' => 0, 'records_unchanged' => 0, 'records_missing' => 0, 'records_rejected' => 0];
            $productsByPlot = collect();

            foreach ($records as $record) {
                if ($record->callNumber === '') {
                    $this->issues->record($run, SourceProjectionIssueType::InvalidSourceRecord, "invalid-source-record:{$sourceName}:blank-call-number", context: ['reason' => 'Call No. is required.']);
                    $counts['records_rejected']++;

                    continue;
                }

                if ($duplicateNumbers->contains($record->callNumber)) {
                    $this->issues->record($run, SourceProjectionIssueType::DuplicateCallNumber, "duplicate-call-number:{$sourceName}:{$record->callNumber}", $record->callNumber);
                    $counts['records_rejected']++;

                    continue;
                }

                if ($this->callTypes->serviceFor($record->callType) === null) {
                    $this->issues->record($run, SourceProjectionIssueType::UnknownCallType, "unknown-call-type:{$sourceName}:{$record->callNumber}", $record->callNumber, context: ['call_type' => $record->callType]);
                    $counts['records_rejected']++;

                    continue;
                }

                try {
                    $result = DB::transaction(fn (): array => $this->applyRecord($run, $sourceName, $record));
                    $counts['records_applied']++;
                    $counts[$result['outcome']]++;
                    $productsByPlot[$result['plot_id']] = array_merge($productsByPlot[$result['plot_id']] ?? [], $record->products);
                } catch (\DomainException $exception) {
                    $type = $exception->getMessage() === 'Stable Call No. association changed.'
                        ? SourceProjectionIssueType::AssociationChanged
                        : SourceProjectionIssueType::InvalidSourceRecord;
                    $this->issues->record($run, $type, ($type === SourceProjectionIssueType::AssociationChanged ? 'association-changed' : 'invalid-source-record').":{$sourceName}:{$record->callNumber}", $record->callNumber, context: ['reason' => $exception->getMessage()]);
                    $counts['records_rejected']++;
                }
            }

            foreach ($productsByPlot as $plotId => $products) {
                DB::transaction(fn () => $this->syncProducts((int) $plotId, $products, $run));
            }

            $counts['records_missing'] = $this->markMissing($run, $sourceName, $callNumbers->all());
            $run->update([...$counts, 'reconciliation_issue_count' => SourceProjectionIssue::query()->where('source_import_run_id', $run->id)->count(), 'status' => $counts['records_rejected'] > 0 ? 'partial' : 'completed', 'finished_at' => now()]);

            return $run->fresh();
        } catch (\Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'safe_error_summary' => 'Source import did not complete. See application logs.',
                'finished_at' => now(),
            ])->save();

            Log::warning('Source projection import failed.', [
                'source_name' => $sourceName,
                'source_import_run_uuid' => $run->uuid,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /** @return array{outcome: string, plot_id: int} */
    private function applyRecord(SourceImportRun $run, string $sourceName, SourceRecord $record): array
    {
        $serviceType = $this->callTypes->serviceFor($record->callType);
        if ($serviceType === null) {
            throw new \DomainException('Unknown Call Type.');
        }

        $site = Site::query()->where('external_source', $sourceName)->where('external_identifier', $record->siteIdentifier)->first();
        if ($site === null) {
            throw new \DomainException('Unknown source site identity.');
        }

        if ($record->plotReference === '') {
            throw new \DomainException('Plot reference is required.');
        }

        foreach ($record->products as $code => $quantity) {
            if (trim((string) $code) === '' || ! is_numeric($quantity) || (float) $quantity < 0) {
                throw new \DomainException('Product quantities must use non-negative numeric values and product codes.');
            }
        }

        $existing = ProjectedPlotService::query()->with('projectedPlot')->where('source_call_number', $record->callNumber)->lockForUpdate()->first();
        if ($existing !== null && ((int) $existing->projectedPlot->site_id !== (int) $site->id || $existing->projectedPlot->plot_reference !== $record->plotReference || $existing->service_identifier !== $serviceType)) {
            throw new \DomainException('Stable Call No. association changed.');
        }

        $plot = $existing?->projectedPlot ?? ProjectedPlot::query()->firstOrCreate(
            ['external_source' => $sourceName, 'external_identifier' => $record->siteIdentifier.'|'.$record->plotReference],
            ['site_id' => $site->id, 'plot_reference' => $record->plotReference, 'synchronised_at' => now()]
        );
        $this->ensureServiceRows($plot);
        $service = $existing ?? ProjectedPlotService::query()->firstOrNew(['projected_plot_id' => $plot->id, 'service_identifier' => $serviceType->value]);
        if ($service->exists && $service->source_call_number !== null && $service->source_call_number !== $record->callNumber) {
            throw new \DomainException('Stable Call No. association changed.');
        }
        $wasSourceProjection = $service->exists && $service->source_call_number !== null;
        $wasComplete = $service->isSourceCompleted();
        $isComplete = $record->completedDate !== null || $this->callTypes->isCompletionStage($serviceType, $record->jobStage);
        if ($isComplete && $record->completedDate === null) {
            $this->issues->record($run, SourceProjectionIssueType::CompletionDateMissing, "completion-date-missing:{$sourceName}:{$record->callNumber}", $record->callNumber, $service);
        }
        $before = ['completed_at' => $service->source_completed_at?->toDateString(), 'stage' => $service->source_job_stage];
        $sourceUpdatedAt = $record->sourceUpdatedAt ?? $service->source_updated_at;
        $service->fill(['source_call_number' => $record->callNumber, 'source_call_type' => $record->callType, 'source_job_stage' => $record->jobStage, 'source_completed_at' => $record->completedDate, 'source_completion_observed_at' => $isComplete ? ($wasComplete ? $service->source_completion_observed_at : now()) : null, 'source_updated_at' => $sourceUpdatedAt, 'last_observed_at' => now(), 'source_present' => true, 'source_missing_since' => null, 'last_source_import_run_id' => $run->id]);
        $outcome = ! $wasSourceProjection
            ? 'records_created'
            : ($service->isDirty(['source_call_number', 'source_call_type', 'source_job_stage', 'source_completed_at', 'source_completion_observed_at', 'source_updated_at', 'source_present', 'source_missing_since']) ? 'records_updated' : 'records_unchanged');
        $service->save();

        $this->issues->resolve("missing-source-record:{$sourceName}:{$record->callNumber}");
        $this->issues->resolve("unknown-call-type:{$sourceName}:{$record->callNumber}");
        $this->issues->resolve("association-changed:{$sourceName}:{$record->callNumber}");
        if ($record->completedDate !== null) {
            $this->issues->resolve("completion-date-missing:{$sourceName}:{$record->callNumber}");
        }

        if (! $wasComplete && $isComplete) {
            $this->applyCompletion($service, $run, $before);
        }
        if ($wasComplete && ! $isComplete) {
            $this->applyReversal($service, $run, $before);
        }
        if ($wasComplete && $isComplete && $before['completed_at'] !== $service->source_completed_at?->toDateString()) {
            SourceProjectionEvent::query()->create(['source_import_run_id' => $run->id, 'projected_plot_service_id' => $service->id, 'event_type' => 'completion_date_updated', 'before_state' => $before, 'after_state' => ['completed_at' => $service->source_completed_at?->toDateString()], 'occurred_at' => now()]);
        }

        $plotSourceUpdatedAt = $sourceUpdatedAt;
        if ($plot->source_updated_at !== null && ($plotSourceUpdatedAt === null || $plot->source_updated_at->greaterThan($plotSourceUpdatedAt))) {
            $plotSourceUpdatedAt = $plot->source_updated_at;
        }
        $plot->update(['source_updated_at' => $plotSourceUpdatedAt, 'synchronised_at' => now()]);

        return ['outcome' => $outcome, 'plot_id' => $plot->id];
    }

    /** @param array<string, mixed> $before */
    private function applyCompletion(ProjectedPlotService $service, SourceImportRun $run, array $before): void
    {
        $requests = $service->callOffRequests()->whereIn('status', [CallOffRequestStatus::Submitted, CallOffRequestStatus::Approved, CallOffRequestStatus::AwaitingFenster, CallOffRequestStatus::AwaitingSiteUser, CallOffRequestStatus::DateAgreed, CallOffRequestStatus::AmendmentOnHold])->lockForUpdate()->get();
        foreach ($requests as $request) {
            $request->dateNegotiations()->where('status', CallOffNegotiationStatus::Open)->update(['status' => CallOffNegotiationStatus::Completed, 'active_negotiation_key' => null, 'closed_at' => now()]);
            $previousStatus = $request->status;
            $request->status = CallOffRequestStatus::Completed;
            $this->conflicts->handle($request);
            SourceProjectionEvent::query()->create(['source_import_run_id' => $run->id, 'projected_plot_service_id' => $service->id, 'call_off_request_id' => $request->id, 'event_type' => 'completion_recorded', 'before_state' => ['request_status' => $previousStatus->value], 'after_state' => ['request_status' => CallOffRequestStatus::Completed->value], 'occurred_at' => now()]);
        }
        if ($requests->isEmpty()) {
            SourceProjectionEvent::query()->create(['source_import_run_id' => $run->id, 'projected_plot_service_id' => $service->id, 'event_type' => 'completion_recorded', 'before_state' => $before, 'after_state' => ['completed_at' => $service->source_completed_at?->toDateString()], 'occurred_at' => now()]);
        }
    }

    /** @param array<string, mixed> $before */
    private function applyReversal(ProjectedPlotService $service, SourceImportRun $run, array $before): void
    {
        $active = $service->callOffRequests()->whereIn('status', [CallOffRequestStatus::Submitted, CallOffRequestStatus::Approved, CallOffRequestStatus::AwaitingFenster, CallOffRequestStatus::AwaitingSiteUser, CallOffRequestStatus::DateAgreed, CallOffRequestStatus::AmendmentOnHold])->lockForUpdate()->first();
        if ($active !== null) {
            $this->issues->record($run, SourceProjectionIssueType::UnsafeCompletionReversal, "unsafe-completion-reversal:{$service->source_call_number}", $service->source_call_number, $service, ['active_request_uuid' => $active->uuid]);
        }
        SourceProjectionEvent::query()->create(['source_import_run_id' => $run->id, 'projected_plot_service_id' => $service->id, 'event_type' => 'completion_reversed', 'before_state' => $before, 'after_state' => ['completed_at' => null], 'occurred_at' => now()]);
    }

    /** @param array<string, int|float|string> $products */
    private function syncProducts(int $plotId, array $products, SourceImportRun $run): void
    {
        $normalised = collect($products)->mapWithKeys(fn ($quantity, $code) => [trim((string) $code) => (float) $quantity])->filter(fn ($quantity, $code) => $code !== '');
        ProjectedPlotProduct::query()->where('projected_plot_id', $plotId)->get()->each(function (ProjectedPlotProduct $product) use ($normalised, $run): void {
            if (! $normalised->has($product->product_code)) {
                $product->update(['quantity' => 0, 'last_source_import_run_id' => $run->id]);
            }
        });
        foreach ($normalised as $code => $quantity) {
            ProjectedPlotProduct::query()->updateOrCreate(['projected_plot_id' => $plotId, 'product_code' => $code], ['quantity' => $quantity, 'last_source_import_run_id' => $run->id]);
        }
    }

    /** @param array<int, string> $callNumbers */
    private function markMissing(SourceImportRun $run, string $sourceName, array $callNumbers): int
    {
        $services = ProjectedPlotService::query()->whereNotNull('source_call_number')->whereNotIn('source_call_number', $callNumbers)->whereHas('projectedPlot', fn ($query) => $query->where('external_source', $sourceName))->lockForUpdate()->get();
        foreach ($services as $service) {
            $service->update(['source_present' => false, 'source_missing_since' => $service->source_missing_since ?? now(), 'last_source_import_run_id' => $run->id]);
            $this->issues->record($run, SourceProjectionIssueType::MissingSourceRecord, "missing-source-record:{$sourceName}:{$service->source_call_number}", $service->source_call_number, $service);
        }

        return $services->count();
    }

    private function ensureServiceRows(ProjectedPlot $plot): void
    {
        foreach (CallOffServiceType::cases() as $service) {
            ProjectedPlotService::query()->firstOrCreate([
                'projected_plot_id' => $plot->id,
                'service_identifier' => $service->value,
            ]);
        }
    }
}
