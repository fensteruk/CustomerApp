<?php

use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\Reconciliation\MasterReconciliationWorkspace;
use App\Services\Reconciliation\SourceConfirmationContract;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['is_preview_user' => false, 'customer_organisation_id' => null]);
});

function reconBind(User $office, string $code, ?Site $site = null): Site
{
    $site ??= Site::factory()->create(['name' => 'Willow Park '.$code]);
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, 'CUSTOMER_CODE', $code, 'Synthetic exact binding.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed synthetic binding.', (string) Str::uuid());

    return $site;
}

function reconUpload(User $office, array $codes = ['FNA001'], array $overrides = []): string
{
    $manifest = ['schema' => 'customerapp.wald-pilot-discovery.v3', 'record_count' => count($codes), 'included_count' => count($codes), 'excluded_count' => 0,
        'sources' => array_map(fn ($code) => ['kind' => 'CUSTOMER_CODE', 'identity' => $code, 'site_name' => 'Descriptive name only', 'rows' => 1], $codes)];
    $stream = DB::table('wald_import_streams')->insertGetId(['identity_hash' => hash('sha256', (string) Str::uuid()), 'source_namespace' => 'redzebra', 'workbook_family' => 'test']);
    $uuid = (string) Str::uuid();
    DB::table('wald_pilot_uploads')->insert(['uuid' => $uuid, 'stream_id' => $stream, 'uploader_id' => $office->id, 'uploader_name' => 'Office Reviewer',
        'storage_key' => $uuid.'.csv', 'original_name' => 'PRIVATE-FILENAME.csv', 'format' => 'csv', 'mime' => 'text/csv', 'byte_count' => 100,
        'workbook_hash' => hash('sha256', $uuid), 'export_date' => '2026-09-23', 'export_slot' => 'MORNING', 'export_order' => '20260923AM',
        'confirmation' => ExportOrder::CONFIRMATION, 'state' => 'READY', 'source_manifest' => Canonical::json($manifest), 'source_manifest_hash' => Canonical::hash($manifest),
        'workbook_retain_until' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now(), ...$overrides]);

    return $uuid;
}

function reconRequest(Site $site, string $plot = '591', string $service = 'windows'): CallOffRequest
{
    $projected = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $plot]);
    $projectedService = ProjectedPlotService::create(['projected_plot_id' => $projected->id, 'service_identifier' => $service]);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'service_identifier' => $service, 'requested_date' => '2026-10-01']);

    return CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $projected->id,
        'projected_plot_service_id' => $projectedService->id, 'service_identifier' => $service, 'status' => 'date_agreed', 'requested_date' => '2026-10-01', 'agreed_date' => '2026-10-01']);
}

function reconAmend(CallOffRequest $request, string $date = '2026-10-05', string $status = 'open'): CallOffDateNegotiation
{
    return CallOffDateNegotiation::create(['call_off_request_id' => $request->id, 'purpose' => 'amendment', 'status' => $status,
        'requested_date' => $date, 'prior_agreed_date' => '2026-10-01', 'requester_name' => 'Alex Example', 'opened_at' => '2026-09-23 07:30:00']);
}

function reconUrl(string $uuid, array $query = []): string
{
    return route('office.workspace.pilot-import.reconciliation', ['upload' => $uuid, ...$query]);
}

it('uses exact binding and plot primary keys without crossing same-reference sites', function (): void {
    $site = reconBind($this->office, 'FNA001');
    $own = reconAmend(reconRequest($site));
    $otherSite = Site::factory()->create(['name' => $site->name]);
    $other = reconAmend(reconRequest($otherSite));
    $uuid = reconUpload($this->office);
    $response = $this->actingAs($this->office)->get(reconUrl($uuid))->assertOk()->assertSee('Portal amendment recorded');
    expect($response->viewData('rows')->pluck('amendment_uuid')->all())->toBe([$own->uuid]);
    $this->get(reconUrl($uuid, ['site' => $otherSite->uuid]))->assertNotFound();
    $this->get(reconUrl($uuid, ['amendment' => $other->uuid]))->assertNotFound();
});

