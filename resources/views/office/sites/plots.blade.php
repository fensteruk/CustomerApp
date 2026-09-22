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
            @php
                $sourceReference = (string) $plot['source_identity']['identifier'];
                $shortReference = mb_strlen($sourceReference) > 23
                    ? mb_substr($sourceReference, 0, 11).'…'.mb_substr($sourceReference, -6)
                    : $sourceReference;
                $productTotals = array_map(
                    static fn ($quantity) => preg_replace('/\.000$/', '', (string) $quantity),
                    $plot['product_totals'],
                );
            @endphp
            <article class="admin-card">
                <div class="flex items-start justify-between gap-3"><h3 class="admin-card-title">{{ $plot['plot_reference'] }}</h3><span class="status status-slate">Source-managed</span></div>
                <dl class="mt-3 space-y-3 text-sm">
                    <div><dt class="admin-term">CustomerApp site</dt><dd class="admin-value">{{ $site['name'] }}</dd></div>
                    <div><dt class="admin-term">Source system</dt><dd class="admin-value">{{ str($plot['source_identity']['source'])->headline() }}</dd></div>
                    <div><dt class="admin-term">Portal status</dt><dd class="admin-value">{{ $plot['overall_status']['label'] }}</dd></div>
                    <div><dt class="admin-term">Products</dt><dd class="admin-value">Windows {{ $productTotals['windows'] }} · Doors {{ $productTotals['doors'] }} · Bifold {{ $productTotals['bifold'] }}</dd></div>
                    <div><dt class="admin-term">Source completion</dt><dd class="admin-value">{{ $plot['is_completed'] ? 'Completed' : 'Not fully completed' }}</dd></div>
                    <div><dt class="admin-term">Last synchronised</dt><dd class="admin-value">{{ $plot['synchronised_at'] ? \Illuminate\Support\Carbon::parse($plot['synchronised_at'])->utc()->format('j M Y, H:i').' UTC' : 'Not recorded' }}</dd></div>
                </dl>
                <footer class="mt-4 border-t border-slate-200 pt-3" x-data="{ copied: false, copyFailed: false, async copy(reference) { this.copied = false; this.copyFailed = false; try { await navigator.clipboard.writeText(reference); this.copied = true; } catch (error) { this.copyFailed = true; } } }">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <span class="inline-flex min-w-0 max-w-full items-center gap-1 rounded-full bg-slate-100 px-3 py-2 text-xs text-slate-600">
                            <span class="shrink-0">Source ref:</span>
                            <span class="min-w-0 truncate font-mono">{{ $shortReference }}</span>
                        </span>
                        <button type="button" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" data-source-reference="{{ $sourceReference }}" @click="copy($el.dataset.sourceReference)">Copy <span class="sr-only">full source reference for {{ $plot['plot_reference'] }}</span></button>
                    </div>
                    <span class="sr-only" role="status" aria-live="polite" x-text="copied ? 'Source reference copied.' : (copyFailed ? 'Copy failed. Show the full source reference below.' : '')"></span>
                    <details class="text-xs text-slate-600">
                        <summary class="min-h-11 cursor-pointer py-3 font-semibold text-sky-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Show full source reference</summary>
                        <code class="block break-all rounded-lg bg-slate-50 p-2 select-all">{{ $sourceReference }}</code>
                    </details>
                    <details class="border-t border-slate-200">
                        <summary class="min-h-11 cursor-pointer py-3 text-sm font-bold text-sky-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Inspect source services</summary>
                        <ul class="space-y-3 text-sm">
                            @forelse ($plot['services'] as $service)
                                <li class="flex flex-wrap justify-between gap-2"><span class="font-semibold">{{ \App\Enums\CallOffServiceType::tryFrom($service['service'])?->label() ?? 'Service' }}</span><span>{{ $service['portal_status']['label'] ?? ($service['source_completion_observed_at'] || $service['source_completed_at'] ? 'Completed' : ($service['source_present'] ? 'Not completed' : 'Not supplied')) }}</span></li>
                            @empty
                                <li>No service information supplied.</li>
                            @endforelse
                        </ul>
                    </details>
                </footer>
            </article>
        @empty
            <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="section-title">{{ $search !== '' ? 'No matching plots' : 'No plots yet' }}</h3><p class="mt-2">{{ $search !== '' ? 'Try another plot reference.' : 'Plots will appear when source data has been imported. You do not need to add them manually.' }}</p></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $items->links() }}</div>
</section>
