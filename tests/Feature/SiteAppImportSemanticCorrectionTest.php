<?php

use App\Contracts\SpreadsheetStructureInterpreter;
use App\Data\SourceRecord;
use App\Data\SpreadsheetCellData;
use App\Data\SpreadsheetSheetData;
use App\Data\SpreadsheetWorkbookData;
use App\Data\XlsxSourceReadResult;
use App\Data\XlsxSourceRow;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Enums\WorkbookColumnRole;
use App\Exceptions\InvalidSourceWorkbook;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkbookInterpretationProfile;
use App\Services\CallOffLeadTimeService;
use App\Services\ManualSourceImportAnalysisService;
use App\Services\SiteAppImportDataDictionary;
use App\Services\SourceCallTypeMapper;
use App\Services\SourceProjectionImportService;
use App\Services\SourceSiteBindingService;
use App\Services\WorkbookInterpretationProfileService;
use App\Services\WorkbookMappingService;
use App\Support\ManualSourceImport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the authoritative Call Type dictionary maps confirmed revisits and never infers the CC typo', function (): void {
    $dictionary = app(SiteAppImportDataDictionary::class);
    $mapper = app(SourceCallTypeMapper::class);

    expect($dictionary->callTypes())->toMatchArray([
        'PC1' => ['description' => 'Plot Install', 'portal_service' => 'windows'],
        'CC1' => ['description' => 'Cavity Closer 1', 'portal_service' => 'cavity_closers'],
        'CM1' => ['description' => 'Revisit 1', 'portal_service' => 'cml'],
        'CM2' => ['description' => 'Revisit 2', 'portal_service' => 'cml'],
        'CML' => ['description' => 'CML Call Off', 'portal_service' => 'cml'],
    ])->and($dictionary->isKnownCallType('CC!'))->toBeFalse()
        ->and($dictionary->likelyCallTypeCorrection('CC!'))->toBe('CC1')
        ->and($mapper->serviceFor('PC1'))->toBe(CallOffServiceType::Windows)
        ->and($mapper->serviceFor('CC1'))->toBe(CallOffServiceType::CavityClosers)
        ->and($mapper->serviceFor('CML'))->toBe(CallOffServiceType::Cml)
        ->and($mapper->serviceFor('CC!'))->toBeNull()
        ->and($mapper->serviceFor('CM1'))->toBe(CallOffServiceType::Cml)
        ->and($mapper->serviceFor('CM2'))->toBe(CallOffServiceType::Cml);
});

test('manual analysis blocks literal CC typo while confirmed revisit codes map to CML', function (): void {
    $office = semanticOfficeUser();
    $site = semanticBoundSite($office);
    $workbook = new XlsxSourceReadResult('Sheet1', ['Sheet1'], [], [
        semanticSourceRow(2, 'CALL-TYPO', 'CC!'),
        semanticSourceRow(3, 'CALL-CM1', 'CM1'),
        semanticSourceRow(4, 'CALL-CM2', 'CM2'),
    ], 0);

    $analysis = app(ManualSourceImportAnalysisService::class)->analyse(ManualSourceImport::SOURCE_NAMESPACE, $workbook);
    $rows = collect($analysis->rows)->keyBy('call_number');

    expect($site->exists)->toBeTrue()
        ->and($rows['CALL-TYPO']['diff_category'])->toBe('UNKNOWN_CALL_TYPE')
        ->and($rows['CALL-TYPO']['errors'][0]['message'])->toContain('likely typo for CC1')
        ->and($rows['CALL-CM1']['diff_category'])->toBe('NEW')
        ->and($rows['CALL-CM1']['mapped_service'])->toBe('CML')
        ->and($rows['CALL-CM2']['diff_category'])->toBe('NEW')
        ->and($analysis->records)->toHaveCount(2);
});

test('the product registry produces only customer window and door totals while retaining Office detail', function (): void {
    $dictionary = app(SiteAppImportDataDictionary::class);
    $products = [
        'VS' => 2,
        'TT' => 1,
        'PSU' => 2,
        'BF' => 1,
        'CAS' => 9,
        'PFD' => 4,
        'MISC' => 3,
        'AOV' => null,
    ];

    expect($dictionary->customerRollups($products))->toBe([
        ['code' => 'WINDOWS', 'label' => 'Total Windows', 'quantity' => 3.0],
        ['code' => 'DOORS', 'label' => 'Total Doors', 'quantity' => 3.0],
    ])->and(collect($dictionary->officeDetails($products))->pluck('code')->all())
        ->toContain('VS', 'TT', 'PSU', 'BF', 'CAS', 'PFD', 'MISC')
        ->and($dictionary->customerRollups(['CAS' => 5, 'PFD' => 1, 'BF' => 0]))->toBe([])
        ->and($dictionary->hasPositiveBifold(['PSU' => 3, 'CDG' => 1]))->toBeFalse()
        ->and($dictionary->hasPositiveBifold(['BF' => 1]))->toBeTrue();
});

