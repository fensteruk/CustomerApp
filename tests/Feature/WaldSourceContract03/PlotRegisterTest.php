<?php

use App\Actions\CallOff\BuildCallOffMatrixAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffLeadTimeService;
use App\Services\CallOffSubmissionWorkflow;
use App\SourceImport\Integration\CustomerCodeReviewWorkflow;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\UnknownRowsWorkflow;
use App\SourceImport\Integration\WaldDryRun;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\View\Presenters\ImportReviewPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Wald04Fixtures;
use Tests\Support\Wald05BackendFixtures;
use Tests\Support\WaldCustapp2Fixtures;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);

function registerWorkbook(array $records, bool $callColumns = true): UploadedFile
{
    $headers = $callColumns ? ['CustomerCode', 'Plot Ref', 'Call No.', 'Call Type', 'complete', 'VS', 'BF'] : ['CustomerCode', 'Plot Ref', 'VS', 'BF'];
    $rows = [1 => array_combine(range(1, count($headers)), $headers)];
    foreach ($records as $offset => $record) {
        $value = ['CustomerCode' => 'REGISTER', 'Plot Ref' => 'Fictional - Register Site - Plot '.($offset + 1),
            'Call No.' => '', 'Call Type' => '', 'complete' => 'Yes', 'VS' => '2', 'BF' => '0', ...$record];
        $rows[$offset + 2] = array_combine(range(1, count($headers)), array_map(fn ($header) => $value[$header], $headers));
    }

    return new UploadedFile(WaldFixtures::xlsx([['name' => 'Register', 'rows' => $rows]]), 'fictional-register.xlsx', null, null, true);
}

function alternateRegisterWorkbook(array $headers): UploadedFile
{
    $values = ['Call' => '', 'Call Type' => '', 'Site Name' => 'Fictional - Register Site - Plot 033',
        'Customer Name' => 'Private description', 'Arrival' => 46000, 'Complete Date' => 46001, 'Customer' => 'REGISTER'];
    $rows = [1 => array_combine(range(1, count($headers)), $headers)];
    for ($i = 2; $i <= 4; $i++) {
        $values['Site Name'] = 'Fictional - Register Site - Plot '.($i === 2 ? '033' : (string) $i);
        $rows[$i] = array_combine(range(1, count($headers)), array_map(fn ($header) => $values[$header], $headers));
    }

    return new UploadedFile(WaldFixtures::xlsx([['name' => 'Alternate Register', 'rows' => $rows]]), 'fictional-alternate.xlsx', null, null, true);
}

function registerPreview(User $office, UploadedFile $book, Site $site, string $slot = 'MORNING'): array
{
    $workflow = new PilotImportWorkflow;
    $upload = $workflow->upload($office, $book, new ExportOrder('2026-10-06', $slot), ExportOrder::CONFIRMATION, (string) Str::uuid());
    expect($upload['state'])->not->toBe('FAILED');
    if ($upload['state'] === 'NEEDS_CLARIFICATION') {
        $upload = $workflow->confirmStructure($office, $upload['upload'], 'CONFIRM DETECTED HEADER AND SITE LIST', (string) Str::uuid());
    }

    return registerExistingPreview($office, $upload, $site);
}

