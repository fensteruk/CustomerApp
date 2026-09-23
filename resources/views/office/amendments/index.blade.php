<x-layouts.portal title="Amendments | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.amendments.styles')
    @inject('amendmentQuery', 'App\Services\OfficeAmendmentsWorkspaceQuery')
    <div class="am-workspace">
        <header class="am-heading">
            <div><h1>Amendments</h1><p>Review the latest date changes across your sites. Update RedZebra manually.</p></div>
            <time datetime="{{ now()->toDateString() }}">{{ now()->format('l j M Y') }}</time>
        </header>
        <section class="am-metrics" aria-label="Latest amendments across all customers">
            @foreach (['attention' => ['Awaiting Office response', 'Latest changes needing your response', 'am-rose', 'amendment'], 'waiting' => ['Awaiting Site User', 'An alternative date needs a response', 'am-amber', 'calendar'], 'closed' => ['Closed amendments', 'Latest cycles that are no longer open', 'am-green', 'activity']] as $key => [$label, $description, $tone, $icon])
                <a class="am-metric {{ $tone }}" href="{{ route('office.workspace.amendments.index', ['status' => $key]) }}">
                    <span class="am-metric-icon">@include('office.dashboard.icon', ['icon' => $icon])</span>
                    <div><strong>{{ number_format($counts[$key]) }}</strong><h2>{{ $label }}</h2><p>{{ $description }}</p></div>
                </a>
            @endforeach
        </section>
        @if ($errors->any())
            <div class="am-notice" role="alert"><h2>Check your filters</h2><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form class="am-filters" method="GET" action="{{ route('office.workspace.amendments.index') }}" aria-label="Filter amendments">
            <label>Status<select name="status">@foreach (['attention' => 'Needs Office response', 'waiting' => 'Awaiting Site User', 'closed' => 'Closed', 'all' => 'All amendments'] as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Customer<select name="customer"><option value="">All customers</option>@foreach ($customers as $customer)<option value="{{ $customer->uuid }}" @selected($filters['customer'] === $customer->uuid)>{{ $customer->name }}</option>@endforeach</select></label>
            <label>Site<select name="site"><option value="">All sites</option>@foreach ($sites as $site)<option value="{{ $site->uuid }}" @selected($filters['site'] === $site->uuid)>{{ $site->name }}</option>@endforeach</select></label>
            <label>Service<select name="service"><option value="">All services</option>@foreach ($services as $service)<option value="{{ $service->value }}" @selected($filters['service'] === $service->value)>{{ $service->label() }}</option>@endforeach</select></label>
            <label class="am-search">Search<input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Customer, site or plot"></label>
            <div class="am-filter-actions"><button class="am-button" type="submit">Apply filters</button><a href="{{ route('office.workspace.amendments.index') }}">Clear filters</a></div>
            @if ($customers->count() >= 100 || $sites->count() >= 100)<p class="am-filter-help">Lists show up to 100 names. Search by name to find other customers or sites.</p>@endif
        </form>

        <div class="am-columns">
            <section class="am-panel" aria-labelledby="am-queue-title">
                <div class="am-panel-heading"><h2 id="am-queue-title">{{ number_format($amendments->total()) }} {{ str('amendment')->plural($amendments->total()) }}</h2><span>Newest first</span></div>
                <p class="am-queue-caption">One latest change per request. Earlier changes stay in the timeline.</p>
                <ul class="am-queue">
                    @forelse ($amendments as $amendment)
                        @php($callOff = $amendment->callOffRequest)
                        <li>
                            <a class="am-row {{ $selected?->id === $amendment->id ? 'am-selected' : '' }}" href="{{ route('office.workspace.amendments.index', [...Illuminate\Support\Arr::except($filters, ['history_page']), 'page' => $amendments->currentPage(), 'request' => $callOff->uuid]) }}#amendment-detail" @if($selected?->id === $amendment->id) aria-current="true" @endif>
                                <div class="am-row-title"><strong>{{ str($callOff->projectedPlot->plot_reference)->lower()->startsWith('plot ') ? $callOff->projectedPlot->plot_reference : 'Plot '.$callOff->projectedPlot->plot_reference }}</strong><span>{{ $callOff->effectiveServiceIdentifier()?->label() ?? 'Service not recorded' }}</span><span aria-hidden="true">›</span></div>
                                <p class="am-context">{{ $callOff->batch->site->customerOrganisation->name }} · {{ $callOff->batch->site->name }}</p>
                                @php($previous = $amendment->prior_agreed_date ?? (!empty($comparison[$amendment->uuid]?->after_state['prior_requested_date']) ? \Illuminate\Support\Carbon::parse($comparison[$amendment->uuid]->after_state['prior_requested_date']) : null))
                                <div class="am-row-change"><span><small>Previous {{ $amendment->prior_agreed_date ? 'agreed' : 'requested' }}</small>{{ $previous?->format('j M Y') ?? 'Not recorded' }}</span><span aria-hidden="true">→</span><span><small>Now requested</small><strong>{{ $callOff->effectiveRequestedDate()?->format('j M Y') ?? 'Not recorded' }}</strong></span></div>
                                <div class="am-row-meta"><span>{{ $amendment->requester_name ?? 'Name not recorded' }}</span><time datetime="{{ $amendment->opened_at->toISOString() }}">{{ $amendment->opened_at->format('j M Y, H:i') }}</time></div>
                                <div class="am-tags"><span class="am-tag">{{ $amendmentQuery->stateLabel($amendment) }}</span><span class="am-tag">Request: {{ $callOff->status->label() }}{{ $callOff->trashed_at ? ' · In Trash' : '' }}</span>@if($amendment->is_early_date_exception)<span class="am-tag am-warning">Early date requested</span>@endif @if($amendment->is_urgent)<span class="am-tag am-warning">Urgent / late amendment</span>@endif</div>
                            </a>
                        </li>
                    @empty
                        <li class="am-empty"><span aria-hidden="true">✓</span><h3>{{ $filters['status'] === 'attention' && ! $filters['search'] && ! $filters['customer'] && ! $filters['site'] && ! $filters['service'] ? 'No amendments need attention' : 'No amendments match these filters' }}</h3><p>New date changes will appear here. You can also browse waiting or closed amendments.</p><a href="{{ route('office.workspace.amendments.index', ['status' => 'all']) }}">View all amendments</a></li>
                    @endforelse
                </ul>
                <div class="am-pagination">{{ $amendments->appends(['history_page' => null])->links() }}</div>
            </section>
            <section class="am-panel am-detail" id="amendment-detail" aria-labelledby="am-detail-title" tabindex="-1">
                @if ($selected)
                    @php($callOff = $selected->callOffRequest)
                    <div class="am-detail-heading"><h2 id="am-detail-title">{{ str($callOff->projectedPlot->plot_reference)->lower()->startsWith('plot ') ? $callOff->projectedPlot->plot_reference : 'Plot '.$callOff->projectedPlot->plot_reference }}</h2><span class="am-tag">{{ $amendmentQuery->stateLabel($selected) }}</span></div>
                    <p class="am-context">{{ $callOff->batch->site->customerOrganisation->name }} · {{ $callOff->batch->site->name }} · {{ $callOff->effectiveServiceIdentifier()?->label() }}</p>
                    <div class="am-change-banner"><strong>{{ $callOff->effectiveServiceIdentifier()?->label() }} date change</strong><p>The latest customer-requested change is shown below.</p></div>
                    @php($previous = $selected->prior_agreed_date ?? (!empty($comparison[$selected->uuid]?->after_state['prior_requested_date']) ? \Illuminate\Support\Carbon::parse($comparison[$selected->uuid]->after_state['prior_requested_date']) : null))
                    <div class="am-comparison">
                        <div><span>Previous {{ $selected->prior_agreed_date ? 'agreed' : 'requested' }} date</span><strong>{{ $previous?->format('j M Y') ?? 'Not recorded' }}</strong></div>
                        <span aria-hidden="true">→</span>
                        <div><span>Now requested</span><strong>{{ $callOff->effectiveRequestedDate()?->format('j M Y') ?? 'Not recorded' }}</strong></div>
                    </div>
                    <dl class="am-facts">
                        <div><dt>Amended by</dt><dd>{{ $selected->requester_name ?? 'Name not recorded' }}@if($selected->requester_role)<span>{{ $selected->requester_role }}</span>@endif</dd></div>
                        <div><dt>Amended at</dt><dd><time datetime="{{ $selected->opened_at->toISOString() }}">{{ $selected->opened_at->format('j M Y, H:i') }}</time></dd></div>
                        <div><dt>Current request state</dt><dd>{{ $callOff->status->label() }}@if($callOff->trashed_at)<span>In Trash</span>@endif</dd></div>
                        <div><dt>Reason</dt><dd>{{ $selected->reason_label ?? 'Not recorded' }}</dd></div>
                    </dl>
                    @if($selected->customer_response)<p class="am-explanation">{{ $selected->customer_response }}</p>@endif
                    @if($selected->is_early_date_exception)<p class="am-notice"><strong>Early date requested.</strong> Recorded normal earliest date: {{ $selected->normal_earliest_date?->format('j M Y') ?? 'Not recorded' }}. Review the existing request before agreeing a date.</p>@endif
                    @if(!empty($comparison[$selected->uuid]?->after_state['early_date_reason']))<p class="am-notice"><strong>Early date reason:</strong> {{ $comparison[$selected->uuid]->after_state['early_date_reason'] }}</p>@endif
                    @if($selected->is_urgent)<p class="am-notice">Urgent / late amendment</p>@endif
                    <h3 class="am-timeline-heading">Request timeline</h3>
                    <p class="am-muted">Recorded history, oldest first. Includes earlier amendment cycles.</p>
                    <ol class="am-timeline">
                        @forelse ($history as $event)
                            <li>
                                <strong>{{ $event->event_type->label() }}</strong>
                                <time datetime="{{ $event->performed_at->toISOString() }}">{{ $event->performed_at->format('j M Y, H:i') }}</time>
                                <p>{{ $event->recordedActorName() }}</p>
                                @if($event->event_type === \App\Enums\CallOffHistoryEventType::AmendmentRequested)
                                    <p>Previous {{ !empty($event->after_state['prior_agreed_date']) ? 'agreed' : 'requested' }}: {{ ($event->after_state['prior_agreed_date'] ?? $event->after_state['prior_requested_date'] ?? $event->before_state['agreed_date'] ?? $event->before_state['requested_date'] ?? null) ? \Illuminate\Support\Carbon::parse($event->after_state['prior_agreed_date'] ?? $event->after_state['prior_requested_date'] ?? $event->before_state['agreed_date'] ?? $event->before_state['requested_date'])->format('j M Y') : 'Not recorded' }} → Requested: {{ !empty($event->after_state['amendment_requested_date']) ? \Illuminate\Support\Carbon::parse($event->after_state['amendment_requested_date'])->format('j M Y') : 'Not recorded' }}</p>
                                    @if(!empty($event->after_state['early_date_reason']))<p>Early date reason: {{ $event->after_state['early_date_reason'] }}</p>@endif
                                @elseif(!empty($event->after_state['proposed_date']))
                                    <p>Proposed: {{ \Illuminate\Support\Carbon::parse($event->after_state['proposed_date'])->format('j M Y') }}</p>
                                @elseif($event->event_type === \App\Enums\CallOffHistoryEventType::DateAgreed && !empty($event->after_state['agreed_date']))
                                    <p>Agreed: {{ \Illuminate\Support\Carbon::parse($event->after_state['agreed_date'])->format('j M Y') }}</p>
                                @elseif(in_array($event->event_type, [\App\Enums\CallOffHistoryEventType::Submitted, \App\Enums\CallOffHistoryEventType::DateRequested]) && !empty($event->after_state['requested_date']))
                                    <p>Requested: {{ \Illuminate\Support\Carbon::parse($event->after_state['requested_date'])->format('j M Y') }}</p>
                                @endif
                                @if($event->customer_response)<p>{{ $event->customer_response }}</p>@endif
                            </li>
                        @empty
                            <li>No immutable history entries were recorded for this request.</li>
                        @endforelse
                    </ol>
                    <div class="am-pagination">{{ $history->fragment('amendment-detail')->links() }}</div>
                    <aside class="am-manual"><h3>RedZebra update</h3><p>Make any required change in RedZebra manually. This page does not send updates or record whether RedZebra has been updated. Closing a date negotiation is not confirmation of a RedZebra update.</p></aside>
                    <div class="am-detail-actions"><a class="am-button" href="{{ route('portal.review-requests.show', $callOff) }}">Review request</a><a class="am-secondary" href="{{ route('office.workspace.sites.show', ['customerOrganisation' => $callOff->batch->site->customerOrganisation, 'site' => $callOff->batch->site, 'section' => 'plots']) }}">View site plots</a></div>
                    <a class="am-back" href="#am-queue-title">Back to amendment queue ↑</a>
                @else
                    <h2 id="am-detail-title">Amendment details</h2><p class="am-muted">Select a date change to see the comparison and recorded timeline.</p>
                @endif
            </section>
        </div>
    </div>
</x-layouts.portal>
