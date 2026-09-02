<?php

namespace App\Services;

use App\Data\ManualSourceImportAnalysis;
use App\Data\XlsxSourceReadResult;
use App\Data\XlsxSourceRow;
use App\Enums\CallOffServiceType;
use App\Enums\ManualSourceImportCategory;
use App\Models\ProjectedPlotService;

class ManualSourceImportAnalysisService
{
    public function __construct(
        private readonly SourceCallTypeMapper $callTypes,
        private readonly SiteAppImportDataDictionary $dictionary,
        private readonly SourceSiteResolver $sites,
        private readonly ManualSourceImportFingerprintService $fingerprints,
    ) {}

    public function analyse(string $sourceNamespace, XlsxSourceReadResult $workbook): ManualSourceImportAnalysis
    {
        $sourceNamespace = mb_strtolower(trim($sourceNamespace));
        $callNumberCounts = collect($workbook->rows)->pluck('callNumber')->filter()->countBy();
        $representedSiteKeys = collect($workbook->rows)
            ->pluck('sourceSiteKey')
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->uniqueStrict()
            ->values()
            ->all();
        $rows = [];
        $records = [];
        $blockingErrorCount = 0;

        foreach ($workbook->rows as $row) {
            $result = $this->analyseRow($sourceNamespace, $row, (int) ($callNumberCounts[$row->callNumber] ?? 0));
            $rows[] = $result;
            $blockingErrorCount += collect($result['errors'])->where('blocking', true)->count();

            if (collect($result['errors'])->where('blocking', true)->isEmpty()) {
                $records[] = $row->toSourceRecord();
            }
        }

        $incomingCallNumbers = collect($workbook->rows)->pluck('callNumber')->filter()->uniqueStrict();
        $bindingIds = collect($representedSiteKeys)
            ->map(fn (string $key) => $this->sites->resolve($sourceNamespace, $key)?->binding?->id)
            ->filter()
            ->values();

        if ($bindingIds->isNotEmpty()) {
            $missingServices = ProjectedPlotService::query()
                ->with(['projectedPlot.site.customerOrganisation'])
                ->whereNotNull('source_call_number')
                ->whereNotIn('source_call_number', $incomingCallNumbers)
                ->whereHas('projectedPlot', fn ($query) => $query
                    ->where('external_source', $sourceNamespace)
                    ->whereIn('source_site_binding_id', $bindingIds))
                ->orderBy('id')
                ->get();

            foreach ($missingServices as $service) {
                $plot = $service->projectedPlot;
                $rows[] = [
                    'row_number' => null,
                    'call_number' => $service->source_call_number,
                    'source_site_key' => $plot->sourceSiteBinding?->source_site_key,
                    'source_site_name' => $plot->sourceSiteBinding?->original_name,
                    'mapped_portal_site' => $this->siteView($plot->site),
                    'plot_reference' => $plot->plot_reference,
                    'call_type' => $service->source_call_type,
                    'mapped_service' => $service->service_identifier->label(),
                    'products' => [],
                    'current_state' => $this->currentState($service),
                    'incoming_state' => null,
                    'diff_category' => ManualSourceImportCategory::MissingFromSource->value,
                    'warnings' => [[
                        'code' => 'MISSING_FROM_SOURCE',
                        'message' => 'This known Call No. is absent from the uploaded site scope. It will be retained and flagged.',
                        'blocking' => false,
                    ]],
                    'errors' => [],
                ];
            }
        }

        $summary = collect(ManualSourceImportCategory::cases())
            ->mapWithKeys(fn (ManualSourceImportCategory $category): array => [$category->value => 0])
            ->all();
        foreach ($rows as $row) {
            $summary[$row['diff_category']]++;
        }

        return new ManualSourceImportAnalysis(
            $summary,
            $rows,
            $records,
            $representedSiteKeys,
            $blockingErrorCount,
            $this->fingerprints->for($sourceNamespace, $representedSiteKeys),
        );
    }

