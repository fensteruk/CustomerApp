<?php

namespace App\Services\Reconciliation;

use App\Enums\CallOffServiceType;
use App\Models\User;
use App\SourceImport\Integration\PilotImportPolicy;
use App\SourceImport\Integration\PilotWorkbookDiscovery;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Live, read-only context. Source-date comparison remains unavailable per OVERHAUL08A. */
final class MasterReconciliationWorkspace
{
    public function __construct(private readonly PortalAmendmentReadModel $amendments = new PortalAmendmentReadModel) {}

    public function read(User $actor, string $uuid, array $filters = []): array
    {
        (new PilotImportPolicy)->authorize($actor);
        $upload = DB::table('wald_pilot_uploads')->where('uuid', $uuid)->firstOrFail();
        $stream = DB::table('wald_import_streams')->where('id', $upload->stream_id)->firstOrFail();
        $manifest = json_decode($upload->source_manifest ?? 'null', true);
        $valid = $this->validManifest($manifest, $upload->source_manifest_hash);
        $sources = collect($valid ? $manifest['sources'] : []);
        $hashes = $sources->filter(fn (array $s): bool => ($s['kind'] ?? '') === 'CUSTOMER_CODE' && is_string($s['identity'] ?? null) && trim($s['identity']) !== '')
            ->map(fn (array $s): string => Canonical::hash([$stream->source_namespace, 'CUSTOMER_CODE', $s['identity']]));
        $bindings = collect();
        foreach ($hashes->unique()->chunk(400) as $chunk) {
            $bindings = $bindings->concat(DB::table('wald_source_bindings as roots')
                ->join('wald_binding_versions as v', fn ($j) => $j->on('v.binding_id', '=', 'roots.id')->on('v.version', '=', 'roots.active_version'))
                ->join('sites as s', fn ($j) => $j->on('s.id', '=', 'v.site_id')->on('s.customer_organisation_id', '=', 'v.customer_organisation_id'))
                ->join('customer_organisations as c', 'c.id', '=', 's.customer_organisation_id')
                ->whereIn('roots.identity_hash', $chunk)->where('roots.source_namespace', $stream->source_namespace)
                ->where('roots.identity_kind', 'CUSTOMER_CODE')->whereColumn('roots.active_version', '>', 'roots.revoked_through')
                ->where('s.is_active', true)->where('c.is_active', true)
                ->get(['roots.identity_hash', 'roots.source_identity', 'roots.uuid as binding_uuid', 'roots.epoch',
                    'v.version', 'v.definition_hash', 's.id as site_id', 's.uuid as site_uuid', 's.name as site_name', 'c.name as customer_name']));
        }
        $bindings = $bindings->keyBy('identity_hash');
        $sourceRows = $sources->map(function (array $source) use ($bindings, $stream): array {
            $code = ($source['kind'] ?? '') === 'CUSTOMER_CODE' ? ($source['identity'] ?? null) : null;
            $binding = is_string($code) && trim($code) !== '' ? $bindings->get(Canonical::hash([$stream->source_namespace, 'CUSTOMER_CODE', $code])) : null;
            if ($binding && $binding->source_identity !== $code) {
                $binding = null;
            }

            return ['code' => $code, 'source_name' => $source['site_name'] ?? 'Source site', 'rows' => $source['rows'] ?? null,
                'binding' => $binding, 'issue' => $binding ? null : ($code ? 'Exact site binding required' : 'CustomerCode required')];
        });
        $sites = $sourceRows->pluck('binding')->filter()->unique('site_id')->keyBy('site_id');
        $siteIds = $sites->keys()->all();
        $query = $this->amendments->forSites($siteIds);
        $counts = (clone $query)->select('p.site_id')->selectRaw('COUNT(*) as amendments')
            ->selectRaw('SUM(CASE WHEN '.PortalAmendmentReadModel::COMPLETED.' THEN 1 ELSE 0 END) as completed')
            ->groupBy('p.site_id')->get()->keyBy('site_id');
        $plotCounts = DB::table('projected_plots')->whereIn('site_id', $siteIds)->select('site_id')->selectRaw('COUNT(*) as plots')->groupBy('site_id')->get()->keyBy('site_id');
        $status = $filters['status'] ?? 'all';
        if ($status === 'pending') {
            $query->whereRaw('NOT '.PortalAmendmentReadModel::COMPLETED);
        } elseif ($status === 'completed') {
            $query->whereRaw(PortalAmendmentReadModel::COMPLETED);
        }
        if (! empty($filters['site'])) {
            $site = $sites->firstWhere('site_uuid', $filters['site']);
            abort_unless($site, 404);
            $query->where('p.site_id', $site->site_id);
        }
        if (! empty($filters['service'])) {
            $query->whereRaw('COALESCE(r.service_identifier, b.service_identifier) = ?', [$filters['service']]);
        }
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('p.plot_reference', 'like', '%'.$filters['q'].'%')
                ->orWhere('s.name', 'like', '%'.$filters['q'].'%')->orWhere('c.name', 'like', '%'.$filters['q'].'%'));
        }
        $rows = (clone $query)->orderByDesc('a.id')->paginate(10, ['*'], 'amendments_page')->withQueryString()->fragment('reconciliation-results');
        $selected = ! empty($filters['amendment'])
            ? (clone $query)->where('a.uuid', $filters['amendment'])->firstOrFail() : $rows->first();
        $history = $selected ? DB::table('call_off_date_negotiations')->where('call_off_request_id', $selected->request_id)
            ->where('purpose', 'amendment')->orderByDesc('id')
            ->paginate(10, ['uuid', 'requested_date', 'requester_name', 'opened_at', 'status'], 'history_page')->withQueryString()->fragment('amendment-history') : null;
        $historyEvidence = $selected ? DB::table('call_off_status_histories')->where('call_off_request_id', $selected->request_id)
            ->where('event_type', 'amendment_requested')->where('after_state->amendment_uuid', $selected->amendment_uuid)
            ->first(['uuid', 'sequence', 'performed_at']) : null;
        $sourcePage = LengthAwarePaginator::resolveCurrentPage('sites_page');
        $sourceRows = $sourceRows->sortBy(fn (array $s) => $s['binding'] ? 1 : 0)->values();
        $sourcePageRows = new LengthAwarePaginator($sourceRows->forPage($sourcePage, 10)->values(), $sourceRows->count(), 10, $sourcePage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'sites_page']);
        $sourcePageRows->withQueryString()->fragment('source-sites');
        $superseded = DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)->where('export_order', $upload->export_order)
            ->where('revision', '>', $upload->revision)->exists();

        return ['upload' => ['uuid' => $upload->uuid, 'revision' => $upload->revision, 'date' => $upload->export_date, 'slot' => $upload->export_slot,
            'state' => $upload->state, 'hash' => $upload->workbook_hash, 'manifest_hash' => $upload->source_manifest_hash],
            'manifestValid' => $valid, 'superseded' => $superseded, 'sourceSites' => $sourcePageRows, 'sites' => $sites, 'siteCounts' => $counts,
            'plotCounts' => $plotCounts, 'rows' => $rows, 'selected' => $selected, 'history' => $history, 'historyEvidence' => $historyEvidence,
            'filters' => $filters, 'services' => CallOffServiceType::cases(), 'observedAt' => now('UTC'),
            'summary' => ['rows' => $valid ? ($manifest['record_count'] ?? null) : null, 'detected' => $valid ? $sources->count() : null,
                'bound' => $sites->count(), 'unbound' => $sourceRows->whereNull('binding')->count(),
                'included' => $valid ? ($manifest['included_count'] ?? null) : null, 'excluded' => $valid ? ($manifest['excluded_count'] ?? null) : null,
                'plots' => $plotCounts->sum('plots'), 'amendments' => $counts->sum('amendments'),
                'completed' => $counts->sum('completed'), 'pending' => $counts->sum('amendments') - $counts->sum('completed')],
            'confirmation' => $selected ? (new SourceConfirmationContract)->forService(CallOffServiceType::from($selected->service)) : null];
    }

    private function validManifest(mixed $manifest, ?string $hash): bool
    {
        if (! is_array($manifest) || ! is_array($manifest['sources'] ?? null)
            || count($manifest['sources']) > PilotWorkbookDiscovery::MAX_PARENT_ROWS) {
            return false;
        }
        foreach ($manifest['sources'] as $source) {
            if (! is_array($source) || (isset($source['identity']) && ! is_string($source['identity']))
                || (isset($source['site_name']) && ! is_string($source['site_name']))
                || (isset($source['rows']) && (! is_int($source['rows']) || $source['rows'] < 0))) {
                return false;
            }
        }
        try {
            return hash_equals($hash ?? '', Canonical::hash($manifest));
        } catch (\InvalidArgumentException|\JsonException) {
            return false;
        }
    }
}
