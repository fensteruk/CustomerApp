<x-layouts.portal title="Master import reconciliation | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.reconciliation.styles')
    <div class="admin-workspace recon-workspace">
        <a class="admin-back" href="{{ route('office.workspace.imports') }}">← Back to Imports</a>
        <header class="recon-heading">
            <div><p class="eyebrow">Office workspace · {{ Illuminate\Support\Carbon::parse($upload['date'])->format('j M Y') }} · {{ str($upload['slot'])->title() }} · Revision {{ $upload['revision'] }}</p>
                <h1 class="admin-title">Master import reconciliation</h1><p class="page-intro">RedZebra import context with CustomerApp amendments safely retained.</p></div>
            <a class="secondary-button" href="{{ route('office.workspace.pilot-import.show', $upload['uuid']) }}">Open import review</a>
        </header>
        <div class="recon-notice"><strong>Awaiting RedZebra reconciliation</strong><p>Automatic date confirmation needs an approved RedZebra date field for each service. This read-only view shows current Portal amendments for the exactly bound sites. It does not apply the import.</p></div>
        @if (! $manifestValid)<div class="recon-warning" role="status">Source discovery evidence is unavailable or could not be verified. Site routing is blocked. Open import review for the next step.</div>@endif
        @if ($superseded)<div class="recon-warning" role="status">This import has been superseded. The amendments below are current Portal context, not a historical comparison result. Open import review to choose the latest revision.</div>@endif
        @if ($upload['state'] === 'FAILED')<div class="recon-warning" role="status">This import failed. Any retained discovery summary does not mean its rows were applied.</div>@endif
        <section class="recon-metrics" aria-label="Import and amendment summary">
            @foreach ([['Source rows', $summary['rows'], 'Detected in this export'], ['Exact bound sites', $summary['bound'], ($summary['detected'] ?? 'Unknown').' source sites detected'], ['Portal amendments', $summary['amendments'], 'Latest valid amendment per request'], ['Awaiting reconciliation', $summary['pending'], $summary['completed'].' closed by source completion']] as [$label, $count, $hint])
                <article class="recon-metric"><p>{{ $label }}</p><strong>{{ $count === null ? '—' : number_format($count) }}</strong><span>{{ $hint }}</span></article>
            @endforeach
        </section>
        <p class="recon-caption">{{ $summary['included'] === null ? 'Unknown' : number_format($summary['included']) }} included rows · {{ $summary['excluded'] === null ? 'Unknown' : number_format($summary['excluded']) }} excluded rows · {{ $summary['unbound'] }} source sites need binding · {{ number_format($summary['plots']) }} existing Portal plots across bound sites.</p>
        <div class="recon-grid">
            <section class="recon-panel" id="reconciliation-results" aria-labelledby="recon-title">
                <h2 id="recon-title" class="section-title">Portal amendments</h2>
                <p class="recon-caption">{{ $rows->total() }} matching requests. A partial export may omit an amended plot; this view does not assert that every plot below appears in this workbook.</p>
                <form method="GET" action="{{ route('office.workspace.pilot-import.reconciliation', $upload['uuid']) }}" class="recon-filters">
                    <label class="recon-search">Search plot, site or customer<input class="form-input" name="q" maxlength="80" value="{{ $filters['q'] ?? '' }}" placeholder="Search amendments"></label>
                    <label>Status<select class="form-input" name="status">@foreach(['all'=>'All amendments', 'pending'=>'Awaiting reconciliation', 'completed'=>'Closed by source completion'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label>Site<select class="form-input" name="site"><option value="">All bound sites</option>@foreach($sites as $site)<option value="{{ $site->site_uuid }}" @selected(($filters['site'] ?? '') === $site->site_uuid)>{{ $site->site_name }} · {{ $site->customer_name }}</option>@endforeach</select></label>
                    <label>Service<select class="form-input" name="service"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->value }}" @selected(($filters['service'] ?? '') === $service->value)>{{ $service->label() }}</option>@endforeach</select></label>
                    <button class="primary-button" type="submit">Apply filters</button><a class="secondary-button" href="{{ route('office.workspace.pilot-import.reconciliation', $upload['uuid']) }}">Clear filters</a>
                </form>
                <table class="recon-table"><caption class="sr-only">Latest Portal amendment per request, with source comparison pending</caption><thead><tr><th scope="col">Plot / site</th><th scope="col">Service</th><th scope="col">Previous RedZebra</th><th scope="col">Portal amendment</th><th scope="col">New RedZebra</th><th scope="col">Status / detail</th></tr></thead><tbody>
                    @forelse($rows as $row)
                        @php($plotLabel = str($row->plot_reference)->lower()->startsWith('plot ') ? $row->plot_reference : 'Plot '.$row->plot_reference)
                        <tr @class(['recon-selected' => $selected?->amendment_uuid === $row->amendment_uuid])>
                            <td data-label="Plot / site"><strong>{{ $plotLabel }}</strong><span>{{ $row->site_name }}</span><span>{{ $row->customer_name }}</span></td>
                            <td data-label="Service">{{ App\Enums\CallOffServiceType::from($row->service)->label() }}</td>
                            <td data-label="Previous RedZebra">Not compared</td>
                            <td data-label="Portal amendment"><strong>{{ Illuminate\Support\Carbon::parse($row->portal_date)->format('j M Y') }}</strong></td>
                            <td data-label="New RedZebra">Not compared</td>
                            <td data-label="Status / detail"><span class="recon-status">{{ $row->completion_closed ? 'Closed by source completion' : 'Portal amendment recorded' }}</span><a class="recon-detail-link" href="{{ route('office.workspace.pilot-import.reconciliation', ['upload'=>$upload['uuid'], ...Illuminate\Support\Arr::except($filters, ['amendment', 'history_page']), 'amendment'=>$row->amendment_uuid]) }}#comparison">View {{ $plotLabel }} · {{ App\Enums\CallOffServiceType::from($row->service)->label() }}</a></td>
                        </tr>
                    @empty<tr><td colspan="6" class="recon-empty">No Portal amendments match this view. Adjust the filters or check the source-site bindings below.</td></tr>@endforelse
                </tbody></table>
                <div class="recon-pagination">{{ $rows->links() }}</div>
            </section>
            <aside class="recon-panel recon-comparison" id="comparison" aria-labelledby="comparison-title">
                <h2 id="comparison-title" class="section-title">Amendment detail</h2>
                @if($selected)
                    @php($selectedPlotLabel = str($selected->plot_reference)->lower()->startsWith('plot ') ? $selected->plot_reference : 'Plot '.$selected->plot_reference)
                    <h3>{{ $selectedPlotLabel }} · {{ App\Enums\CallOffServiceType::from($selected->service)->label() }}</h3>
                    <p class="recon-caption">{{ $selected->site_name }}<br>{{ $selected->customer_name }}</p>
                    <p class="recon-status">{{ $selected->completion_closed ? 'Closed by source completion' : 'Portal amendment recorded' }}</p>
                    <dl class="recon-values"><div><dt>Previous RedZebra date</dt><dd>Not compared</dd></div><div class="recon-portal-value"><dt>Latest Portal amendment</dt><dd><strong>{{ Illuminate\Support\Carbon::parse($selected->portal_date)->format('j M Y') }}</strong><span>Requested by {{ $selected->requester_name ?: 'Recorded Portal user' }}</span><span>{{ $selected->opened_at ? Illuminate\Support\Carbon::parse($selected->opened_at)->format('j M Y, H:i').' UTC' : 'Time not recorded' }}</span></dd></div><div><dt>New RedZebra date</dt><dd>Not compared</dd></div></dl>
                    <div class="recon-notice"><strong>{{ $selected->completion_closed ? 'Completion remains in effect' : 'Awaiting RedZebra reconciliation' }}</strong><p>{{ $confirmation['explanation'] }}</p><p>{{ $selected->completion_closed ? 'This retained amendment does not reopen the completed request, including after a source reversal.' : 'The customer amendment is retained. Office can review the request through the existing workflow.' }}</p></div>
                    <a class="secondary-button recon-request" href="{{ route('portal.review-requests.show', $selected->request_uuid) }}">Open request</a>
                    <section id="amendment-history" class="recon-history"><h3>Amendment history</h3><p class="recon-caption">{{ $history->total() }} retained records for this request.</p><ol>@foreach($history as $record)<li><strong>{{ $record->requested_date ? Illuminate\Support\Carbon::parse($record->requested_date)->format('j M Y') : 'No date recorded' }}</strong> · {{ str($record->status)->replace('_', ' ')->title() }}<span>{{ $record->requester_name ?: 'Recorded Portal user' }} · {{ $record->opened_at ? Illuminate\Support\Carbon::parse($record->opened_at)->format('j M Y, H:i').' UTC' : 'Time not recorded' }}</span></li>@endforeach</ol><div class="recon-pagination">{{ $history->links() }}</div></section>
                    <details class="recon-provenance"><summary>Technical provenance</summary><dl><dt>Live context observed (UTC)</dt><dd>{{ $observedAt->toIso8601String() }}</dd><dt>Import</dt><dd>{{ $upload['uuid'] }}</dd><dt>Workbook hash</dt><dd>{{ $upload['hash'] }}</dd><dt>Discovery hash</dt><dd>{{ $upload['manifest_hash'] }}</dd><dt>Exact binding / version / epoch</dt><dd>{{ $sites[$selected->site_id]->binding_uuid }} / {{ $sites[$selected->site_id]->version }} / {{ $sites[$selected->site_id]->epoch }}</dd><dt>CustomerApp plot ID</dt><dd>{{ $selected->plot_id }}</dd><dt>Request</dt><dd>{{ $selected->request_uuid }}</dd><dt>Amendment</dt><dd>{{ $selected->amendment_uuid }}</dd><dt>History event</dt><dd>{{ $historyEvidence ? $historyEvidence->uuid.' / sequence '.$historyEvidence->sequence : 'No linked event located' }}</dd><dt>Amendment adapter</dt><dd>{{ App\Services\Reconciliation\PortalAmendmentReadModel::VERSION }}</dd></dl><p>No source-date comparison has occurred. This live view is not a stored reconciliation receipt.</p></details>
                @else<p class="recon-caption">Choose an amendment to see its dates, requester and retained history.</p>@endif
            </aside>
        </div>
        <section class="recon-panel recon-sites" id="source-sites" aria-labelledby="source-sites-title"><h2 id="source-sites-title" class="section-title">Source sites</h2><p class="recon-caption">CustomerCode determines the exact binding. Site names are descriptive. Counts show current Portal context; missing source rows never delete or reverse Portal facts.</p>
            <div class="recon-site-grid">@forelse($sourceSites as $source)<article class="recon-site"><h3>{{ $source['code'] ?: 'CustomerCode missing' }}</h3><p>{{ $source['source_name'] }}</p><p class="recon-caption">{{ $source['rows'] ?? 'Unknown' }} included source rows</p>@if($source['binding']) @php($bound = $source['binding'])<p><strong>{{ $bound->site_name }}</strong><br>{{ $bound->customer_name }}</p><p class="recon-caption">{{ $plotCounts->get($bound->site_id)?->plots ?? 0 }} existing Portal plots · {{ $siteCounts->get($bound->site_id)?->amendments ?? 0 }} Portal amendments · {{ $siteCounts->get($bound->site_id)?->completed ?? 0 }} closed by completion</p><a class="recon-detail-link" href="{{ route('office.workspace.pilot-import.reconciliation', ['upload'=>$upload['uuid'], 'site'=>$bound->site_uuid]) }}#reconciliation-results">View site amendments</a>@else<p class="recon-warning">{{ $source['issue'] }}</p><a class="recon-detail-link" href="{{ route('office.workspace.pilot-import.show', $upload['uuid']) }}">Review site binding</a>@endif</article>@empty<p>No verified source sites are available.</p>@endforelse</div><div class="recon-pagination">{{ $sourceSites->links() }}</div>
        </section>
    </div>
</x-layouts.portal>