function registerExistingPreview(User $office, array $upload, Site $site, ?string $sourceHash = null): array
{
    $workflow = new PilotImportWorkflow;
    $selected = $workflow->select($office, $upload['upload'], $sourceHash ?? $upload['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
    try {
        (new ImportAnalysis)->analyse($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    } catch (ImportConflict $exception) {
        if ($exception->getMessage() !== 'structural_clarification_required') {
            throw $exception;
        }
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        $context = KnowledgeContext::query()->findOrFail($run->context_id);
        foreach ((new KnowledgeQueries)->questions($office, $selected['scope'], $context->uuid) as $question) {
            if ($question['state'] === 'OPEN' && $question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                (new AnswerClarification)->handle($office, $selected['scope'], $context->uuid, $question['uuid'], $question['sequence'], $question['evidence']['candidates'][0]['id'], 'Reviewed fictional register header.', (string) Str::uuid());
            }
        }
        (new ImportAnalysis)->analyse($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    }
    $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
    $preview = (new ImportReview)->preview($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());

    return [$selected['scope'], $preview, $upload];
}

function registerApply(User $office, array $prepared): array
{
    [$scope, $preview] = $prepared;
    expect($preview['blockers'])->toBe([]);
    $review = new ImportReview;
    $review->approve($office, $scope, $preview['run'], $preview['preview'], $preview['hash'], (string) Str::uuid());

    return $review->commit($office, $scope, $preview['run'], $preview['preview'], $preview['hash'], (string) Str::uuid());
}

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Fictional']);
    $this->site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Register Site']);
});

it('discovers and applies repeated base evidence with blank or absent call columns without visits', function (bool $columns): void {
    $records = [['Plot Ref' => 'Fictional - Register Site - Plot 033'], ['Plot Ref' => 'Fictional - Register Site - Plot 033'], ['Plot Ref' => 'Fictional - Register Site - Plot Com 4']];
    $report = (new WaldDryRun)->analyse($this->office, registerWorkbook($records, $columns));
    expect($report['content']['summary'])->toMatchArray(['base_plot_rows' => 3, 'recognised_calloff_rows' => 0,
        'plots_discovered' => 2, 'plots_without_source_calloff_in_export' => 2, 'blocking_units' => 0]);
    expect(DB::table('projected_plots')->count())->toBe(0);
    $prepared = registerPreview($this->office, registerWorkbook($records, $columns), $this->site);
    if ($columns) {
        $context = DB::table('wald_import_runs')->where('uuid', $prepared[1]['run'])->value('context_id');
        $questionIds = DB::table('wald_clarifications')->where('context_id', $context)
            ->whereIn('question_key', ['structure:call_reference', 'structure:call_type'])->pluck('id');
        expect(DB::table('wald_clarification_answers')->whereIn('clarification_id', $questionIds)
            ->pluck('decision')->all())->toBe(['AUTO_SELECTED', 'AUTO_SELECTED']);
    }
    $rows = (new ImportReview)->details($this->office, $prepared[0], $prepared[1]['run']);
    expect($rows)->toHaveCount(3)->and(array_column(array_column($rows, 'facts'), 'call_number'))->toBe([null, null, null]);
    $receipt = registerApply($this->office, $prepared);
    expect(array_column(array_column($receipt['effects'], 'register_evidence'), 'row'))->toBe([2, 3, 4]);
    expect($receipt['plot_counts'])->toBe(['created' => 2, 'reused' => 0])
        ->and(DB::table('wald_source_rows')->count())->toBe(0)
        ->and(DB::table('wald_source_visits')->count())->toBe(0)
        ->and(DB::table('projected_plot_services')->count())->toBe(0)
        ->and(DB::table('call_off_requests')->count())->toBe(0)
        ->and(DB::table('projected_plot_products')->count())->toBe(4)
        ->and(DB::table('projected_plots')->whereNotNull('plot_register_established_at')->count())->toBe(2);
})->with([true, false]);

it('handles a representative mixed register and retains one plot with independent services', function (): void {
    $records = [];
    for ($i = 1; $i <= 20; $i++) {
        $records[] = ['Plot Ref' => 'Fictional - Register Site - Plot '.$i];
    }
    $records[] = ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '3101', 'Call Type' => 'PC1', 'complete' => 'No'];
    $records[] = ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '3102', 'Call Type' => 'CC1', 'complete' => 'No'];
    $records[] = ['Plot Ref' => 'Fictional - Register Site - Plot Com 4', 'Call No.' => '3103', 'Call Type' => 'CM1', 'complete' => 'No'];
    $report = (new WaldDryRun)->analyse($this->office, registerWorkbook($records));
    expect($report['content']['summary'])->toMatchArray(['base_plot_rows' => 20, 'recognised_calloff_rows' => 3, 'plots_discovered' => 21,
        'plots_without_source_calloff_in_export' => 19, 'blocking_units' => 0]);
    $receipt = registerApply($this->office, registerPreview($this->office, registerWorkbook($records), $this->site));
    expect($receipt['plot_counts']['created'])->toBe(21)->and(DB::table('wald_source_visits')->count())->toBe(3)
        ->and(DB::table('projected_plot_services')->count())->toBe(3)->and(DB::table('projected_plots')->where('plot_reference', '1')->count())->toBe(1);
});

