<x-layouts.portal title="Office Dashboard | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.dashboard.styles')
    <div class="office-dashboard">
        <header class="od-heading">
            <div>
                <h1>{{ $greeting }}, {{ auth()->user()->name }}</h1>
                <p class="od-intro">Here’s what’s happening across your sites today.</p>
            </div>
            <time class="od-today" datetime="{{ $today->toDateString() }}">{{ $today->format('l j M Y') }}</time>
        </header>

        <section class="od-attention" aria-labelledby="office-attention-title">
            <div class="od-section-heading">
                @include('office.dashboard.icon', ['icon' => 'bell'])
                <div><h2 id="office-attention-title">Needs attention</h2><p>Items that need your review or action</p></div>
                <span class="od-attention-total">{{ number_format($attentionCount) }} {{ str('item')->plural($attentionCount) }} to review</span>
            </div>
            <div class="od-attention-grid">
                <article class="od-attention-card od-rose" aria-labelledby="office-amendments-title">
                    <a class="od-card-title" href="{{ route('portal.review-requests', ['status' => 'amendment_on_hold']) }}">
                        <span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'amendment'])</span>
                        <h3 id="office-amendments-title">Amendments</h3>
                        <span class="od-count"><span class="sr-only">Date amendments awaiting Office: </span>{{ number_format($amendmentCount) }}</span>
                        <span class="od-arrow" aria-hidden="true">›</span>
                    </a>
                    <p class="od-card-description">Requested changes to agreed dates that need your response.</p>
                    <ul class="od-attention-items">
                        @forelse ($amendmentItems as $amendment)
                            @php($request = $amendment->callOffRequest)
                            <li>
                                <a class="od-attention-item" href="{{ route('portal.review-requests.show', $request) }}">
                                    <strong>{{ str($request->projectedPlot->plot_reference)->lower()->startsWith('plot ') ? $request->projectedPlot->plot_reference : 'Plot '.$request->projectedPlot->plot_reference }} · {{ $request->effectiveServiceIdentifier()?->label() }} date change</strong>
                                    <span class="od-date-change">Agreed {{ $amendment->prior_agreed_date?->format('j M Y') ?? 'Not recorded' }} → Requested {{ $amendment->requested_date?->format('j M Y') ?? 'Not recorded' }}</span>
                                    <span class="od-item-meta"><span>{{ $request->batch->site->name }}</span><time datetime="{{ $amendment->opened_at->toISOString() }}" title="{{ $amendment->opened_at->format('j M Y, H:i') }}">{{ $amendment->opened_at->diffForHumans() }}</time></span>
                                    <span class="od-item-meta">Requested by {{ $amendment->requester_name ?? 'Name not recorded' }}</span>
                                </a>
                            </li>
                        @empty
                            <li><p class="od-empty">No date amendments awaiting your response.</p></li>
                        @endforelse
                    </ul>
                    <a class="od-card-footer" href="{{ route('portal.review-requests', ['status' => 'amendment_on_hold']) }}">View date amendments <span aria-hidden="true">→</span></a>
                </article>

                <article class="od-attention-card" aria-labelledby="office-imports-title">
                    <div class="od-card-title">
                        <span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'import'])</span>
                        <h3 id="office-imports-title">Import reviews</h3>
                        <span class="od-count"><span class="sr-only">Uploads needing review: </span>{{ $importsAvailable ? number_format($importCount) : '—' }}</span>
                    </div>
                    <p class="od-card-description">Uploaded workbooks and selected sites that need attention.</p>
                    <ul class="od-attention-items">
                        @forelse ($importItems as $import)
                            <li><a class="od-attention-item" href="{{ $import['url'] }}"><strong>{{ $import['title'] }}</strong><span class="od-item-meta"><span>{{ $import['detail'] }}</span><time datetime="{{ $import['time']->toISOString() }}" title="{{ $import['time']->format('j M Y, H:i') }}">{{ $import['time']->diffForHumans() }}</time></span></a></li>
                        @empty
                            <li><p class="od-empty">{{ $importsAvailable ? 'No uploaded workbooks need review.' : 'Imports are currently unavailable for this account.' }}</p></li>
                        @endforelse
                    </ul>
                    @if ($importsAvailable)
                        <a class="od-card-footer" href="{{ route('office.workspace.imports') }}">Review imports <span aria-hidden="true">→</span></a>
                    @endif
                </article>

                <article class="od-attention-card od-orange" aria-labelledby="office-call-offs-title">
                    <a class="od-card-title" href="{{ route('portal.review-requests') }}">
                        <span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'request'])</span>
                        <h3 id="office-call-offs-title">Call-off actions</h3>
                        <span class="od-count"><span class="sr-only">Call-off requests awaiting Office: </span>{{ number_format($requestCount) }}</span>
                        <span class="od-arrow" aria-hidden="true">›</span>
                    </a>
                    <p class="od-card-description">New call-offs and date requests awaiting Fenster.</p>
                    <ul class="od-attention-items">
                        @forelse ($requestItems as $request)
                            <li><a class="od-attention-item" href="{{ route('portal.review-requests.show', $request) }}"><strong>{{ str($request->projectedPlot->plot_reference)->lower()->startsWith('plot ') ? $request->projectedPlot->plot_reference : 'Plot '.$request->projectedPlot->plot_reference }} · {{ $request->effectiveServiceIdentifier()?->label() }}{{ $request->is_early_date_exception ? ' · Early date requested' : ' · Review request' }}</strong><span class="od-item-meta"><span>{{ $request->batch->site->name }}</span><time datetime="{{ $request->created_at->toISOString() }}" title="{{ $request->created_at->format('j M Y, H:i') }}">{{ $request->created_at->diffForHumans() }}</time></span></a></li>
                        @empty
                            <li><p class="od-empty">No call-off requests awaiting your response.</p></li>
                        @endforelse
                    </ul>
                    <a class="od-card-footer" href="{{ route('portal.review-requests') }}">Review call-offs <span aria-hidden="true">→</span></a>
                </article>
            </div>
        </section>

        <section class="od-summary-grid" aria-label="Across your sites">
            <a class="od-summary" href="{{ route('office.workspace.customers.index') }}"><div class="od-summary-top"><span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'sites'])</span><span aria-hidden="true">›</span></div><h2>Active sites</h2><span class="od-value">{{ number_format($activeSiteCount) }}</span><p>Across active customers</p></a>
            <a class="od-summary" href="{{ route('portal.review-requests') }}"><div class="od-summary-top"><span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'request'])</span><span aria-hidden="true">›</span></div><h2>Open requests</h2><span class="od-value">{{ number_format($openRequestCount) }}</span><p>Awaiting a response or date agreement</p></a>
            <a class="od-summary" href="{{ route('portal.review-requests', ['status' => 'amendment_on_hold']) }}"><div class="od-summary-top"><span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'amendment'])</span><span aria-hidden="true">›</span></div><h2>Pending amendments</h2><span class="od-value">{{ number_format($pendingAmendmentCount) }}</span><p>Awaiting Office or a Site User</p></a>
            <div class="od-summary"><div class="od-summary-top"><span class="od-icon-wrap">@include('office.dashboard.icon', ['icon' => 'import'])</span></div><h2>Last applied import</h2>
                @if ($lastImport)
                    <a class="od-value od-import-value" href="{{ $lastImport['url'] }}"><time datetime="{{ $lastImport['time']->toISOString() }}">{{ $lastImport['time']->isToday() ? 'Today, '.$lastImport['time']->format('H:i') : $lastImport['time']->format('j M Y, H:i') }}</time></a><p>{{ $lastImport['detail'] }} · Applied</p>
                @else
                    <span class="od-value od-import-value">{{ $importsAvailable ? 'None yet' : 'Not available' }}</span><p>{{ $importsAvailable ? 'No site import has been applied' : 'Import access is currently off' }}</p>
                @endif
            </div>
        </section>

        <div class="od-panels">
            <section class="od-panel" aria-labelledby="office-upcoming-title">
                <div class="od-section-heading">@include('office.dashboard.icon', ['icon' => 'calendar'])<h2 id="office-upcoming-title">Upcoming activity</h2><a class="od-view-all" href="{{ route('portal.review-requests', ['status' => 'date_agreed']) }}" aria-label="View all agreed call-off dates">View all <span aria-hidden="true">→</span></a></div>
                <ul class="od-activity-list">
                    @forelse ($upcoming as $request)
                        @php($date = Illuminate\Support\Carbon::parse($request->dashboard_date))
                        <li><a class="od-activity-link" href="{{ route('portal.review-requests.show', $request) }}">
                            <time class="od-date-tile" datetime="{{ $date->toDateString() }}" aria-label="{{ $date->format('j F Y') }}"><strong>{{ $date->format('j') }}</strong><span>{{ $date->format('M') }}</span></time>
                            <div class="od-activity-copy"><strong>{{ $request->effectiveServiceIdentifier()?->label() }} · {{ str($request->projectedPlot->plot_reference)->lower()->startsWith('plot ') ? $request->projectedPlot->plot_reference : 'Plot '.$request->projectedPlot->plot_reference }}</strong><p>{{ $request->batch->site->name }}</p><p>View agreed date <span aria-hidden="true">→</span></p></div>
                            <span class="od-status">Date Agreed</span>
                        </a></li>
                    @empty
                        <li><p class="od-empty">No upcoming agreed dates. Agreed call-offs will appear here.</p></li>
                    @endforelse
                </ul>
            </section>
            <section class="od-panel" aria-labelledby="office-recent-title">
                <div class="od-section-heading">@include('office.dashboard.icon', ['icon' => 'activity'])<h2 id="office-recent-title">Recent activity</h2></div>
                <ul class="od-activity-list">
                    @forelse ($recent as $activity)
                        <li><a class="od-activity-link" href="{{ $activity['url'] }}">
                            <span class="od-icon-wrap od-recent-icon {{ $activity['tone'] === 'green' ? 'od-green' : ($activity['tone'] === 'rose' ? 'od-recent-rose' : '') }}">@include('office.dashboard.icon', ['icon' => $activity['icon']])</span>
                            <div class="od-activity-copy"><strong>{{ $activity['title'] }}</strong><p>{{ $activity['detail'] }}</p><p>{{ $activity['actor'] }}</p></div>
                            <time class="od-relative-time" datetime="{{ $activity['time']->toISOString() }}" title="{{ $activity['time']->format('j M Y, H:i') }}">{{ $activity['time']->diffForHumans() }}</time>
                        </a></li>
                    @empty
                        <li><p class="od-empty">No recent activity to show. Recorded request and import updates will appear here.</p></li>
                    @endforelse
                </ul>
            </section>
        </div>
        <nav class="od-quick" aria-labelledby="office-quick-title">
            <h2 id="office-quick-title">Quick actions</h2>
            <a href="{{ route('portal.review-requests') }}">Review requests <span aria-hidden="true">→</span></a>
            @if ($importsAvailable)<a href="{{ route('office.workspace.imports') }}">Import workbook <span aria-hidden="true">→</span></a>@endif
            <a href="{{ route('office.workspace.customers.index') }}">Customers <span aria-hidden="true">→</span></a>
            <a href="{{ route('office.workspace.users.index') }}">Users <span aria-hidden="true">→</span></a>
        </nav>
    </div>
</x-layouts.portal>
