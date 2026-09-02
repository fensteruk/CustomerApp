<?php

use App\Contracts\SpreadsheetStructureInterpreter;
use App\Data\SpreadsheetCellData;
use App\Data\SpreadsheetSheetData;
use App\Data\SpreadsheetWorkbookData;
use App\Enums\PortalRoleIdentifier;
use App\Enums\WorkbookColumnRole;
use App\Models\CustomerOrganisation;
use App\Models\ManualSourceImportPreview;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceImportRun;
use App\Models\User;
use App\Models\WorkbookInterpretationProfile;
use App\Services\SourceSiteBindingService;
use App\Services\WorkbookInterpretationProfileService;
use App\Services\WorkbookMappingService;
use App\Services\XlsxSourceReader;
use App\Services\XlsxWorkbookInspector;
use App\Support\ManualSourceImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

test('it deterministically detects shifted aliases dynamic products and safety overrides', function (): void {
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['SiteApp export for review'],
            [],
            ['Generated locally'],
            ['Plot Ref', 'Site Name', 'PFD', 'Call Type', 'Call No.', 'Plot To Be Installed', 'Site Value', 'complete', 'CAS', 'Notes'],
            ['P-001', 'SITE-A', 0, 'PC1', 'CALL-1', new DateTimeImmutable('2026-09-01'), 1200.50, 'No', 2, 'safe note'],
            ['P-002', 'SITE-A', 1, 'CC1', 'CALL-2', new DateTimeImmutable('2026-09-02'), 900, 'Yes', 0, 'safe note'],
            ['P-003', 'SITE-A', 0, 'CML', 'CALL-3', new DateTimeImmutable('2026-09-03'), 450, 'No', 3, 'safe note'],
        ],
    ]]);

    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
    } finally {
        @unlink($path);
    }

    $columns = collect($interpretation->selected()?->columns)->keyBy('originalHeader');
    expect($interpretation->selectedSheet)->toBe('Calls')
        ->and($interpretation->headerRow)->toBe(4)
        ->and($columns['Call No.']->role)->toBe(WorkbookColumnRole::CallNumber)
        ->and($columns['Site Name']->role)->toBe(WorkbookColumnRole::SiteName)
        ->and($columns['Plot Ref']->role)->toBe(WorkbookColumnRole::PlotReference)
        ->and($columns['Call Type']->role)->toBe(WorkbookColumnRole::CallType)
        ->and($columns['PFD']->role)->toBe(WorkbookColumnRole::ProductQuantity)
        ->and($columns['CAS']->role)->toBe(WorkbookColumnRole::ProductQuantity)
        ->and($columns['Plot To Be Installed']->role)->toBe(WorkbookColumnRole::OperationalTargetDate)
        ->and($columns['Site Value']->role)->toBe(WorkbookColumnRole::CommercialValue)
        ->and($columns['complete']->role)->toBe(WorkbookColumnRole::Unknown)
        ->and($columns['complete']->reasons)->toContain('The source meaning of complete is not confirmed.')
        ->and($columns['Notes']->role)->toBe(WorkbookColumnRole::Unknown);
});