it('keeps unknown nonblank types and recognised calls without references blocked', function (array $row, string $issue): void {
    $report = (new WaldDryRun)->analyse($this->office, registerWorkbook([$row]));
    expect($report['content']['summary']['blocking_units'])->toBe(1)
        ->and($report['content']['units'][0]['issues'])->toContain($issue);
    expect($report['content']['summary']['malformed_groups'])->toBe(0)
        ->and($report['content']['summary']['plots_discovered'])->toBe(1);
})->with([[['Call Type' => 'ABC'], 'UNKNOWN_CALL_TYPE'], [['Call Type' => 'PC1'], 'MISSING_CALLOFF_REFERENCE']]);

it('keeps all approved exclusion rows outside plot creation', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, registerWorkbook([
        ['Call Type' => 'CU4', 'Plot Ref' => 'Fictional - Register Site - Plot 10'],
        ['CustomerCode' => 'XXTEST', 'Plot Ref' => 'Fictional - Register Site - Plot 11'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 12'],
    ]));
    expect($report['content']['summary'])->toMatchArray(['excluded' => 2, 'included' => 1, 'plots_discovered' => 1, 'base_plot_rows' => 1]);
});

it('allows the first Windows request then reuses that plot and preserves Portal dates on later source import', function (): void {
    registerApply($this->office, registerPreview($this->office, registerWorkbook([['Plot Ref' => 'Fictional - Register Site - Plot 776']]), $this->site));
    $plot = ProjectedPlot::query()->firstOrFail();
    $manager = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $this->site->customer_organisation_id]);
    $manager->assignedSites()->attach($this->site);
    $date = now()->addWeeks(6)->next('Monday')->toDateString();
    $matrix = app(BuildCallOffMatrixAction::class)->handle($manager, $this->site, [$plot->uuid], [CallOffServiceType::Windows->value => $date]);
    expect($matrix[0]['available'])->toBeTrue()->and($matrix[0]['plot_service_id'])->toBeNull();
    $this->actingAs($manager)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $this->site->id]);
    $this->get(route('portal.plots.show', $plot))->assertOk()->assertSee('Not Called Off');
    $this->post(route('portal.call-offs.review'), ['plots' => [$plot->uuid], 'service_dates' => ['windows' => $date]])->assertOk();
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];
    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])->assertRedirect();
    $request = CallOffRequest::query()->firstOrFail();
    expect($request->projectedPlotService->source_present)->toBeFalse();
    $history = $request->histories()->count();
    $this->actingAs($this->office)->get(route('portal.review-requests.show', $request))->assertOk()->assertSee($plot->plot_reference);
    $this->post(route('portal.review-requests.agree-requested-date', $request))->assertRedirect();
    expect($request->fresh()->agreed_date->toDateString())->toBe($date);
    $history = $request->histories()->count();
    registerApply($this->office, registerPreview($this->office, registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 776', 'Call No.' => '3201', 'Call Type' => 'PC1', 'complete' => 'No'],
    ]), $this->site, 'AFTERNOON'));
    expect(ProjectedPlot::query()->count())->toBe(1)->and($request->fresh()->projected_plot_id)->toBe($plot->id)
        ->and($request->fresh()->requested_date->toDateString())->toBe($date)
        ->and($request->fresh()->agreed_date->toDateString())->toBe($date)
        ->and($request->histories()->count())->toBe($history)->and(DB::table('wald_source_visits')->count())->toBe(1);
    $matrix = app(BuildCallOffMatrixAction::class)->handle($manager, $this->site, [$plot->uuid], ['windows' => $date]);
    expect($matrix[0]['available'])->toBeFalse();
    $otherCustomer = CustomerOrganisation::factory()->create();
    $otherSite = Site::factory()->create(['customer_organisation_id' => $otherCustomer->id]);
    $other = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $otherCustomer->id]);
    $other->assignedSites()->attach($otherSite);
    $this->actingAs($other)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $otherSite->id])->get(route('portal.plots.show', $plot))->assertNotFound();
});

