<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
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
    $button = $xpath->query('//button[@form="next-'.$f['selection']['selection'].'"]')->item(0);
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