test('the reference workbook shape is interpreted from a fictional structure-equivalent fixture', function (): void {
    $headers = [
        'Call No.', 'Site Name', 'Plot Ref', "Items\nOrdered Status", "Plot To Be \nInstalled ", 'complete', 'Call type',
        'CAS', 'FLU', 'VS', 'TT', 'BAY', 'PFD', 'PSU', 'PSG', 'CDF', 'CDU', 'CDG', 'GLS', 'PSP', 'BF', 'ALI', 'AOV', 'FI', 'WP', 'MISC',
        'Site Value',
    ];
    $dateStyle = (new Style)->setFormat('mm-dd-yy');
    $referenceRow = static function (array $values) use ($dateStyle): Row {
        return new Row(array_map(
            static fn (mixed $value, int $index): Cell => Cell::fromValue($value, $index === 4 ? $dateStyle : null),
            $values,
            array_keys($values),
        ));
    };
    $path = adaptiveWorkbook([[
        'name' => 'Sheet1',
        'rows' => [
            $headers,
            $referenceRow([9001, 'TEST — Willow Park', 'TEST — Plot 001', 'All In Stock', new DateTimeImmutable('2026-09-01'), 'No', 'PC1', 5, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, 4, 0, 1, 0, 0, 0, 0, 0, 1250]),
            $referenceRow([9002, 'TEST — Willow Park', 'TEST — Plot 002', 'Partly In Stock', new DateTimeImmutable('2026-09-02'), 'yes', 'CC1', 0, 2, 0, 0, 0, 0, 1, 0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 0, 0, 900]),
            [],
            $referenceRow([9003, 'TEST — Riverside', 'TEST — Plot 003', 'To Be Checked', new DateTimeImmutable('2026-09-03'), 'YES', 'CML', 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 450]),
        ],
    ]]);

    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
        $selected = $interpretation->selected();
        $columns = collect($selected?->columns)->keyBy('originalHeader');
        $mapping = app(WorkbookMappingService::class)->canonicalise($interpretation, [
            'sheet' => 'Sheet1',
            'header_row' => 1,
            'columns' => collect($selected?->columns)->map(fn ($column): array => [
                'source_index' => $column->sourceIndex,
                'semantic_role' => ($column->role === WorkbookColumnRole::Unknown ? WorkbookColumnRole::Ignore : $column->role)->value,
                'subtype' => $column->role === WorkbookColumnRole::ProductQuantity ? $column->subtype : null,
            ])->all(),
        ]);
        $read = app(XlsxSourceReader::class)->readMapped($path, $mapping);
    } finally {
        @unlink($path);
    }

    expect($interpretation->selectedSheet)->toBe('Sheet1')
        ->and($interpretation->headerRow)->toBe(1)
        ->and($interpretation->overallConfidence)->toBeGreaterThanOrEqual(95)
        ->and($selected?->columns)->toHaveCount(27)
        ->and(collect($selected?->columns)->where('role', WorkbookColumnRole::ProductQuantity))->toHaveCount(19)
        ->and($columns["Items\nOrdered Status"]->role)->toBe(WorkbookColumnRole::Unknown)
        ->and($columns["Plot To Be \nInstalled"]->role)->toBe(WorkbookColumnRole::OperationalTargetDate)
        ->and($columns["Plot To Be \nInstalled"]->profile['date_percent'])->toBe(100.0)
        ->and($columns['complete']->profile['boolean_like_percent'])->toBe(100.0)
        ->and($columns['Site Value']->role)->toBe(WorkbookColumnRole::CommercialValue)
        ->and($columns['Site Value']->profile['sample_values'])->toBe('[commercial values withheld]')
        ->and($read->rows)->toHaveCount(3)
        ->and($read->blankRowCount)->toBe(1)
        ->and(collect($read->rows)->pluck('completionFlag')->all())->toBe([null, null, null]);
});

test('typed Excel dates are safe during every header-candidate and profile pass', function (): void {
    $workbook = new SpreadsheetWorkbookData([
        new SpreadsheetSheetData('Calls', true, [
            1 => array_map(fn (string $value): SpreadsheetCellData => new SpreadsheetCellData($value), ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Plot To Be Installed']),
            2 => [
                new SpreadsheetCellData(9001),
                new SpreadsheetCellData('TEST — Willow Park'),
                new SpreadsheetCellData('TEST — Plot 001'),
                new SpreadsheetCellData('PC1'),
                new SpreadsheetCellData(new DateTimeImmutable('2026-09-01')),
            ],
        ], 2, 0),
    ], '1900');

    $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret($workbook);
    $operationalDate = collect($interpretation->selected()?->columns)->firstWhere('role', WorkbookColumnRole::OperationalTargetDate);

    expect($interpretation->headerRow)->toBe(1)
        ->and($operationalDate->profile['date_percent'])->toBe(100.0);
});

test('ambiguous critical candidates and multiple plausible sheets require explicit Office selection', function (): void {
    $path = adaptiveWorkbook([
        ['name' => 'Calls A', 'rows' => [
            ['Call No.', 'Site', 'Plot Ref', 'Unit', 'Call Type'],
            ['A-1', 'SITE-A', 'P-1', 'U-1', 'PC1'],
        ]],
        ['name' => 'Calls B', 'rows' => [
            ['Call Number', 'Development', 'Plot Number', 'Call Code'],
            ['B-1', 'SITE-B', 'P-2', 'CC1'],
        ]],
    ]);

    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
    } finally {
        @unlink($path);
    }

    expect(collect($interpretation->issues)->pluck('code')->all())->toContain('WORKSHEET_SELECTION_REQUIRED')
        ->and(collect($interpretation->sheets[0]->issues)->pluck('code')->all())->toContain('AMBIGUOUS_CRITICAL_MAPPING')
        ->and($interpretation->confirmationNeeded)->toBeTrue();
});