it('supports each controlled alternate profile with absent call columns and evidence-only dates', function (array $headers): void {
    $report = (new WaldDryRun)->analyse($this->office, alternateRegisterWorkbook($headers));
    expect($report['content']['summary'])->toMatchArray(['base_plot_rows' => 3, 'plots_discovered' => 3, 'blocking_units' => 0]);
    $prepared = registerPreview($this->office, alternateRegisterWorkbook($headers), $this->site);
    $rows = (new ImportReview)->details($this->office, $prepared[0], $prepared[1]['run']);
    foreach ($rows as $row) {
        expect($row['facts']['complete'])->toBeNull()->and($row['facts']['call_number'])->toBeNull()
            ->and($row['facts']['service'])->toBeNull()->and($row['provenance']['unmapped_private_evidence'])->not->toBeEmpty();
    }
    registerApply($this->office, $prepared);
    expect(DB::table('projected_plot_services')->count())->toBe(0);
})->with([
    [['Call', 'Call Type', 'Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer']],
    [['Call Type', 'Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer']],
    [['Call', 'Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer']],
    [['Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer']],
]);

it('supports a register with only the two approved hierarchy fields', function (): void {
    $rows = [1 => [1 => 'CustomerCode', 2 => 'Plot Ref'], 2 => [1 => 'REGISTER', 2 => 'Fictional - Register Site - Plot 033'],
        3 => [1 => 'REGISTER', 2 => 'Fictional - Register Site - Plot Com 4']];
    $file = new UploadedFile(WaldFixtures::xlsx([['rows' => $rows]]), 'fictional-two-fields.xlsx', null, null, true);
    $report = (new WaldDryRun)->analyse($this->office, $file);
    expect($report['content']['summary'])->toMatchArray(['base_plot_rows' => 2, 'plots_discovered' => 2, 'blocking_units' => 0]);
    registerApply($this->office, registerPreview($this->office, $file, $this->site));
    expect(DB::table('projected_plots')->count())->toBe(2)->and(DB::table('projected_plot_products')->count())->toBe(0);
});

it('reuses an existing plot and keeps completed visits intact when a blank-call base row arrives later', function (): void {
    registerApply($this->office, registerPreview($this->office, registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '3301', 'Call Type' => 'PC1', 'complete' => 'Yes'],
    ]), $this->site));
    $plotId = ProjectedPlot::query()->firstOrFail()->id;
    $completed = DB::table('projected_plot_services')->first()->source_completion_observed_at;
    registerApply($this->office, registerPreview($this->office, registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'VS' => ''],
    ], false), $this->site, 'AFTERNOON'));
    expect(ProjectedPlot::query()->sole()->id)->toBe($plotId)
        ->and(DB::table('projected_plot_services')->first()->source_completion_observed_at)->toBe($completed)
        ->and(DB::table('wald_source_rows')->count())->toBe(1)->and(DB::table('wald_source_visits')->count())->toBe(1)
        ->and(DB::table('projected_plot_products')->where('product_code', 'VS')->first()->quantity)->toEqual(2);
});