    /** @return array<string, mixed> */
    private function analyseRow(string $sourceNamespace, XlsxSourceRow $row, int $callNumberOccurrences): array
    {
        $errors = $row->errors;
        $warnings = $row->warnings;
        $resolution = $this->sites->resolve($sourceNamespace, $row->sourceSiteKey);
        $serviceType = $this->serviceForSource($sourceNamespace, $row->callType);
        $existing = $row->callNumber === '' ? null : ProjectedPlotService::query()
            ->with(['projectedPlot.site.customerOrganisation', 'projectedPlot.products', 'projectedPlot.sourceSiteBinding'])
            ->where('source_call_number', $row->callNumber)
            ->first();
        $category = ManualSourceImportCategory::Invalid;

        if ($callNumberOccurrences > 1) {
            $errors[] = $this->message('DUPLICATE_CALL_NUMBER', 'Call No. occurs more than once in this workbook.');
        }

        if ($resolution === null && $row->sourceSiteKey !== '') {
            $category = ManualSourceImportCategory::SiteMappingRequired;
            $errors[] = $this->message('SITE_MAPPING_REQUIRED', 'The source site must be explicitly bound to an existing Portal site.');
        }
        if ($serviceType === null && $row->callType !== '') {
            $normalisedCallType = mb_strtoupper(trim($row->callType));
            if ($this->dictionary->isKnownCallType($normalisedCallType)) {
                if ($resolution !== null) {
                    $category = ManualSourceImportCategory::ReconciliationRequired;
                }
                $errors[] = $this->message(
                    'CALL_TYPE_SERVICE_MAPPING_REQUIRED',
                    "Call Type '{$normalisedCallType}' is valid source data but has no confirmed Portal service mapping.",
                );
            } else {
                if ($resolution !== null) {
                    $category = ManualSourceImportCategory::UnknownCallType;
                }
                $correction = $this->dictionary->likelyCallTypeCorrection($normalisedCallType);
                $message = $correction === null
                    ? "Unknown Call Type '{$row->callType}'."
                    : "Unknown Call Type '{$row->callType}'; it is a likely typo for {$correction} and requires Office confirmation.";
                $errors[] = $this->message('UNKNOWN_CALL_TYPE', $message);
            }
        }
        if ($errors === [] && $resolution !== null && $serviceType !== null) {
            $associationChanged = $existing !== null && (
                $existing->projectedPlot->external_source !== $sourceNamespace
                || (int) $existing->projectedPlot->site_id !== (int) $resolution->site->id
                || $existing->projectedPlot->plot_reference !== $row->plotReference
                || $existing->service_identifier !== $serviceType
            );
            $targetService = ProjectedPlotService::query()
                ->whereHas('projectedPlot', fn ($query) => $query
                    ->where('external_source', $sourceNamespace)
                    ->where('external_identifier', $row->sourceSiteKey.'|'.$row->plotReference))
                ->where('service_identifier', $serviceType)
                ->first();

            if ($associationChanged || ($targetService !== null && $targetService->source_call_number !== null && $targetService->source_call_number !== $row->callNumber)) {
                $category = ManualSourceImportCategory::ReconciliationRequired;
                $errors[] = $this->message('SOURCE_IDENTITY_CONFLICT', 'The permanent Call No. conflicts with an existing site, plot or service identity.');
            } else {
                $incomingComplete = $row->completedDate !== null || $row->completionFlag === true || $this->callTypes->isCompletionStage($serviceType, $row->jobStage);
                $existingComplete = $existing?->isSourceCompleted() ?? false;

                if ($incomingComplete && $row->completedDate === null) {
                    $category = ManualSourceImportCategory::ReconciliationRequired;
                    $warnings[] = [
                        'code' => 'COMPLETION_DATE_MISSING',
                        'message' => 'The completion stage proves completion, but no Completed Date was supplied.',
                        'blocking' => false,
                    ];
                } elseif ($existing === null) {
                    $category = ManualSourceImportCategory::New;
                } elseif (! $existingComplete && $incomingComplete) {
                    $category = ManualSourceImportCategory::Completed;
                } elseif ($existingComplete && ! $incomingComplete) {
                    $category = ManualSourceImportCategory::CompletionReversed;
                } elseif ($this->isChanged($existing, $row)) {
                    $category = ManualSourceImportCategory::Updated;
                } else {
                    $category = ManualSourceImportCategory::Unchanged;
                }
            }
        }

        if ($errors !== [] && ! in_array($category, [ManualSourceImportCategory::SiteMappingRequired, ManualSourceImportCategory::UnknownCallType, ManualSourceImportCategory::ReconciliationRequired], true)) {
            $category = ManualSourceImportCategory::Invalid;
        }

        return [
            'row_number' => $row->rowNumber,
            'call_number' => $row->callNumber,
            'source_site_key' => $row->sourceSiteKey,
            'source_site_name' => $row->sourceSiteName,
            'mapped_portal_site' => $resolution === null ? null : $this->siteView($resolution->site),
            'plot_reference' => $row->plotReference,
            'call_type' => $row->callType,
            'mapped_service' => $serviceType?->label(),
            'products' => collect($row->products)->map(fn ($quantity) => $quantity === null ? 0 : (float) $quantity)->all(),
            'current_state' => $existing === null ? null : $this->currentState($existing),
            'incoming_state' => [
                'job_stage' => $row->jobStage,
                'completion_flag' => $row->completionFlag,
                'completed_date' => $row->completedDate?->toDateString(),
                'completed' => $serviceType === null ? null : ($row->completedDate !== null || $row->completionFlag === true || $this->callTypes->isCompletionStage($serviceType, $row->jobStage)),
            ],
            'diff_category' => $category->value,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function isChanged(ProjectedPlotService $existing, XlsxSourceRow $row): bool
    {
        $existingProducts = $existing->projectedPlot->products
            ->mapWithKeys(fn ($product): array => [$product->product_code => (float) $product->quantity]);
        $incomingProducts = collect($row->products)->map(fn ($quantity): float => (float) ($quantity ?? 0));

        return $existing->source_call_type !== $row->callType
            || $existing->source_job_stage !== $row->jobStage
            || $existing->source_completion_flag !== $row->completionFlag
            || $existing->source_completed_at?->toDateString() !== $row->completedDate?->toDateString()
            || ! $existing->source_present
            || $incomingProducts->contains(fn (float $quantity, string $code): bool => (float) ($existingProducts[$code] ?? 0) !== $quantity);
    }

    /** @return array<string, mixed> */
    private function currentState(ProjectedPlotService $service): array
    {
        return [
            'source_present' => $service->source_present,
            'job_stage' => $service->source_job_stage,
            'completion_flag' => $service->source_completion_flag,
            'completed_date' => $service->source_completed_at?->toDateString(),
            'completed' => $service->isSourceCompleted(),
        ];
    }

    /** @return array{name: string, customer: string} */
    private function siteView($site): array
    {
        return [
            'name' => $site->name,
            'customer' => $site->customerOrganisation->name,
        ];
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private function message(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message, 'blocking' => true];
    }

    private function serviceForSource(string $sourceNamespace, string $callType): ?CallOffServiceType
    {
        return $this->callTypes->serviceFor($callType);
    }
}
