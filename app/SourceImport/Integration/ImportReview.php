<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportReview
{
    public function preview(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'review', $command, [$uuid, $epoch, 'preview'], function (User $fresh) use ($scope, $uuid, $epoch): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            if ((int) $run->epoch !== $epoch || ! in_array($run->state, ['REQUIRES_REVIEW', 'REVIEWED', 'READY_TO_COMMIT'], true)) {
                throw new ImportConflict('review_state_or_epoch_conflict');
            }
            [$stage, $manifest, $rows] = (new BackendStore)->stage($run);
            $stream = DB::table('wald_import_streams')->where('id', $run->stream_id)->lockForUpdate()->firstOrFail();
            $this->dependencies($fresh, $scope, $run, $manifest, $rows);
            $blockers = [];
            if ($run->coverage !== 'PARTIAL_FILTERED_EXPORT') {
                $blockers[] = 'NON_COMMITTABLE_EXPORT_SCOPE';
            }
            if ($stage->blocked_count > 0) {
                $blockers[] = 'BLOCKED_STAGED_RECORDS';
            }
            $projection = null;
            if ($blockers === []) {
                try {
                    $this->ordering($run, $stage, $scope, $stream);
                    $projection = (new ProjectionSnapshot)->capture($scope, $rows, $run);
                } catch (ImportConflict $e) {
                    $blockers[] = $e->getMessage();
                }
            }
            $payload = ['run' => $uuid, 'run_epoch' => (int) $run->epoch + 1, 'stage' => $stage->uuid, 'stage_hash' => $stage->manifest_hash,
                'canonical_hash' => $stage->canonical_hash, 'manifest' => $manifest, 'stream_epoch' => (int) $stream->epoch,
                'projection' => $projection, 'blockers' => $blockers, 'reviewer' => $fresh->id, 'reviewed_at' => now('UTC')->toISOString()];
            $preview = (string) Str::uuid();
            $hash = Canonical::hash($payload);
            $id = DB::table('wald_import_previews')->insertGetId(['uuid' => $preview, 'run_id' => $run->id, 'stage_id' => $stage->id,
                'reviewer_id' => $fresh->id, 'reviewer_name' => $fresh->name, 'payload' => Canonical::json($payload), 'payload_hash' => $hash,
                'created_at' => now('UTC'), 'expires_at' => now('UTC')->addHours(24), 'retain_until' => now('UTC')->addDays(7)]);
            (new BackendStore)->state($run, 'REVIEWED', ['preview_id' => $id]);
            $result = ['run' => $uuid, 'preview' => $preview, 'hash' => $hash, 'blockers' => $blockers, 'state' => 'REVIEWED'];

            return [$result, ['state' => $run->state], $result];
        });
    }

    public function approve(User $actor, KnowledgeScope $scope, string $uuid, string $previewUuid, string $hash, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'review', $command, [$uuid, $previewUuid, $hash, 'approve'], function (User $fresh) use ($scope, $uuid, $previewUuid, $hash): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            if ($run->state !== 'REVIEWED') {
                throw new ImportConflict('review_approval_state_conflict');
            }
            $this->validate($fresh, $scope, $run, $previewUuid, $hash);
            (new BackendStore)->state($run, 'READY_TO_COMMIT');

            return [['run' => $uuid, 'state' => 'READY_TO_COMMIT', 'preview' => $previewUuid], ['state' => 'REVIEWED'], ['state' => 'READY_TO_COMMIT', 'preview' => $previewUuid, 'approved_by' => $fresh->id]];
        });
    }

    public function commit(User $actor, KnowledgeScope $scope, string $uuid, string $previewUuid, string $hash, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'commit', $command, [$uuid, $previewUuid, $hash], function (User $fresh) use ($scope, $uuid, $previewUuid, $hash, $command): array {
            $store = new BackendStore;
            $run = $store->run($scope, $uuid, true);
            $existing = DB::table('wald_import_receipts')->where('run_id', $run->id)->first();
            if ($existing) {
                $oldPreview = DB::table('wald_import_previews')->where('id', $existing->preview_id)->firstOrFail();
                if ($oldPreview->uuid !== $previewUuid || ! hash_equals($oldPreview->payload_hash, $hash)) {
                    throw new ImportConflict('reviewed_replay_identity_conflict');
                }

                return [$store->payload($existing), [], ['receipt_replay' => $existing->uuid]];
            }
            if ($run->state !== 'READY_TO_COMMIT') {
                throw new ImportConflict('commit_state_conflict');
            }
            [$preview, $payload, $stage, $rows, $stream] = $this->validate($fresh, $scope, $run, $previewUuid, $hash);
            $prior = $this->ordering($run, $stage, $scope, $stream);
            if ($prior && $prior->canonical_hash === $stage->canonical_hash) {
                $store->state($run, 'SUPERSEDED', ['terminal_at' => now('UTC'), 'workbook_retain_until' => now('UTC')->addDays(30)]);

                return [$store->payload($prior), ['state' => 'READY_TO_COMMIT'], ['canonical_replay' => $prior->uuid]];
            }
            // State, every projection/event, observations, receipt and command audit share this transaction.
            $store->state($run, 'COMMITTING');
            $effects = (new ProjectionAdapter)->apply($scope, $run, $rows, $payload['projection']);
            $receiptUuid = (string) Str::uuid();
            $receipt = ['run' => $uuid, 'receipt' => $receiptUuid, 'preview' => $previewUuid, 'preview_hash' => $hash,
                'workbook_hash' => $run->workbook_hash, 'canonical_hash' => $stage->canonical_hash, 'export_order' => $run->export_order,
                'scope' => $scope->columns(), 'pins' => $payload['manifest']['pins'], 'selection_version' => ReviewedWorkbookSelection::VERSION,
                'bindings' => array_values(array_unique(array_map(fn ($r) => Canonical::json($r['binding']), array_values(array_filter($rows, fn ($r) => ! $r['excluded']))))),
                'knowledge_hash' => Canonical::hash($payload['manifest']['knowledge']), 'reviewer_id' => $preview->reviewer_id,
                'idempotency_identity' => Canonical::hash([$scope->columns(), $run->export_order, $stage->canonical_hash, $prior?->uuid]),
                'actor_id' => $fresh->id, 'actor_name' => $fresh->name, 'committed_at' => now('UTC')->toISOString(),
                'predecessor_receipt' => $prior?->uuid, 'revision' => $prior ? (int) $prior->revision + 1 : 1, ...$effects];
            DB::table('wald_import_receipts')->insert(['uuid' => $receiptUuid, 'run_id' => $run->id, 'preview_id' => $preview->id, 'stream_id' => $run->stream_id,
                'export_order' => $run->export_order, 'revision' => $receipt['revision'], 'canonical_hash' => $stage->canonical_hash,
                'unit_scope_key' => $run->pilot_selection_id === null ? str_repeat('0', 64) : Canonical::hash($scope->columns()),
                'actor_id' => $fresh->id, 'actor_name' => $fresh->name, 'payload' => Canonical::json($receipt), 'payload_hash' => Canonical::hash($receipt),
                'created_at' => now('UTC'), 'retain_until' => now('UTC')->addYears(6)]);
            DB::table('wald_import_streams')->where('id', $stream->id)->update([
                'epoch' => $run->pilot_selection_id === null ? $stream->epoch + 1 : $stream->epoch,
                'latest_order' => $run->export_order,
            ]);
            if ($prior) {
                DB::table('wald_import_runs')->where('id', $prior->run_id)->update(['state' => 'SUPERSEDED', 'epoch' => DB::raw('epoch + 1'), 'updated_at' => now('UTC')]);
            }
            $store->state($run, 'COMMITTED', ['epoch' => $run->epoch + 2, 'terminal_at' => now('UTC'), 'workbook_retain_until' => now('UTC')->addDays(30)]);
            if ($run->pilot_selection_id !== null) {
                $selection = DB::table('wald_pilot_selections')->where('id', $run->pilot_selection_id)->lockForUpdate()->firstOrFail();
                DB::table('wald_pilot_selections')->where('id', $selection->id)->update(['state' => 'COMMITTED', 'updated_at' => now('UTC')]);
                (new PilotImportAudit)->record($fresh, $selection->pilot_upload_id, 'pilot_site_committed', [
                    'selection' => $selection->uuid,
                    'run' => $run->uuid,
                    'receipt' => $receiptUuid,
                    'site_id' => $run->site_id,
                ], $selection->id, $command);
            }

            return [$receipt, ['state' => 'READY_TO_COMMIT'], ['state' => 'COMMITTED', 'receipt' => $receiptUuid, 'effects_hash' => Canonical::hash($effects)]];
        });
    }

    private function validate(User $actor, KnowledgeScope $scope, object $run, string $uuid, string $hash): array
    {
        $store = new BackendStore;
        $preview = DB::table('wald_import_previews')->where('id', $run->preview_id)->where('run_id', $run->id)->where('uuid', $uuid)->firstOrFail();
        if (! hash_equals($preview->payload_hash, $hash) || now('UTC')->greaterThanOrEqualTo($preview->expires_at)) {
            throw new ImportConflict('stale_preview');
        }
        $payload = $store->payload($preview);
        [$stage, $manifest, $rows] = $store->stage($run);
        if ($payload['blockers'] !== [] || $stage->blocked_count > 0 || $run->coverage !== 'PARTIAL_FILTERED_EXPORT') {
            throw new ImportConflict('preview_blocked');
        }
        if ($stage->uuid !== $payload['stage'] || $stage->manifest_hash !== $payload['stage_hash'] || $stage->canonical_hash !== $payload['canonical_hash']
            || (int) $run->epoch !== $payload['run_epoch'] + ($run->state === 'READY_TO_COMMIT' ? 1 : 0)) {
            throw new ImportConflict('stale_preview_generation');
        }
        $stream = DB::table('wald_import_streams')->where('id', $run->stream_id)->lockForUpdate()->firstOrFail();
        if ((int) $stream->epoch !== $payload['stream_epoch']) {
            throw new ImportConflict('stale_source_stream');
        }
        $this->dependencies($actor, $scope, $run, $manifest, $rows);
        $this->ordering($run, $stage, $scope, $stream);
        if (Canonical::hash((new ProjectionSnapshot)->capture($scope, $rows, $run)) !== Canonical::hash($payload['projection'])) {
            throw new ImportConflict('stale_projection');
        }

        return [$preview, $payload, $stage, $rows, $stream];
    }

    private function dependencies(User $actor, KnowledgeScope $scope, object $run, array $manifest, array $rows): void
    {
        if (Canonical::hash((new KnowledgeIdentity)->current()) !== Canonical::hash($manifest['pins']) || $manifest['selection_version'] !== ReviewedWorkbookSelection::VERSION
            || ($manifest['schema'] ?? null) !== 'customerapp.wald-staging.v2' || Canonical::hash($manifest['integration'] ?? []) !== Canonical::hash(BackendStore::IDENTITY)
            || Canonical::hash($manifest['scope']) !== Canonical::hash($scope->columns())
            || $manifest['workbook_hash'] !== $run->workbook_hash || $manifest['export_order'] !== $run->export_order || $manifest['coverage'] !== $run->coverage) {
            throw new ImportConflict('stale_component_or_scope');
        }
        (new PrivateWorkbookStorage)->path($run);
        (new ImportKnowledge)->assertCurrent($actor, $scope, (int) $run->context_id, $manifest['knowledge']);
        $checked = [];
        foreach ($rows as $row) {
            if ($row['excluded'] || $row['issues'] !== []) {
                continue;
            }
            $key = Canonical::hash([$row['facts']['site_kind'], $row['facts']['source_site']]);
            if (! isset($checked[$key])) {
                (new SourceBindingService)->assertCurrent($actor, $scope, $row['facts']['site_kind'], $row['facts']['source_site'], $row['binding']);
                $checked[$key] = true;
            }
        }
    }

    private function ordering(object $run, object $stage, KnowledgeScope $scope, object $stream): ?object
    {
        if ($stream->latest_order !== null && $stream->latest_order > $run->export_order) {
            throw new ImportConflict('older_export_refused');
        }
        $priorQuery = DB::table('wald_import_receipts')
            ->join('wald_import_runs as prior_runs', 'prior_runs.id', '=', 'wald_import_receipts.run_id')
            ->where('wald_import_receipts.stream_id', $stream->id)
            ->where('wald_import_receipts.export_order', $run->export_order);
        if ($run->pilot_selection_id !== null) {
            $priorQuery->where('prior_runs.site_id', $run->site_id);
        }
        $prior = $priorQuery->orderByDesc('wald_import_receipts.revision')->select('wald_import_receipts.*')->first();
        if ($prior) {
            $receipt = (new BackendStore)->payload($prior);
            if (Canonical::hash($receipt['scope']) !== Canonical::hash($scope->columns())) {
                throw new ImportConflict('slot_scope_conflict');
            }
            if ($prior->canonical_hash !== $stage->canonical_hash && (int) $run->predecessor_id !== (int) $prior->run_id) {
                throw new ImportConflict('explicit_correction_required');
            }
            if ($run->predecessor_id !== null && (int) $run->predecessor_id !== (int) $prior->run_id) {
                throw new ImportConflict('replacement_predecessor_stale');
            }
        } elseif ($run->predecessor_id !== null) {
            throw new ImportConflict('replacement_predecessor_missing');
        }

        return $prior;
    }

    public function details(User $actor, KnowledgeScope $scope, string $uuid, int $after = -1, int $limit = 50): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'audit');
        $run = (new BackendStore)->run($scope, $uuid);

        return DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->where('ordinal', '>', max(-1, $after))->orderBy('ordinal')->limit(max(1, min(100, $limit)))->get()
            ->map(fn ($row) => ['ordinal' => $row->ordinal, ...(new BackendStore)->payload($row)])->all();
    }
}
