<?php

namespace App\Http\Controllers;

use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\IdenticalPilotImportConflict;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportPolicy;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\PilotReplacementConfirmationRequired;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Integration\WaldPilotAvailability;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OfficePilotImportController extends Controller
{
    public function index(Request $request): View
    {
        if (! (new WaldPilotAvailability)->enabled()) {
            return view('office.imports', ['site' => null]);
        }
        (new PilotImportPolicy)->authorize($request->user());

        $current = DB::table('wald_pilot_uploads as uploads')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('wald_pilot_uploads as newer')
                    ->whereColumn('newer.stream_id', 'uploads.stream_id')
                    ->whereColumn('newer.export_order', 'uploads.export_order')
                    ->whereColumn('newer.revision', '>', 'uploads.revision');
            });
        $replacementSlots = (clone $current)->orderByDesc('uploads.export_order')->limit(1000)
            ->get(['uploads.uuid', 'uploads.export_date', 'uploads.export_slot', 'uploads.revision', 'uploads.state'])
            ->mapWithKeys(fn (object $upload): array => [
                $upload->export_date.':'.$upload->export_slot => [
                    'uuid' => $upload->uuid,
                    'revision' => (int) $upload->revision,
                    'state' => $upload->state,
                ],
            ])->all();

        return view('office.pilot-import.index', [
            'uploads' => $current->orderByDesc('uploads.export_order')->paginate(20),
            'replacementSlots' => $replacementSlots,
        ]);
    }

    public function upload(Request $request, PilotImportWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'workbook' => ['required', 'file', 'max:20480', 'mimes:xls,xlsx,csv'],
            'export_date' => ['required', 'date_format:Y-m-d'],
            'export_slot' => ['required', Rule::in(['MORNING', 'AFTERNOON'])],
            'confirmation' => ['required', Rule::in([ExportOrder::CONFIRMATION])],
            'command_uuid' => ['required', 'uuid'],
            'predecessor' => ['nullable', 'uuid'],
            'replacement_reason' => ['nullable', 'string', 'max:2000'],
            'replacement_confirmation' => ['nullable', Rule::in([PilotImportWorkflow::REPLACEMENT_CONFIRMATION])],
        ]);
        try {
            $result = $workflow->upload(
                $request->user(),
                $request->file('workbook'),
                new ExportOrder($data['export_date'], $data['export_slot']),
                $data['confirmation'],
                $data['command_uuid'],
                $data['predecessor'] ?? null,
                $data['replacement_reason'] ?? null,
                $data['replacement_confirmation'] ?? null,
            );
        } catch (IdenticalPilotImportConflict $exception) {
            return back()->withInput()->with('existing_import_url', route('office.workspace.pilot-import.show', $exception->existingUploadUuid))
                ->withErrors(['import' => 'This exact export has already been uploaded. No duplicate revision was created.']);
        } catch (PilotReplacementConfirmationRequired $exception) {
            return back()->withInput()->with('existing_import_url', route('office.workspace.pilot-import.show', $exception->existingUploadUuid))
                ->withErrors(['import' => 'An import already exists for this date and slot. Review the replacement warning and confirm before continuing.']);
        } catch (ImportConflict $exception) {
            return back()->withInput()->withErrors(['import' => $this->message($exception)]);
        }

        return redirect()->route('office.workspace.pilot-import.show', $result['upload'])->with('status', 'Workbook uploaded and inspected. No plots have been applied yet. Continue with one site below.');
    }

    public function show(Request $request, string $upload, PilotImportWorkflow $workflow): View
    {
        $this->enabled();
        $summary = $workflow->summary($request->user(), $upload);
        $details = [];
        foreach ($summary['selections'] as $selection) {
            $details[$selection['uuid']] = $this->details($request, $upload, $selection);
        }

        return view('office.pilot-import.show', [
            'import' => $summary,
            'details' => $details,
            'sites' => DB::table('sites')->join('customer_organisations', 'customer_organisations.id', '=', 'sites.customer_organisation_id')
                ->where('sites.is_active', true)->where('customer_organisations.is_active', true)
                ->orderBy('customer_organisations.name')->orderBy('sites.name')
                ->get(['sites.uuid', 'sites.name', 'customer_organisations.name as customer_name']),
        ]);
    }

    public function confirmStructure(Request $request, string $upload, PilotImportWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['confirmation' => ['required', 'in:CONFIRM DETECTED HEADER AND SITE LIST'], 'command_uuid' => ['required', 'uuid']]);
        try {
            $workflow->confirmStructure($request->user(), $upload, $data['confirmation'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'Detected structure confirmed. This did not assign business meaning.');
    }

    public function draftBinding(Request $request, string $upload, PilotImportWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['source_hash' => ['required', 'size:64'], 'site_uuid' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:2000'], 'command_uuid' => ['required', 'uuid']]);
        try {
            $summary = $workflow->summary($request->user(), $upload);
            $source = collect($summary['sources'])->firstWhere('hash', $data['source_hash']) ?? throw new ImportConflict('source_identity_not_found');
            $site = (new PilotImportPolicy)->activeSite($request->user(), $data['site_uuid']);
            $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
            (new SourceBindingService)->draft($request->user(), $scope, $source['kind'], $source['identity'], $data['reason'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['binding' => $this->message($exception)]);
        }

        return back()->with('status', 'Binding draft saved. Review and activate it before selecting this source site.');
    }

    public function activateBinding(Request $request, string $upload, PilotImportWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'binding' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'], 'definition_hash' => ['required', 'size:64'],
            'epoch' => ['required', 'integer', 'min:0'], 'site_uuid' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:2000'], 'command_uuid' => ['required', 'uuid'],
        ]);
        try {
            $summary = $workflow->summary($request->user(), $upload);
            $belongsToUpload = collect($summary['sources'])->contains(function (array $source) use ($data): bool {
                $draft = $source['draft'] ?? null;

                return $draft
                    && hash_equals((string) $draft['uuid'], $data['binding'])
                    && (int) $draft['version'] === (int) $data['version']
                    && hash_equals((string) $draft['definition_hash'], $data['definition_hash'])
                    && (int) $draft['epoch'] === (int) $data['epoch']
                    && hash_equals((string) $draft['site_uuid'], $data['site_uuid']);
            });
            if (! $belongsToUpload) {
                throw new ImportConflict('binding_draft_not_in_upload');
            }
            $site = (new PilotImportPolicy)->activeSite($request->user(), $data['site_uuid']);
            $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
            (new SourceBindingService)->activate($request->user(), $scope, $data['binding'], (int) $data['version'], $data['definition_hash'], (int) $data['epoch'], $data['reason'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['binding' => $this->message($exception)]);
        }

        return back()->with('status', 'Exact source-site binding activated.');
    }

    public function select(Request $request, string $upload, PilotImportWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['source_hash' => ['required', 'size:64'], 'site_uuid' => ['required', 'uuid'], 'command_uuid' => ['required', 'uuid']]);
        try {
            $workflow->select($request->user(), $upload, $data['source_hash'], $data['site_uuid'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'One site selected. Analyse, review and apply its changes to create or update plots.');
    }

    public function analyse(Request $request, string $upload, string $selection): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['command_uuid' => ['required', 'uuid']]);
        try {
            [$scope, $run] = $this->selection($request, $upload, $selection);
            (new ImportAnalysis)->analyse($request->user(), $scope, $run->uuid, (int) $run->epoch, $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'Selected site analysed. Review its evidence and blockers.');
    }

    public function answer(Request $request, string $upload, string $selection): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'context_uuid' => ['required', 'uuid'], 'question_uuid' => ['required', 'uuid'], 'sequence' => ['required', 'integer', 'min:0'],
            'candidate_id' => ['required', 'string', 'max:255'], 'reason' => ['required', 'string', 'max:2000'], 'command_uuid' => ['required', 'uuid'],
        ]);
        try {
            [$scope] = $this->selection($request, $upload, $selection);
            (new AnswerClarification)->handle($request->user(), $scope, $data['context_uuid'], $data['question_uuid'], (int) $data['sequence'], $data['candidate_id'], $data['reason'], $data['command_uuid']);
        } catch (KnowledgeConflict|ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'Clarification recorded. Analyse the selected site again.');
    }

    public function preview(Request $request, string $upload, string $selection): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['command_uuid' => ['required', 'uuid']]);
        try {
            [$scope, $run] = $this->selection($request, $upload, $selection);
            (new ImportReview)->preview($request->user(), $scope, $run->uuid, (int) $run->epoch, $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'Non-mutating preview created for this site only.');
    }

    public function approve(Request $request, string $upload, string $selection): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['preview' => ['required', 'uuid'], 'hash' => ['required', 'size:64'], 'command_uuid' => ['required', 'uuid']]);
        try {
            [$scope, $run] = $this->selection($request, $upload, $selection);
            (new ImportReview)->approve($request->user(), $scope, $run->uuid, $data['preview'], $data['hash'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'Preview approved. The plots have not been applied yet. Choose Apply to CustomerApp below.');
    }

    public function commit(Request $request, string $upload, string $selection): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'preview' => ['required', 'uuid'], 'hash' => ['required', 'size:64'], 'confirmation' => ['required', 'in:COMMIT THIS ONE SITE'], 'command_uuid' => ['required', 'uuid'],
        ]);
        try {
            [$scope, $run] = $this->selection($request, $upload, $selection);
            (new ImportReview)->commit(
                $request->user(),
                $scope,
                $run->uuid,
                $data['preview'],
                $data['hash'],
                $data['command_uuid'],
            );
        } catch (ImportConflict $exception) {
            return back()->withErrors(['import' => $this->message($exception)]);
        }

        return back()->with('status', 'Import applied successfully to the selected site. View its plots below.');
    }

    private function selection(Request $request, string $uploadUuid, string $selectionUuid): array
    {
        (new PilotImportPolicy)->authorize($request->user());
        $selection = DB::table('wald_pilot_selections as selections')
            ->join('wald_pilot_uploads as uploads', 'uploads.id', '=', 'selections.pilot_upload_id')
            ->where('uploads.uuid', $uploadUuid)->where('selections.uuid', $selectionUuid)
            ->first(['selections.*']);
        abort_unless($selection, 404);
        $run = DB::table('wald_import_runs')->where('id', $selection->run_id)->where('pilot_selection_id', $selection->id)->firstOrFail();

        return [new KnowledgeScope((int) $selection->customer_organisation_id, (int) $selection->site_id, 'redzebra', 'call-offs'), $run, $selection];
    }

    private function details(Request $request, string $upload, array $selection): array
    {
        [$scope, $run] = $this->selection($request, $upload, $selection['uuid']);
        $context = $run->context_id ? DB::table('wald_knowledge_contexts')->where('id', $run->context_id)->first() : null;
        $preview = $run->preview_id ? DB::table('wald_import_previews')->where('id', $run->preview_id)->first() : null;
        $receipt = DB::table('wald_import_receipts')->where('run_id', $run->id)->first();
        $autoResolved = $context ? DB::table('wald_clarification_answers as answers')
            ->join('wald_clarifications as questions', 'questions.id', '=', 'answers.clarification_id')
            ->where('questions.context_id', $context->id)->where('answers.decision', 'AUTO_SELECTED')->count() : 0;

        return [
            'run' => (array) $run,
            'context_uuid' => $context?->uuid,
            'questions' => $context ? (new KnowledgeQueries)->questions($request->user(), $scope, $context->uuid) : [],
            'auto_resolved_count' => $autoResolved,
            'rows' => $run->stage_id ? (new ImportReview)->details($request->user(), $scope, $run->uuid, -1, 100) : [],
            'preview' => $preview ? ['uuid' => $preview->uuid, 'hash' => $preview->payload_hash, 'payload' => json_decode($preview->payload, true, flags: JSON_THROW_ON_ERROR)] : null,
            'receipt' => $receipt ? json_decode($receipt->payload, true, flags: JSON_THROW_ON_ERROR) : null,
        ];
    }

    private function enabled(): void
    {
        abort_unless((new WaldPilotAvailability)->enabled(), 404);
    }

    private function message(\RuntimeException $exception): string
    {
        return match ($exception->getMessage()) {
            'source_site_binding_required' => 'Activate an exact binding to the chosen active Portal site first.',
            'customer_code_missing' => 'CustomerCode is missing. Wald cannot safely identify this source site.',
            'identical_pilot_import_exists' => 'This exact export has already been uploaded. No duplicate revision was created.',
            'pilot_replacement_confirmation_required' => 'An import already exists for this date and slot. Confirm the retained-history replacement before continuing.',
            'duplicate_call_number' => 'The workbook contains a duplicate Call No. Nothing was staged.',
            'stale_preview', 'stale_preview_generation', 'stale_source_stream', 'stale_source_binding', 'stale_projection' => 'The preview is stale. Analyse and review this site again.',
            default => 'The supervised pilot stopped safely: '.Str::headline($exception->getMessage()).'.',
        };
    }
}