it('selects the latest valid amendment per request and preserves separate services and full history', function (): void {
    $site = reconBind($this->office, 'FNA001');
    $request = reconRequest($site);
    $prior = reconAmend($request, '2026-10-03', 'superseded');
    $latest = reconAmend($request);
    reconAmend($request, '2026-10-08', 'withdrawn');
    $cavity = reconRequest($site, 'separate', 'cavity_closers');
    $service = ProjectedPlotService::create(['projected_plot_id' => $request->projected_plot_id, 'service_identifier' => 'cavity_closers']);
    $cavity->update(['projected_plot_id' => $request->projected_plot_id, 'projected_plot_service_id' => $service->id]);
    reconAmend($cavity, '2026-10-09');
    $response = $this->actingAs($this->office)->get(reconUrl(reconUpload($this->office), ['amendment' => $latest->uuid]))->assertOk();
    expect($response->viewData('rows')->total())->toBe(2)
        ->and($response->viewData('rows')->pluck('service')->sort()->values()->all())->toBe(['cavity_closers', 'windows'])
        ->and(Carbon::parse($response->viewData('selected')->portal_date)->toDateString())->toBe('2026-10-05')
        ->and($response->viewData('history')->total())->toBe(3)
        ->and($response->viewData('history')->pluck('uuid'))->toContain($prior->uuid)
        ->and($request->fresh()->requested_date->toDateString())->toBe('2026-10-01')
        ->and($request->fresh()->agreed_date->toDateString())->toBe('2026-10-01');
});

it('defers every service without treating an operational date as source confirmation', function (CallOffServiceType $service): void {
    $site = reconBind($this->office, 'FNA001');
    reconAmend(reconRequest($site, '591', $service->value));
    $response = $this->actingAs($this->office)->get(reconUrl(reconUpload($this->office)))->assertOk();
    $response->assertSee('Awaiting RedZebra reconciliation')->assertSee('Not compared')->assertDontSee('Synced')->assertDontSee('Confirmed by RedZebra')->assertDontSee('RedZebra conflict');
    $contract = (new SourceConfirmationContract)->forService($service);
    expect($contract['supported'])->toBeFalse()->and($contract['date_field'])->toBeNull()->and($contract['call_types'])->toBe([]);
})->with(CallOffServiceType::cases());

it('blocks unknown missing case-changed and revoked codes instead of matching site names', function (): void {
    $site = reconBind($this->office, 'FNA001');
    reconAmend(reconRequest($site));
    $uuid = reconUpload($this->office, ['unknown', '', 'fna001']);
    $response = $this->actingAs($this->office)->get(reconUrl($uuid))->assertOk()->assertSee('CustomerCode required')->assertSee('Exact site binding required');
    expect($response->viewData('rows')->total())->toBe(0)->and($response->viewData('summary')['unbound'])->toBe(3);
    DB::table('wald_source_bindings')->update(['revoked_through' => 1]);
    $this->get(reconUrl(reconUpload($this->office)))->assertOk()->assertViewHas('summary', fn ($s) => $s['bound'] === 0);
});

it('retains completion closure even after source reversal and never writes while reading', function (): void {
    $site = reconBind($this->office, 'FNA001');
    $request = reconRequest($site);
    reconAmend($request);
    $service = ProjectedPlotService::findOrFail($request->projected_plot_service_id);
    $service->update(['source_completion_observed_at' => now()]);
    $uuid = reconUpload($this->office);
    $this->actingAs($this->office)->get(reconUrl($uuid))->assertOk()->assertSee('Closed by source completion')->assertViewHas('summary', fn ($s) => $s['pending'] === 0);
    $request->update(['status' => 'completed']);
    $service->update(['source_completion_observed_at' => null]);
    DB::enableQueryLog();
    $this->get(reconUrl($uuid, ['status' => 'completed']))->assertOk()->assertViewHas('summary', fn ($s) => (int) $s['completed'] === 1);
    $writes = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^\s*(insert|update|delete|replace)/i', $q['query']));
    DB::disableQueryLog();
    expect($writes)->toBeEmpty();
    $this->get(reconUrl($uuid, ['status' => 'pending']))->assertOk()->assertViewHas('rows', fn ($r) => $r->isEmpty());
});

it('handles no amendments invalid evidence and superseded revisions truthfully', function (): void {
    reconBind($this->office, 'FNA001');
    $uuid = reconUpload($this->office);
    $this->actingAs($this->office)->get(reconUrl($uuid))->assertOk()->assertSee('No Portal amendments match this view.');
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $uuid)->first();
    reconUpload($this->office, ['FNA001'], ['stream_id' => $upload->stream_id, 'revision' => 2]);
    $this->get(reconUrl($uuid))->assertOk()->assertSee('This import has been superseded.');
    DB::table('wald_pilot_uploads')->where('uuid', $uuid)->update(['source_manifest_hash' => str_repeat('0', 64)]);
    $this->get(reconUrl($uuid))->assertOk()->assertSee('Source discovery evidence is unavailable')->assertViewHas('summary', fn ($s) => $s['bound'] === 0 && $s['rows'] === null);
});