it('retains product conflicts and stale preview checks for base-only Apply', function (): void {
    $conflict = registerPreview($this->office, registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'VS' => '2'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'VS' => '3'],
    ]), $this->site);
    expect($conflict[1]['blockers'])->toContain('BLOCKED_STAGED_RECORDS');
    expect(DB::table('projected_plots')->count())->toBe(0);
    // A fresh later slot remains independently reviewable.
    $prepared = registerPreview($this->office, registerWorkbook([['Plot Ref' => 'Fictional - Register Site - Plot 2']]), $this->site, 'AFTERNOON');
    [$scope, $preview] = $prepared;
    $review = new ImportReview;
    $review->approve($this->office, $scope, $preview['run'], $preview['preview'], $preview['hash'], (string) Str::uuid());
    $plot = ProjectedPlot::factory()->create(['site_id' => $this->site->id, 'plot_reference' => '2']);
    expect(fn () => $review->commit($this->office, $scope, $preview['run'], $preview['preview'], $preview['hash'], (string) Str::uuid()))->toThrow(ImportConflict::class);
    expect($plot->fresh()->plot_register_established_at)->toBeNull()
        ->and(DB::table('wald_import_receipts')->count())->toBe(0)->and(DB::table('projected_plot_services')->count())->toBe(0);
});

it('keeps blank rows in CustomerCode review and routes unknown nonblank meanings to Unknown', function (): void {
    $workflow = new PilotImportWorkflow;
    $upload = $workflow->upload($this->office, registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 2', 'Call Type' => 'ABC'],
    ]), new ExportOrder('2026-10-06', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $record = DB::table('wald_pilot_uploads')->where('uuid', $upload['upload'])->firstOrFail();
    $review = new CustomerCodeReviewWorkflow;
    expect($review->overview($this->office, $upload['upload'])['pending'])->toHaveCount(1);
    $review->confirm($this->office, $upload['upload'], $upload['sources'][0]['hash'], $this->site->customerOrganisation->uuid,
        $this->site->uuid, '', $record->source_manifest_hash, (int) $record->epoch, (string) Str::uuid());
    $rows = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $record->id)->orderBy('row_number')->get();
    expect($rows[0]->disposition)->toBe('CONFIRMED')->and($rows[0]->call_no)->toBe('')
        ->and($rows[1]->disposition)->toBe('UNKNOWN')->and($rows[1]->issue)->toBe('UNKNOWN_CALL_TYPE');
    $record = DB::table('wald_pilot_uploads')->where('id', $record->id)->firstOrFail();
    expect(fn () => (new UnknownRowsWorkflow)->resolveCode($this->office, $upload['upload'], 'REGISTER', $this->site->customerOrganisation->uuid,
        $this->site->uuid, $record->source_manifest_hash, (int) $record->epoch, (string) Str::uuid()))->toThrow(ImportConflict::class, 'unknown_call_type_requires_dictionary_review');
    expect(DB::table('projected_plots')->count())->toBe(0);
});

it('shows all four register services and enforces the normal product lead time before first submission', function (): void {
    registerApply($this->office, registerPreview($this->office, registerWorkbook([['BF' => '1']]), $this->site));
    $plot = ProjectedPlot::query()->sole();
    $manager = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $this->site->customer_organisation_id]);
    $manager->assignedSites()->attach($this->site);
    $dates = array_fill_keys(array_map(fn ($type) => $type->value, CallOffServiceType::cases()), now()->addWeeks(6)->next('Monday')->toDateString());
    $matrix = app(BuildCallOffMatrixAction::class)->handle($manager, $this->site, [$plot->uuid], $dates);
    expect(array_column($matrix, 'available'))->toBe([true, true, true, true]);
    $windows = collect($matrix)->firstWhere('service', 'windows');
    expect($windows['normal_earliest_date'])->toBe(app(CallOffLeadTimeService::class)->earliestPlotDate($plot)->toDateString());
    expect(fn () => app(BuildCallOffMatrixAction::class)->handle($manager, $this->site, [$plot->uuid], ['windows' => now()->addDay()->toDateString()]))->toThrow(ValidationException::class);
});

