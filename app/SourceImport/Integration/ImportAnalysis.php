<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportAnalysis
{
    public const REANALYSIS_CONFIRMATION = 'RE-ANALYSE THIS IMPORT';

    /** Start a new interpretation of the same private source; old evidence stays immutable. */
    public function reanalyse(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $command): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'reanalyse');
        $run = (new BackendStore)->run($scope, $uuid);
        if ($run->context_id === null) {
            throw new ImportConflict('reanalysis_state_or_epoch_conflict');
        }
        $inspection = (new WorkbookStager)->inspect($run); // Outside write locks.
        $result = (new ImportStore)->run($actor, $scope, 'reanalyse', $command, [$uuid, $epoch, $run->workbook_hash],
            function (User $fresh) use ($scope, $uuid, $epoch, $inspection, $command): array {
                $store = new BackendStore;
                $run = $store->run($scope, $uuid, true);
                $selection = DB::table('wald_pilot_selections')->where('id', $run->pilot_selection_id)->where('run_id', $run->id)->lockForUpdate()->first();
                $upload = $selection ? DB::table('wald_pilot_uploads')->where('id', $selection->pilot_upload_id)->lockForUpdate()->first() : null;
                if ((int) $run->epoch !== $epoch || ! in_array($run->state, ['UPLOADED', 'NEEDS_CLARIFICATION', 'REQUIRES_REVIEW', 'REVIEWED', 'READY_TO_COMMIT', 'FAILED'], true)
                    || ! $selection || ! $upload || $selection->state !== 'UPLOADED' || ! in_array($upload->state, ['READY', 'IN_PROGRESS'], true)
                    || (int) $run->pilot_upload_id !== (int) $upload->id || $run->context_id === null
                    || DB::table('wald_import_receipts')->where('run_id', $run->id)->exists()) {
                    throw new ImportConflict('reanalysis_not_permitted');
                }
                $latest = DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)->where('export_order', $upload->export_order)->max('revision');
                if ((int) $upload->revision !== (int) $latest || ! hash_equals($upload->workbook_hash, $run->workbook_hash)) {
                    throw new ImportConflict('reanalysis_source_stale');
                }
                $old = KnowledgeContext::query()->whereKey($run->context_id)->lockForUpdate()->firstOrFail();
                if ($old->state === 'SUPERSEDED') {
                    throw new ImportConflict('reanalysis_context_stale');
                }
                $context = (new RegisterContext)->handle($fresh, $scope, $inspection[2], (string) Str::uuid(), $old->uuid);
                $before = ['state' => $run->state, 'context' => $old->uuid, 'analysis_hash' => $old->analysis_hash,
                    'pins' => $old->pins, 'stage_id' => $run->stage_id, 'preview_id' => $run->preview_id];
                $store->state($run, 'UPLOADED', ['context_id' => $context->id, 'stage_id' => null, 'preview_id' => null,
                    'attempts' => 0, 'lease_token' => null, 'lease_until' => null, 'failure_code' => null,
                    'terminal_at' => null, 'workbook_retain_until' => null]);
                $result = ['run' => $uuid, 'state' => 'UPLOADED', 'epoch' => (int) $run->epoch + 1,
                    'previous_context' => $old->uuid, 'context' => $context->uuid];
                (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_import_reanalysis_requested', [
                    'selection' => $selection->uuid, 'run' => $uuid, 'source_hash' => $run->workbook_hash,
                    'previous_context' => $old->uuid, 'current_context' => $context->uuid,
                    'previous_analysis_hash' => $old->analysis_hash, 'current_analysis_hash' => $context->analysis_hash,
                    'previous_pins' => $old->pins, 'current_pins' => $context->pins,
                    'superseded_stage_id' => $run->stage_id, 'superseded_preview_id' => $run->preview_id,
                    'reason' => 'Re-run the existing upload with current Wald rules and fresh clarifications.',
                ], $selection->id, $command);

                return [$result, $before, ['state' => 'UPLOADED', 'context' => $context->uuid, 'analysis_hash' => $context->analysis_hash, 'pins' => $context->pins]];
            });
        $current = (new BackendStore)->run($scope, $uuid);
        if ((int) $current->epoch !== $result['epoch'] || $current->state !== 'UPLOADED') {
            return $result; // An idempotent repeat must not start another generation.
        }

        return $this->analyse($actor, $scope, $uuid, $result['epoch'], (string) Str::uuid());
    }

    public function claim(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'analyse', $command, [$uuid, $epoch], function () use ($scope, $uuid, $epoch): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            if ($run->pilot_selection_id !== null && $run->stage_id !== null) {
                throw new ImportConflict('reanalysis_required');
            }
            if ((int) $run->epoch !== $epoch || ! in_array($run->state, ['UPLOADED', 'NEEDS_CLARIFICATION', 'REQUIRES_REVIEW', 'REVIEWED', 'READY_TO_COMMIT'], true) || $run->attempts >= 3) {
                throw new ImportConflict('analysis_state_or_epoch_conflict');
            }
            $token = (string) Str::uuid();
            (new BackendStore)->state($run, 'ANALYSING', ['lease_token' => $token, 'lease_until' => now('UTC')->addSeconds(600), 'attempts' => $run->attempts + 1, 'generation' => $run->generation + 1, 'preview_id' => null]);
            $result = ['run' => $uuid, 'token' => $token, 'generation' => (int) $run->generation + 1];

            return [$result, ['state' => $run->state], ['state' => 'ANALYSING', ...$result]];
        });
    }

    public function analyse(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $command): array
    {
        $claim = $this->claim($actor, $scope, $uuid, $epoch, $command);

        return $this->execute($actor, $scope, $uuid, $claim['token']);
    }

    public function execute(User $actor, KnowledgeScope $scope, string $uuid, string $token): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'analyse');
        $store = new BackendStore;
        $run = $store->run($scope, $uuid);
        $this->fence($run, $token);
        try {
            $inspection = (new WorkbookStager)->inspect($run); // Deliberately outside write locks.
            $contextId = (new ImportStore)->run($actor, $scope, 'analyse', (string) Str::uuid(), [$uuid, $token, 'context'], function (User $fresh) use ($scope, $uuid, $token, $inspection): array {
                $run = (new BackendStore)->run($scope, $uuid, true);
                $this->fence($run, $token);
                $previousContext = $run->context_id ? KnowledgeContext::query()->findOrFail($run->context_id) : null;
                if (! $previousContext || $previousContext->state !== 'OPEN' || now('UTC')->greaterThanOrEqualTo($previousContext->expires_at)) {
                    $context = (new RegisterContext)->handle($fresh, $scope, $inspection[2], (string) Str::uuid(), $previousContext?->state === 'SUPERSEDED' ? null : $previousContext?->uuid);
                    DB::table('wald_import_runs')->where('id', $run->id)->update(['context_id' => $context->id]);
                    $run->context_id = $context->id;
                }

                return [['run' => $uuid, 'context_id' => (int) $run->context_id], [], ['analysis_hash' => $inspection[2]->data['analysis_hash']]];
            })['context_id'];
            $knowledge = DB::transaction(function () use ($actor, $scope, $contextId, $inspection, $run) {
                (new ImportPolicy)->authorize($actor, $scope, 'analyse', true);

                if (DB::table('wald_pilot_selections')->where('run_id', $run->id)->exists()) {
                    (new AutoResolveClarifications)->forContext($actor, $scope, $contextId, $inspection[1]);
                }

                return (new ImportKnowledge)->capture($actor, $scope, $contextId);
            });
            $rows = (new WorkbookStager)->rows($run, $inspection, $knowledge);
            $manifest = ['workbook_hash' => $run->workbook_hash, 'pins' => (new KnowledgeIdentity)->current(), 'knowledge' => $knowledge,
                'selection_version' => ReviewedWorkbookSelection::VERSION, 'scope' => $scope->columns(), 'export_order' => $run->export_order, 'coverage' => $run->coverage,
                'schema' => 'customerapp.wald-staging.v2', 'integration' => BackendStore::IDENTITY];

            return (new ImportStore)->run($actor, $scope, 'analyse', (string) Str::uuid(), [$uuid, $token, 'stage'], function (User $fresh) use ($scope, $uuid, $token, $rows, $manifest, $contextId): array {
                (new ImportKnowledge)->assertCurrent($fresh, $scope, $contextId, $manifest['knowledge']);
                $run = (new BackendStore)->run($scope, $uuid, true);
                $this->fence($run, $token);
                $bound = [];
                $blocked = 0;
                foreach ($rows as &$row) {
                    if (! $row['excluded'] && $row['issues'] === []) {
                        $key = Canonical::hash([$row['facts']['site_kind'], $row['facts']['source_site']]);
                        $bound[$key] ??= (new SourceBindingService)->resolve($fresh, $scope, $row['facts']['site_kind'], $row['facts']['source_site'], true);
                        $row['binding'] = $bound[$key];
                    }
                    $blocked += $row['issues'] !== [] ? 1 : 0;
                } unset($row);
                $stageUuid = (string) Str::uuid();
                $canonicalHash = Canonical::evidenceHash(array_column($rows, 'canonical'));
                $stageId = DB::table('wald_import_stages')->insertGetId(['uuid' => $stageUuid, 'run_id' => $run->id, 'generation' => $run->generation,
                    'manifest' => Canonical::json($manifest), 'manifest_hash' => Canonical::hash($manifest), 'canonical_hash' => $canonicalHash,
                    'row_count' => count($rows), 'blocked_count' => $blocked, 'created_at' => now('UTC'), 'retain_until' => now('UTC')->addDays(7)]);
                foreach (array_chunk($rows, 50, true) as $chunk) {
                    $inserts = [];
                    foreach ($chunk as $ordinal => $row) {
                        $inserts[] = ['stage_id' => $stageId, 'ordinal' => $ordinal, 'payload' => Canonical::json($row, 65536), 'payload_hash' => Canonical::hash($row)];
                    }
                    DB::table('wald_staged_rows')->insert($inserts);
                }
                (new BackendStore)->state($run, 'REQUIRES_REVIEW', ['stage_id' => $stageId, 'lease_token' => null, 'lease_until' => null, 'failure_code' => null]);
                $result = ['run' => $uuid, 'state' => 'REQUIRES_REVIEW', 'stage' => $stageUuid, 'rows' => count($rows), 'blocked' => $blocked];

                return [$result, ['state' => 'ANALYSING'], $result];
            });
        } catch (\Throwable $exception) {
            $code = $exception instanceof ImportConflict ? $exception->getMessage() : 'analysis_failed';
            $clarification = in_array($code, ['ambiguous_composite_table', 'structural_clarification_required', 'required_column_unresolved', 'required_site_column_unresolved', 'source_site_binding_required'], true);
            (new ImportStore)->run($actor, $scope, 'analyse', (string) Str::uuid(), [$uuid, $token, 'failure'], function () use ($scope, $uuid, $token, $code, $clarification): array {
                $run = (new BackendStore)->run($scope, $uuid, true);
                $this->fence($run, $token);
                $state = $clarification ? 'NEEDS_CLARIFICATION' : 'FAILED';
                (new BackendStore)->state($run, $state, ['failure_code' => $code, 'lease_token' => null, 'lease_until' => null,
                    'terminal_at' => $clarification ? null : now('UTC'), 'workbook_retain_until' => $clarification ? null : now('UTC')->addDays(30)]);

                return [['run' => $uuid, 'state' => $state], ['state' => 'ANALYSING'], ['state' => $state, 'code' => $code]];
            });
            throw $exception;
        }
    }

    public function retry(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'retry', $command, [$uuid, $epoch], function () use ($scope, $uuid, $epoch): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            if ((int) $run->epoch !== $epoch || (! in_array($run->state, ['FAILED', 'NEEDS_CLARIFICATION'], true) && ! ($run->state === 'ANALYSING' && now('UTC')->greaterThanOrEqualTo($run->lease_until)))) {
                throw new ImportConflict('retry_not_permitted');
            }
            (new BackendStore)->state($run, 'UPLOADED', ['lease_token' => null, 'lease_until' => null, 'preview_id' => null, 'attempts' => 0, 'failure_code' => null, 'terminal_at' => null, 'workbook_retain_until' => null]);

            return [['run' => $uuid, 'state' => 'UPLOADED'], ['state' => $run->state], ['state' => 'UPLOADED']];
        });
    }

    private function fence(object $run, string $token): void
    {
        if ($run->state !== 'ANALYSING' || ! hash_equals($run->lease_token ?? '', $token) || now('UTC')->greaterThanOrEqualTo($run->lease_until)) {
            throw new ImportConflict('stale_analysis_lease');
        }
    }
}