test('only exact positive BF extends lead time and a later source import can remove the signal', function (): void {
    $site = semanticSourceSite();
    $importer = app(SourceProjectionImportService::class);
    $from = CarbonImmutable::parse('2026-09-02');

    $importer->import('fixture', [new SourceRecord('CALL-1', 'SITE-1', 'P-001', 'PC1', null, null, ['BF' => 1, 'PSU' => 2])]);
    $service = ProjectedPlotService::query()->where('source_call_number', 'CALL-1')->firstOrFail();
    $fiveWeekDate = app(CallOffLeadTimeService::class)->earliestNormalDate($service, $from);

    $importer->import('fixture', [new SourceRecord('CALL-1', 'SITE-1', 'P-001', 'PC1', null, null, ['PSU' => 2, 'CDG' => 1])]);
    $service = $service->fresh('projectedPlot.products');
    $service->setRelation('projectedPlot', $service->projectedPlot);
    $fourWeekDate = app(CallOffLeadTimeService::class)->earliestNormalDate($service, $from);

    $lookalike = ProjectedPlotProduct::make(['product_code' => 'BF-LEGACY', 'quantity' => 1]);
    expect($site->exists)->toBeTrue()
        ->and($fiveWeekDate->toDateString())->toBe('2026-10-07')
        ->and($fourWeekDate->toDateString())->toBe('2026-09-30')
        ->and($service->projectedPlot->products->firstWhere('product_code', 'BF')->quantity)->toBe('0.000')
        ->and($lookalike->isBifold())->toBeFalse();
});

test('confirmed complete semantics are mapped while unknown product codes remain blocked', function (): void {
    $workbook = new SpreadsheetWorkbookData([
        new SpreadsheetSheetData('Sheet1', true, [
            1 => collect(['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'complete', 'XYZ'])
                ->map(fn (string $value): SpreadsheetCellData => new SpreadsheetCellData($value))->all(),
            2 => collect(['CALL-1', 'SITE-A', 'P-001', 'PC1', 'yes', 2])
                ->map(fn ($value): SpreadsheetCellData => new SpreadsheetCellData($value))->all(),
            3 => collect(['CALL-2', 'SITE-A', 'P-002', 'CC1', 'no', 0])
                ->map(fn ($value): SpreadsheetCellData => new SpreadsheetCellData($value))->all(),
        ], 3, 0),
    ], '1900');
    $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret($workbook);
    $columns = collect($interpretation->selected()->columns)->keyBy('originalHeader');

    expect($columns['complete']->role)->toBe(WorkbookColumnRole::CompletionFlag)
        ->and(collect($interpretation->selected()->issues)->pluck('code')->all())->not->toContain('UNCONFIRMED_SOURCE_FIELD')
        ->and($columns['XYZ']->role)->toBe(WorkbookColumnRole::ProductQuantity);

    $mapping = [
        'sheet' => 'Sheet1',
        'header_row' => 1,
        'columns' => collect($interpretation->selected()->columns)->map(function ($column): array {
            return [
                'source_index' => $column->sourceIndex,
                'semantic_role' => $column->role->value,
                'subtype' => $column->role === WorkbookColumnRole::ProductQuantity ? $column->subtype : null,
            ];
        })->all(),
    ];

    try {
        app(WorkbookMappingService::class)->canonicalise($interpretation, $mapping);
        $this->fail('The unsafe mapping should have been rejected.');
    } catch (InvalidSourceWorkbook $exception) {
        expect(collect($exception->errors)->pluck('code')->all())
            ->toContain('UNCONFIRMED_PRODUCT_CODE');
    }
});

test('profiles from an earlier semantic version are not exact or likely matches', function (): void {
    $office = semanticOfficeUser();
    $workbook = new SpreadsheetWorkbookData([
        new SpreadsheetSheetData('Sheet1', true, [
            1 => collect(['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'VS'])
                ->map(fn (string $value): SpreadsheetCellData => new SpreadsheetCellData($value))->all(),
            2 => collect(['CALL-1', 'SITE-A', 'P-001', 'PC1', 1])
                ->map(fn ($value): SpreadsheetCellData => new SpreadsheetCellData($value))->all(),
        ], 2, 0),
    ], '1900');
    $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret($workbook);
    $profiles = app(WorkbookInterpretationProfileService::class);
    $sheet = $interpretation->selected();

    WorkbookInterpretationProfile::query()->create([
        'source_namespace' => ManualSourceImport::SOURCE_NAMESPACE,
        'semantic_version' => 1,
        'sheet_identifier' => 'Sheet1',
        'structural_fingerprint' => $profiles->fingerprintForSheet($sheet),
        'normalised_headers' => collect($sheet->columns)->pluck('normalisedHeader')->all(),
        'type_profile' => [],
        'confirmed_mappings' => ['sheet' => 'Sheet1', 'header_row' => 1, 'columns' => []],
        'snapshot_scope' => 'PARTIAL_FILTERED_EXPORT',
        'version' => 1,
        'confirmed_by_user_id' => $office->id,
    ]);

    $match = $profiles->match(ManualSourceImport::SOURCE_NAMESPACE, $interpretation);

    expect($match['kind'])->toBe('none')
        ->and($match['profile'])->toBeNull()
        ->and(app(SiteAppImportDataDictionary::class)->semanticVersion())->toBe(3);
});

function semanticOfficeUser(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
}

function semanticBoundSite(User $office): Site
{
    $site = Site::factory()->create(['customer_organisation_id' => CustomerOrganisation::factory()->create()->id]);
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);

    return $site;
}

function semanticSourceSite(): Site
{
    return Site::factory()->create([
        'customer_organisation_id' => CustomerOrganisation::factory()->create()->id,
        'external_source' => 'fixture',
        'external_identifier' => 'SITE-1',
    ]);
}

function semanticSourceRow(int $row, string $callNumber, string $callType): XlsxSourceRow
{
    return new XlsxSourceRow($row, $callNumber, 'SITE-A', 'Site A', 'P-'.$row, $callType, null, null, []);
}