it('keeps omitted plots as live context and ignores excluded source rows through real discovery', function (): void {
    $site = reconBind($this->office, 'FNA001');
    $other = reconBind($this->office, 'FNA002');
    reconAmend(reconRequest($site, 'not-in-export'));
    reconAmend(reconRequest($other, 'excluded-plot'));
    $file = UploadedFile::fake()->createWithContent('synthetic.csv', "Call No.,CustomerCode,Site Name,Plot number,Call Type,Complete,Plot To Be Installed\n1001,FNA001,Changed descriptive name,1,PC1,No,2026-10-05\n1002,FNA002,Another site,2,CU4,No,2026-10-05\n");
    $flow = new PilotImportWorkflow;
    $upload = $flow->upload($this->office, $file, new ExportOrder('2026-09-23', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    try {
        $response = $this->actingAs($this->office)->get(reconUrl($upload['upload']))->assertOk()->assertSee('A partial export may omit an amended plot')->assertSee('Not compared');
        expect($response->viewData('rows')->pluck('plot_reference')->all())->toBe(['not-in-export'])
            ->and($response->viewData('summary')['excluded'])->toBe(1)->and($response->viewData('summary')['rows'])->toBe(2);
    } finally {
        $key = DB::table('wald_pilot_uploads')->where('uuid', $upload['upload'])->value('storage_key');
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($key);
    }
});

it('denies external inactive preview and disabled access at both route and service boundaries', function (string $kind): void {
    $uuid = reconUpload($this->office);
    $user = $this->office;
    match ($kind) {
        'external' => $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(),
        'inactive' => $user->update(['is_active' => false]),
        'preview' => $user->update(['is_preview_user' => true]),
        'environment' => config(['wald_import.pilot_available' => false]),
        'setting' => DB::table('wald_pilot_settings')->update(['enabled' => false]),
    };
    expect(fn () => app(MasterReconciliationWorkspace::class)->read($user, $uuid))->toThrow(AuthorizationException::class);
    expect($this->actingAs($user)->get(reconUrl($uuid))->status())->toBeIn([302, 403]);
})->with(['external', 'inactive', 'preview', 'environment', 'setting']);

it('paginates amendments history and sites and supports bounded query counts for many sites', function (): void {
    $codes = [];
    for ($i = 1; $i <= 100; $i++) {
        $codes[] = $code = sprintf('FNA%03d', $i);
        $site = reconBind($this->office, $code);
        for ($j = 1; $j <= 10; $j++) {
            $request = reconRequest($site, 'plot-'.$j);
            $latest = reconAmend($request);
        }
    }
    for ($i = 0; $i < 12; $i++) {
        $latest = reconAmend($request, '2026-10-06', 'date_agreed');
    }
    $uuid = reconUpload($this->office, $codes);
    DB::enableQueryLog();
    $data = app(MasterReconciliationWorkspace::class)->read($this->office, $uuid, ['amendment' => $latest->uuid]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($queries)->toBeLessThanOrEqual(18)->and($data['rows']->total())->toBe(1000)
        ->and($data['rows']->count())->toBe(10)->and($data['history']->count())->toBe(10)->and($data['history']->total())->toBe(13)
        ->and($data['sourceSites']->count())->toBe(10)->and($data['sourceSites']->total())->toBe(100);
    $this->actingAs($this->office)->get(reconUrl($uuid, ['amendments_page' => 2, 'sites_page' => 2]))->assertOk()->assertViewHas('rows', fn ($r) => $r->currentPage() === 2);
    $this->get(reconUrl($uuid, ['site' => $site->uuid, 'service' => 'windows', 'q' => 'plot-10']))->assertOk()->assertViewHas('rows', fn ($r) => $r->total() === 1);
    $this->get(reconUrl($uuid, ['amendment' => $latest->uuid, 'history_page' => 2]))->assertOk()->assertViewHas('history', fn ($h) => $h->count() === 3);
});

it('renders escaped representative fictional data for responsive qualification', function (): void {
    $site = reconBind($this->office, 'FNA001');
    foreach (['591', '673', '228'] as $plot) {
        $request = reconRequest($site, $plot);
        reconAmend($request, '2026-10-03', 'superseded');
        reconAmend($request);
    }
    $other = reconBind($this->office, 'FNA002', Site::factory()->create(['name' => 'Meadow View']));
    reconAmend(reconRequest($other, '814', 'cavity_closers'));
    $response = $this->actingAs($this->office)->get(reconUrl(reconUpload($this->office, ['FNA001', 'FNA002', '<script>test</script>'])))->assertOk();
    $response->assertDontSee('<script>test</script>', false)->assertDontSee('PRIVATE-FILENAME')->assertSee('Previous RedZebra date')->assertSee('Latest Portal amendment')->assertSee('New RedZebra date');
    if (getenv('RECON_UI_CAPTURE') === '1') {
        $path = storage_path('app/recon-preview');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path.'/index.html', str_replace('http://localhost', 'http://127.0.0.1:8795', $response->getContent()));
    }
});
