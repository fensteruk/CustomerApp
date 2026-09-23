<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Services\OfficeImportsWorkspaceQuery;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportReview;
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
});

afterEach(function (): void {
    $storage = Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')]);
    foreach (DB::table('wald_pilot_uploads')->pluck('storage_key') as $key) {
        $storage->delete($key);
    }
});

function importsWorkspaceOffice(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null, 'is_preview_user' => false]);
}

function importsWorkspaceUpload(User $actor, int $index, ?array $manifest = null, array $overrides = []): string
{
    $stream = DB::table('wald_import_streams')->insertGetId([
        'identity_hash' => hash('sha256', (string) Str::uuid()), 'source_namespace' => 'redzebra', 'workbook_family' => 'test',
    ]);
    $uuid = (string) Str::uuid();
    DB::table('wald_pilot_uploads')->insert([
        'uuid' => $uuid, 'stream_id' => $stream, 'uploader_id' => $actor->id, 'uploader_name' => 'Office Reviewer',
        'storage_key' => $uuid.'.csv', 'original_name' => 'PRIVATE-FILENAME.csv', 'format' => 'csv', 'mime' => 'text/csv',
        'byte_count' => 100, 'workbook_hash' => hash('sha256', $uuid), 'export_date' => '2026-09-23',
        'export_slot' => 'MORNING', 'export_order' => '20260923AM', 'confirmation' => ExportOrder::CONFIRMATION,
        'state' => 'READY', 'source_manifest' => $manifest ? Canonical::json($manifest) : null,
        'source_manifest_hash' => $manifest ? Canonical::hash($manifest) : null,
        'workbook_retain_until' => now()->addDays(30), 'created_at' => now()->subMinutes($index), 'updated_at' => now(),
        ...$overrides,
    ]);

    return $uuid;
}

function importsWorkspaceManifest(int $sites = 1): array
{
    return ['source_count' => $sites, 'record_count' => 4358, 'included_count' => 2567, 'excluded_count' => 1791,
        'sources' => array_map(fn (int $i): array => ['customer_code' => sprintf('FNA%03d', $i), 'site_name' => $i === 1 ? 'Willow Park' : 'Example Site '.$i], range(1, $sites))];
}

it('keeps exactly the latest three uploads outside independently paginated history', function (): void {
    $office = importsWorkspaceOffice();
    $ids = [];
    for ($i = 0; $i < 26; $i++) {
        $ids[] = importsWorkspaceUpload($office, $i);
    }
    $response = $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk();
    expect($response->viewData('recentImports')->pluck('uuid')->all())->toBe(array_slice($ids, 0, 3))
        ->and($response->viewData('importHistory')->pluck('uuid')->all())->toBe(array_slice($ids, 3, 10))
        ->and($response->viewData('importHistory')->total())->toBe(23);
    $next = $this->get(route('office.workspace.imports', ['history_page' => 2]))->assertOk();
    expect($next->viewData('importHistory')->pluck('uuid')->all())->toBe(array_slice($ids, 13, 10))
        ->and($next->viewData('recentImports')->pluck('uuid')->all())->toBe(array_slice($ids, 0, 3));
    $response->assertDontSee('PRIVATE-FILENAME')->assertSee('Uploading does not apply data to CustomerApp.');
});

it('uses safe titles and stage-aware counts without inventing plots or matched sites', function (): void {
    $office = importsWorkspaceOffice();
    importsWorkspaceUpload($office, 0);
    importsWorkspaceUpload($office, 1, importsWorkspaceManifest());
    importsWorkspaceUpload($office, 2, importsWorkspaceManifest(145));
    $response = $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk();
    $cards = $response->viewData('recentImports');
    expect($cards->pluck('title')->all())->toBe(['RedZebra export', 'FNA001 — Willow Park', 'RedZebra master export'])
        ->and($cards[0]['rows'])->toBeNull()->and($cards[0]['sites'])->toBeNull()
        ->and($cards[1]['plots'])->toBeNull()->and($cards[1]['blocked_rows'])->toBeNull()
        ->and($cards[2]['sites'])->toBe(145)->and($cards[2]['rows'])->toBe(4358)
        ->and($cards[2]['included'])->toBe(2567)->and($cards[2]['excluded'])->toBe(1791)
        ->and($cards[2]['preview_sites'])->toHaveCount(5)->and($cards[2]['more_sites'])->toBe(140);
    $response->assertSee('Source sites detected')->assertSee('+140 more sites')->assertDontSee('CustomerApp sites matched');
});

