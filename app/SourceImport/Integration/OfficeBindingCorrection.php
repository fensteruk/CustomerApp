<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit Office correction of an active binding using evidence retained in this upload. */
final class OfficeBindingCorrection
{
    public function confirm(User $actor, object $upload, array $source, Collection $selectedRows,
        object $site, bool $confirmed, string $command): ?array
    {
        $stream = DB::table('wald_import_streams')->where('id', $upload->stream_id)->firstOrFail();
        $root = DB::table('wald_source_bindings')->where('identity_hash', Canonical::hash([
            $stream->source_namespace, $source['kind'], $source['identity'],
        ]))->lockForUpdate()->first();
        if (! $root || $root->active_version === null) {
            return null;
        }
        $previous = DB::table('wald_binding_versions')->where('binding_id', $root->id)
            ->where('version', $root->active_version)->firstOrFail();
        if ((int) $previous->customer_organisation_id === (int) $site->customer_id
            && (int) $previous->site_id === (int) $site->id) {
            return null;
        }
        if (! $confirmed) {
            throw new ImportConflict('binding_confirmation_required');
        }
        if ($selectedRows->isEmpty() || $selectedRows->contains(fn (object $row): bool => $row->parsed_customer !== null && $row->parsed_site !== null
            && (! MasterSourceResolver::sameName($row->parsed_customer, $site->customer_name)
                || ! MasterSourceResolver::sameName($row->parsed_site, $site->site_name)))
            || ! $selectedRows->contains(fn (object $row): bool => $row->parsed_customer !== null && $row->parsed_site !== null
                && MasterSourceResolver::sameName($row->parsed_customer, $site->customer_name)
                && MasterSourceResolver::sameName($row->parsed_site, $site->site_name))) {
            throw new ImportConflict('binding_source_evidence_conflict');
        }
        $sourceRow = $selectedRows->first(fn (object $row): bool => $row->parsed_customer !== null && $row->parsed_site !== null);
        $evidence = ['upload' => $upload->uuid, 'revision' => (int) $upload->revision,
            'review_command' => $command,
            'discovery_generation' => PilotWorkbookDiscovery::SCHEMA,
            'source_manifest_hash' => $upload->source_manifest_hash,
            'source_identity_hash' => $source['hash'],
            'source_customer' => $sourceRow->parsed_customer, 'source_site' => $sourceRow->parsed_site,
            'target_customer' => $site->customer_name, 'target_site' => $site->site_name,
            'selected_rows' => $selectedRows->pluck('row_number')->map(fn ($number): int => (int) $number)->all()];
        $scope = new KnowledgeScope((int) $site->customer_id, (int) $site->id,
            $stream->source_namespace, $stream->workbook_family);
        $result = (new SourceBindingService)->correct($actor, $scope, $source['kind'], $source['identity'],
            $root->uuid, (int) $root->epoch, $evidence, $command);
        (new PilotImportAudit)->record($actor, $upload->id, 'pilot_source_binding_corrected', [
            ...$evidence, 'binding' => $root->uuid,
            'previous_version' => (int) $root->active_version,
            'previous_customer_id' => (int) $previous->customer_organisation_id,
            'previous_site_id' => (int) $previous->site_id,
            'new_version' => $result['version'],
            'new_customer_id' => (int) $site->customer_id, 'new_site_id' => (int) $site->id,
        ], command: (string) Str::uuid());

        return $result;
    }
}
