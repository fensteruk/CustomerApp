<?php

use App\Data\SourceImportContext;
use App\Data\SourceRecord;
use App\Data\XlsxSourceReadResult;
use App\Data\XlsxSourceRow;
use App\Enums\PortalRoleIdentifier;
use App\Exceptions\InvalidSourceWorkbook;
use App\Exceptions\SourceWorkbookContractUnavailable;
use App\Models\CustomerOrganisation;
use App\Models\ManualSourceImportPreview;
use App\Models\PortalRole;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceImportRun;
use App\Models\SourceSiteBinding;
use App\Models\User;
use App\Services\ManualSourceImportAnalysisService;
use App\Services\ManualSourceImportService;
use App\Services\ManualSourceWorkbookContract;
use App\Services\SourceProjectionImportService;
use App\Services\SourceSiteBindingService;
use App\Services\XlsxSourceReader;
use App\Support\ManualSourceImport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (PortalRoleIdentifier::cases() as $role) {
        PortalRole::query()->firstOrCreate(['identifier' => $role->value], ['name' => $role->name]);
    }
});

test('the operational workbook contract remains unavailable until the representative workbook confirms it', function (): void {
    config()->set('manual_source_import.workbook.worksheet', null);
    config()->set('manual_source_import.workbook.headers.source_site_key', null);

    expect(fn () => app(ManualSourceWorkbookContract::class)->definition())
        ->toThrow(SourceWorkbookContractUnavailable::class);
});

test('the workbook contract cannot treat a commercial or core source field as a product', function (): void {
    configureMechanicalWorkbookContract();
    config()->set('manual_source_import.workbook.product_headers', ['CAS', 'Site Value']);

    expect(fn () => app(ManualSourceWorkbookContract::class)->definition())
        ->toThrow(SourceWorkbookContractUnavailable::class);
});

test('active Office Staff can create one exact source-site binding and duplicate identity is rejected', function (): void {
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = manualImportSite();

    $response = $this->actingAs($office)->postJson('/portal/source-site-bindings', [
        'source_namespace' => ' SITEAPP-XLSX ',
        'source_site_key' => '  Willow Park / 001  ',
        'original_name' => ' Willow Park ',
        'display_name' => ' Willow Park source ',
        'portal_site_uuid' => $site->uuid,
    ])->assertCreated()
        ->assertJsonPath('source_namespace', ManualSourceImport::SOURCE_NAMESPACE)
        ->assertJsonPath('source_site_key', 'Willow Park / 001')
        ->assertJsonPath('portal_site.uuid', $site->uuid);

    expect($response->json('binding_uuid'))->toBeString()
        ->and(SourceSiteBinding::query()->count())->toBe(1)
        ->and(SourceSiteBinding::query()->firstOrFail()->created_by_user_id)->toBe($office->id);

    $this->actingAs($office)->postJson('/portal/source-site-bindings', [
        'source_namespace' => ManualSourceImport::SOURCE_NAMESPACE,
        'source_site_key' => 'Willow Park / 001',
        'original_name' => 'Willow Park',
        'portal_site_uuid' => $site->uuid,
    ])->assertUnprocessable();
});

test('binding routes reject unauthenticated external inactive and preview users', function (): void {
    $site = manualImportSite();
    $payload = [
        'source_namespace' => ManualSourceImport::SOURCE_NAMESPACE,
        'source_site_key' => 'SITE-A',
        'original_name' => 'Site A',
        'portal_site_uuid' => $site->uuid,
    ];

    $this->postJson('/portal/source-site-bindings', $payload)->assertRedirect('/login');
    $this->actingAs(manualImportUser(PortalRoleIdentifier::SiteManager, $site->customerOrganisation))->postJson('/portal/source-site-bindings', $payload)->assertForbidden();
    $inactiveResponse = $this->actingAs(manualImportUser(PortalRoleIdentifier::FensterOfficeStaff, attributes: ['is_active' => false]))
        ->postJson('/portal/source-site-bindings', $payload);
    expect($inactiveResponse->getStatusCode())->toBe(302)
        ->and($inactiveResponse->headers->get('Location'))->toEndWith('/login');

    $previewResponse = $this->actingAs(manualImportUser(PortalRoleIdentifier::FensterOfficeStaff, attributes: ['is_preview_user' => true]))
        ->postJson('/portal/source-site-bindings', $payload);
    expect($previewResponse->getStatusCode())->toBe(403);

    expect(SourceSiteBinding::query()->count())->toBe(0);
});