it('filters history without changing the three recent cards and retains superseded revisions', function (): void {
    $office = importsWorkspaceOffice();
    for ($i = 0; $i < 3; $i++) {
        importsWorkspaceUpload($office, $i);
    }
    $old = importsWorkspaceUpload($office, 4, importsWorkspaceManifest());
    $record = DB::table('wald_pilot_uploads')->where('uuid', $old)->first();
    importsWorkspaceUpload($office, 5, null, ['state' => 'FAILED']);
    importsWorkspaceUpload($office, 6, importsWorkspaceManifest(), ['state' => 'NEEDS_CLARIFICATION']);
    importsWorkspaceUpload($office, 3, importsWorkspaceManifest(), ['stream_id' => $record->stream_id, 'revision' => 2]);
    $response = $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk();
    expect($response->viewData('importHistory')->firstWhere('uuid', $old)['status'])->toBe('Superseded');
    $failed = $this->get(route('office.workspace.imports', ['history_filter' => 'failed']))->assertOk();
    expect($failed->viewData('importHistory')->total())->toBe(1)
        ->and($failed->viewData('importHistory')->first()['action'])->toBe('View failed import')
        ->and($failed->viewData('recentImports')->pluck('uuid'))->toEqual($response->viewData('recentImports')->pluck('uuid'));
    $attention = $this->get(route('office.workspace.imports', ['history_filter' => 'attention']))->assertOk();
    expect($attention->viewData('importHistory')->total())->toBe(2);
});

it('escapes source names and falls back for missing or corrupt discovery evidence', function (): void {
    $office = importsWorkspaceOffice();
    $manifest = importsWorkspaceManifest();
    $manifest['sources'][0]['site_name'] = '<script>private()</script>';
    importsWorkspaceUpload($office, 0, $manifest);
    importsWorkspaceUpload($office, 1, importsWorkspaceManifest(), ['source_manifest_hash' => str_repeat('0', 64)]);
    $response = $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk()->assertDontSee('<script>private()</script>', false);
    expect($response->viewData('recentImports')[1]['title'])->toBe('RedZebra export');
});

