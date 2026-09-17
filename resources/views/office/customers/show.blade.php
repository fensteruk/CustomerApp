<x-layouts.portal :title="$customer['name'].' | Customers | Fenster'" sidebar-label="Menu">
    <x-slot:sidebar>
        @include('office.partials.filters', ['action' => route('office.workspace.customers.show', $customer['uuid']), 'label' => 'Site filters', 'placeholder' => 'Site name or location'])
    </x-slot:sidebar>
    <div class="admin-workspace">
        <a class="admin-back" href="{{ route('office.workspace.customers.index') }}">← Customers</a>
        <header class="admin-page-header">
            <div><p class="eyebrow">Customer</p><h1 class="admin-title">{{ $customer['name'] }}</h1><div class="mt-3">@include('office.partials.status', ['active' => $customer['is_active']])</div></div>
            <div class="admin-actions">
                <a class="primary-button" href="{{ route('office.workspace.sites.create', $customer['uuid']) }}">Add Site</a>
                <a class="secondary-button" href="{{ route('office.workspace.customers.edit', $customer['uuid']) }}">Edit Customer</a>
                <a class="admin-link-button" href="{{ route('office.workspace.customers.lifecycle', $customer['uuid']) }}">{{ $customer['is_active'] ? 'Deactivate' : 'Reactivate' }}</a>
            </div>
        </header>
        @include('office.partials.feedback')
        @unless ($customer['is_active'])
            <div class="admin-notice">This customer is inactive. External users cannot access the portal for this customer. Sites, users and history are retained.</div>
        @endunless
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Sites <span class="text-base font-normal text-slate-500">({{ $sites->total() }})</span></h2>
            <a class="admin-link-button" href="{{ route('office.workspace.customers.audit', $customer['uuid']) }}">Customer activity</a>
        </div>
        <div class="admin-card-grid">
            @forelse ($sites as $site)
                <article class="admin-card">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="admin-card-title"><a class="admin-text-link" href="{{ route('office.workspace.sites.show', [$customer['uuid'], $site['uuid']]) }}">{{ $site['name'] }}</a></h3>
                        @include('office.partials.status', ['active' => $site['is_active']])
                    </div>
                    <p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $site['location'] ?: 'No location added' }}</p>
                    <p class="mt-3 text-sm text-slate-600">{{ $site['plot_count'] }} {{ Str::plural('plot', $site['plot_count']) }} · {{ $site['assignment_count'] }} assigned {{ Str::plural('user', $site['assignment_count']) }}</p>
                    <a class="admin-link-button mt-3" href="{{ route('office.workspace.sites.show', [$customer['uuid'], $site['uuid']]) }}" aria-label="View {{ $site['name'] }}">View Site</a>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="section-title">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'No matching sites' : 'No sites yet' }}</h3><p class="mt-2">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'Try another search or clear the filters in the menu.' : 'Use Add Site to create the first site. A source reference is not needed.' }}</p></div>
            @endforelse
        </div>
        {{ $sites->links() }}
        @isset($customerUsers)<section class="border-t border-slate-200 pt-5" aria-labelledby="customer-users-title">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="customer-users-title" class="section-title">Customer Users <span class="text-base font-normal text-slate-500">({{ $customerUsers->total() }})</span></h2><p class="mt-1 text-sm text-slate-600">Accounts belonging to this customer and their current site access.</p></div><a class="primary-button" href="{{ route('office.workspace.users.create', ['customer' => $customer['uuid']]) }}">Add User</a></div>
            <div class="admin-card-grid mt-4">
                @forelse($customerUsers as $user)<article class="admin-card"><div class="flex items-start justify-between gap-3"><h3 class="admin-card-title"><a class="admin-text-link" href="{{ route('office.workspace.users.show', $user) }}">{{ $user->name }}</a></h3>@include('office.partials.status', ['active' => $user->is_active])</div><p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $user->email }}</p><p class="mt-2 font-semibold">{{ $user->portalRole?->name }}</p><p class="mt-1 text-sm text-slate-600">{{ $user->assigned_sites_count }} assigned {{ Str::plural('site', $user->assigned_sites_count) }}</p><a class="admin-link-button mt-2" href="{{ route('office.workspace.users.edit', $user) }}">View / Edit User</a></article>@empty<div class="empty-state"><p>No users belong to this customer yet.</p></div>@endforelse
            </div>{{ $customerUsers->links() }}
        </section>@endisset
    </div>
</x-layouts.portal>
