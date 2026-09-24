<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Upload-scoped, append-only Office answers for ambiguous composite source cells. */
final class HierarchyClarifications
{
    public function all(int $uploadId): array
    {
        $answers = [];
        foreach (DB::table('wald_pilot_hierarchy_answers')->where('pilot_upload_id', $uploadId)->orderBy('id')->get() as $row) {
            $answers[$row->source_identity_hash][$row->raw_hash] = [
                'customer' => $row->customer_name, 'site' => $row->site_name,
                'plot' => $row->plot_reference, 'actor' => $row->actor_name,
            ];
        }

        return $answers;
    }

    public function resolve(array $source, array $answers): array
    {
        if (($source['hierarchy_mode'] ?? null) !== 'COMPOSITE') {
            return $source;
        }
        foreach ($source['hierarchy_issues'] ?? [] as &$issue) {
            $answer = $answers[$issue['hash']] ?? null;
            if ($answer === null) {
                continue;
            }
            $issue['answer'] = $answer;
            $source['plots'][] = $answer['plot'];
            $source['hierarchy_variants'][] = ['customer' => $answer['customer'], 'site' => $answer['site'], 'rows' => $issue['rows']];
            $count = count($issue['rows']);
            $source['hierarchy']['invalid_rows'] -= $count;
            $source['hierarchy']['valid_rows'] += $count;
            if ($source['hierarchy']['customer'] === null) {
                $source['hierarchy']['customer'] = $answer['customer'];
                $source['hierarchy']['site'] = $answer['site'];
            } elseif (! MasterSourceResolver::sameName($source['hierarchy']['customer'], $answer['customer'])
                || ! MasterSourceResolver::sameName($source['hierarchy']['site'], $answer['site'])) {
                $source['hierarchy']['conflicting_rows'] += $count;
            }
        }
        unset($issue);
        $source['plots'] = array_values(array_unique($source['plots'] ?? []));

        return $source;
    }

    public function answer(User $actor, string $uploadUuid, string $sourceHash, string $rawHash,
        string $customer, string $site, string $plot, string $command): void
    {
        $customer = trim($customer);
        $site = trim($site);
        $plot = SourceIdentity::plotReference($plot);
        if (preg_match('/^Plot\s+(.+)$/iu', $plot, $match)) {
            $plot = SourceIdentity::plotReference($match[1]);
        }
        if (! Str::isUuid($command)) {
            throw new ImportConflict('command_uuid_required');
        }
        foreach ([$customer, $site, $plot] as $value) {
            if ($value === '' || mb_strlen($value) > 200 || preg_match('/[\x00-\x1f\x7f<>]/', $value)) {
                throw new ImportConflict('invalid_hierarchy_answer');
            }
        }
        DB::transaction(function () use ($actor, $uploadUuid, $sourceHash, $rawHash, $customer, $site, $plot, $command): void {
            $fresh = (new PilotImportPolicy)->authorize($actor, true);
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $uploadUuid)->lockForUpdate()->firstOrFail();
            if (! in_array($upload->state, ['READY', 'NEEDS_CLARIFICATION'], true)
                || DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)
                    ->where('export_order', $upload->export_order)->max('revision') !== $upload->revision) {
                throw new ImportConflict('pilot_upload_not_selectable');
            }
            $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
            $source = collect($manifest['sources'] ?? [])->firstWhere('hash', $sourceHash);
            if (! $source || ($source['hierarchy_mode'] ?? null) !== 'COMPOSITE'
                || ! collect($source['hierarchy_issues'] ?? [])->contains('hash', $rawHash)) {
                throw new ImportConflict('hierarchy_issue_not_found');
            }
            if (DB::table('wald_pilot_selections')->where('pilot_upload_id', $upload->id)
                ->where('source_identity_hash', $sourceHash)->exists()) {
                throw new ImportConflict('hierarchy_answer_after_selection');
            }
            $issue = collect($source['hierarchy_issues'])->firstWhere('hash', $rawHash);
            $suggested = (new HierarchySuggestion)->forIssue((string) ($source['customer_code'] ?? ''), $issue['raw']);
            if ($suggested !== null) {
                $plot = $suggested['plot'];
            }
            $last = $this->all($upload->id)[$sourceHash][$rawHash] ?? null;
            if ($last && $last['customer'] === $customer && $last['site'] === $site && $last['plot'] === $plot) {
                return;
            }
            DB::table('wald_pilot_hierarchy_answers')->insert([
                'pilot_upload_id' => $upload->id, 'source_identity_hash' => $sourceHash, 'raw_hash' => $rawHash,
                'customer_name' => $customer, 'site_name' => $site, 'plot_reference' => $plot,
                'actor_id' => $fresh->id, 'actor_name' => $fresh->name, 'created_at' => now('UTC'),
            ]);
            (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_hierarchy_answered', [
                'source_identity_hash' => $sourceHash, 'raw_hash' => $rawHash,
                'customer' => $customer, 'site' => $site, 'plot' => $plot,
            ], command: $command);
        }, 3);
    }
}
