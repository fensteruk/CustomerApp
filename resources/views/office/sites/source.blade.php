<section aria-labelledby="source-title">
    <h2 id="source-title" class="section-title">Source Binding</h2>
    <p class="mt-2 text-sm text-slate-600">Read-only links between this site and its source records. Editing a site does not change these links.</p>
    @if (($items['availability'] ?? '') !== 'AVAILABLE')
        <div class="empty-state mt-4"><h3 class="font-bold">Source binding information is not available yet</h3><p class="mt-2">This does not prevent you from managing the customer and site.</p></div>
    @else
        <div class="admin-card-grid mt-4">
            @forelse (($items['bindings'] ?? $items['active_bindings']) as $binding)
                <article class="admin-card">
                    <span class="status {{ $binding['state'] === 'ACTIVE' ? 'status-green' : 'status-slate' }}">{{ ['ACTIVE' => 'Active', 'DRAFT' => 'Draft', 'SUPERSEDED' => 'Superseded', 'REVOKED' => 'Revoked'][$binding['state']] ?? 'Inactive' }}</span>
                    <h3 class="admin-card-title mt-3">{{ $binding['source_identity'] }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="admin-term">Source</dt><dd class="admin-value">{{ $binding['source_namespace'] ?? 'Not recorded' }}</dd></div>
                        <div><dt class="admin-term">Identity type</dt><dd class="admin-value">{{ ['CUSTOMER_CODE' => 'CustomerCode', 'SOURCE_SITE_ID' => 'Source site reference', 'EXACT_SITE_NAME' => 'Exact source site name'][$binding['identity_kind'] ?? ''] ?? 'Source identity' }}</dd></div>
                        <div><dt class="admin-term">Version</dt><dd class="admin-value">{{ $binding['version'] ?? 'Not recorded' }}</dd></div>
                        <div><dt class="admin-term">Created by</dt><dd class="admin-value">{{ $binding['created_by'] ?? 'Not recorded' }}</dd></div>
                        @if (! empty($binding['reason']))<div><dt class="admin-term">Reason</dt><dd class="admin-value">{{ $binding['reason'] }}</dd></div>@endif
                        <div><dt class="admin-term">Last changed</dt><dd class="admin-value">{{ ! empty($binding['last_changed_at']) ? \Illuminate\Support\Carbon::parse($binding['last_changed_at'])->utc()->format('j M Y, H:i').' UTC' : 'Not recorded' }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="font-bold">Not linked</h3><p class="mt-2">No active source binding is recorded for this site. Source linking can be added later.</p></div>
            @endforelse
        </div>
        @if (($items['bindings'] ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="mt-5">{{ $items['bindings']->links() }}</div>@endif
    @endif
</section>
