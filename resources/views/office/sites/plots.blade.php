@php
    $plotSort = $plotSort ?? 'plot_asc';
    $plotSearchValue = old('search', $search);
    $plotSelectedStatuses = array_values(array_filter((array) old('overall_status', $selectedStatuses)));
    $plotSortValue = old('sort', $plotSort);
    $plotFilterCount = (int) filled($plotSearchValue)
        + count($plotSelectedStatuses)
        + (int) ($plotSortValue !== 'plot_asc');
    $plotFilterErrors = $errors->hasAny(['search', 'overall_status', 'overall_status.*', 'sort']);
@endphp
<section class="sw-section" aria-labelledby="plots-title">
    <div class="sw-section-heading"><div><h2 id="plots-title">Plots at {{ $site['name'] }}</h2><p class="sw-muted">Inspect service status and source information for each plot.</p></div><span class="sw-badge">{{ $plots->total() }} {{ Str::plural('plot', $plots->total()) }}</span></div>
    <details class="sw-filter-disclosure" data-plot-filters @if ($plotFilterErrors) open @endif>
        <summary>
            <svg class="sw-filter-search-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16.5 16.5 4 4"/></svg>
            <span class="sw-filter-trigger-copy"><strong>Search &amp; Filters</strong><span>{{ $plotFilterCount ? $plotFilterCount.' '.Str::plural('setting', $plotFilterCount).' active' : 'All plots · Reference A–Z' }}</span></span>
            <svg class="sw-filter-arrow" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5"/></svg>
        </summary>
        <div class="sw-filter-panel">
            @if ($plotFilterErrors)<p class="sw-filter-error" role="alert">{{ $errors->first() }}</p>@endif
            <form method="GET" action="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid']]) }}" class="sw-filters" aria-label="Search plots">
                <input type="hidden" name="section" value="plots">
                <div class="sw-field"><label for="plot-search">Plot reference</label><input id="plot-search" type="search" name="search" maxlength="100" value="{{ $plotSearchValue }}" placeholder="Plot number or reference"></div>
                <div class="sw-field"><label for="plot-status">Plot status</label><select id="plot-status" name="overall_status[]"><option value="">All plots</option>@foreach (App\Enums\PlotOverallStatus::cases() as $status)<option value="{{ $status->value }}" @selected(in_array($status->value, $plotSelectedStatuses, true))>{{ $status->label() }}</option>@endforeach</select></div>
                <div class="sw-field"><label for="plot-sort">Sort plots</label><select id="plot-sort" name="sort"><option value="plot_asc" @selected($plotSortValue === 'plot_asc')>Reference A–Z</option><option value="plot_desc" @selected($plotSortValue === 'plot_desc')>Reference Z–A</option></select></div>
                <button class="sw-button sw-primary" type="submit">Search</button>
                @if ($plotFilterCount)<a class="sw-button" href="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid'], 'section' => 'plots']) }}">Clear</a>@endif
            </form>
        </div>
    </details>
    @if ($plots->isNotEmpty())
        @include('site-workspace.plot-list')
    @else
        <div class="empty-state"><h3 class="section-title">{{ $search !== '' || $selectedStatuses ? 'No matching plots' : 'No plots yet' }}</h3><p>{{ $search !== '' || $selectedStatuses ? 'Try another plot reference or status.' : 'Plots will appear when source data has been imported. You do not need to add them manually.' }}</p></div>
    @endif
    @if ($plots->hasPages())<nav class="mt-5" aria-label="Plot pages">{{ $plots->links() }}</nav>@endif
    <details class="sw-disclosure"><summary>About the plot inventory</summary><p class="sw-muted">Every plot shown here belongs to <strong>{{ $site['customer']['name'] }} · {{ $site['name'] }}</strong>. Plots are source-managed, read-only, and added or updated through imports after the source site is linked to this site.</p></details>
</section>