test('Office confirmation can select a different plausible worksheet without trusting the initial proposal', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $site = adaptiveSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-B', 'Site B', null, $site);
    $path = adaptiveWorkbook([
        ['name' => 'Calls A', 'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'CAS'],
            ['A-1', 'SITE-A', 'P-1', 'PC1', 0],
        ]],
        ['name' => 'Calls B', 'rows' => [
            ['Call Number', 'Development Name', 'Plot Number', 'Call Code', 'PFD'],
            ['B-1', 'SITE-B', 'P-2', 'CC1', 1],
        ]],
    ]);
    $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
        'workbook' => new UploadedFile($path, 'two-sheets.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertCreated()
        ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_MAPPING_REQUIRED);
    $preview = ManualSourceImportPreview::query()->sole();
    $sheet = collect($response->json('workbook_interpretation.sheets'))->firstWhere('sheet', 'Calls B');
    $columns = collect($sheet['columns'])->map(fn (array $column): array => [
        'source_index' => $column['source_index'],
        'semantic_role' => $column['semantic_role'],
        'subtype' => $column['semantic_role'] === WorkbookColumnRole::ProductQuantity->value ? $column['original_header'] : null,
    ])->all();

    try {
        $this->postJson("/portal/source-imports/previews/{$preview->uuid}/interpretation", [
            'sheet' => 'Calls B', 'header_row' => 1, 'columns' => $columns, 'confirm' => true,
        ])->assertOk()
            ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_READY)
            ->assertJsonPath('rows.0.call_number', 'B-1')
            ->assertJsonPath('rows.0.mapped_service', 'Cavity Closers');
    } finally {
        @unlink($path);
    }
});

test('the central alias dictionary recognises approved punctuation and wording variants', function (): void {
    $path = adaptiveWorkbook([[
        'name' => 'Alias variants',
        'rows' => [
            ['Call #', 'Development Name', 'Unit No.', 'Call Type Code', 'Is Complete', 'Completion Date', 'Installation Date', 'Plot Value'],
            ['CALL-1', 'SITE-A', 'P-1', 'PC1', 'TRUE', new DateTimeImmutable('2026-08-28'), new DateTimeImmutable('2026-09-10'), 1000],
            ['CALL-2', 'SITE-A', 'P-2', 'CC1', 'false', null, new DateTimeImmutable('2026-09-11'), 800],
        ],
    ]]);
    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
    } finally {
        @unlink($path);
    }
    $roles = collect($interpretation->selected()?->columns)->mapWithKeys(fn ($column): array => [$column->originalHeader => $column->role]);

    expect($roles['Call #'])->toBe(WorkbookColumnRole::CallNumber)
        ->and($roles['Development Name'])->toBe(WorkbookColumnRole::SiteName)
        ->and($roles['Unit No.'])->toBe(WorkbookColumnRole::PlotReference)
        ->and($roles['Call Type Code'])->toBe(WorkbookColumnRole::CallType)
        ->and($roles['Is Complete'])->toBe(WorkbookColumnRole::Unknown)
        ->and($roles['Completion Date'])->toBe(WorkbookColumnRole::CompletedDate)
        ->and($roles['Installation Date'])->toBe(WorkbookColumnRole::OperationalTargetDate)
        ->and($roles['Plot Value'])->toBe(WorkbookColumnRole::CommercialValue);
});

