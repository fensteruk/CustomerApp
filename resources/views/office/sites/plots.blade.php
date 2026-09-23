<section class="sw-section" aria-labelledby="plots-title">
    <div class="sw-section-heading"><div><h2 id="plots-title">Plots at {{ $site['name'] }}</h2><p class="sw-muted">Select a card to inspect its services and source information.</p></div><span class="sw-badge">{{ $plots->total() }} {{ Str::plural('plot', $plots->total()) }}</span></div>
    <form method="GET" action="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid']]) }}" class="sw-filters" aria-label="Search plots">
        <input type="hidden" name="section" value="plots">
        <div class="sw-field"><label for="plot-search">Plot reference</label><input id="plot-search" type="search" name="search" maxlength="100" value="{{ $search }}" placeholder="Plot number or reference"></div>
        <div class="sw-field"><label for="plot-status">Plot status</label><select id="plot-status" name="overall_status[]"><option value="">All plots</option>@foreach (App\Enums\PlotOverallStatus::cases() as $status)<option value="{{ $status->value }}" @selected(in_array($status->value, $selectedStatuses, true))>{{ $status->label() }}</option>@endforeach</select></div>
        <button class="sw-button sw-primary" type="submit">Search</button>
        @if ($search !== '' || $selectedStatuses)<a class="sw-button" href="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid'], 'section' => 'plots']) }}">Clear</a>@endif
    </form>
    @if ($plots->isNotEmpty())
        @include('site-workspace.cards')
    @else
        <div class="empty-state"><h3 class="section-title">{{ $search !== '' || $selectedStatuses ? 'No matching plots' : 'No plots yet' }}</h3><p>{{ $search !== '' || $selectedStatuses ? 'Try another plot reference or status.' : 'Plots will appear when source data has been imported. You do not need to add them manually.' }}</p></div>
    @endif
    @if ($plots->hasPages())<nav class="mt-5" aria-label="Plot pages">{{ $plots->links() }}</nav>@endif
    <details class="sw-disclosure"><summary>About the plot inventory</summary><p class="sw-muted">Every plot shown here belongs to <strong>{{ $site['customer']['name'] }} · {{ $site['name'] }}</strong>. Plots are source-managed, read-only, and added or updated through imports after the source site is linked to this site.</p></details>
</section>
