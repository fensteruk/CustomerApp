<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PilotImportWorkflow
{
    public const REPLACEMENT_CONFIRMATION = 'REPLACE EXISTING MASTER EXPORT';

    public function upload(User $actor, UploadedFile $file, ExportOrder $order, string $confirmation, string $command, ?string $predecessor = null, ?string $reason = null, ?string $replacementConfirmation = null): array
    {
        $fresh = (new PilotImportPolicy)->authorize($actor);
        $this->command($command);
        if ($confirmation !== ExportOrder::CONFIRMATION) {
            throw new ImportConflict('export_confirmation_required');
        }
        if ($reason !== null && mb_strlen($reason) > 2000) {
            throw new ImportConflict('replacement_reason_too_long');
        }
        $storage = new PrivateWorkbookStorage;
        $artifact = $storage->store($file);
        try {
            $uuid = DB::transaction(function () use ($fresh, $artifact, $order, $confirmation, $command, $predecessor, $reason, $replacementConfirmation): string {
                (new PilotImportPolicy)->authorize($fresh, true);
                $namespace = 'redzebra';
                $family = 'call-offs';
                $streamKey = Canonical::hash([$namespace, $family]);
                DB::table('wald_import_streams')->insertOrIgnore(['identity_hash' => $streamKey, 'source_namespace' => $namespace, 'workbook_family' => $family]);
                $stream = DB::table('wald_import_streams')->where('identity_hash', $streamKey)->lockForUpdate()->firstOrFail();
                $latest = DB::table('wald_pilot_uploads')->where('stream_id', $stream->id)->where('export_order', $order->key())->orderByDesc('revision')->lockForUpdate()->first();
                if ($latest && hash_equals($latest->workbook_hash, $artifact['workbook_hash'])) {
                    throw new IdenticalPilotImportConflict($latest->uuid);
                }

                $prior = $latest;
                $confirmationRequired = $prior !== null && $prior->state !== 'FAILED';
                if ($prior !== null && $predecessor !== null && ! hash_equals($prior->uuid, $predecessor)) {
                    throw new ImportConflict('invalid_pilot_predecessor');
                }
                if ($confirmationRequired && $predecessor === null) {
                    throw new PilotReplacementConfirmationRequired($prior->uuid);
                }
                if ($confirmationRequired && $replacementConfirmation !== self::REPLACEMENT_CONFIRMATION) {
                    throw new PilotReplacementConfirmationRequired($prior->uuid);
                }
                if ($prior === null && $predecessor !== null) {
                    throw new ImportConflict('invalid_pilot_predecessor');
                }
                $replacementReason = $prior === null ? null : (trim((string) $reason) !== ''
                    ? trim((string) $reason)
                    : ($confirmationRequired ? 'Confirmed replacement of the current master export revision.' : 'Replaced a failed master export revision.'));
                $uuid = (string) Str::uuid();
                $successorRevision = $prior ? (int) $prior->revision + 1 : 1;
                $id = DB::table('wald_pilot_uploads')->insertGetId([
                    ...$artifact,
                    'uuid' => $uuid,
                    'stream_id' => $stream->id,
                    'uploader_id' => $fresh->id,
                    'uploader_name' => $fresh->name,
                    'export_date' => $order->date,
                    'export_slot' => $order->slot,
                    'export_order' => $order->key(),
                    'confirmation' => $confirmation,
                    'revision' => $successorRevision,
                    'predecessor_upload_id' => $prior?->id,
                    'replacement_reason' => $replacementReason,
                    'workbook_retain_until' => now('UTC')->addDays(30),
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
                if ($prior !== null) {
                    DB::table('wald_pilot_uploads')->where('id', $prior->id)->update([
                        'state' => 'SUPERSEDED',
                        'epoch' => DB::raw('epoch + 1'),
                        'terminal_at' => now('UTC'),
                        'updated_at' => now('UTC'),
                    ]);
                    $selectionIds = DB::table('wald_pilot_selections')->where('pilot_upload_id', $prior->id)->where('state', '!=', 'COMMITTED')->pluck('id');
                    if ($selectionIds->isNotEmpty()) {
                        DB::table('wald_pilot_selections')->whereIn('id', $selectionIds)->update(['state' => 'SUPERSEDED', 'updated_at' => now('UTC')]);
                        DB::table('wald_import_runs')->whereIn('pilot_selection_id', $selectionIds)
                            ->whereNotIn('state', ['COMMITTED', 'SUPERSEDED'])
                            ->update(['state' => 'SUPERSEDED', 'epoch' => DB::raw('epoch + 1'), 'terminal_at' => now('UTC'), 'updated_at' => now('UTC')]);
                    }
                    (new PilotImportAudit)->record($fresh, $prior->id, 'pilot_upload_superseded', [
                        'predecessor_revision' => (int) $prior->revision,
                        'successor' => $uuid,
                        'successor_revision' => $successorRevision,
                    ]);
                }
                DB::table('wald_import_streams')->where('id', $stream->id)->update(['epoch' => (int) $stream->epoch + 1]);
                (new PilotImportAudit)->record($fresh, $id, 'pilot_upload_created', [
                    'upload' => $uuid,
                    'workbook_hash' => $artifact['workbook_hash'],
                    'export_order' => $order->key(),
                    'predecessor' => $prior?->uuid,
                    'predecessor_revision' => $prior ? (int) $prior->revision : null,
                    'predecessor_state' => $prior?->state,
                    'successor_revision' => $successorRevision,
                    'replacement_reason' => $replacementReason,
                    'confirmation_required' => $confirmationRequired,
                    'confirmation_received' => $confirmationRequired && $replacementConfirmation === self::REPLACEMENT_CONFIRMATION,
                ], command: $command);

                return $uuid;
            }, 3);
            $this->discover($fresh, $uuid);

            return $this->summary($fresh, $uuid);
        } finally {
            $storage->discardUnregistered($artifact['storage_key']);
        }
    }

    public function confirmStructure(User $actor, string $uuid, string $confirmation, string $command): array
    {
        $fresh = (new PilotImportPolicy)->authorize($actor);
        $this->command($command);
        if ($confirmation !== 'CONFIRM DETECTED HEADER AND SITE LIST') {
            throw new ImportConflict('pilot_structure_confirmation_required');
        }
        DB::transaction(function () use ($fresh, $uuid, $command): void {
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if ($upload->state !== 'NEEDS_CLARIFICATION' || $upload->source_manifest === null) {
                throw new ImportConflict('pilot_structure_confirmation_state_conflict');
            }
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update(['state' => 'READY', 'epoch' => (int) $upload->epoch + 1, 'updated_at' => now('UTC')]);
            (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_structure_confirmed', ['upload' => $uuid], command: $command);
        }, 3);

        return $this->summary($fresh, $uuid);
    }

    public function select(User $actor, string $uuid, string $sourceHash, string $siteUuid, string $command): array
    {
        $fresh = (new PilotImportPolicy)->authorize($actor);
        $this->command($command);
        $site = (new PilotImportPolicy)->activeSite($fresh, $siteUuid);

        return DB::transaction(function () use ($fresh, $uuid, $sourceHash, $site, $command): array {
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if (! in_array($upload->state, ['READY', 'IN_PROGRESS'], true)) {
                throw new ImportConflict('pilot_upload_not_selectable');
            }
            $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
            $source = collect($manifest['sources'])->firstWhere('hash', $sourceHash);
            if (! $source) {
                throw new ImportConflict('source_identity_not_found');
            }
            $stream = DB::table('wald_import_streams')->where('id', $upload->stream_id)->firstOrFail();
            $binding = DB::table('wald_source_bindings')->where('identity_hash', Canonical::hash([$stream->source_namespace, $source['kind'], $source['identity']]))->lockForUpdate()->first();
            $version = $binding?->active_version
                ? DB::table('wald_binding_versions')->where('binding_id', $binding->id)->where('version', $binding->active_version)->first()
                : null;
            if (! $binding || ! $version || (int) $version->site_id !== (int) $site->id || (int) $version->customer_organisation_id !== (int) $site->customer_organisation_id) {
                throw new ImportConflict('source_site_binding_required');
            }
            if (DB::table('wald_pilot_selections')->where('pilot_upload_id', $upload->id)->where('source_identity_hash', $sourceHash)->exists()) {
                throw new ImportConflict('pilot_source_already_selected');
            }
            $selectionUuid = (string) Str::uuid();
            $selectionId = DB::table('wald_pilot_selections')->insertGetId([
                'uuid' => $selectionUuid,
                'pilot_upload_id' => $upload->id,
                'customer_organisation_id' => $site->customer_organisation_id,
                'site_id' => $site->id,
                'source_identity_kind' => $source['kind'],
                'source_identity' => $source['identity'],
                'source_identity_hash' => $source['hash'],
                'binding_id' => $binding->id,
                'binding_version' => $version->version,
                'binding_definition_hash' => $version->definition_hash,
                'binding_epoch' => $binding->epoch,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            $predecessorRunId = null;
            if ($upload->predecessor_upload_id) {
                $predecessorRunId = DB::table('wald_pilot_selections')
                    ->where('pilot_upload_id', $upload->predecessor_upload_id)
                    ->where('site_id', $site->id)
                    ->value('run_id');
            }
            $runUuid = (string) Str::uuid();
            $runId = DB::table('wald_import_runs')->insertGetId([
                'uuid' => $runUuid,
                'stream_id' => $upload->stream_id,
                'customer_organisation_id' => $site->customer_organisation_id,
                'site_id' => $site->id,
                'source_namespace' => $stream->source_namespace,
                'workbook_family' => $stream->workbook_family,
                'uploader_id' => $upload->uploader_id,
                'uploader_name' => $upload->uploader_name,
                'storage_key' => $upload->storage_key,
                'original_name' => $upload->original_name,
                'format' => $upload->format,
                'mime' => $upload->mime,
                'byte_count' => $upload->byte_count,
                'workbook_hash' => $upload->workbook_hash,
                'export_date' => $upload->export_date,
                'export_slot' => $upload->export_slot,
                'export_order' => $upload->export_order,
                'confirmation' => $upload->confirmation,
                'provenance' => ExportOrder::PROVENANCE,
                'coverage' => 'PARTIAL_FILTERED_EXPORT',
                'predecessor_id' => $predecessorRunId,
                'replacement_reason' => $predecessorRunId ? $upload->replacement_reason : null,
                'pilot_upload_id' => $upload->id,
                'pilot_selection_id' => $selectionId,
                'source_site_filter_hash' => $source['hash'],
                'source_site_filter' => $source['identity'],
                'import_mode' => 'PILOT_SINGLE_SITE_SELECTION',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            DB::table('wald_pilot_selections')->where('id', $selectionId)->update(['run_id' => $runId]);
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update(['state' => 'IN_PROGRESS', 'epoch' => (int) $upload->epoch + 1, 'updated_at' => now('UTC')]);
            (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_site_selected', [
                'selection' => $selectionUuid,
                'run' => $runUuid,
                'source_identity_hash' => $source['hash'],
                'site_id' => $site->id,
            ], $selectionId, $command);

            return ['selection' => $selectionUuid, 'run' => $runUuid, 'scope' => new KnowledgeScope($site->customer_organisation_id, $site->id, $stream->source_namespace, $stream->workbook_family)];
        }, 3);
    }

    public function summary(User $actor, string $uuid): array
    {
        (new PilotImportPolicy)->authorize($actor);
        $upload = DB::table('wald_pilot_uploads')->where('uuid', $uuid)->firstOrFail();
        $stream = DB::table('wald_import_streams')->where('id', $upload->stream_id)->firstOrFail();
        $manifest = $upload->source_manifest ? json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR) : ['sources' => []];
        $sources = array_map(
            fn (array $source): array => $this->presentSource($stream->source_namespace, $source),
            $manifest['sources'] ?? [],
        );
        $legacySourceHashes = array_fill_keys(array_column(array_filter(
            $sources,
            fn (array $source): bool => $source['legacy_pre_customer_code'],
        ), 'hash'), true);
        $selections = DB::table('wald_pilot_selections as selections')
            ->join('wald_import_runs as runs', 'runs.id', '=', 'selections.run_id')
            ->join('sites', 'sites.id', '=', 'selections.site_id')
            ->join('customer_organisations', 'customer_organisations.id', '=', 'selections.customer_organisation_id')
            ->where('selections.pilot_upload_id', $upload->id)
            ->get([
                'selections.*', 'runs.uuid as run_uuid', 'runs.state as run_state', 'runs.epoch as run_epoch',
                'runs.context_id', 'runs.stage_id', 'runs.preview_id', 'sites.uuid as site_uuid',
                'sites.name as site_name', 'customer_organisations.uuid as customer_uuid',
                'customer_organisations.name as customer_name',
            ])
            ->map(fn ($item): array => $this->presentSelection(
                (array) $item,
                isset($legacySourceHashes[$item->source_identity_hash]),
            ))->all();
        $latestRevision = (int) DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)->where('export_order', $upload->export_order)->max('revision');
        $revisions = DB::table('wald_pilot_uploads')
            ->where('stream_id', $upload->stream_id)
            ->where('export_order', $upload->export_order)
            ->orderByDesc('revision')
            ->get(['uuid', 'revision', 'state', 'failure_code', 'created_at'])
            ->map(fn (object $revision): array => [
                'uuid' => $revision->uuid,
                'revision' => (int) $revision->revision,
                'state' => $revision->state,
                'failure_code' => $revision->failure_code,
                'created_at' => $revision->created_at,
                'current' => (int) $revision->revision === $latestRevision,
            ])->all();

        return [
            'upload' => $uuid,
            'id' => $upload->id,
            'state' => $upload->state,
            'export_date' => $upload->export_date,
            'export_slot' => $upload->export_slot,
            'revision' => (int) $upload->revision,
            'mode' => $upload->mode,
            'failure_code' => $upload->failure_code,
            'manifest' => $manifest,
            'sources' => $sources,
            'selections' => $selections,
            'revisions' => $revisions,
        ];
    }

    private function presentSource(string $namespace, array $source): array
    {
        $customerCode = is_string($source['customer_code'] ?? null) && trim($source['customer_code']) !== ''
            ? $source['customer_code']
            : null;
        $siteName = is_string($source['site_name'] ?? null) && trim($source['site_name']) !== ''
            ? $source['site_name']
            : null;
        $observedSiteNames = array_values(array_filter(
            is_array($source['observed_site_names'] ?? null) ? $source['observed_site_names'] : [],
            fn ($name): bool => is_string($name) && trim($name) !== '',
        ));
        $warnings = array_values(array_filter(
            is_array($source['warnings'] ?? null) ? $source['warnings'] : [],
            fn ($warning): bool => is_string($warning) && trim($warning) !== '',
        ));
        $legacy = $customerCode === null;

        return [
            'hash' => (string) ($source['hash'] ?? ''),
            'kind' => (string) ($source['kind'] ?? ''),
            'identity' => (string) ($source['identity'] ?? ''),
            'rows' => (int) ($source['rows'] ?? 0),
            'customer_code' => $customerCode,
            'site_name' => $siteName,
            'observed_site_names' => $observedSiteNames,
            'warnings' => $warnings,
            'legacy_pre_customer_code' => $legacy,
            'identity_label' => $legacy ? 'Legacy source identity' : 'CustomerCode',
            ...$this->binding($namespace, $source),
        ];
    }

    private function presentSelection(array $selection, bool $legacyManifestSource): array
    {
        $customerCode = ! $legacyManifestSource && ($selection['source_identity_kind'] ?? null) === 'CUSTOMER_CODE';

        return [
            ...$selection,
            'source_identity_label' => $customerCode ? 'Source CustomerCode' : 'Legacy source identity',
            'legacy_pre_customer_code' => ! $customerCode,
        ];
    }

    private function discover(User $actor, string $uuid): void
    {
        $upload = DB::table('wald_pilot_uploads')->where('uuid', $uuid)->firstOrFail();
        try {
            $manifest = (new PilotWorkbookDiscovery)->inspect($upload);
            DB::transaction(function () use ($actor, $upload, $manifest): void {
                $locked = DB::table('wald_pilot_uploads')->where('id', $upload->id)->lockForUpdate()->firstOrFail();
                $payload = Canonical::json($manifest);
                DB::table('wald_pilot_uploads')->where('id', $locked->id)->update([
                    'source_manifest' => $payload,
                    'source_manifest_hash' => Canonical::hash($manifest),
                    'state' => $manifest['requires_confirmation'] ? 'NEEDS_CLARIFICATION' : 'READY',
                    'epoch' => (int) $locked->epoch + 1,
                    'updated_at' => now('UTC'),
                ]);
                (new PilotImportAudit)->record($actor, $locked->id, 'pilot_workbook_discovered', [
                    'source_count' => $manifest['source_count'],
                    'record_count' => $manifest['record_count'],
                    'included_count' => $manifest['included_count'],
                    'excluded_count' => $manifest['excluded_count'],
                    'analysis_hash' => $manifest['analysis_hash'],
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update(['state' => 'FAILED', 'failure_code' => $exception instanceof ImportConflict ? $exception->getMessage() : 'pilot_analysis_failed', 'updated_at' => now('UTC')]);
            throw $exception;
        }
    }

    private function binding(string $namespace, array $source): array
    {
        $root = DB::table('wald_source_bindings')->where('identity_hash', Canonical::hash([$namespace, $source['kind'], $source['identity']]))->first();
        if (! $root) {
            return ['binding' => null, 'draft' => null];
        }
        $present = function (?object $version) use ($root): ?array {
            if (! $version) {
                return null;
            }
            $site = DB::table('sites')->where('id', $version->site_id)->where('is_active', true)->first();
            $customer = $site ? DB::table('customer_organisations')->where('id', $site->customer_organisation_id)->where('is_active', true)->first() : null;

            return $site && $customer ? [
                'uuid' => $root->uuid,
                'epoch' => (int) $root->epoch,
                'version' => (int) $version->version,
                'definition_hash' => $version->definition_hash,
                'site_uuid' => $site->uuid,
                'site_name' => $site->name,
                'customer_name' => $customer->name,
            ] : null;
        };
        $active = $root->active_version ? DB::table('wald_binding_versions')->where('binding_id', $root->id)->where('version', $root->active_version)->first() : null;
        $latest = DB::table('wald_binding_versions')->where('binding_id', $root->id)->where('version', $root->latest_version)->first();

        return ['binding' => $present($active), 'draft' => $present($latest)];
    }

    private function command(string $command): void
    {
        if (! Str::isUuid($command)) {
            throw new \InvalidArgumentException('command_uuid_required');
        }
    }
}