test('missing critical fields and formulas without cached values block automatic mapping', function (): void {
    $workbook = new SpreadsheetWorkbookData([
        new SpreadsheetSheetData('Calls', true, [
            1 => array_map(fn (string $value): SpreadsheetCellData => new SpreadsheetCellData($value), ['Call No.', 'Site Name', 'Call Type']),
            2 => [
                new SpreadsheetCellData(null, true, false),
                new SpreadsheetCellData('SITE-A'),
                new SpreadsheetCellData('PC1'),
            ],
        ], 2, 0),
    ], '1900');
    $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret($workbook);

    $codes = collect($interpretation->issues)->merge($interpretation->selected()?->issues ?? [])->pluck('code')->all();
    expect($codes)->toContain('CRITICAL_MAPPING_REQUIRED', 'CRITICAL_FORMULA_VALUE_MISSING')
        ->and($interpretation->confirmationNeeded)->toBeTrue();
});

test('a workbook with no Call No column is explicitly blocked', function (): void {
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Site Name', 'Plot Ref', 'Call Type', 'CAS'],
            ['SITE-A', 'P-001', 'PC1', 0],
            ['SITE-A', 'P-002', 'CC1', 1],
        ],
    ]]);
    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
    } finally {
        @unlink($path);
    }

    expect(collect($interpretation->selected()?->issues)->pluck('message')->all())
        ->toContain('CALL_NUMBER must be mapped before import.')
        ->and($interpretation->confirmationNeeded)->toBeTrue();
});

test('formula cells with a usable cached value are profiled and normalised without evaluation', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $site = adaptiveSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'CAS'],
            new Row([
                Cell::fromValue('CALL-1'),
                Cell::fromValue('SITE-A'),
                Cell::fromValue('P-001'),
                Cell::fromValue('PC1'),
                new FormulaCell('=1+1', new Style),
            ]),
        ],
    ]]);
    addCachedFormulaValue($path, 'E2', '2');

    try {
        $this->actingAs($office)->postJson('/portal/source-imports/previews', [
            'workbook' => new UploadedFile($path, 'cached-formula.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertCreated()
            ->assertJsonPath('can_commit', true)
            ->assertJsonPath('rows.0.products.CAS', 2);
    } finally {
        @unlink($path);
    }
});

test('Office confirmation saves a structural profile and exact reuse is revalidated without filename matching', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $site = adaptiveSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'VS'],
            ['CALL-1', 'SITE-A', 'P-001', 'PC1', 2],
            ['CALL-2', 'SITE-A', 'P-002', 'CC1', 0],
        ],
    ]]);

    $first = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
        'workbook' => new UploadedFile($path, 'first-name.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertCreated()
        ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_READY)
        ->assertJsonPath('can_commit', true);
    $preview = ManualSourceImportPreview::query()->sole();
    $columns = collect($first->json('workbook_interpretation.sheets.0.columns'))
        ->map(fn (array $column): array => [
            'source_index' => $column['source_index'],
            'semantic_role' => $column['semantic_role'],
            'subtype' => $column['semantic_role'] === WorkbookColumnRole::ProductQuantity->value ? 'VS' : null,
        ])->all();

    $this->postJson("/portal/source-imports/previews/{$preview->uuid}/interpretation", [
        'sheet' => 'Calls',
        'header_row' => 1,
        'columns' => $columns,
        'confirm' => true,
    ])->assertOk()
        ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_READY)
        ->assertJsonPath('can_commit', true);

    expect(WorkbookInterpretationProfile::query()->count())->toBe(1)
        ->and(SourceImportRun::query()->count())->toBe(0)
        ->and(ProjectedPlotService::query()->count())->toBe(0);

    $secondPath = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'VS'],
            ['CALL-9', 'SITE-A', 'P-009', 'CML', 4],
            ['CALL-10', 'SITE-A', 'P-010', 'PC1', 0],
        ],
    ]]);
    try {
        $this->actingAs($office)->postJson('/portal/source-imports/previews', [
            'workbook' => new UploadedFile($secondPath, 'completely-different-name.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertCreated()
            ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_READY)
            ->assertJsonPath('workbook_interpretation.profile_match.kind', 'exact')
            ->assertJsonPath('can_commit', true);
    } finally {
        @unlink($path);
        @unlink($secondPath);
    }
});