test('a binding may be relabelled but cannot move imported projections to a different Portal site', function (): void {
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $firstSite = manualImportSite();
    $secondSite = manualImportSite();
    $binding = app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $firstSite);

    $this->actingAs($office)->putJson('/portal/source-site-bindings/'.$binding->uuid, [
        'portal_site_uuid' => $secondSite->uuid,
        'display_name' => 'Temporary corrected label',
    ])->assertOk();

    app(SourceProjectionImportService::class)->import(ManualSourceImport::SOURCE_NAMESPACE, [
        manualSourceRecord('CALL-1', 'SITE-A', 'P-001', 'PC1'),
    ], context: new SourceImportContext($office->id, representedSiteIdentifiers: ['SITE-A']));

    $this->actingAs($office)->putJson('/portal/source-site-bindings/'.$binding->uuid, [
        'portal_site_uuid' => $firstSite->uuid,
        'display_name' => 'Unsafe move',
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'BINDING_REJECTED');

    expect($binding->fresh()->site_id)->toBe($secondSite->id);
});

test('dry-run analysis is non-mutating and returns explicit blocking categories', function (): void {
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = manualImportSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $workbook = new XlsxSourceReadResult('Test', ['Test'], [], [
        manualXlsxRow(2, 'CALL-1', 'SITE-A', 'P-001', 'PC1', products: ['CAS' => 1]),
        manualXlsxRow(3, 'CALL-2', 'UNKNOWN-SITE', 'P-002', 'CC!'),
        manualXlsxRow(4, 'CALL-3', 'SITE-A', 'P-003', 'NOPE'),
        manualXlsxRow(5, 'CALL-4', 'SITE-A', 'P-004', 'CC1', errors: [[
            'code' => 'INVALID_PRODUCT_QUANTITY',
            'message' => 'BF must be non-negative.',
            'blocking' => true,
        ]]),
    ], 0);

    $analysis = app(ManualSourceImportAnalysisService::class)->analyse(ManualSourceImport::SOURCE_NAMESPACE, $workbook);
    $unmappedUnknown = collect($analysis->rows)->firstWhere('call_number', 'CALL-2');

    expect($analysis->summary['NEW'])->toBe(1)
        ->and($analysis->summary['SITE_MAPPING_REQUIRED'])->toBe(1)
        ->and($analysis->summary['UNKNOWN_CALL_TYPE'])->toBe(1)
        ->and($analysis->summary['INVALID'])->toBe(1)
        ->and(collect($unmappedUnknown['errors'])->pluck('code')->all())->toBe(['SITE_MAPPING_REQUIRED', 'UNKNOWN_CALL_TYPE'])
        ->and($analysis->blockingErrorCount)->toBe(4)
        ->and(SourceImportRun::query()->count())->toBe(0)
        ->and(ProjectedPlotService::query()->count())->toBe(0);
});

test('manual imports retain audit attribution and are idempotent through the existing importer', function (): void {
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = manualImportSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $context = new SourceImportContext($office->id, 'source.xlsx', str_repeat('a', 64), ['SITE-A']);
    $record = manualSourceRecord('CALL-1', 'SITE-A', 'P-001', 'PC1', ['CAS' => 2, 'PFD' => 0, 'BF' => 0]);

    $first = app(SourceProjectionImportService::class)->import(ManualSourceImport::SOURCE_NAMESPACE, [$record], 'v1', $context);
    $repeat = app(SourceProjectionImportService::class)->import(ManualSourceImport::SOURCE_NAMESPACE, [$record], 'v1', $context);

    $service = ProjectedPlotService::query()->where('source_call_number', 'CALL-1')->firstOrFail();
    expect($first->records_created)->toBe(1)
        ->and($repeat->records_unchanged)->toBe(1)
        ->and($repeat->initiated_by_user_id)->toBe($office->id)
        ->and($repeat->original_filename)->toBe('source.xlsx')
        ->and($repeat->content_sha256)->toBe(str_repeat('a', 64))
        ->and($repeat->source_scope)->toBe(['represented_site_keys' => ['SITE-A']])
        ->and($service->projectedPlot->site_id)->toBe($site->id)
        ->and($service->projectedPlot->sourceSiteBinding)->not->toBeNull()
        ->and($service->projectedPlot->products()->where('product_code', 'CAS')->value('quantity'))->toBe('2.000');
});

