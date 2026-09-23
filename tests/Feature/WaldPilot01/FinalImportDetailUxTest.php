<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['name' => 'Alex Example', 'is_preview_user' => false]);
    $this->actingAs($this->office);
});

afterEach(function (): void {
    $disk = Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')]);
    foreach (DB::table('wald_pilot_uploads')->pluck('storage_key') as $key) {
        $disk->delete($key);
    }
});

function finalUxFixture(User $office, string $callType = 'PC1'): array
{
    $file = UploadedFile::fake()->createWithContent('synthetic.csv', "Call No.,CustomerCode,Site Name,Plot number,Call Type,Complete,VS\n1001,FNA001,Willow Park,591,{$callType},No,2\n1002,FNA001,Willow Park,592,CC1,No,3\n1003,FNA001,Willow Park,593,CU4,No,0\n");
    $workflow = new PilotImportWorkflow;
    $upload = $workflow->upload($office, $file, new ExportOrder('2026-09-23', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $site = Site::factory()->create(['name' => 'Willow Park']);
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $source = $upload['sources'][0];
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Synthetic exact site.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed synthetic site.', (string) Str::uuid());
    $selection = $workflow->select($office, $upload['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('uuid', $selection['run'])->first();

    return compact('upload', 'scope', 'site', 'selection', 'run');
}

function finalUxCapture(string $name, string $html): void
{
    if (getenv('FINAL_UX_CAPTURE') !== '1') {
        return;
    }
    $path = storage_path('app/final-ux-preview');
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
    file_put_contents($path.'/'.$name.'.html', str_replace('http://localhost', 'http://127.0.0.1:8796', $html));
}

it('presents actual upload analysis review approval and apply steps with preserved form gates', function (): void {
    $f = finalUxFixture($this->office);
    $url = route('office.workspace.pilot-import.show', $f['upload']['upload']);
    $response = $this->get($url)->assertOk()->assertSee('Ready to analyse')->assertSee('Whole workbook')->assertSee('Analyse selected site');
    finalUxCapture('uploaded', $response->getContent());
    (new ImportAnalysis)->analyse($this->office, $f['scope'], $f['run']->uuid, (int) $f['run']->epoch, (string) Str::uuid());
    $response = $this->get($url)->assertOk()->assertSee('We found 2 plots')->assertSee('1 Customer Care row ignored (CU4).')->assertSee('Create one-site preview');
    finalUxCapture('review', $response->getContent());
    $run = DB::table('wald_import_runs')->where('id', $f['run']->id)->first();
    $review = new ImportReview;
    $preview = $review->preview($this->office, $f['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $response = $this->get($url)->assertOk()->assertSee('Ready to approve')->assertSee('2 plots: 2 to create, 0 existing plots to reuse.')->assertSee('No blockers in this preview')->assertSee('No data is applied until');
    finalUxCapture('approve', $response->getContent());
    $review->approve($this->office, $f['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $response = $this->get($url)->assertOk()->assertSee('Ready to apply')->assertSee('Apply to CustomerApp')->assertSee('COMMIT THIS ONE SITE');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    // Every sticky submit refers to a real POST form; the Apply confirmation stays required.
    $buttons = $xpath->query('//div[contains(@class,"wald-action-bar")]//button[@form]');
    expect($buttons->length)->toBe(1);
    $form = $document->getElementById($buttons->item(0)->getAttribute('form'));
    expect($form->getAttribute('method'))->toBe('POST')
        ->and($xpath->query('.//input[@name="confirmation" and @required]', $form)->length)->toBe(1)
        ->and(DB::table('projected_plots')->where('site_id', $f['site']->id)->count())->toBe(0);
    finalUxCapture('apply', $response->getContent());
});

it('shows blockers without an approval or apply action', function (): void {
    $f = finalUxFixture($this->office, 'UNKNOWN');
    (new ImportAnalysis)->analyse($this->office, $f['scope'], $f['run']->uuid, (int) $f['run']->epoch, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('id', $f['run']->id)->first();
    (new ImportReview)->preview($this->office, $f['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $response = $this->get(route('office.workspace.pilot-import.show', $f['upload']['upload']))->assertOk()->assertSee('This preview cannot be approved.')->assertDontSee('Approve this one-site preview')->assertDontSee('Apply to CustomerApp');
    finalUxCapture('blocker', $response->getContent());
});

it('distinguishes expired preview from stale dictionary analysis without enabling apply', function (): void {
    $f = finalUxFixture($this->office);
    (new ImportAnalysis)->analyse($this->office, $f['scope'], $f['run']->uuid, (int) $f['run']->epoch, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('id', $f['run']->id)->first();
    $preview = (new ImportReview)->preview($this->office, $f['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $this->travel(25)->hours();
    $url = route('office.workspace.pilot-import.show', $f['upload']['upload']);
    $response = $this->get($url)->assertOk()->assertSee('Preview expired')->assertSee('Create fresh preview')->assertDontSee('Approve this one-site preview');
    finalUxCapture('expired', $response->getContent());
    $this->travelBack();
    $stage = DB::table('wald_import_stages')->where('id', $run->stage_id)->first();
    $manifest = json_decode($stage->manifest, true);
    $manifest['pins']['dictionary'] = 'earlier-dictionary';
    $copy = (array) $stage;
    unset($copy['id']);
    $id = DB::table('wald_import_stages')->insertGetId([...$copy, 'uuid' => (string) Str::uuid(), 'generation' => $stage->generation + 1, 'manifest' => Canonical::json($manifest), 'manifest_hash' => Canonical::hash($manifest)]);
    foreach (DB::table('wald_staged_rows')->where('stage_id', $stage->id)->get() as $row) {
        $copy = (array) $row;
        unset($copy['id']);
        DB::table('wald_staged_rows')->insert([...$copy, 'stage_id' => $id]);
    }
    DB::table('wald_import_runs')->where('id', $run->id)->update(['stage_id' => $id]);
    $response = $this->get($url)->assertOk()->assertSee('Re-analysis needed')->assertSee('Review re-analysis')->assertDontSee('Approve this one-site preview');
    finalUxCapture('stale', $response->getContent());
});

it('renders genuine clarification choices and a large master summary without inventing totals', function (): void {
    $f = finalUxFixture($this->office);
    $response = $this->get(route('office.workspace.pilot-import.show', $f['upload']['upload']))->assertOk();
    $data = $response->getOriginalContent()->getData();
    $key = array_key_first($data['details']);
    $data['import']['manifest'] = ['record_count' => 4358, 'included_count' => 2567, 'excluded_count' => 1791, 'source_count' => 59];
    $data['details'][$key]['run']['state'] = 'NEEDS_CLARIFICATION';
    $data['details'][$key]['questions'] = [['uuid' => (string) Str::uuid(), 'sequence' => 1, 'state' => 'OPEN', 'key' => 'structure:plot_reference',
        'evidence' => ['candidates' => [['id' => 'ref', 'header' => 'Plot Ref', 'column' => 4], ['id' => 'number', 'header' => 'Plot number', 'column' => 5]]]]];
    $html = view('office.pilot-import.show', $data)->render();
    expect($html)->toContain('Which column identifies the plot?', 'Workbook column 4', 'Record clarification', '4,358', 'Whole workbook', 'Reviewing this site only')->not->toContain('We found 4,358 plots');
    finalUxCapture('clarification', $html);
});

it('shows applied receipt facts and onward links without replaying mutation controls', function (): void {
    $f = finalUxFixture($this->office);
    (new ImportAnalysis)->analyse($this->office, $f['scope'], $f['run']->uuid, (int) $f['run']->epoch, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('id', $f['run']->id)->first();
    $review = new ImportReview;
    $preview = $review->preview($this->office, $f['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $review->approve($this->office, $f['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $receipt = $review->commit($this->office, $f['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $url = route('office.workspace.pilot-import.show', $f['upload']['upload']);
    $response = $this->get($url)->assertOk()->assertSee('Applied to CustomerApp')->assertSee('Plots created')->assertSee('Existing plots reused')->assertSee('Applied (UTC)')->assertSee('Alex Example')->assertSee('View site plots')->assertSee('View import history')->assertDontSee('RE-ANALYSE THIS IMPORT')->assertDontSee('COMMIT THIS ONE SITE');
    $detail = array_values($response->viewData('details'))[0];
    // MySQL JSON storage reorders keys; compare the complete canonical payload.
    expect(Canonical::json($detail['receipt']))->toBe(Canonical::json($receipt))->and($receipt['plot_counts']['created'])->toBe(2)->and($receipt['counts']['excluded'])->toBe(1);
    finalUxCapture('applied', $response->getContent());
    $this->travel(25)->hours();
    $expiredReceipt = $this->get($url)->assertOk()->assertDontSee('This preview has expired.');
    $document = new DOMDocument;
    @$document->loadHTML($expiredReceipt->getContent());
    expect((new DOMXPath($document))->query('//li[@aria-current="step"]')->item(0)->textContent)->toBe('7Applied');
    $this->travelBack();
    $history = $this->get(route('office.workspace.sites.show', [$f['site']->customerOrganisation->uuid, $f['site']->uuid, 'section' => 'imports']))->assertOk()->assertSee('Applied to CustomerApp')->assertSee('View import details');
    finalUxCapture('site-history', $history->getContent());
    $replacement = UploadedFile::fake()->createWithContent('replacement.csv', "Call No.,CustomerCode,Site Name,Plot number,Call Type,Complete,VS\n2001,FNA001,Willow Park,594,PC1,No,2\n");
    $new = (new PilotImportWorkflow)->upload($this->office, $replacement, new ExportOrder('2026-09-23', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid(), $f['upload']['upload'], 'Corrected source export.', PilotImportWorkflow::REPLACEMENT_CONFIRMATION);
    $response = $this->get($url)->assertOk()->assertSee('Superseded upload')->assertSee('Open current revision 2')->assertSee('earlier applied receipt')->assertDontSee('COMMIT THIS ONE SITE');
    expect(DB::table('projected_plots')->where('site_id', $f['site']->id)->count())->toBe(2);
    finalUxCapture('superseded', $response->getContent());
    $this->get(route('office.workspace.pilot-import.show', $new['upload']))->assertOk()->assertSee('Corrected source export.')->assertSee('Revision 2');
});

it('explains failed upload recovery without claiming partial application', function (): void {
    $file = UploadedFile::fake()->createWithContent('missing-code.csv', "Call No.,Site Name,Plot number,Call Type,Complete\n3001,Willow Park,591,PC1,No\n");
    expect(fn () => (new PilotImportWorkflow)->upload($this->office, $file, new ExportOrder('2026-09-24', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid()))->toThrow(ImportConflict::class, 'customer_code_missing');
    $upload = DB::table('wald_pilot_uploads')->sole();
    expect($upload->state)->toBe('FAILED');
    $response = $this->get(route('office.workspace.pilot-import.show', $upload->uuid))->assertOk()->assertSee('The workbook could not be prepared')->assertSee('No site from this upload has been applied')->assertSee('Upload a corrected export')->assertDontSee('RE-ANALYSE THIS IMPORT');
    finalUxCapture('failed-upload', $response->getContent());
});

it('reanalyzes stored bytes with current and historical generations shown separately', function (): void {
    $f = finalUxFixture($this->office);
    (new ImportAnalysis)->analyse($this->office, $f['scope'], $f['run']->uuid, (int) $f['run']->epoch, (string) Str::uuid());
    $old = DB::table('wald_import_runs')->where('id', $f['run']->id)->first();
    DB::table('wald_import_runs')->where('id', $old->id)->update(['state' => 'FAILED', 'failure_code' => 'analysis_failed']);
    $url = route('office.workspace.pilot-import.show', $f['upload']['upload']);
    $response = $this->get($url)->assertOk()->assertSee('Analysis stopped for Willow Park')->assertSee('No changes from this site review were applied.')->assertSee('You do not need to upload identical bytes again.')->assertSee('RE-ANALYSE THIS IMPORT');
    finalUxCapture('failed-analysis', $response->getContent());
    $selection = DB::table('wald_pilot_selections')->where('run_id', $old->id)->value('uuid');
    $this->post(route('office.workspace.pilot-import.selections.reanalyse', [$f['upload']['upload'], $selection]), ['command_uuid' => (string) Str::uuid(), 'confirmation' => 'RE-ANALYSE THIS IMPORT'])->assertRedirect()->assertSessionHasNoErrors();
    $response = $this->get($url)->assertOk()->assertSee('Superseded / historical')->assertSee('Current')->assertSee('Ready to review');
    $new = DB::table('wald_import_runs')->where('id', $old->id)->first();
    expect($new->context_id)->not->toBe($old->context_id)->and($new->stage_id)->not->toBe($old->stage_id)
        ->and($new->workbook_hash)->toBe($old->workbook_hash)->and(DB::table('wald_pilot_uploads')->count())->toBe(1)
        ->and(DB::table('wald_import_stages')->where('id', $old->stage_id)->exists())->toBeTrue()
        ->and(DB::table('projected_plots')->count())->toBe(0);
    finalUxCapture('reanalysed', $response->getContent());
});

it('keeps retained evidence readable when the stored workbook has been removed', function (): void {
    $f = finalUxFixture($this->office);
    (new ImportAnalysis)->analyse($this->office, $f['scope'], $f['run']->uuid, (int) $f['run']->epoch, (string) Str::uuid());
    Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($f['run']->storage_key);
    $response = $this->get(route('office.workspace.pilot-import.show', $f['upload']['upload']))->assertOk()->assertSee('The stored workbook is unavailable')->assertSee('Upload a fresh export')->assertDontSee('RE-ANALYSE THIS IMPORT')->assertDontSee('Create one-site preview');
    finalUxCapture('missing-workbook', $response->getContent());
});

it('preserves independently paginated import history and surfaces export identity and receipt counts', function (): void {
    $f = finalUxFixture($this->office);
    $record = (array) DB::table('wald_pilot_uploads')->where('uuid', $f['upload']['upload'])->first();
    unset($record['id']);
    for ($i = 1; $i <= 24; $i++) {
        DB::table('wald_pilot_uploads')->insert([...$record, 'uuid' => (string) Str::uuid(), 'storage_key' => Str::uuid().'.csv', 'export_order' => '202610'.sprintf('%02d', $i).'AM',
            'export_date' => '2026-10-'.sprintf('%02d', $i), 'workbook_hash' => hash('sha256', 'fictional-'.$i), 'created_at' => now()->subDays($i),
            'state' => $i % 2 ? 'FAILED' : 'READY', 'failure_code' => $i % 2 ? 'duplicate_call_number' : null]);
    }
    $response = $this->get(route('office.workspace.imports'))->assertOk()->assertSee('site receipts')->assertSee('Export 3 Oct 2026');
    expect($response->viewData('recentImports'))->toHaveCount(3)->and($response->viewData('importHistory')->count())->toBe(10)->and($response->viewData('importHistory')->total())->toBe(22);
    finalUxCapture('history', $response->getContent());
    $this->get(route('office.workspace.imports', ['history_page' => 2]))->assertOk()->assertViewHas('importHistory', fn ($history) => $history->currentPage() === 2 && $history->count() === 10);
    $this->get(route('office.workspace.imports', ['history_filter' => 'failed']))->assertOk()->assertViewHas('importHistory', fn ($history) => $history->total() === 11);
});