test('minor layout changes surface likely saved mappings but require reconfirmation', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $site = adaptiveSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $baseline = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'VS'],
            ['CALL-1', 'SITE-A', 'P-001', 'PC1', 2],
            ['CALL-2', 'SITE-A', 'P-002', 'CC1', 0],
        ],
    ]]);
    $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
        'workbook' => new UploadedFile($baseline, 'baseline.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertCreated();
    $preview = ManualSourceImportPreview::query()->sole();
    $columns = collect($response->json('workbook_interpretation.sheets.0.columns'))->map(fn (array $column): array => [
        'source_index' => $column['source_index'],
        'semantic_role' => $column['semantic_role'],
        'subtype' => $column['semantic_role'] === WorkbookColumnRole::ProductQuantity->value ? 'VS' : null,
    ])->all();
    $this->postJson("/portal/source-imports/previews/{$preview->uuid}/interpretation", [
        'sheet' => 'Calls', 'header_row' => 1, 'columns' => $columns, 'confirm' => true,
    ])->assertOk();

    $changed = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Plot Ref', 'Call Type', 'Call No.', 'VS', 'Site Name', 'Irrelevant'],
            ['P-001', 'PC1', 'CALL-1', 2, 'SITE-A', 'x'],
            ['P-002', 'CC1', 'CALL-2', 0, 'SITE-A', 'y'],
        ],
    ]]);
    try {
        $changedResponse = $this->postJson('/portal/source-imports/previews', [
            'workbook' => new UploadedFile($changed, 'changed.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertCreated()
            ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_MAPPING_REQUIRED)
            ->assertJsonPath('workbook_interpretation.profile_match.kind', 'likely')
            ->assertJsonPath('workbook_interpretation.profile_match.requires_confirmation', true);
        $callNumberSuggestion = collect($changedResponse->json('workbook_interpretation.profile_match.suggested_mappings.columns'))
            ->firstWhere('semantic_role', WorkbookColumnRole::CallNumber->value);
        expect($callNumberSuggestion['source_index'])->toBe(3)
            ->and($callNumberSuggestion['previous_source_index'])->toBe(1);
    } finally {
        @unlink($baseline);
        @unlink($changed);
    }
});

test('profile fingerprint versions are unique and changed confirmations create an auditable version', function (): void {
    $office = adaptiveOfficeUser();
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'VS'],
            ['CALL-1', 'SITE-A', 'P-001', 'PC1', 2],
            ['CALL-2', 'SITE-A', 'P-002', 'CC1', 0],
        ],
    ]]);
    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
    } finally {
        @unlink($path);
    }
    $base = [
        'sheet' => 'Calls',
        'header_row' => 1,
        'columns' => collect($interpretation->selected()?->columns)->map(fn ($column): array => [
            'source_index' => $column->sourceIndex,
            'semantic_role' => $column->role->value,
            'subtype' => $column->role === WorkbookColumnRole::ProductQuantity ? 'VS' : null,
        ])->all(),
    ];
    $mapping = app(WorkbookMappingService::class)->canonicalise($interpretation, $base);
    $profiles = app(WorkbookInterpretationProfileService::class);
    $first = $profiles->save($office, ManualSourceImport::SOURCE_NAMESPACE, $interpretation, $mapping);
    $repeat = $profiles->save($office, ManualSourceImport::SOURCE_NAMESPACE, $interpretation, $mapping);
    $changed = $mapping;
    $changed['columns'][4]['semantic_role'] = WorkbookColumnRole::Ignore->value;
    $changed['columns'][4]['subtype'] = null;
    $second = $profiles->save($office, ManualSourceImport::SOURCE_NAMESPACE, $interpretation, $changed);

    expect($repeat->id)->toBe($first->id)
        ->and($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and(WorkbookInterpretationProfile::query()->count())->toBe(2);
});

