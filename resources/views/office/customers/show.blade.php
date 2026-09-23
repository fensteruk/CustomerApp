<x-layouts.portal :title="$customer['name'].' | Customers | Fenster'" sidebar-label="Menu">
    @include('office.customers.detail-styles')
    <div class="customer-detail" data-customer-detail>
        <a class="admin-back w-fit" href="{{ route('office.workspace.customers.index') }}">← Customers</a>
        <x-page-header :title="$customer['name']" eyebrow="Customer workspace" description="Sites, call-offs and the people with access.">
            <x-slot:context>@include('office.partials.status', ['active' => $customer['is_active']])</x-slot:context>
            <x-slot:actions>
                @if($customer['is_active'])<a class="primary-button" href="{{ route('office.workspace.sites.create', $customer['uuid']) }}"><span aria-hidden="true">+</span> Add site</a>@endif
                <a class="secondary-button" href="{{ route('office.workspace.customers.edit', $customer['uuid']) }}">Edit customer</a>
            </x-slot:actions>
        </x-page-header>
        @include('office.partials.feedback')
        @unless($customer['is_active'])
            <div class="admin-notice">This customer is inactive. External users cannot access the portal for this customer. Sites, users and history are retained. Reactivate the customer before adding a site. <a class="admin-link-button" href="{{ route('office.workspace.customers.lifecycle', $customer['uuid']) }}">Reactivate customer</a></div>
        @endunless
        <dl class="customer-metrics" aria-label="Customer totals">
            <div class="customer-metric"><dt>Sites</dt><dd>{{ number_format($customer['site_count']) }}</dd></div>
            <div class="customer-metric"><dt>Active sites</dt><dd>{{ number_format($customer['active_site_count']) }}</dd></div>
            <div class="customer-metric"><dt>Plots</dt><dd>{{ number_format($plotCount) }}</dd></div>
            <div class="customer-metric"><dt>Awaiting Office response</dt><dd>{{ number_format($attentionCount) }}</dd></div>
        </dl>
        <section aria-labelledby="customer-sites-title" class="space-y-4">
            <div><h2 id="customer-sites-title" class="section-title">Sites</h2><p class="admin-intro">Open a site to see its plots, dates and call-offs.</p></div>
            <form method="GET" action="{{ route('office.workspace.customers.show', $customer['uuid']) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-slate-100/60 p-4" aria-label="Site filters">
                <div class="min-w-0 basis-full sm:flex-1 sm:basis-auto"><label class="form-label" for="detail-site-search">Find a site</label><input class="form-input" id="detail-site-search" type="search" name="search" maxlength="100" placeholder="Site name or location" value="{{ $filters['search'] }}"></div>
                <div class="min-w-0 flex-1 sm:flex-none"><label class="form-label" for="detail-site-status">Site status</label><select class="form-input" id="detail-site-status" name="active"><option value="all" @selected($filters['active'] === null)>All sites</option><option value="1" @selected($filters['active'] === true)>Active</option><option value="0" @selected($filters['active'] === false)>Inactive</option></select></div>
                <button class="secondary-button" type="submit">Search</button>
                @if($filters['search'] !== '' || $filters['active'] !== null)<a class="admin-link-button" href="{{ route('office.workspace.customers.show', $customer['uuid']) }}">Clear filters</a>@endif
            </form>
            <p class="text-sm text-slate-600">{{ $sites->total() }} {{ Str::plural('site', $sites->total()) }}{{ $filters['search'] !== '' || $filters['active'] !== null ? ' found' : '' }}</p>
            <div class="customer-site-grid">
                @forelse($sites as $site)
                    <a class="customer-site-card" href="{{ route('office.workspace.sites.show', [$customer['uuid'], $site['uuid']]) }}" aria-label="Open site: {{ $site['name'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-2"><h3 class="admin-card-title">{{ $site['name'] }}</h3>@include('office.partials.status', ['active' => $site['is_active']])</div>
                        <p class="text-sm text-slate-600">{{ $site['location'] ?: 'No location added' }}</p>
                        <p class="font-semibold text-slate-700">{{ $site['plot_count'] }} {{ Str::plural('plot', $site['plot_count']) }} · {{ $site['assignment_count'] }} assigned {{ Str::plural('user', $site['assignment_count']) }}</p>
                        <p class="text-sm {{ $site['attention_count'] ? 'font-semibold text-amber-900' : 'text-slate-500' }}">{{ $site['attention_count'] ? $site['attention_count'].' awaiting Office response' : 'No Office response pending' }}</p>
                        <span class="open-site">Open site <span aria-hidden="true">→</span></span>
                    </a>
                @empty
                    <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="section-title">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'No matching sites' : 'No sites yet' }}</h3><p class="mt-2">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'Try another search or clear the filters.' : ($customer['is_active'] ? 'Add the first site to start organising this customer’s plots.' : 'Reactivate this customer before adding its first site.') }}</p></div>
                @endforelse
            </div>
            {{ $sites->links() }}
        </section>
        <section class="admin-card" aria-labelledby="customer-attention-title">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="customer-attention-title" class="section-title">Needs your attention</h2><p class="admin-intro">Call-offs and current amendments awaiting an Office response, including retained inactive-site records.</p></div><a class="admin-link-button" href="{{ route('office.workspace.customers.audit', $customer['uuid']) }}">Customer activity</a></div>
            @forelse($attentionItems as $item)
                <div class="customer-user"><div><p class="font-bold">{{ $item->batch->site->name }} · {{ $item->projectedPlot->plot_reference }}</p><p class="mt-1 text-sm text-slate-600">{{ $item->effectiveServiceIdentifier()?->label() }} · {{ $item->status === \App\Enums\CallOffRequestStatus::AmendmentOnHold ? 'Date amendment' : 'Call-off request' }} · Awaiting Office response</p></div><a class="admin-link-button" href="{{ route('portal.review-requests.show', $item) }}">Review request <span class="sr-only">for {{ $item->batch->site->name }} {{ $item->projectedPlot->plot_reference }}</span></a></div>
            @empty
                <p class="mt-4 text-sm text-slate-600">No call-offs or amendments awaiting your response.</p>
            @endforelse
            @if($attentionCount > $attentionItems->count())<p class="mt-3 text-sm text-slate-600">Showing the latest {{ $attentionItems->count() }} of {{ $attentionCount }} requests. Open a site above to see its remaining requests.</p>@endif
        </section>
        <section class="admin-card" aria-labelledby="customer-users-title">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="customer-users-title" class="section-title">Customer Users <span class="text-base font-normal text-slate-500">({{ $customerUsers->total() }})</span></h2><p class="admin-intro">Accounts belonging to this customer and their current site access.</p></div><div class="admin-actions"><a class="admin-link-button" href="{{ route('office.workspace.users.index', ['customer' => $customer['uuid']]) }}">Manage users</a><a class="secondary-button" href="{{ route('office.workspace.users.create', ['customer' => $customer['uuid']]) }}">Add User</a></div></div>
            @forelse($customerUsers as $user)
                @php
                    $access = match(true) {
                        !$user->is_active => 'Account inactive',
                        $user->isFensterOfficeStaff() => 'Office access',
                        !$customer['is_active'] => 'Customer inactive',
                        !$user->isSiteRole() => 'Role needs attention',
                        !$user->assigned_sites_count => 'No sites assigned',
                        !$user->active_assigned_sites_count => 'No active assigned sites',
                        default => 'Site access assigned',
                    };
                @endphp
                <div class="customer-user"><div><h3 class="font-bold">{{ $user->name }}</h3><p class="mt-1 text-sm text-slate-600">{{ $user->portalRole?->name ?? 'No Portal role' }} · {{ $user->assigned_sites_count }} assigned {{ Str::plural('site', $user->assigned_sites_count) }}</p><p class="mt-1 text-sm font-semibold">{{ $access }}</p></div><a class="admin-link-button" href="{{ route('office.workspace.users.show', $user) }}">View user<span class="sr-only"> {{ $user->name }}</span></a></div>
            @empty
                <p class="mt-4 text-sm text-slate-600">No users belong to this customer yet.</p>
            @endforelse
            {{ $customerUsers->links() }}
        </section>
        <details class="admin-card">
            <summary>Customer administration</summary>
            <div class="space-y-4 pt-2 text-sm text-slate-600">
                <p>Deactivate keeps this customer, its access relationships and history.</p>
                <a class="admin-link-button" href="{{ route('office.workspace.customers.lifecycle', $customer['uuid']) }}">{{ $customer['is_active'] ? 'Deactivate' : 'Reactivate' }} customer</a>
                <div class="border-t border-slate-200 pt-4"><p>Permanent deletion removes eligible disposable data and cannot be undone.</p><a class="destructive-button mt-3" href="{{ route('office.workspace.customers.delete-preview', $customer['uuid']) }}">Delete permanently</a></div>
                <div class="border-t border-red-200 pt-4"><p><strong>Demo/test cleanup only.</strong> Purge removes explicitly certified test data, including associated Wald evidence. Do not use this for genuine customer records.</p><a class="destructive-button mt-3" href="{{ route('office.workspace.customers.demo-purge-preview', $customer['uuid']) }}">Purge demo/test data</a></div>
            </div>
        </details>
    </div>
</x-layouts.portal>