it('refuses a competing controlled header pair instead of assuming an all-text register', function (): void {
    $rows = [1 => [1 => 'CustomerCode', 2 => 'Plot Ref'], 2 => [1 => 'REGISTER', 2 => 'Fictional - Register Site - Plot 1'],
        3 => [1 => 'CustomerCode', 2 => 'Plot Ref'], 4 => [1 => 'REGISTER', 2 => 'Fictional - Register Site - Plot 2']];
    $file = new UploadedFile(WaldFixtures::xlsx([['rows' => $rows]]), 'fictional-competing-headers.xlsx', null, null, true);
    expect(fn () => (new WaldDryRun)->analyse($this->office, $file))->toThrow(ImportConflict::class);
    expect(DB::table('projected_plots')->count())->toBe(0);
});

it('keeps malformed two-field registers in hierarchy review and never falls back to a flat plot', function (): void {
    $rows = [1 => [1 => 'CustomerCode', 2 => 'Plot Ref'], 2 => [1 => 'REGISTER', 2 => 'Missing hierarchy'],
        3 => [1 => 'REGISTER', 2 => 'Still missing']];
    $file = new UploadedFile(WaldFixtures::xlsx([['rows' => $rows]]), 'fictional-malformed-register.xlsx', null, null, true);
    $report = (new WaldDryRun)->analyse($this->office, $file);
    expect($report['content']['summary'])->toMatchArray(['malformed_groups' => 1, 'blocking_units' => 1, 'plots_discovered' => 0]);
});

it('creates customer and site proposals from base rows without inventing call-off evidence', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, registerWorkbook([
        ['CustomerCode' => 'NEW-REGISTER', 'Plot Ref' => 'New Fictional Customer - New Site - Plot 1'],
    ], false));
    expect($report['content']['summary'])->toMatchArray(['new_customer_site_proposals' => 1, 'base_plot_rows' => 1,
        'plots_create' => 1, 'recognised_calloff_rows' => 0, 'blocking_units' => 0]);
    expect(DB::table('customer_organisations')->count())->toBe(1)->and(DB::table('sites')->count())->toBe(1)
        ->and(DB::table('projected_plots')->count())->toBe(0);
});

it('does not let quantity columns hide a malformed canonical register hierarchy', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, registerWorkbook([
        ['Plot Ref' => 'Missing hierarchy'], ['Plot Ref' => 'Still missing'],
    ], false));
    expect($report['content']['summary'])->toMatchArray(['malformed_groups' => 1, 'blocking_units' => 1, 'plots_discovered' => 0]);
});

it('does not describe a blocked unknown type as a base row in the selected-site summary', function (): void {
    config(['wald_import.enabled' => true]);
    [$actor, $scope] = Wald04Fixtures::owner();
    Wald05BackendFixtures::binding($actor, $scope);
    $run = WaldCustapp2Fixtures::staged($actor, $scope, [
        ['call' => '3901', 'plot' => '1', 'type' => 'ABC', 'complete' => 'No', 'products' => ['VS' => '2']],
    ]);
    $rows = (new ImportReview)->details($actor, $scope, $run['run']);
    expect(ImportReviewPresentation::stage($rows))->toMatchArray([
        'base_plot_rows' => 0, 'recognised_calloff_rows' => 0, 'unknown_call_type_rows' => 1, 'blocked' => 1,
    ]);
});

it('retains independent base plot discovery when repeated call evidence requires review', function (): void {
    $book = registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 2', 'Call No.' => '3991', 'Call Type' => 'PC1', 'complete' => 'No'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 2', 'Call No.' => '3991', 'Call Type' => 'PC1', 'complete' => 'Yes'],
    ]);
    $report = (new WaldDryRun)->analyse($this->office, $book);
    expect($report['content']['summary'])->toMatchArray(['base_plot_rows' => 1, 'plots_discovered' => 2, 'blocking_units' => 1])
        ->and($report['content']['apply_approval'])->toBeFalse();
    expect(DB::table('projected_plots')->count())->toBe(0)
        ->and(DB::table('wald_source_visits')->count())->toBe(0)
        ->and(DB::table('call_off_requests')->count())->toBe(0)
        ->and(DB::table('wald_import_receipts')->count())->toBe(0);
});

