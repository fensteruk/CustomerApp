<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\ReviewedWorkbookSelection;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Wald05BackendFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
});

function pilotOffice(): User
{
    return User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
}

function pilotBind(User $office, array $source, Site $site): void
{
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $service = new SourceBindingService;
    $draft = $service->draft($office, $scope, $source['kind'], $source['identity'], 'Exact weekend pilot binding.', (string) Str::uuid());
    $service->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed weekend pilot binding.', (string) Str::uuid());
}

function pilotAnalyse(User $office, KnowledgeScope $scope, object $run): void
{
    try {
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    } catch (ImportConflict $exception) {
        if ($exception->getMessage() !== 'structural_clarification_required') {
            throw $exception;
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $context = KnowledgeContext::query()->findOrFail($run->context_id);
        foreach ((new KnowledgeQueries)->questions($office, $scope, $context->uuid) as $question) {
            if ($question['state'] !== 'ANSWERED' && $question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                (new AnswerClarification)->handle($office, $scope, $context->uuid, $question['uuid'], $question['sequence'], $question['evidence']['candidates'][0]['id'], 'Reviewed deterministic pilot header.', (string) Str::uuid());
            }
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    }
}

it('renders the upload controls without leaking Alpine source into visible content', function (): void {
    $response = $this->actingAs(pilotOffice())
        ->get(route('office.workspace.imports'))
        ->assertOk();

    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form[@x-ref="importForm"]')->item(0);
    $submit = $xpath->query('//button[@x-text]')->item(0);

    expect($form)->not->toBeNull()
        ->and($form->getAttribute('x-data'))->toContain('confirmReplacement()', 'requestSubmit()')
        ->and($submit)->not->toBeNull()
        ->and(trim($submit->textContent))->toBe('Upload and inspect privately')
        ->and($document->textContent)->not->toContain('this.$refs.importForm.requestSubmit())');
});

it('imports two selected sites from the actual workbook independently and leaves every other site unchanged', function (): void {
    $path = base_path('Copy of siteapp1.xlsx');
    if (! is_file($path)) {
        $this->markTestSkipped('Approved local workbook is not present.');
    }
    expect(hash_file('sha256', $path))->toBe(ReviewedWorkbookSelection::CHECKSUM);
    $office = pilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, new UploadedFile($path, 'private-pilot.xlsx', null, null, true), new ExportOrder('2026-09-11', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    if ($pilot['state'] === 'NEEDS_CLARIFICATION') {
        $pilot = $workflow->confirmStructure($office, $pilot['upload'], 'CONFIRM DETECTED HEADER AND SITE LIST', (string) Str::uuid());
    }
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        expect($pilot['mode'])->toBe('PILOT_SINGLE_SITE_SELECTION')
            ->and($pilot['sources'])->toHaveCount(12)
            ->and($pilot['manifest']['record_count'])->toBe(47)
            ->and($pilot['manifest']['included_count'])->toBe(45)
            ->and($pilot['manifest']['excluded_count'])->toBe(2);

        $targets = [];
        foreach (array_slice($pilot['sources'], 0, 2) as $index => $source) {
            $customer = CustomerOrganisation::factory()->create(['name' => 'TEST PILOT Customer '.($index + 1).' '.Str::uuid()]);
            $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'TEST PILOT Site '.($index + 1), 'is_active' => true]);
            pilotBind($office, $source, $site);
            $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
            $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
            pilotAnalyse($office, $selected['scope'], $run);
            $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
            $preview = (new ImportReview)->preview($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
            expect($preview['blockers'])->toBe([]);
            (new ImportReview)->approve($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
            (new ImportReview)->commit($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
            $targets[] = $site->id;
        }

        expect(DB::table('wald_import_receipts')->count())->toBe(2)
            ->and(DB::table('wald_pilot_selections')->where('state', 'COMMITTED')->count())->toBe(2)
            ->and(DB::table('projected_plots')->whereNotIn('site_id', $targets)->count())->toBe(0)
            ->and(DB::table('wald_import_runs')->distinct()->count('storage_key'))->toBe(1)
            ->and(DB::table('wald_pilot_events')->where('action', 'pilot_site_committed')->count())->toBe(2);
        $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
            ->assertOk()
            ->assertSee('WEEKEND PILOT')
            ->assertSee('ONE SITE AT A TIME')
            ->assertDontSee('private-pilot.xlsx');
        $this->actingAs($office)->post(route('office.workspace.pilot-import.selections.analyse', [
            $pilot['upload'],
            (string) Str::uuid(),
        ]), ['command_uuid' => (string) Str::uuid()])->assertNotFound();
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('commits one synthetic pilot site through the authenticated HTTP route', function (): void {
    $office = pilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload(
        $office,
        Wald05BackendFixtures::workbook(
            overrides: [
                0 => ['CustomerNo' => 'SYNTHETIC-CUSTOMER-001', 'Plot' => '101'],
                1 => ['CustomerNo' => 'SYNTHETIC-CUSTOMER-001', 'Plot' => '102'],
                2 => ['CustomerNo' => 'SYNTHETIC-CUSTOMER-001', 'Plot' => '103'],
            ],
            headers: ['CustomerNo', 'Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'],
            count: 3,
        ),
        new ExportOrder('2099-01-02', 'MORNING'),
        ExportOrder::CONFIRMATION,
        (string) Str::uuid(),
    );
    if ($pilot['state'] === 'NEEDS_CLARIFICATION') {
        $pilot = $workflow->confirmStructure($office, $pilot['upload'], 'CONFIRM DETECTED HEADER AND SITE LIST', (string) Str::uuid());
    }
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        $customer = CustomerOrganisation::factory()->create(['name' => 'TEST — Acme Developments']);
        $site = Site::factory()->create([
            'customer_organisation_id' => $customer->id,
            'name' => 'TEST — Willow Park',
            'is_active' => true,
        ]);
        $source = $pilot['sources'][0];
        pilotBind($office, $source, $site);
        $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        pilotAnalyse($office, $selected['scope'], $run);
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $preview = (new ImportReview)->preview($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
        expect($preview['blockers'])->toBe([]);
        $previewPayload = json_decode(DB::table('wald_import_previews')->where('uuid', $preview['preview'])->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        expect($previewPayload['projection']['target']['source']['identity'])->toBe('SYNTHETIC-CUSTOMER-001')
            ->and($previewPayload['projection']['target']['source']['site_names'])->toBe(['Synthetic Site'])
            ->and($previewPayload['projection']['target']['customer']['name'])->toBe('TEST — Acme Developments')
            ->and($previewPayload['projection']['target']['site']['name'])->toBe('TEST — Willow Park')
            ->and(array_keys($previewPayload['projection']['target']['plots']))->toBe([101, 102, 103])
            ->and(array_unique(array_values($previewPayload['projection']['target']['plots'])))->toBe(['CREATE']);

        $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
            ->assertOk()
            ->assertSee('Source CustomerCode:')
            ->assertSee('TEST — Acme Developments · TEST — Willow Park')
            ->assertSee('Create under this site')
            ->assertDontSee('Retained pre-CustomerCode')
            ->assertSeeInOrder(['101', '102', '103']);
        (new ImportReview)->approve($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());

        $command = (string) Str::uuid();
        $this->actingAs($office)->post(
            route('office.workspace.pilot-import.selections.commit', [$pilot['upload'], $selected['selection']]),
            [
                'preview' => $preview['preview'],
                'hash' => $preview['hash'],
                'confirmation' => 'COMMIT THIS ONE SITE',
                'command_uuid' => $command,
            ],
        )->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'One selected site committed atomically. No other source site was changed.');

        $attempt = DB::table('wald_commit_attempts')->where('command_uuid', $command)->firstOrFail();
        expect(DB::table('wald_import_receipts')->where('run_id', $run->id)->count())->toBe(1)
            ->and(DB::table('wald_commit_attempts')->where('command_uuid', $command)->count())->toBe(1)
            ->and(DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->value('outcome'))->toBe('SUCCEEDED')
            ->and(DB::table('wald_pilot_selections')->where('uuid', $selected['selection'])->value('state'))->toBe('COMMITTED')
            ->and(DB::table('projected_plots')->where('site_id', $site->id)->orderBy('plot_reference')->pluck('plot_reference')->all())->toBe(['101', '102', '103']);

        $unrelated = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'TEST — Hidden Site']);
        $manager = User::factory()->role(PortalRoleIdentifier::SiteManager)->create([
            'customer_organisation_id' => $customer->id,
            'name' => 'Fictional Site Manager',
            'is_active' => true,
        ]);
        $this->actingAs($office)->post(route('portal.office.sites.users.store', [$customer, $site]), ['user_uuid' => $manager->uuid])->assertRedirect();
        $this->actingAs($manager)->get('/sites/select')->assertOk()
            ->assertSee('TEST — Willow Park')
            ->assertSee('3 outstanding projected plots')
            ->assertDontSee($unrelated->name);
        $this->actingAs($office)->delete(route('portal.office.sites.users.destroy', [$customer, $site, $manager]))->assertRedirect();
        $this->actingAs($manager->fresh())->get('/sites/select')->assertOk()->assertSee('No assigned sites');
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('renders an exact minimal legacy manifest at review and committed states without inventing a CustomerCode', function (bool $commit): void {
    $office = pilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload(
        $office,
        Wald05BackendFixtures::workbook(
            overrides: [0 => ['CustomerNo' => 'LEGACY-SOURCE-001', 'Plot' => '41']],
            headers: ['CustomerNo', 'Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'],
            count: 1,
        ),
        new ExportOrder('2099-01-03', 'MORNING'),
        ExportOrder::CONFIRMATION,
        (string) Str::uuid(),
    );
    if ($pilot['state'] === 'NEEDS_CLARIFICATION') {
        $pilot = $workflow->confirmStructure($office, $pilot['upload'], 'CONFIRM DETECTED HEADER AND SITE LIST', (string) Str::uuid());
    }
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        $customer = CustomerOrganisation::factory()->create(['name' => 'Legacy Customer']);
        $site = Site::factory()->create([
            'customer_organisation_id' => $customer->id,
            'name' => 'Legacy Site',
            'is_active' => true,
        ]);
        $source = $pilot['sources'][0];
        pilotBind($office, $source, $site);
        $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        pilotAnalyse($office, $selected['scope'], $run);
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $preview = (new ImportReview)->preview($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
        expect($preview['blockers'])->toBe([]);
        (new ImportReview)->approve($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        if ($commit) {
            (new ImportReview)->commit($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        }

        $storedManifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
        $legacySource = array_intersect_key($storedManifest['sources'][0], array_flip(['hash', 'kind', 'rows', 'identity']));
        expect(array_keys($legacySource))->toBe(['hash', 'identity', 'kind', 'rows']);
        $legacyManifest = [...$storedManifest, 'sources' => [$legacySource]];
        DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
            'source_manifest' => Canonical::json($legacyManifest),
            'source_manifest_hash' => Canonical::hash($legacyManifest),
            'updated_at' => now('UTC'),
        ]);

        $summary = $workflow->summary($office, $pilot['upload']);
        expect(array_keys($summary['sources'][0]))->toBe([
            'hash', 'kind', 'identity', 'rows', 'customer_code', 'site_name', 'observed_site_names',
            'warnings', 'legacy_pre_customer_code', 'identity_label', 'binding', 'draft',
        ])
            ->and($summary['sources'][0]['customer_code'])->toBeNull()
            ->and($summary['sources'][0]['identity'])->toBe('LEGACY-SOURCE-001')
            ->and($summary['sources'][0]['identity_label'])->toBe('Legacy source identity')
            ->and($summary['selections'][0]['source_identity_label'])->toBe('Legacy source identity');

        $response = $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
            ->assertOk()
            ->assertSee('Legacy source identity LEGACY-SOURCE-001')
            ->assertSee('Retained pre-CustomerCode record')
            ->assertSee('Retained pre-CustomerCode selection')
            ->assertDontSee('Source CustomerCode:')
            ->assertDontSee('Undefined array key');

        if ($commit) {
            expect(DB::table('wald_import_receipts')->where('run_id', $run->id)->exists())->toBeTrue();
            $response->assertSee('Committed atomically');
        } else {
            $response->assertSee('Commit this one site');
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
                'state' => 'SUPERSEDED',
                'updated_at' => now('UTC'),
            ]);
            $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
                ->assertOk()
                ->assertSee('Legacy source identity LEGACY-SOURCE-001')
                ->assertSee('Retained pre-CustomerCode record');
        }
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
})->with([
    'READY_TO_COMMIT' => false,
    'COMMITTED with receipt' => true,
]);

it('keeps real actions unavailable by default and denies external and stale Office users', function (): void {
    $office = pilotOffice();
    config(['wald_import.pilot_available' => false]);
    $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk()->assertDontSee('WEEKEND PILOT');
    $this->actingAs($office)->post(route('office.workspace.pilot-import.upload'))->assertNotFound();

    config(['wald_import.pilot_available' => true]);
    $siteUser = User::factory()->create(['portal_role_id' => PortalRole::query()->where('identifier', 'site_manager')->value('id'), 'is_active' => true]);
    $this->actingAs($siteUser)->get(route('office.workspace.imports'))->assertForbidden();
    DB::table('users')->where('id', $office->id)->update(['is_active' => false]);
    $this->actingAs($office)->get(route('office.workspace.imports'))->assertForbidden();
});
