<section aria-labelledby="source-title">
    <h2 id="source-title" class="section-title">Source Binding</h2>
    <p class="mt-2 text-sm text-slate-600">Read-only links between this site and its source records. Editing a site does not change these links.</p>
    @if (($items['availability'] ?? '') !== 'AVAILABLE')
        <div class="empty-state mt-4"><h3 class="font-bold">Source binding information is not available yet</h3><p class="mt-2">This does not prevent you from managing the customer and site.</p></div>
    @else
        <div class="admin-card-grid mt-4">
            @forelse ($items['active_bindings'] as $binding)
                <article class="admin-card">
                    <span class="status status-green">Active</span>
                    <h3 class="admin-card-title mt-3">{{ $binding['source_identity'] }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="admin-term">Identity type</dt><dd class="admin-value">{{ $binding['identity_kind'] === 'SOURCE_SITE_ID' ? 'Source site reference' : 'Exact source site name' }}</dd></div>
                        <div><dt class="admin-term">Created by</dt><dd class="admin-value">{{ $binding['created_by'] }}</dd></div>
                        <div><dt class="admin-term">Last changed</dt><dd class="admin-value">{{ $binding['last_changed_at'] ? \Illuminate\Support\Carbon::parse($binding['last_changed_at'])->utc()->format('j M Y, H:i').' UTC' : 'Not recorded' }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="font-bold">Not linked</h3><p class="mt-2">No active source binding is recorded for this site. Source linking can be added later.</p></div>
            @endforelse
        </div>
    @endif
</section>
