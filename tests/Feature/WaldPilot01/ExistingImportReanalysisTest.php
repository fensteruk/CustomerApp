<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\BackendStore;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\IdenticalPilotImportConflict;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Integration\WorkbookStager;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('reanalyses an existing uncommitted upload with fresh automatic plot choice and preserved history', function (): void {
    $path = (string) getenv('WALD_UX_WORKBOOK');
    if ($path === '' || ! is_file($path)) {
        $this->markTestSkipped('Set WALD_UX_WORKBOOK to the approved private small workbook.');
    }
    expect(hash_file('sha256', $path))->toBe('052b5f4b508e69c5131c995bb3c6a67e5d9faa63ec23d92131785f404e9e52d4');
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->create(['customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true, 'is_preview_user' => false]);
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, new UploadedFile($path, 'existing.xlsx', null, null, true),
        new ExportOrder('2026-09-22', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        expect(fn () => $workflow->upload($office, new UploadedFile($path, 'same-bytes.xlsx', null, null, true),
            new ExportOrder('2026-09-22', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid()))
            ->toThrow(IdenticalPilotImportConflict::class);
        $source = $pilot['sources'][0];
        $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
        $binding = new SourceBindingService;
        $draft = $binding->draft($office, $scope, $source['kind'], $source['identity'], 'Exact test binding.', (string) Str::uuid());
        $binding->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'],
            'Exact test binding reviewed.', (string) Str::uuid());
        $selection = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail();
        $analysis = new ImportAnalysis;
        $claim = $analysis->claim($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        $inspection = (new WorkbookStager)->inspect($run);
        $oldContext = (new RegisterContext)->handle($office, $scope, $inspection[2], (string) Str::uuid());
        DB::table('wald_import_runs')->where('id', $run->id)->update(['context_id' => $oldContext->id]);
        $plotQuestion = collect((new KnowledgeQueries)->questions($office, $scope, $oldContext->uuid))
            ->firstWhere('key', 'structure:plot_reference');
        expect($plotQuestion)->not->toBeNull();
        $oldCandidate = collect($plotQuestion['evidence']['candidates'])->firstWhere('header', 'Plot Ref');
        expect($oldCandidate)->not->toBeNull();
        (new AnswerClarification)->handle($office, $scope, $oldContext->uuid, $plotQuestion['uuid'], $plotQuestion['sequence'],
            $oldCandidate['id'], 'Historical Office Plot Ref choice.', (string) Str::uuid());
        $analysis->execute($office, $scope, $run->uuid, $claim['token']);
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $oldStage = $run->stage_id;
        $oldRows = DB::table('wald_staged_rows')->where('stage_id', $oldStage)->count();
        $oldPlot = (new BackendStore)->payload(DB::table('wald_staged_rows')->where('stage_id', $oldStage)->firstOrFail())['facts']['plot'];
        expect($oldPlot)->toContain('Plot ');
        $review = new ImportReview;
        $originalStage = DB::table('wald_import_stages')->where('id', $oldStage)->firstOrFail();
        $oldManifest = (new BackendStore)->payload($originalStage, 'manifest', 'manifest_hash');
        $olderRules = $oldManifest;
        $olderRules['integration']['application'] = 'customerapp.wald-import-backend.pilot-v3';
        $dependencies = new ReflectionMethod(ImportReview::class, 'dependencies');
        expect(fn () => $dependencies->invoke($review, $office, $scope, $run, $olderRules, []))
            ->toThrow(ImportConflict::class, 'stale_component_or_scope');
        $oldPreview = $review->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        $review->approve($office, $scope, $run->uuid, $oldPreview['preview'], $oldPreview['hash'], (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();

        $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
            ->assertOk()->assertSee('Re-analyse import');
        $this->actingAs($office)->post(route('office.workspace.pilot-import.selections.reanalyse', [$pilot['upload'], $selection['selection']]),
            ['command_uuid' => (string) Str::uuid()])->assertSessionHasErrors('confirmation');
        $response = $this->actingAs($office)->post(route('office.workspace.pilot-import.selections.reanalyse', [$pilot['upload'], $selection['selection']]),
            ['command_uuid' => (string) Str::uuid(), 'confirmation' => ImportAnalysis::REANALYSIS_CONFIRMATION]);
        $response->assertSessionHasNoErrors();
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        expect($run->state)->toBe('REQUIRES_REVIEW')
            ->and($run->stage_id)->not->toBe($oldStage)
            ->and($run->preview_id)->toBeNull()
            ->and($run->workbook_hash)->toBe($upload->workbook_hash)
            ->and(DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->count())->toBe(1)
            ->and(DB::table('wald_knowledge_contexts')->where('id', $oldContext->id)->value('state'))->toBe('SUPERSEDED')
            ->and(DB::table('wald_knowledge_contexts')->where('id', $run->context_id)->value('predecessor_id'))->toBe($oldContext->id)
            ->and(DB::table('wald_staged_rows')->where('stage_id', $oldStage)->count())->toBe($oldRows)
            ->and(DB::table('wald_import_previews')->where('uuid', $oldPreview['preview'])->count())->toBe(1)
            ->and(DB::table('wald_import_receipts')->where('run_id', $run->id)->count())->toBe(0)
            ->and(DB::table('projected_plots')->count())->toBe(0);
        $newContext = DB::table('wald_knowledge_contexts')->where('id', $run->context_id)->firstOrFail();
        $newPlotAnswer = DB::table('wald_clarification_answers as answers')
            ->join('wald_clarifications as questions', 'questions.id', '=', 'answers.clarification_id')
            ->where('questions.context_id', $newContext->id)->where('questions.question_key', 'structure:plot_reference')
            ->first(['answers.decision', 'answers.actor_role']);
        expect($newPlotAnswer->decision)->toBe('AUTO_SELECTED')->and($newPlotAnswer->actor_role)->toBe('wald_automatic');
        $newPlots = DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->get()
            ->map(fn ($row) => (new BackendStore)->payload($row))->filter(fn ($row) => ! $row['excluded'])->pluck('facts.plot')->all();
        expect($newPlots)->toHaveCount(15)->toContain('673');
        foreach ($newPlots as $plot) {
            expect($plot)->toMatch('/^[0-9]+$/');
        }
        expect(fn () => $review->commit($office, $scope, $run->uuid, $oldPreview['preview'], $oldPreview['hash'], (string) Str::uuid()))
            ->toThrow(ImportConflict::class, 'commit_state_conflict');
        $newPreview = $review->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        expect($newPreview['blockers'])->toBe([])
            ->and($newPreview['preview'])->not->toBe($oldPreview['preview']);
        $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
            ->assertOk()->assertSee('Analysis history')->assertSee('Superseded');
        foreach (['site_manager', 'assistant_site_manager', 'finishing_foreman'] as $role) {
            $external = User::factory()->create(['customer_organisation_id' => $customer->id,
                'portal_role_id' => PortalRole::query()->where('identifier', $role)->value('id'),
                'is_active' => true, 'is_preview_user' => false]);
            $this->actingAs($external)->post(route('office.workspace.pilot-import.selections.reanalyse', [$pilot['upload'], $selection['selection']]),
                ['command_uuid' => (string) Str::uuid(), 'confirmation' => ImportAnalysis::REANALYSIS_CONFIRMATION])->assertForbidden();
            expect(fn () => $analysis->reanalyse($external, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid()))
                ->toThrow(AuthorizationException::class);
        }
        $inactive = User::factory()->create(['customer_organisation_id' => null,
            'portal_role_id' => $office->portal_role_id, 'is_active' => false, 'is_preview_user' => false]);
        expect(fn () => $analysis->reanalyse($inactive, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid()))
            ->toThrow(AuthorizationException::class);
        $stale = User::factory()->create(['customer_organisation_id' => null,
            'portal_role_id' => $office->portal_role_id, 'is_active' => true, 'is_preview_user' => false]);
        DB::table('users')->where('id', $stale->id)->update(['portal_role_id' => PortalRole::query()->where('identifier', 'site_manager')->value('id')]);
        expect(fn () => $analysis->reanalyse($stale, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid()))
            ->toThrow(AuthorizationException::class);
        expect(DB::table('wald_import_receipts')->where('run_id', $run->id)->count())->toBe(0);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});
