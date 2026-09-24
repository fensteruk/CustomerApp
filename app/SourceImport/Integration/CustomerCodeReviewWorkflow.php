<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Upload-scoped manual exception layer over the existing exact source resolver. */
final class CustomerCodeReviewWorkflow
{
    private const AUTOMATIC = ['EXACT_EXISTING_BINDING', 'EXACT_CUSTOMER_EXACT_SITE'];

    public function overview(User $actor, string $uploadUuid): array
    {
        $summary = (new PilotImportWorkflow)->summary($actor, $uploadUuid);
        $confirmed = DB::table('wald_pilot_review_groups')->where('pilot_upload_id', $summary['id'])
            ->orderBy('id')->get(['source_identity_hash', 'version', 'site_id'])->keyBy('source_identity_hash');
        $created = [];
        foreach (DB::table('wald_pilot_events')->where('pilot_upload_id', $summary['id'])
            ->where('action', 'pilot_hierarchy_creation_approved')->get(['payload']) as $event) {
            $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
            $created[$payload['source_identity_hash']] = true;
        }
        $manual = [];
        $automatic = [];
        foreach ($summary['sources'] as $source) {
            if (($source['hierarchy_mode'] ?? null) !== 'COMPOSITE') {
                continue;
            }
            $hash = $source['hash'];
            $reviewed = isset($confirmed[$hash]);
            if (in_array($source['resolution']['state'], self::AUTOMATIC, true)
                && ! $reviewed && ! isset($created[$hash])) {
                $automatic[] = $source;
            } else {
                $manual[] = [
                    ...$source,
                    'reviewed' => $reviewed,
                    'review_version' => (int) ($confirmed[$hash]->version ?? 0),
                ];
            }
        }
        usort($manual, fn (array $a, array $b): int => strnatcasecmp($a['customer_code'] ?? '', $b['customer_code'] ?? ''));
        $pending = array_values(array_filter($manual, fn (array $source): bool => ! $source['reviewed']));

        return [
            'import' => $summary,
            'manual' => $manual,
            'pending' => $pending,
            'automatic' => $automatic,
            'reviewed_count' => count($manual) - count($pending),
            'deferred_count' => $confirmed->filter(fn (object $group): bool => $group->site_id === null)->count(),
            'total_manual' => count($manual),
            'unknown_count' => DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $summary['id'])
                ->where('disposition', 'UNKNOWN')->count(),
        ];
    }

    public function confirm(User $actor, string $uploadUuid, string $sourceHash, ?string $customerUuid,
        ?string $siteUuid, string $excludedCsv, string $manifestHash, int $epoch, string $command,
        bool $deferAll = false): void
    {
        if (! Str::isUuid($command) || ! preg_match('/^[a-f0-9]{64}$/D', $sourceHash)
            || ! preg_match('/^[a-f0-9]{64}$/D', $manifestHash)) {
            throw new ImportConflict('review_command_invalid');
        }
        $excluded = $this->excludedRows($excludedCsv);
        DB::transaction(function () use ($actor, $uploadUuid, $sourceHash, $customerUuid, $siteUuid,
            $excluded, $manifestHash, $epoch, $command, $deferAll): void {
            $fresh = (new PilotImportPolicy)->authorize($actor, true);
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $uploadUuid)->lockForUpdate()->firstOrFail();
            if (! in_array($upload->state, ['READY', 'NEEDS_CLARIFICATION'], true)
                || $upload->source_manifest_hash !== $manifestHash || (int) $upload->epoch !== $epoch
                || (int) DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)
                    ->where('export_order', $upload->export_order)->max('revision') !== (int) $upload->revision
                || DB::table('wald_pilot_selections')->where('pilot_upload_id', $upload->id)->exists()) {
                throw new ImportConflict('review_stale_or_site_selected');
            }
            $site = $deferAll ? null : DB::table('sites')
                ->join('customer_organisations', 'customer_organisations.id', '=', 'sites.customer_organisation_id')
                ->where('sites.uuid', $siteUuid)->where('customer_organisations.uuid', $customerUuid)
                ->where('sites.is_active', true)->where('customer_organisations.is_active', true)
                ->first(['sites.id', 'sites.name as site_name', 'customer_organisations.id as customer_id',
                    'customer_organisations.name as customer_name']);
            if (! $deferAll && ! $site) {
                throw new ImportConflict('review_target_invalid');
            }
            $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['schema'] ?? null) !== PilotWorkbookDiscovery::SCHEMA) {
                throw new ImportConflict('pilot_source_manifest_stale');
            }
            $sourceIndex = collect($manifest['sources'])->search(fn (array $source): bool => $source['hash'] === $sourceHash);
            if ($sourceIndex === false || ($manifest['sources'][$sourceIndex]['hierarchy_mode'] ?? null) !== 'COMPOSITE') {
                throw new ImportConflict('review_source_not_found');
            }
            $rows = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->where('source_identity_hash', $sourceHash)->orderBy('row_number')->lockForUpdate()->get();
            if ($rows->isEmpty() || count($excluded) > $rows->count()) {
                throw new ImportConflict('review_rows_invalid');
            }
            if ($deferAll) {
                $excluded = array_fill_keys($rows->pluck('row_number')->map(fn ($number): int => (int) $number)->all(), true);
            }
            if ($rows->contains(fn (object $row): bool => $row->disposition === 'EXCLUDED'
                || ($row->disposition === 'CONFIRMED' && $row->issue === 'OFFICE_MANUAL_PLOT'))
                || DB::table('wald_pilot_events')->where('pilot_upload_id', $upload->id)
                    ->whereIn('action', ['pilot_unknown_row_reviewed', 'pilot_unknown_row_excluded',
                        'pilot_unknown_code_bulk_reviewed'])->where('payload', 'like', '%'.$sourceHash.'%')->exists()) {
                throw new ImportConflict('review_group_has_unknown_decisions');
            }
            $knownRows = array_fill_keys($rows->pluck('row_number')->map(fn ($value): int => (int) $value)->all(), true);
            if (array_diff_key($excluded, $knownRows)) {
                throw new ImportConflict('review_rows_invalid');
            }
            $plots = [];
            $selectedNumbers = [];
            $unknown = 0;
            foreach ($rows as $row) {
                $reason = null;
                $plot = null;
                if (isset($excluded[(int) $row->row_number])) {
                    $reason = 'OFFICE_UNTICKED';
                } elseif ($row->parsed_plot !== null
                    && MasterSourceResolver::sameName($row->parsed_customer, $site->customer_name)
                    && MasterSourceResolver::sameName($row->parsed_site, $site->site_name)) {
                    $plot = $row->parsed_plot;
                } else {
                    $result = (new ReviewedPlotDerivation)->derive($row->raw_plot_ref, $site->customer_name, $site->site_name);
                    $plot = $result['plot'];
                    $reason = $result['issue'];
                    if ($plot === null) {
                        $suggestion = (new HierarchySuggestion)->forIssue((string) $row->customer_code, $row->raw_plot_ref);
                        if ($suggestion && MasterSourceResolver::sameName($suggestion['customer'], $site->customer_name)
                            && MasterSourceResolver::sameName($suggestion['site'], $site->site_name)) {
                            $plot = $suggestion['plot'];
                            $reason = 'OFFICE_CONFIRMED_EXACT_SOURCE_SUGGESTION';
                        }
                    }
                }
                $disposition = $plot === null ? 'UNKNOWN' : 'CONFIRMED';
                if ($plot === null) {
                    $unknown++;
                } else {
                    $plots[$plot] = true;
                    $selectedNumbers[] = (int) $row->row_number;
                }
                DB::table('wald_pilot_review_rows')->where('id', $row->id)->update([
                    'disposition' => $disposition,
                    'customer_organisation_id' => $plot === null ? null : $site->customer_id,
                    'site_id' => $plot === null ? null : $site->id,
                    'confirmed_plot' => $plot,
                    'issue' => $reason,
                    'decision_actor_id' => $fresh->id,
                    'decision_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
                DB::table('wald_pilot_review_decisions')->insert([
                    'pilot_review_row_id' => $row->id,
                    'actor_id' => $fresh->id,
                    'actor_name' => $fresh->name,
                    'disposition' => $disposition,
                    'customer_organisation_id' => $plot === null ? null : $site->customer_id,
                    'site_id' => $plot === null ? null : $site->id,
                    'plot_reference' => $plot,
                    'reason' => $reason,
                    'created_at' => now('UTC'),
                ]);
            }
            $source = &$manifest['sources'][$sourceIndex];
            $source['rows'] = count($selectedNumbers);
            $source['hierarchy'] = ['customer' => $site?->customer_name, 'site' => $site?->site_name,
                'valid_rows' => count($selectedNumbers), 'invalid_rows' => 0, 'conflicting_rows' => 0];
            $source['hierarchy_issues'] = [];
            $source['hierarchy_variants'] = $selectedNumbers === [] ? [] : [[
                'customer' => $site->customer_name, 'site' => $site->site_name, 'rows' => $selectedNumbers,
            ]];
            $source['plots'] = array_keys($plots);
            sort($source['plots'], SORT_NATURAL);
            $source['warnings'] = array_values(array_diff($source['warnings'] ?? [],
                ['SOURCE_HIERARCHY_INVALID', 'SOURCE_HIERARCHY_CONFLICT']));
            unset($source);
            $manifest['included_count'] = array_sum(array_map(fn (array $item): int => $item['rows'], $manifest['sources']));
            $manifest['unclassified_count'] = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->whereIn('disposition', ['UNKNOWN', 'REVIEWED_MISSING_CODE'])->count();
            $stream = DB::table('wald_import_streams')->where('id', $upload->stream_id)->firstOrFail();
            $resolved = (new MasterSourceResolver)->resolve([$manifest['sources'][$sourceIndex]], $stream->source_namespace)[$sourceHash];
            if ($selectedNumbers !== [] && ! in_array($resolved['state'], self::AUTOMATIC, true)) {
                throw new ImportConflict('review_target_conflicts_with_binding');
            }
            $newHash = Canonical::hash($manifest);
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
                'source_manifest' => Canonical::json($manifest), 'source_manifest_hash' => $newHash,
                'epoch' => $epoch + 1, 'updated_at' => now('UTC'),
            ]);
            $version = (int) DB::table('wald_pilot_review_groups')->where('pilot_upload_id', $upload->id)
                ->where('source_identity_hash', $sourceHash)->max('version') + 1;
            DB::table('wald_pilot_review_groups')->insert([
                'pilot_upload_id' => $upload->id, 'source_identity_hash' => $sourceHash,
                'version' => $version, 'customer_organisation_id' => $site?->customer_id,
                'site_id' => $site?->id, 'confirmed_rows' => count($selectedNumbers), 'unknown_rows' => $unknown,
                'source_manifest_hash' => $newHash, 'actor_id' => $fresh->id,
                'actor_name' => $fresh->name, 'created_at' => now('UTC'),
            ]);
            (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_customer_code_confirmed', [
                'source_identity_hash' => $sourceHash, 'version' => $version,
                'customer_id' => $site?->customer_id, 'site_id' => $site?->id,
                'deferred_all' => $deferAll,
                'confirmed_rows' => count($selectedNumbers), 'unknown_rows' => $unknown,
                'source_manifest_hash' => $newHash,
            ], command: $command);
        }, 3);
    }

    private function excludedRows(string $csv): array
    {
        if ($csv === '') {
            return [];
        }
        if (strlen($csv) > 30000 || ! preg_match('/^[0-9]+(?:,[0-9]+)*$/D', $csv)) {
            throw new ImportConflict('review_rows_invalid');
        }

        return array_fill_keys(array_map('intval', explode(',', $csv)), true);
    }
}
