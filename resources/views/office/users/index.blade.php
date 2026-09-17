<x-layouts.portal title="Users | Fenster Customer Portal" sidebar-label="User filters">
    <x-slot:sidebar>
        <form method="GET" action="{{ route('office.workspace.users.index') }}" class="space-y-4">
            <div><label class="sidebar-label" for="user-search">Search users</label><input class="sidebar-input" id="user-search" name="search" value="{{ $search }}" placeholder="Name or email"></div>
            <div><label class="sidebar-label" for="user-active">Status</label><select class="sidebar-input" id="user-active" name="active"><option value="all" @selected($active === 'all')>All</option><option value="active" @selected($active === 'active')>Active</option><option value="inactive" @selected($active === 'inactive')>Inactive</option></select></div>
            <button class="sidebar-button" type="submit">Apply filters</button>
            <a class="sidebar-clear" href="{{ route('office.workspace.users.index') }}">Clear filters</a>
        </form>
    </x-slot:sidebar>
    <div class="admin-workspace">
        <header class="admin-page-header"><div><p class="eyebrow">Office administration</p><h1 class="admin-title">Users</h1><p class="admin-intro">Manage portal accounts, customer ownership and site access.</p></div><a class="primary-button" href="{{ route('office.workspace.users.create') }}">Add User</a></header>
        @include('office.partials.feedback')
        <div class="admin-card-grid">
            @forelse ($users as $user)
                <article class="admin-card">
                    <div class="flex items-start justify-between gap-3"><h2 class="admin-card-title"><a class="admin-text-link" href="{{ route('office.workspace.users.show', $user) }}">{{ $user->name }}</a></h2>@include('office.partials.status', ['active' => $user->is_active])</div>
                    <p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $user->email }}</p><p class="mt-2 font-semibold">{{ $user->portalRole?->name ?? 'Role unavailable' }}</p><p class="mt-1 text-sm text-slate-600">{{ $user->customerOrganisation?->name ?? 'Fenster / global access' }}</p><p class="mt-2 text-sm">{{ $user->assigned_sites_count }} assigned {{ Str::plural('site', $user->assigned_sites_count) }}</p>
                    <a class="admin-link-button mt-2" href="{{ route('office.workspace.users.show', $user) }}">View / Edit User</a>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3"><h2 class="section-title">No users found</h2><p class="mt-2">Try another search or add a user.</p></div>
            @endforelse
        </div>{{ $users->links() }}
    </div>
</x-layouts.portal>