test('commercial and operational date safety overrides reject unsafe Office remapping', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Site Value', 'Plot To Be Installed', 'XYZ'],
            ['CALL-1', 'SITE-A', 'P-001', 'PC1', 1000, new DateTimeImmutable('2026-09-01'), 2],
        ],
    ]]);
    $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
        'workbook' => new UploadedFile($path, 'unsafe.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertCreated();
    $preview = ManualSourceImportPreview::query()->sole();
    $columns = collect($response->json('workbook_interpretation.sheets.0.columns'))->map(function (array $column): array {
        if ($column['original_header'] === 'Site Value' || $column['original_header'] === 'Plot To Be Installed') {
            return ['source_index' => $column['source_index'], 'semantic_role' => WorkbookColumnRole::ProductQuantity->value, 'subtype' => 'BAD'];
        }

        return ['source_index' => $column['source_index'], 'semantic_role' => $column['semantic_role'], 'subtype' => $column['subtype']];
    })->all();

    try {
        $this->postJson("/portal/source-imports/previews/{$preview->uuid}/interpretation", [
            'sheet' => 'Calls', 'header_row' => 1, 'columns' => $columns, 'confirm' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'MAPPING_REJECTED');
    } finally {
        @unlink($path);
    }
});

test('known product columns with negative evidence cannot be silently ignored or confirmed', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'BF'],
            ['CALL-1', 'SITE-A', 'P-001', 'PC1', -1],
            ['CALL-2', 'SITE-A', 'P-002', 'CC1', 0],
        ],
    ]]);
    $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
        'workbook' => new UploadedFile($path, 'negative-product.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertCreated()
        ->assertJsonPath('status', ManualSourceImport::PREVIEW_STATUS_MAPPING_REQUIRED)
        ->assertJsonPath('can_commit', false);
    $preview = ManualSourceImportPreview::query()->sole();
    $columns = collect($response->json('workbook_interpretation.sheets.0.columns'))->map(fn (array $column): array => [
        'source_index' => $column['source_index'],
        'semantic_role' => $column['semantic_role'],
        'subtype' => $column['semantic_role'] === WorkbookColumnRole::ProductQuantity->value ? 'BF' : null,
    ])->all();

    try {
        $this->postJson("/portal/source-imports/previews/{$preview->uuid}/interpretation", [
            'sheet' => 'Calls', 'header_row' => 1, 'columns' => $columns, 'confirm' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'MAPPING_REJECTED');
    } finally {
        @unlink($path);
    }
});

test('the unresolved complete field is ignored and cannot drive source completion', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $site = adaptiveSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Complete'],
            ['CALL-CC', 'SITE-A', 'P-001', 'CC1', 'Yes'],
            ['CALL-CML', 'SITE-A', 'P-002', 'CML', 'No'],
        ],
    ]]);
    try {
        $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
            'workbook' => new UploadedFile($path, 'completion.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertCreated()
            ->assertJsonPath('can_commit', true)
            ->assertJsonPath('workbook_interpretation.sheets.0.columns.4.semantic_role', WorkbookColumnRole::Unknown->value);
        $preview = ManualSourceImportPreview::query()->sole();
        $this->postJson("/portal/source-imports/previews/{$preview->uuid}/commit", [
            'confirm' => true,
            'content_sha256' => $response->json('metadata.sha256'),
        ])->assertOk()->assertJsonPath('counts.created', 2);
    } finally {
        @unlink($path);
    }

    expect(ProjectedPlotService::query()->where('source_call_number', 'CALL-CC')->firstOrFail()->isSourceCompleted())->toBeFalse()
        ->and(ProjectedPlotService::query()->where('source_call_number', 'CALL-CML')->firstOrFail()->service_identifier->value)->toBe('cml');
});

