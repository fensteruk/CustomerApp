<section aria-labelledby="users-title">
    <h2 id="users-title" class="section-title">Assigned users</h2>
    <p class="mt-2 text-sm text-slate-600">These are the users currently assigned to this site. Account and assignment changes are managed separately.</p>
    <div class="admin-card-grid mt-4">
        @forelse ($items as $user)
            <article class="admin-card">
                <div class="flex items-start justify-between gap-3"><h3 class="admin-card-title">{{ $user['name'] }}</h3>@include('office.partials.status', ['active' => $user['is_active']])</div>
                <p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $user['email'] }}</p>
                <p class="mt-2 text-sm font-semibold">{{ $user['role_label'] ?: 'Role unavailable' }}</p>
            </article>
        @empty
            <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="section-title">No assigned users</h3><p class="mt-2">No customer users are currently assigned to this site.</p></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $items->links() }}</div>
</section>
