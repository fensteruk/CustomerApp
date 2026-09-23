<?php

namespace App\Services;

use App\Models\User;
use App\SourceImport\Integration\BackendStore;
use App\SourceImport\Integration\PilotImportPolicy;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only, bounded presentation of master uploads and their selected-site progress. */
final class OfficeImportsWorkspaceQuery
{
    public const FILTERS = ['all' => 'All', 'applied' => 'Applied', 'failed' => 'Failed', 'attention' => 'Needs attention'];

    public function forOffice(User $actor, string $filter = 'all'): array
    {
        (new PilotImportPolicy)->authorize($actor);
        $recent = $this->uploads()->orderByDesc('uploads.created_at')->orderByDesc('uploads.id')->limit(3)->get();
        $history = $this->uploads()->whereNotIn('uploads.id', $recent->pluck('id'));
        if ($filter === 'applied') {
            $history->where('progress.applied', '>', 0);
        } elseif ($filter === 'failed') {
            $history->where(fn (Builder $q) => $q->where('uploads.state', 'FAILED')->orWhere('progress.failed', '>', 0));
        } elseif ($filter === 'attention') {
            $history->where(fn (Builder $q) => $q->whereIn('uploads.state', ['FAILED', 'NEEDS_CLARIFICATION'])
                ->orWhere('progress.failed', '>', 0)->orWhere('progress.clarification', '>', 0)
                ->orWhere('progress.blocked', '>', 0)->orWhere('refresh.needed', '>', 0)
                ->orWhere('preview_issues.issues', '>', 0));
        }
        $history = $history->orderByDesc('uploads.created_at')->orderByDesc('uploads.id')
            ->paginate(10, ['*'], 'history_page')->appends(['history_filter' => $filter])->fragment('import-history');

        return [
            'recentImports' => $recent->map(fn (object $upload): array => $this->present($upload, true)),
            'importHistory' => $history->through(fn (object $upload): array => $this->present($upload, false)),
            'historyFilter' => $filter,
            'historyFilters' => self::FILTERS,
        ];
    }

    private function selections(): Builder
    {
        return DB::table('wald_pilot_selections as selections')
            ->join('wald_import_runs as runs', 'runs.id', '=', 'selections.run_id')
            ->leftJoin('wald_import_stages as stages', 'stages.id', '=', 'runs.stage_id')
            ->leftJoin('wald_import_previews as previews', 'previews.id', '=', 'runs.preview_id');
    }

    private function uploads(): Builder
    {
        $progress = $this->selections()
            ->leftJoin('wald_import_receipts as receipts', 'receipts.run_id', '=', 'runs.id')
            ->select('selections.pilot_upload_id')->selectRaw('COUNT(*) as selected, COUNT(receipts.id) as applied')
            ->selectRaw('SUM(COALESCE(stages.blocked_count, 0)) as blocked')
            ->selectRaw('MAX(runs.stage_id) as sample_stage_id');
        foreach (['FAILED' => 'failed', 'NEEDS_CLARIFICATION' => 'clarification', 'ANALYSING' => 'analysing',
            'REQUIRES_REVIEW' => 'review', 'REVIEWED' => 'previewed', 'READY_TO_COMMIT' => 'approved', 'UPLOADED' => 'uploaded'] as $state => $alias) {
            $progress->selectRaw("SUM(CASE WHEN runs.state = ? THEN 1 ELSE 0 END) as {$alias}", [$state]);
        }
        $progress->groupBy('selections.pilot_upload_id');
        $pins = (new KnowledgeIdentity)->current();
        $refresh = $this->selections()->whereIn('runs.state', ['REQUIRES_REVIEW', 'REVIEWED', 'READY_TO_COMMIT'])
            ->where(function (Builder $q) use ($pins): void {
                $q->whereNull('stages.id')->orWhereNull('stages.manifest->pins->fingerprint')
                    ->orWhereNull('stages.manifest->pins->dictionary')
                    ->orWhereNull('stages.manifest->integration->application')
                    ->orWhere('stages.manifest->pins->fingerprint', '!=', $pins['fingerprint'])
                    ->orWhere('stages.manifest->pins->dictionary', '!=', $pins['dictionary'])
                    ->orWhere('stages.manifest->integration->application', '!=', BackendStore::IDENTITY['application'])
                    ->orWhere('previews.expires_at', '<=', now('UTC'));
            })->select('selections.pilot_upload_id')->selectRaw('COUNT(*) as needed')->groupBy('selections.pilot_upload_id');
        $issues = $this->selections()->whereIn('runs.state', ['REVIEWED', 'READY_TO_COMMIT'])
            ->whereJsonLength('previews.payload->blockers', '>', 0)
            ->select('selections.pilot_upload_id')->selectRaw('COUNT(*) as issues')->groupBy('selections.pilot_upload_id');

        return DB::table('wald_pilot_uploads as uploads')
            ->leftJoinSub($progress, 'progress', 'progress.pilot_upload_id', '=', 'uploads.id')
            ->leftJoinSub($refresh, 'refresh', 'refresh.pilot_upload_id', '=', 'uploads.id')
            ->leftJoinSub($issues, 'preview_issues', 'preview_issues.pilot_upload_id', '=', 'uploads.id')
            ->select(['uploads.id', 'uploads.uuid', 'uploads.stream_id', 'uploads.export_order', 'uploads.export_date',
                'uploads.export_slot', 'uploads.revision', 'uploads.state', 'uploads.created_at', 'uploads.uploader_name',
                'uploads.source_manifest', 'uploads.source_manifest_hash', 'progress.*', 'refresh.needed', 'preview_issues.issues'])
            ->selectSub(DB::table('wald_pilot_uploads as newer')->selectRaw('COUNT(*)')
                ->whereColumn('newer.stream_id', 'uploads.stream_id')->whereColumn('newer.export_order', 'uploads.export_order')
                ->whereColumn('newer.revision', '>', 'uploads.revision'), 'newer_revisions');
    }

