<section aria-labelledby="plots-title">
    <h2 id="plots-title" class="section-title">Plot inventory</h2>
    <p class="mt-2 text-sm text-slate-600">Every plot shown here belongs to <strong>{{ $site['customer']['name'] }} · {{ $site['name'] }}</strong>. Plots are source-managed, read-only, and added or updated through imports after the source CustomerCode is bound to this site.</p>
    <form method="GET" action="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid']]) }}" class="my-4 flex max-w-xl flex-wrap items-end gap-3" aria-label="Search plots">
        <input type="hidden" name="section" value="plots">
        <div class="min-w-0 flex-1 basis-48"><label class="form-label" for="plot-search">Plot reference</label><input class="form-input" id="plot-search" type="search" name="search" maxlength="100" value="{{ $search }}"></div>
        <button class="primary-button" type="submit">Search</button>
        @if ($search !== '')<a class="admin-link-button" href="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid'], 'section' => 'plots']) }}">Clear</a>@endif
    </form>
    <p class="mb-3 text-sm text-slate-600">{{ $items->total() }} {{ Str::plural('plot', $items->total()) }}</p>
    <div class="admin-card-grid">
        @forelse ($items as $plot)
            <article class="admin-card">
                <div class="flex items-start justify-between gap-3"><h3 class="admin-card-title">{{ $plot['plot_reference'] }}</h3><span class="status status-slate">Source-managed</span></div>
                <dl class="mt-3 space-y-3 text-sm">
                    <div><dt class="admin-term">CustomerApp site</dt><dd class="admin-value">{{ $site['name'] }}</dd></div>
                    <div><dt class="admin-term">Source system</dt><dd class="admin-value">{{ str($plot['source_identity']['source'])->headline() }}</dd></div>
                    <div><dt class="admin-term">Source reference</dt><dd class="admin-value">{{ $plot['source_identity']['identifier'] }}</dd></div>
                    <div><dt class="admin-term">Portal status</dt><dd class="admin-value">{{ $plot['overall_status']['label'] }}</dd></div>
                    <div><dt class="admin-term">Products</dt><dd class="admin-value">Windows {{ $plot['product_totals']['windows'] }} · Doors {{ $plot['product_totals']['doors'] }} · Bifold {{ $plot['product_totals']['bifold'] }}</dd></div>
                    <div><dt class="admin-term">Source completion</dt><dd class="admin-value">{{ $plot['is_completed'] ? 'Completed' : 'Not fully completed' }}</dd></div>
                    <div><dt class="admin-term">Last synchronised</dt><dd class="admin-value">{{ $plot['synchronised_at'] ? \Illuminate\Support\Carbon::parse($plot['synchronised_at'])->utc()->format('j M Y, H:i').' UTC' : 'Not recorded' }}</dd></div>
                </dl>
                <details class="mt-4 border-t border-slate-200 pt-2">
                    <summary class="min-h-11 cursor-pointer py-3 text-sm font-bold text-sky-800">Inspect source services</summary>
                    <ul class="space-y-3 text-sm">
                        @forelse ($plot['services'] as $service)
                            <li class="flex flex-wrap justify-between gap-2"><span class="font-semibold">{{ \App\Enums\CallOffServiceType::tryFrom($service['service'])?->label() ?? 'Service' }}</span><span>{{ $service['portal_status']['label'] ?? ($service['source_completion_observed_at'] || $service['source_completed_at'] ? 'Completed' : ($service['source_present'] ? 'Not completed' : 'Not supplied')) }}</span></li>
                        @empty
                            <li>No service information supplied.</li>
                        @endforelse
                    </ul>
                </details>
            </article>
        @empty
            <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="section-title">{{ $search !== '' ? 'No matching plots' : 'No plots yet' }}</h3><p class="mt-2">{{ $search !== '' ? 'Try another plot reference.' : 'Plots will appear when source data has been imported. You do not need to add them manually.' }}</p></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $items->links() }}</div>
</section>