it('applies safe inventory and safe calls on a site while withholding every repeated call observation', function (): void {
    $book = registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 2', 'Call No.' => '4101', 'Call Type' => 'PC1', 'complete' => 'No'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 3', 'Call No.' => '4102', 'Call Type' => 'PC1', 'complete' => 'No'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 3', 'Call No.' => '4102', 'Call Type' => 'PC1', 'complete' => 'Yes'],
    ]);
    $report = (new WaldDryRun)->analyse($this->office, $book);
    expect($report['content']['summary'])->toMatchArray(['plots_discovered' => 3, 'repeated_call_groups' => 1, 'call_blocker_rows' => 2]);
    $prepared = registerPreview($this->office, $book, $this->site);
    $preview = DB::table('wald_import_previews')->where('uuid', $prepared[1]['preview'])->firstOrFail();
    expect(json_decode($preview->payload, true)['withheld_facts'])->toHaveCount(2);
    $receipt = registerApply($this->office, $prepared);
    expect($receipt['effects'])->toHaveCount(4)->and(ProjectedPlot::query()->count())->toBe(3)
        ->and(DB::table('wald_source_rows')->pluck('call_number')->all())->toBe(['4101'])
        ->and(DB::table('wald_source_visits')->pluck('call_number')->all())->toBe(['4101'])
        ->and(DB::table('projected_plot_services')->count())->toBe(1)
        ->and(DB::table('call_off_requests')->count())->toBe(0);
    $this->actingAs($this->office)->get(route('office.workspace.pilot-import.show', $prepared[2]['upload']))
        ->assertOk()->assertSee('some facts are withheld')->assertSee('4102');
});

it('keeps a base plot and independent products when its own call evidence and quantity disagree', function (): void {
    $book = registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 776', 'VS' => '2', 'BF' => '1'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 776', 'Call No.' => '4201', 'Call Type' => 'PC1', 'VS' => '3', 'BF' => '1', 'complete' => 'No'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 776', 'Call No.' => '4201', 'Call Type' => 'PC1', 'VS' => '4', 'BF' => '1', 'complete' => 'Yes'],
    ]);
    $prepared = registerPreview($this->office, $book, $this->site);
    registerApply($this->office, $prepared);
    expect(ProjectedPlot::query()->count())->toBe(1)->and(DB::table('wald_source_visits')->count())->toBe(0)
        ->and(DB::table('wald_source_rows')->count())->toBe(0)
        ->and(DB::table('projected_plot_products')->pluck('product_code')->all())->toBe(['BF']);
});

it('retains global repeated-call evidence after Office unticks one observation during structural review', function (): void {
    $workflow = new PilotImportWorkflow;
    $book = registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4301', 'Call Type' => 'PC1', 'complete' => 'No'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4301', 'Call Type' => 'PC1', 'complete' => 'Yes'],
    ]);
    $upload = $workflow->upload($this->office, $book, new ExportOrder('2026-10-06', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $record = DB::table('wald_pilot_uploads')->where('uuid', $upload['upload'])->firstOrFail();
    (new CustomerCodeReviewWorkflow)->confirm($this->office, $upload['upload'], $upload['sources'][0]['hash'],
        $this->site->customerOrganisation->uuid, $this->site->uuid, '3', $record->source_manifest_hash, (int) $record->epoch, (string) Str::uuid());
    $prepared = registerExistingPreview($this->office, $workflow->summary($this->office, $upload['upload']), $this->site);
    $receipt = registerApply($this->office, $prepared);
    expect($receipt['effects'])->toHaveCount(1)->and($receipt['effects'][0]['withheld_call'][0]['row_count'])->toBe(2)
        ->and(DB::table('wald_source_visits')->count())->toBe(0);
});

