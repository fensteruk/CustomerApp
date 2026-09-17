<section aria-labelledby="users-title">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="users-title" class="section-title">Assigned Users</h2><p class="mt-2 text-sm text-slate-600">External users who currently have access to this site.</p></div><div class="admin-actions"><a class="primary-button" href="{{ route('office.workspace.sites.assign-user', [$site['customer']['uuid'], $site['uuid']]) }}">Assign Existing User</a><a class="secondary-button" href="{{ route('office.workspace.users.create', ['customer' => $site['customer']['uuid'], 'site' => $site['uuid']]) }}">Add New User</a></div></div>
    <div class="admin-card-grid mt-4">
        @forelse ($items as $user)
            <article class="admin-card">
                <div class="flex items-start justify-between gap-3"><h3 class="admin-card-title">@if($user['management_identity_available'] ?? false)<a class="admin-text-link" href="{{ route('office.workspace.users.show', $user['uuid']) }}">{{ $user['name'] }}</a>@else{{ $user['name'] }}@endif</h3>@include('office.partials.status', ['active' => $user['is_active']])</div>
                <p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $user['email'] }}</p>
                <p class="mt-2 text-sm font-semibold">{{ $user['role_label'] ?: 'Role unavailable' }}</p>
                @if($user['management_identity_available'] ?? false)<div class="admin-actions mt-3"><a class="admin-link-button" href="{{ route('office.workspace.users.edit', $user['uuid']) }}">View / Edit User</a><form method="POST" action="{{ route('portal.office.sites.users.destroy', [$site['customer']['uuid'], $site['uuid'], $user['uuid']]) }}" onsubmit="return confirm('Remove this user’s access to this site?')">@csrf @method('DELETE')<button class="reject-button" type="submit">Remove Access</button></form></div>@endif
            </article>
        @empty
            <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="section-title">No assigned users</h3><p class="mt-2">No customer users are currently assigned to this site.</p></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $items->links() }}</div>
</section>