test('missing-source evaluation is restricted to represented mapped site keys', function (): void {
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $siteA = manualImportSite();
    $siteB = manualImportSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $siteA);
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-B', 'Site B', null, $siteB);
    $importer = app(SourceProjectionImportService::class);
    $importer->import(ManualSourceImport::SOURCE_NAMESPACE, [
        manualSourceRecord('CALL-A', 'SITE-A', 'P-001', 'PC1'),
        manualSourceRecord('CALL-B', 'SITE-B', 'P-002', 'PC1'),
    ], context: new SourceImportContext($office->id, representedSiteIdentifiers: ['SITE-A', 'SITE-B']));

    $run = $importer->import(
        ManualSourceImport::SOURCE_NAMESPACE,
        [],
        context: new SourceImportContext($office->id, representedSiteIdentifiers: ['SITE-A']),
    );

    expect($run->records_missing)->toBe(1)
        ->and(ProjectedPlotService::query()->where('source_call_number', 'CALL-A')->value('source_present'))->toBeFalsy()
        ->and(ProjectedPlotService::query()->where('source_call_number', 'CALL-B')->value('source_present'))->toBeTruthy();
});

test('the generic XLSX reader handles typed dates quantities blanks and ignores operational placeholder dates under a test-only contract', function (): void {
    configureMechanicalWorkbookContract();
    $path = mechanicalWorkbook([
        ['CALL-1', 'SITE-A', 'Site A', 'P-001', 'PC1', 'CA02', new DateTimeImmutable('2026-08-28'), new DateTimeImmutable('2026-09-15'), 2, 0, null],
        [null, null, null, null, null, null, null, null, null, null, null],
        ['CALL-2', 'SITE-A', 'Site A', 'P-002', 'CC!', null, null, null, 0, null, 1],
    ]);

    try {
        $result = app(XlsxSourceReader::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($result->worksheet)->toBe('Mechanical Test Only')
        ->and($result->blankRowCount)->toBe(1)
        ->and($result->rows)->toHaveCount(2)
        ->and($result->rows[0]->completedDate?->toDateString())->toBe('2026-08-28')
        ->and($result->rows[0]->products)->toBe(['CAS' => 2.0, 'PFD' => 0.0, 'BF' => 0.0]);
});

test('the XLSX reader rejects a renamed non-XLSX payload before parsing', function (): void {
    configureMechanicalWorkbookContract();
    $path = storage_path('framework/'.uniqid('not-xlsx-', true).'.xlsx');
    file_put_contents($path, 'not a zip workbook');

    try {
        expect(fn () => app(XlsxSourceReader::class)->read($path))->toThrow(InvalidSourceWorkbook::class);
    } finally {
        @unlink($path);
    }
});

test('the XLSX reader blocks invalid dates and quantities while normalising blank quantities to zero', function (): void {
    configureMechanicalWorkbookContract();
    $path = mechanicalWorkbook([
        ['CALL-1', 'SITE-A', 'Site A', 'P-001', 'PC1', null, '31/02/2026', null, 'not-a-number', null, -1],
    ]);

    try {
        $row = app(XlsxSourceReader::class)->read($path)->rows[0];
    } finally {
        @unlink($path);
    }

    expect(collect($row->errors)->pluck('code')->all())
        ->toContain('INVALID_DATE', 'INVALID_PRODUCT_QUANTITY')
        ->and($row->products['PFD'])->toBe(0.0);
});

test('upload preview is non-mutating and explicit commit reparses imports once and deletes the private file', function (): void {
    Storage::fake('local');
    configureMechanicalWorkbookContract();
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = manualImportSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = mechanicalWorkbook([
        ['CALL-1', 'SITE-A', 'Site A', 'P-001', 'PC1', null, null, 'Plot To Be Installed', 1, 0, null],
    ]);
    $upload = new UploadedFile($path, 'representative-test-only.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $previewResponse = $this->actingAs($office)
        ->postJson('/portal/source-imports/previews', ['workbook' => $upload])
        ->assertCreated()
        ->assertJsonPath('summary.NEW', 1)
        ->assertJsonPath('can_commit', true);
    @unlink($path);

    expect(SourceImportRun::query()->count())->toBe(0)
        ->and(ProjectedPlotService::query()->count())->toBe(0);

    $preview = ManualSourceImportPreview::query()->firstOrFail();
    Storage::disk('local')->assertExists($preview->storage_path);

    $commitResponse = $this->postJson('/portal/source-imports/previews/'.$preview->uuid.'/commit', [
        'confirm' => true,
        'content_sha256' => $previewResponse->json('metadata.sha256'),
    ])->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('counts.created', 1);

    $this->postJson('/portal/source-imports/previews/'.$preview->uuid.'/commit', [
        'confirm' => true,
        'content_sha256' => $previewResponse->json('metadata.sha256'),
    ])->assertOk()
        ->assertJsonPath('result_uuid', $commitResponse->json('result_uuid'));

    expect(SourceImportRun::query()->count())->toBe(1)
        ->and(ProjectedPlotService::query()->where('source_call_number', 'CALL-1')->count())->toBe(1);
    Storage::disk('local')->assertMissing($preview->storage_path);
});

test('commit rejects a stale preview after a relevant binding changes', function (): void {
    Storage::fake('local');
    configureMechanicalWorkbookContract();
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = manualImportSite();
    $replacementSite = manualImportSite();
    $binding = app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = mechanicalWorkbook([
        ['CALL-1', 'SITE-A', 'Site A', 'P-001', 'PC1', null, null, null, 1, 0, 0],
    ]);
    $upload = new UploadedFile($path, 'stale-test-only.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', ['workbook' => $upload])->assertCreated();
    @unlink($path);
    $preview = ManualSourceImportPreview::query()->firstOrFail();
    $binding->update(['site_id' => $replacementSite->id]);

    $this->postJson('/portal/source-imports/previews/'.$preview->uuid.'/commit', [
        'confirm' => true,
        'content_sha256' => $response->json('metadata.sha256'),
    ])->assertConflict()
        ->assertJsonPath('error', 'PREVIEW_NOT_COMMITTABLE');

    expect(SourceImportRun::query()->count())->toBe(0)
        ->and(ProjectedPlotService::query()->count())->toBe(0);
});

test('an unexpected commit failure rolls back projections and retains a safe failed audit', function (): void {
    Storage::fake('local');
    configureMechanicalWorkbookContract();
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = manualImportSite();
    app(SourceSiteBindingService::class)->create($office, ManualSourceImport::SOURCE_NAMESPACE, 'SITE-A', 'Site A', null, $site);
    $path = mechanicalWorkbook([
        ['CALL-1', 'SITE-A', 'Site A', 'P-001', 'PC1', null, null, null, 1, 0, 0],
    ]);
    $upload = new UploadedFile($path, 'failed-test-only.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $response = $this->actingAs($office)->postJson('/portal/source-imports/previews', ['workbook' => $upload])->assertCreated();
    @unlink($path);
    $preview = ManualSourceImportPreview::query()->firstOrFail();
    $importer = Mockery::mock(SourceProjectionImportService::class);
    $importer->shouldReceive('import')->once()->andThrow(new RuntimeException('Simulated importer failure.'));
    app()->instance(SourceProjectionImportService::class, $importer);

    $this->postJson('/portal/source-imports/previews/'.$preview->uuid.'/commit', [
        'confirm' => true,
        'content_sha256' => $response->json('metadata.sha256'),
    ])->assertServerError();

    $run = SourceImportRun::query()->sole();
    expect($run->status)->toBe('failed')
        ->and($run->safe_error_summary)->toBe('Manual source import did not complete. See application logs.')
        ->and($preview->fresh()->status)->toBe(ManualSourceImport::PREVIEW_STATUS_FAILED)
        ->and(ProjectedPlotService::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($preview->storage_path);
});

test('a preview is visible only to its initiating Office Staff user', function (): void {
    $owner = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $otherOffice = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    $preview = ManualSourceImportPreview::query()->create([
        'initiated_by_user_id' => $owner->id,
        'source_namespace' => ManualSourceImport::SOURCE_NAMESPACE,
        'original_filename' => 'private.xlsx',
        'content_sha256' => str_repeat('a', 64),
        'storage_disk' => 'local',
        'storage_path' => 'manual-source-imports/previews/private.xlsx',
        'workbook_contract_fingerprint' => str_repeat('b', 64),
        'source_fingerprint' => str_repeat('c', 64),
        'status' => ManualSourceImport::PREVIEW_STATUS_READY,
        'metadata' => [],
        'summary' => [],
        'rows' => [],
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($otherOffice)->getJson('/portal/source-imports/previews/'.$preview->uuid)->assertNotFound();
    $this->actingAs($owner)->getJson('/portal/source-imports/previews/'.$preview->uuid)->assertOk();
});

test('expired previews are made non-committable and their private workbooks are removed', function (): void {
    Storage::fake('local');
    $office = manualImportUser(PortalRoleIdentifier::FensterOfficeStaff);
    Storage::disk('local')->put('manual-source-imports/previews/expired.xlsx', 'temporary');
    $preview = ManualSourceImportPreview::query()->create([
        'initiated_by_user_id' => $office->id,
        'source_namespace' => ManualSourceImport::SOURCE_NAMESPACE,
        'original_filename' => 'expired.xlsx',
        'content_sha256' => str_repeat('a', 64),
        'storage_disk' => 'local',
        'storage_path' => 'manual-source-imports/previews/expired.xlsx',
        'workbook_contract_fingerprint' => str_repeat('b', 64),
        'source_fingerprint' => str_repeat('c', 64),
        'status' => ManualSourceImport::PREVIEW_STATUS_READY,
        'metadata' => [],
        'summary' => [],
        'rows' => [],
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(ManualSourceImportService::class)->pruneExpired())->toBe(1)
        ->and($preview->fresh()->status)->toBe(ManualSourceImport::PREVIEW_STATUS_EXPIRED);
    Storage::disk('local')->assertMissing($preview->storage_path);
});

function manualImportUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null, array $attributes = []): User
{
    return User::factory()->role($role)->create([
        'customer_organisation_id' => $role->isSiteRole() ? ($organisation ?? CustomerOrganisation::factory()->create())->id : null,
        ...$attributes,
    ]);
}

function manualImportSite(): Site
{
    return Site::factory()->create(['customer_organisation_id' => CustomerOrganisation::factory()->create()->id]);
}

function manualSourceRecord(string $callNumber, string $siteKey, string $plot, string $type, array $products = [], ?string $completedDate = null): SourceRecord
{
    return new SourceRecord(
        $callNumber,
        $siteKey,
        $plot,
        $type,
        null,
        $completedDate === null ? null : CarbonImmutable::parse($completedDate),
        $products,
        CarbonImmutable::parse('2026-08-28 10:00:00'),
    );
}

function manualXlsxRow(int $row, string $callNumber, string $siteKey, string $plot, string $type, array $products = [], array $errors = []): XlsxSourceRow
{
    return new XlsxSourceRow($row, $callNumber, $siteKey, $siteKey, $plot, $type, null, null, $products, null, $errors);
}

function configureMechanicalWorkbookContract(): void
{
    config()->set('manual_source_import.workbook', [
        'worksheet' => 'Mechanical Test Only',
        'header_row' => 1,
        'date_system' => '1900',
        'headers' => [
            'call_number' => 'Call No.',
            'source_site_key' => 'TEST Source Site Key',
            'source_site_name' => 'TEST Source Site Name',
            'plot_reference' => 'TEST Plot Reference',
            'call_type' => 'Call Type',
            'job_stage' => 'Job Stage',
            'completed_date' => 'Completed Date',
            'plot_to_be_installed' => 'Plot To Be Installed',
            'source_updated_at' => null,
        ],
        'product_headers' => ['CAS', 'PFD', 'BF'],
    ]);
}

/** @param list<list<mixed>> $rows */
function mechanicalWorkbook(array $rows): string
{
    $path = storage_path('framework/'.uniqid('mechanical-source-', true).'.xlsx');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Mechanical Test Only');
    $writer->addRow(Row::fromValues([
        'Call No.', 'TEST Source Site Key', 'TEST Source Site Name', 'TEST Plot Reference',
        'Call Type', 'Job Stage', 'Completed Date', 'Plot To Be Installed', 'CAS', 'PFD', 'BF',
    ]));
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return $path;
}