    private function present(object $upload, bool $withPreview): array
    {
        $manifest = $this->manifest($upload);
        $sources = collect($manifest['sources'] ?? []);
        $siteCount = isset($manifest['source_count']) ? (int) $manifest['source_count'] : null;
        $source = $siteCount === 1 ? $sources->first() : null;
        $title = $source ? implode(' — ', array_filter([$source['customer_code'] ?? null, $source['site_name'] ?? null])) : '';
        if ($siteCount > 1) {
            $title = $siteCount >= 50 ? 'RedZebra master export' : $siteCount.'-site RedZebra export';
        }
        [$status, $action, $tone] = $this->status($upload, $siteCount);
        $rows = $withPreview && $siteCount === 1 && $upload->sample_stage_id && ! $upload->needed
            ? $this->sampleRows((int) $upload->sample_stage_id) : collect();
        $plotCount = $rows->isNotEmpty() ? $rows->filter(fn (array $row): bool => ! $row['excluded'])
            ->pluck('facts.plot')->filter(fn ($plot): bool => $plot !== null && $plot !== '')->unique()->count() : null;
        $preview = $rows->filter(fn (array $row): bool => ! $row['excluded'])->take(5)->map(fn (array $row): array => [
            'plot' => $row['facts']['plot'] ?? 'Not resolved',
            'call_type' => $row['provenance']['raw_call_type'] ?? 'No call-off',
            'service' => match ($row['facts']['service'] ?? null) {
                'windows' => 'Windows', 'cavity_closers' => 'Cavity Closers', 'cml' => 'CML',
                'snagging' => 'Snagging', default => $row['issues'] ? 'Needs review' : 'No call-off started',
            },
        ])->values();

        return [
            'uuid' => $upload->uuid, 'title' => $title ?: 'RedZebra export', 'created_at' => $upload->created_at,
            'export_date' => $upload->export_date, 'export_slot' => $upload->export_slot, 'revision' => (int) $upload->revision,
            'uploader' => $upload->uploader_name, 'status' => $status, 'action' => $action, 'tone' => $tone,
            'url' => route('office.workspace.pilot-import.show', $upload->uuid),
            'type' => match (true) {
                $siteCount === 1 => 'Single-site export', $siteCount >= 50 => 'Master export', $siteCount > 1 => 'Multi-site export', default => 'Workbook upload'
            },
            'rows' => $manifest['record_count'] ?? null, 'sites' => $siteCount, 'plots' => $plotCount,
            'included' => $manifest['included_count'] ?? null, 'excluded' => $manifest['excluded_count'] ?? null,
            'blocked_rows' => $upload->sample_stage_id ? (int) $upload->blocked : null,
            'applied' => (int) ($upload->applied ?? 0), 'selected' => (int) ($upload->selected ?? 0),
            'needs_refresh' => (bool) $upload->needed, 'preview_issues' => (int) ($upload->issues ?? 0),
            'preview_rows' => $preview,
            'preview_sites' => $sources->take(5)->map(fn (array $s): string => implode(' — ', array_filter([$s['customer_code'] ?? null, $s['site_name'] ?? null])) ?: 'Source site')->values(),
            'more_sites' => max(0, ($siteCount ?? 0) - 5),
        ];
    }

    private function status(object $u, ?int $sites): array
    {
        return match (true) {
            $u->newer_revisions > 0 => ['Superseded', 'View earlier revision', 'muted'],
            $u->state === 'FAILED' || $u->failed > 0 => ['Failed', 'View failed import', 'danger'],
            $u->needed > 0 => ['Needs fresh review', 'Continue review', 'warning'],
            $u->state === 'NEEDS_CLARIFICATION' || $u->clarification > 0 => ['Needs clarification', 'Resolve issue', 'warning'],
            $u->blocked > 0 || $u->issues > 0 => ['Needs attention', 'Resolve issue', 'warning'],
            $u->analysing > 0 => ['Analysing', 'View analysis', 'info'],
            $sites > 0 && (int) $u->applied === $sites => ['Applied', 'View result', 'success'],
            $u->approved > 0 => ['Ready to apply', 'Continue to apply', 'success'],
            $u->previewed > 0 => ['Ready to approve', 'Review preview', 'success'],
            $u->review > 0 => ['Ready to review', 'Continue review', 'success'],
            $u->uploaded > 0 => ['Uploaded', 'Continue analysis', 'info'],
            $u->applied > 0 => ['Partially applied', 'Continue review', 'info'],
            $u->state === 'READY' || $u->state === 'IN_PROGRESS' => ['Ready to select a site', 'Review source sites', 'info'],
            default => ['Uploaded', 'View import', 'muted'],
        };
    }

    private function manifest(object $upload): array
    {
        $manifest = json_decode($upload->source_manifest ?? 'null', true);

        return is_array($manifest) && hash_equals($upload->source_manifest_hash ?? '', Canonical::hash($manifest)) ? $manifest : [];
    }

    private function sampleRows(int $stage): Collection
    {
        return DB::table('wald_staged_rows')->where('stage_id', $stage)->orderBy('ordinal')->limit(BackendStore::MAX_ROWS)
            ->get()->map(fn (object $row): array => (new BackendStore)->payload($row));
    }
}