it('follows actual selected-site analysis preview approval and receipt states', function (): void {
    $office = importsWorkspaceOffice();
    $file = UploadedFile::fake()->createWithContent('synthetic.csv', "Call No.,CustomerCode,Site Name,Plot number,Call Type,Complete,VS\n1001,FNA001,Willow Park,1,PC1,No,2\n1002,FNA001,Willow Park,2,CC1,No,3\n");
    $flow = new PilotImportWorkflow;
    $upload = $flow->upload($office, $file, new ExportOrder('2026-09-23', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $source = $upload['sources'][0];
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Synthetic binding.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed binding.', (string) Str::uuid());
    $selection = $flow->select($office, $upload['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('uuid', $selection['run'])->first();
    $read = fn () => app(OfficeImportsWorkspaceQuery::class)->forOffice($office)['recentImports']->first();
    expect($read()['action'])->toBe('Continue analysis');
    (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('id', $run->id)->first();
    expect($read()['status'])->toBe('Ready to review')->and($read()['plots'])->toBe(2)
        ->and($read()['preview_rows']->pluck('plot')->all())->toBe(['1', '2']);
    $stage = DB::table('wald_import_stages')->where('id', $run->stage_id)->first();
    $stale = json_decode($stage->manifest, true);
    $stale['pins']['dictionary'] = 'older-dictionary';
    $stageCopy = (array) $stage;
    unset($stageCopy['id']);
    $staleId = DB::table('wald_import_stages')->insertGetId([...$stageCopy, 'generation' => $stage->generation + 1, 'uuid' => (string) Str::uuid(), 'manifest' => Canonical::json($stale), 'manifest_hash' => Canonical::hash($stale)]);
    DB::table('wald_import_runs')->where('id', $run->id)->update(['stage_id' => $staleId]);
    expect($read()['status'])->toBe('Needs fresh review')->and($read()['plots'])->toBeNull()
        ->and($read()['preview_rows'])->toBeEmpty();
    $blockedId = DB::table('wald_import_stages')->insertGetId([...$stageCopy, 'generation' => $stage->generation + 2, 'uuid' => (string) Str::uuid(), 'blocked_count' => 1]);
    DB::table('wald_import_runs')->where('id', $run->id)->update(['stage_id' => $blockedId]);
    expect($read()['status'])->toBe('Needs attention')->and($read()['blocked_rows'])->toBe(1);
    DB::table('wald_import_runs')->where('id', $run->id)->update(['stage_id' => $stage->id]);
    $review = new ImportReview;
    $preview = $review->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    expect($read()['status'])->toBe('Ready to approve');
    $storedPreview = DB::table('wald_import_previews')->where('uuid', $preview['preview'])->first();
    $this->travelTo(Carbon::parse($storedPreview->expires_at)->addSecond());
    expect($read()['status'])->toBe('Needs fresh review');
    $this->travelBack();
    $review->approve($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    expect($read()['status'])->toBe('Ready to apply');
    $review->commit($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    expect($read()['status'])->toBe('Applied')->and($read()['action'])->toBe('View result');
    $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk()->assertSee('1 of 1 source sites applied.');
    $multi = importsWorkspaceManifest(2);
    DB::table('wald_pilot_uploads')->where('uuid', $upload['upload'])->update(['source_manifest' => Canonical::json($multi), 'source_manifest_hash' => Canonical::hash($multi)]);
    expect($read()['status'])->toBe('Partially applied')->and($read()['applied'])->toBe(1)
        ->and($read()['plots'])->toBeNull();
    for ($i = 1; $i <= 3; $i++) {
        importsWorkspaceUpload($office, -$i);
    }
    $filtered = $this->get(route('office.workspace.imports', ['history_filter' => 'applied']))->assertOk();
    expect($filtered->viewData('importHistory')->total())->toBe(1)
        ->and($filtered->viewData('importHistory')->first()['status'])->toBe('Partially applied');
});

it('denies private import summaries to external inactive and preview users', function (string $kind): void {
    $office = importsWorkspaceOffice();
    importsWorkspaceUpload($office, 0, importsWorkspaceManifest());
    $user = match ($kind) {
        'external' => User::factory()->role(PortalRoleIdentifier::SiteManager)->create(),
        'inactive' => tap(importsWorkspaceOffice(), fn (User $u) => $u->update(['is_active' => false])),
        default => tap(importsWorkspaceOffice(), fn (User $u) => $u->update(['is_preview_user' => true])),
    };
    expect(fn () => app(OfficeImportsWorkspaceQuery::class)->forOffice($user))->toThrow(AuthorizationException::class);
    $response = $this->actingAs($user)->get(route('office.workspace.imports'));
    expect($response->status())->toBeIn([302, 403]);
    $response->assertDontSee('Willow Park');
})->with(['external', 'inactive', 'preview']);

it('renders a representative synthetic workspace for visual qualification', function (): void {
    $office = importsWorkspaceOffice();
    importsWorkspaceUpload($office, 0, importsWorkspaceManifest());
    importsWorkspaceUpload($office, 1, importsWorkspaceManifest(145));
    importsWorkspaceUpload($office, 2, importsWorkspaceManifest(), ['state' => 'NEEDS_CLARIFICATION']);
    for ($i = 3; $i < 25; $i++) {
        importsWorkspaceUpload($office, $i, importsWorkspaceManifest($i % 3 + 1), ['state' => $i % 2 ? 'FAILED' : 'READY']);
    }
    $response = $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk();
    if (getenv('IMPORTS_UI_CAPTURE') === '1') {
        file_put_contents(public_path('__imports-preview.html'), str_replace('http://localhost', 'http://127.0.0.1:8794', $response->getContent()));
    }
    expect(substr_count($response->getContent(), '<article class="imports-card"'))->toBe(3);
});