it('isolates a multi-site workbook and applies safe inventory on each site', function (): void {
    $sites = ['A' => $this->site,
        'B' => Site::factory()->create(['customer_organisation_id' => $this->site->customer_organisation_id, 'name' => 'Site B']),
        'C' => Site::factory()->create(['customer_organisation_id' => $this->site->customer_organisation_id, 'name' => 'Site C'])];
    $book = registerWorkbook([
        ['CustomerCode' => 'A', 'Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4401', 'Call Type' => 'PC1', 'complete' => 'No'],
        ['CustomerCode' => 'B', 'Plot Ref' => 'Fictional - Site B - Plot 1', 'Call No.' => '4402', 'Call Type' => 'PC1', 'complete' => 'No'],
        ['CustomerCode' => 'B', 'Plot Ref' => 'Fictional - Site B - Plot 1', 'Call No.' => '4402', 'Call Type' => 'PC1', 'complete' => 'Yes'],
        ['CustomerCode' => 'C', 'Plot Ref' => 'Fictional - Site C - Plot 1'],
    ]);
    $report = (new WaldDryRun)->analyse($this->office, $book);
    expect($report['content']['summary'])->toMatchArray(['source_units' => 3, 'plots_discovered' => 3, 'blocking_units' => 1]);
    $workflow = new PilotImportWorkflow;
    $upload = $workflow->upload($this->office, $book, new ExportOrder('2026-10-06', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    foreach ($upload['sources'] as $source) {
        registerApply($this->office, registerExistingPreview($this->office, $workflow->summary($this->office, $upload['upload']), $sites[$source['identity']], $source['hash']));
    }
    expect(ProjectedPlot::query()->where('plot_reference', '1')->count())->toBe(3)
        ->and(DB::table('wald_source_visits')->pluck('call_number')->all())->toBe(['4401'])
        ->and(DB::table('wald_import_receipts')->count())->toBe(3);
});

it('withholds a cross-CustomerCode repeated reference on every site', function (): void {
    $siteB = Site::factory()->create(['customer_organisation_id' => $this->site->customer_organisation_id, 'name' => 'Site B']);
    $book = registerWorkbook([
        ['CustomerCode' => 'A', 'Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4501', 'Call Type' => 'PC1'],
        ['CustomerCode' => 'B', 'Plot Ref' => 'Fictional - Site B - Plot 2', 'Call No.' => '4501', 'Call Type' => 'CC1'],
    ]);
    $workflow = new PilotImportWorkflow;
    $upload = $workflow->upload($this->office, $book, new ExportOrder('2026-10-06', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    foreach ($upload['sources'] as $source) {
        registerApply($this->office, registerExistingPreview($this->office, $workflow->summary($this->office, $upload['upload']), $source['identity'] === 'A' ? $this->site : $siteB, $source['hash']));
    }
    expect(ProjectedPlot::query()->count())->toBe(2)->and(DB::table('wald_source_visits')->count())->toBe(0)
        ->and(DB::table('wald_source_rows')->count())->toBe(0);
});

it('preserves established completion and quantity when duplicate evidence is withheld', function (): void {
    registerApply($this->office, registerPreview($this->office, registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4601', 'Call Type' => 'PC1', 'VS' => '7', 'complete' => 'Yes'],
    ]), $this->site));
    $service = DB::table('projected_plot_services')->first();
    $book = registerWorkbook([
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4601', 'Call Type' => 'PC1', 'VS' => '2', 'complete' => 'No'],
        ['Plot Ref' => 'Fictional - Register Site - Plot 1', 'Call No.' => '4601', 'Call Type' => 'PC1', 'VS' => '3', 'complete' => 'Yes'],
    ]);
    registerApply($this->office, registerPreview($this->office, $book, $this->site, 'AFTERNOON'));
    expect(DB::table('projected_plot_services')->first()->source_completion_observed_at)->toBe($service->source_completion_observed_at)
        ->and(DB::table('projected_plot_products')->where('product_code', 'VS')->value('quantity'))->toEqual(7)
        ->and(DB::table('wald_visit_observations')->count())->toBe(1)
        ->and(DB::table('wald_source_row_observations')->count())->toBe(1);
});
