<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Office decisions for rows left outside a confirmed CustomerCode group. */
final class UnknownRowsWorkflow
{
    public function resolveCode(User $actor, string $uploadUuid, string $code, string $customerUuid,
        string $siteUuid, string $manifestHash, int $epoch, string $command, bool $confirmBinding = false,
        string $excludedCsv = ''): array
    {
        if (! Str::isUuid($command) || trim($code) === '') {
            throw new ImportConflict('review_command_invalid');
        }
        if ($excludedCsv !== '' && ! preg_match('/^\d+(,\d+)*$/D', $excludedCsv)) {
            throw new ImportConflict('review_rows_invalid');
        }
        $excluded = $excludedCsv === '' ? [] : array_fill_keys(array_map('intval', explode(',', $excludedCsv)), true);

        return DB::transaction(function () use ($actor, $uploadUuid, $code, $customerUuid, $siteUuid, $manifestHash, $epoch, $command, $confirmBinding, $excluded): array {
            $fresh = (new PilotImportPolicy)->authorize($actor, true);
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $uploadUuid)->lockForUpdate()->firstOrFail();
            if (! in_array($upload->state, ['READY', 'NEEDS_CLARIFICATION'], true)
                || $upload->source_manifest_hash !== $manifestHash || (int) $upload->epoch !== $epoch
                || (int) DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)
                    ->where('export_order', $upload->export_order)->max('revision') !== (int) $upload->revision
                || DB::table('wald_pilot_selections')->where('pilot_upload_id', $upload->id)->exists()
                || (new CustomerCodeReviewWorkflow)->overview($fresh, $uploadUuid)['pending'] !== []) {
                throw new ImportConflict('unknown_review_not_available');
            }
            $rows = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->where('customer_code', $code)->where('disposition', 'UNKNOWN')
                ->orderBy('row_number')->lockForUpdate()->get();
            if ($rows->isEmpty() || $rows->contains(fn (object $row): bool => $row->customer_code !== $code
                || $row->source_identity_hash === null
                || $row->source_identity_hash !== $rows[0]->source_identity_hash)) {
                throw new ImportConflict('bulk_unknown_code_mismatch');
            }
            $knownRows = array_fill_keys($rows->pluck('row_number')->map(fn ($number): int => (int) $number)->all(), true);
            if (array_diff_key($excluded, $knownRows) || count($excluded) === $rows->count()) {
                throw new ImportConflict('review_rows_invalid');
            }
            $selectedRows = $rows->reject(fn (object $row): bool => isset($excluded[(int) $row->row_number]));
            $site = DB::table('sites')->join('customer_organisations', 'customer_organisations.id', '=', 'sites.customer_organisation_id')
                ->where('sites.uuid', $siteUuid)->where('customer_organisations.uuid', $customerUuid)
                ->where('sites.is_active', true)->where('customer_organisations.is_active', true)
                ->first(['sites.id', 'sites.uuid', 'sites.name as site_name', 'customer_organisations.id as customer_id',
                    'customer_organisations.name as customer_name']);
            $group = DB::table('wald_pilot_review_groups')->where('pilot_upload_id', $upload->id)
                ->where('source_identity_hash', $rows[0]->source_identity_hash)->orderByDesc('version')->first();
            if (! $site || ! $group || ($group->site_id !== null && ((int) $group->site_id !== (int) $site->id
                || (int) $group->customer_organisation_id !== (int) $site->customer_id))) {
                throw new ImportConflict('unknown_target_must_match_customer_code');
            }
            $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
            $source = collect($manifest['sources'])->firstWhere('hash', $rows[0]->source_identity_hash);
            if (! $source || $source['customer_code'] !== $code) {
                throw new ImportConflict('review_source_not_found');
            }
            (new OfficeBindingCorrection)->confirm($fresh, $upload, $source, $selectedRows, $site, $confirmBinding, $command);
            $resolved = 0;
            foreach ($selectedRows as $row) {
                $result = (new ReviewedPlotDerivation)->derive($row->raw_plot_ref, $site->customer_name, $site->site_name);
                $plot = $result['plot'];
                $reason = $result['issue'];
                if ($plot === null) {
                    $suggestion = (new HierarchySuggestion)->forIssue($code, $row->raw_plot_ref);
                    if ($suggestion && MasterSourceResolver::sameName($suggestion['customer'], $site->customer_name)
                        && MasterSourceResolver::sameName($suggestion['site'], $site->site_name)) {
                        $plot = $suggestion['plot'];
                        $reason = 'OFFICE_CONFIRMED_EXACT_SOURCE_SUGGESTION';
                    }
                }
                if ($plot === null) {
                    continue;
                }
                $resolved++;
                DB::table('wald_pilot_review_rows')->where('id', $row->id)->update([
                    'disposition' => 'CONFIRMED', 'customer_organisation_id' => $site->customer_id,
                    'site_id' => $site->id, 'confirmed_plot' => $plot, 'issue' => $reason,
                    'decision_actor_id' => $fresh->id, 'decision_at' => now('UTC'), 'updated_at' => now('UTC'),
                ]);
                DB::table('wald_pilot_review_decisions')->insert([
                    'pilot_review_row_id' => $row->id, 'actor_id' => $fresh->id, 'actor_name' => $fresh->name,
                    'disposition' => 'CONFIRMED', 'customer_organisation_id' => $site->customer_id,
                    'site_id' => $site->id, 'plot_reference' => $plot, 'reason' => $reason,
                    'created_at' => now('UTC'),
                ]);
            }
            if ($resolved > 0) {
                $this->refreshSource($upload, $manifest, $rows[0]->source_identity_hash, $site);
            }
            $manifest['unclassified_count'] = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->whereIn('disposition', ['UNKNOWN', 'REVIEWED_MISSING_CODE'])->count();
            $hash = Canonical::hash($manifest);
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
                'source_manifest' => Canonical::json($manifest), 'source_manifest_hash' => $hash,
                'epoch' => (int) $upload->epoch + 1, 'updated_at' => now('UTC'),
            ]);
            (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_unknown_code_bulk_reviewed', [
                'customer_code' => $code, 'source_identity_hash' => $rows[0]->source_identity_hash,
                'customer_id' => $site->customer_id, 'site_id' => $site->id,
                'resolved_rows' => $resolved, 'still_unknown_rows' => $rows->count() - $resolved,
                'office_unticked_rows' => array_keys($excluded),
                'source_manifest_hash' => $hash,
            ], command: $command);

            return ['resolved' => $resolved, 'unknown' => $rows->count() - $resolved];
        }, 3);
    }

    public function resolve(User $actor, string $uploadUuid, int $rowNumber, string $customerUuid,
        string $siteUuid, ?string $manualPlot, string $manifestHash, int $epoch, string $command): void
    {
        $this->decide($actor, $uploadUuid, $rowNumber, 'RESOLVE', $customerUuid, $siteUuid, $manualPlot, null, $manifestHash, $epoch, $command);
    }

    public function exclude(User $actor, string $uploadUuid, int $rowNumber, string $reason,
        string $manifestHash, int $epoch, string $command): void
    {
        $this->decide($actor, $uploadUuid, $rowNumber, 'EXCLUDE', null, null, null, $reason, $manifestHash, $epoch, $command);
    }

    private function decide(User $actor, string $uploadUuid, int $rowNumber, string $action,
        ?string $customerUuid, ?string $siteUuid, ?string $manualPlot, ?string $reason,
        string $manifestHash, int $epoch, string $command): void
    {
        if (! Str::isUuid($command)) {
            throw new ImportConflict('review_command_invalid');
        }
        DB::transaction(function () use ($actor, $uploadUuid, $rowNumber, $action,
            $customerUuid, $siteUuid, $manualPlot, $reason, $manifestHash, $epoch, $command): void {
            $fresh = (new PilotImportPolicy)->authorize($actor, true);
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $uploadUuid)->lockForUpdate()->firstOrFail();
            if (! in_array($upload->state, ['READY', 'NEEDS_CLARIFICATION'], true)
                || $upload->source_manifest_hash !== $manifestHash || (int) $upload->epoch !== $epoch
                || (int) DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)
                    ->where('export_order', $upload->export_order)->max('revision') !== (int) $upload->revision
                || DB::table('wald_pilot_selections')->where('pilot_upload_id', $upload->id)->exists()
                || (new CustomerCodeReviewWorkflow)->overview($fresh, $uploadUuid)['pending'] !== []) {
                throw new ImportConflict('unknown_review_not_available');
            }
            $row = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->where('row_number', $rowNumber)->lockForUpdate()->first();
            if (! $row || ! in_array($row->disposition, ['UNKNOWN', 'REVIEWED_MISSING_CODE'], true)) {
                throw new ImportConflict('unknown_row_not_found');
            }
            $site = null;
            $plot = null;
            $disposition = 'EXCLUDED';
            $issue = $reason;
            if ($action === 'RESOLVE') {
                $site = DB::table('sites')->join('customer_organisations', 'customer_organisations.id', '=', 'sites.customer_organisation_id')
                    ->where('sites.uuid', $siteUuid)->where('customer_organisations.uuid', $customerUuid)
                    ->where('sites.is_active', true)->where('customer_organisations.is_active', true)
                    ->first(['sites.id', 'sites.uuid', 'sites.name as site_name', 'customer_organisations.id as customer_id',
                        'customer_organisations.name as customer_name']);
                if (! $site) {
                    throw new ImportConflict('review_target_invalid');
                }
                if ($row->source_identity_hash !== null) {
                    $group = DB::table('wald_pilot_review_groups')->where('pilot_upload_id', $upload->id)
                        ->where('source_identity_hash', $row->source_identity_hash)->orderByDesc('version')->first();
                    if (! $group || ($group->site_id !== null && ((int) $group->site_id !== (int) $site->id
                        || (int) $group->customer_organisation_id !== (int) $site->customer_id))) {
                        throw new ImportConflict('unknown_target_must_match_customer_code');
                    }
                }
                if ($manualPlot !== null && trim($manualPlot) !== '') {
                    $plot = $this->manualPlot($manualPlot);
                    $issue = 'OFFICE_MANUAL_PLOT';
                } else {
                    $result = (new ReviewedPlotDerivation)->derive($row->raw_plot_ref, $site->customer_name, $site->site_name);
                    $plot = $result['plot'];
                    $issue = $result['issue'];
                    if ($plot === null) {
                        throw new ImportConflict('unknown_plot_needs_manual_value');
                    }
                }
                $disposition = $row->customer_code === null ? 'REVIEWED_MISSING_CODE' : 'CONFIRMED';
                if ($disposition === 'REVIEWED_MISSING_CODE') {
                    $issue = 'CUSTOMER_CODE_MISSING';
                }
            } elseif (trim((string) $reason) === '' || mb_strlen($reason) > 100) {
                throw new ImportConflict('unknown_exclusion_reason_required');
            }
            DB::table('wald_pilot_review_rows')->where('id', $row->id)->update([
                'disposition' => $disposition,
                'customer_organisation_id' => $site?->customer_id,
                'site_id' => $site?->id,
                'confirmed_plot' => $plot,
                'issue' => $issue,
                'decision_actor_id' => $fresh->id,
                'decision_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            DB::table('wald_pilot_review_decisions')->insert([
                'pilot_review_row_id' => $row->id, 'actor_id' => $fresh->id,
                'actor_name' => $fresh->name, 'disposition' => $disposition,
                'customer_organisation_id' => $site?->customer_id, 'site_id' => $site?->id,
                'plot_reference' => $plot, 'reason' => $issue, 'created_at' => now('UTC'),
            ]);
            $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
            if ($disposition === 'CONFIRMED') {
                $this->refreshSource($upload, $manifest, $row->source_identity_hash, $site);
            }
            $manifest['unclassified_count'] = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->whereIn('disposition', ['UNKNOWN', 'REVIEWED_MISSING_CODE'])->count();
            $manifest['office_excluded_count'] = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
                ->where('disposition', 'EXCLUDED')->count();
            $manifest['excluded_count'] = (int) ($manifest['dictionary_excluded_count'] ?? $manifest['excluded_count'])
                + $manifest['office_excluded_count'];
            $hash = Canonical::hash($manifest);
            DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
                'source_manifest' => Canonical::json($manifest), 'source_manifest_hash' => $hash,
                'epoch' => (int) $upload->epoch + 1, 'updated_at' => now('UTC'),
            ]);
            (new PilotImportAudit)->record($fresh, $upload->id,
                $action === 'EXCLUDE' ? 'pilot_unknown_row_excluded' : 'pilot_unknown_row_reviewed', [
                    'row_number' => $rowNumber, 'call_no' => $row->call_no,
                    'source_identity_hash' => $row->source_identity_hash,
                    'disposition' => $disposition, 'customer_id' => $site?->customer_id,
                    'site_id' => $site?->id, 'plot' => $plot, 'reason' => $issue,
                    'source_manifest_hash' => $hash,
                ], command: $command);
        }, 3);
    }

    private function refreshSource(object $upload, array &$manifest, string $sourceHash, object $site): void
    {
        $index = collect($manifest['sources'])->search(fn (array $source): bool => $source['hash'] === $sourceHash);
        if ($index === false) {
            throw new ImportConflict('review_source_not_found');
        }
        $rows = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $upload->id)
            ->where('source_identity_hash', $sourceHash)->where('disposition', 'CONFIRMED')
            ->orderBy('row_number')->get(['row_number', 'confirmed_plot']);
        $source = &$manifest['sources'][$index];
        $source['rows'] = $rows->count();
        $source['plots'] = $rows->pluck('confirmed_plot')->unique()->values()->all();
        sort($source['plots'], SORT_NATURAL);
        $source['hierarchy'] = ['customer' => $site->customer_name, 'site' => $site->site_name,
            'valid_rows' => $rows->count(), 'invalid_rows' => 0, 'conflicting_rows' => 0];
        $source['hierarchy_issues'] = [];
        $source['hierarchy_variants'] = [[
            'customer' => $site->customer_name, 'site' => $site->site_name,
            'rows' => $rows->pluck('row_number')->map(fn ($value): int => (int) $value)->all(),
        ]];
        unset($source);
        $manifest['included_count'] = array_sum(array_map(fn (array $item): int => $item['rows'], $manifest['sources']));
        $stream = DB::table('wald_import_streams')->where('id', $upload->stream_id)->firstOrFail();
        $resolution = (new MasterSourceResolver)->resolve([$manifest['sources'][$index]], $stream->source_namespace)[$sourceHash];
        if (! in_array($resolution['state'], ['EXACT_EXISTING_BINDING', 'EXACT_CUSTOMER_EXACT_SITE'], true)
            || $resolution['site_uuid'] !== $site->uuid) {
            throw new ImportConflict('unknown_target_conflicts_with_binding');
        }
    }

    private function manualPlot(string $value): string
    {
        $value = SourceIdentity::plotReference($value);
        if (preg_match('/^Plot\s+(.+)$/iu', $value, $match)) {
            $value = SourceIdentity::plotReference($match[1]);
        }
        if ($value === '' || mb_strlen($value) > 200 || preg_match('/[\x00-\x1f\x7f<>]/', $value)) {
            throw new ImportConflict('invalid_manual_plot');
        }

        return $value;
    }
}