test('valid but unmapped CM1 and CM2 codes require reconciliation for the adaptive SiteApp XLSX source', function (): void {
    Storage::fake('local');
    $office = adaptiveOfficeUser();
    $site = adaptiveSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = adaptiveWorkbook([[
        'name' => 'Calls',
        'rows' => [
            ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'CAS'],
            ['CALL-1', 'SITE-A', 'P-001', 'CM1', 0],
            ['CALL-2', 'SITE-A', 'P-002', 'CM2', 0],
        ],
    ]]);
    try {
        $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', [
            'workbook' => new UploadedFile($path, 'unconfirmed-codes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertCreated()
            ->assertJsonPath('can_commit', false);
    } finally {
        @unlink($path);
    }

    expect(collect($response->json('rows'))->pluck('diff_category')->all())->toBe(['RECONCILIATION_REQUIRED', 'RECONCILIATION_REQUIRED'])
        ->and(collect($response->json('rows'))->flatMap(fn (array $row): array => $row['errors'])->pluck('code')->unique()->all())->toBe(['CALL_TYPE_SERVICE_MAPPING_REQUIRED'])
        ->and(SourceImportRun::query()->count())->toBe(0);
});

test('date-looking numeric product values remain products and large local workbooks stay bounded', function (): void {
    $rows = [['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'DTP']];
    for ($index = 1; $index <= 3000; $index++) {
        $rows[] = ["CALL-{$index}", 'SITE-A', "P-{$index}", 'PC1', $index % 3 === 0 ? 0 : 45000 + $index];
    }
    $path = adaptiveWorkbook([['name' => 'Calls', 'rows' => $rows]]);
    $startMemory = memory_get_usage(true);
    $start = hrtime(true);
    try {
        $interpretation = app(SpreadsheetStructureInterpreter::class)->interpret(app(XlsxWorkbookInspector::class)->inspect($path));
    } finally {
        @unlink($path);
    }
    $seconds = (hrtime(true) - $start) / 1_000_000_000;
    $memory = memory_get_peak_usage(true) - $startMemory;
    $dtp = collect($interpretation->selected()?->columns)->firstWhere('originalHeader', 'DTP');

    expect($dtp->role)->toBe(WorkbookColumnRole::ProductQuantity)
        ->and($dtp->profile['date_percent'])->toBe(0.0)
        ->and($seconds)->toBeLessThan(12)
        ->and($memory)->toBeLessThan(160 * 1024 * 1024);
});

test('the interpreter implementation has no external network or AI dependency', function (): void {
    $source = file_get_contents(app_path('Services/DeterministicSpreadsheetStructureInterpreter.php'));

    expect($source)->not->toContain('Http::', 'Guzzle', 'OpenAI', 'Gemini', 'Claude', 'curl_');
});

function adaptiveOfficeUser(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
}

function adaptiveSite(): Site
{
    return Site::factory()->create(['customer_organisation_id' => CustomerOrganisation::factory()->create()->id]);
}

/** @param list<array{name: string, rows: list<array<mixed>|Row>}> $sheets */
function adaptiveWorkbook(array $sheets): string
{
    $path = storage_path('framework/'.uniqid('adaptive-source-', true).'.xlsx');
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($sheets as $sheetIndex => $sheet) {
        $writerSheet = $sheetIndex === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $writerSheet->setName($sheet['name']);
        foreach ($sheet['rows'] as $row) {
            $writer->addRow($row instanceof Row ? $row : Row::fromValues($row));
        }
    }
    $writer->close();

    return $path;
}

function addCachedFormulaValue(string $path, string $cellReference, string $value): void
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $pattern = '/(<c[^>]*r="'.preg_quote($cellReference, '/').'"[^>]*>\s*<f[^>]*>.*?<\/f>)(\s*<\/c>)/s';
    $updated = preg_replace($pattern, '$1<v>'.$value.'</v>$2', $xml, 1, $count);
    expect($count)->toBe(1);
    $zip->addFromString('xl/worksheets/sheet1.xml', (string) $updated);
    $zip->close();
}
