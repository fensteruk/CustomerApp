<x-layouts.portal :title="($target->is_active ? 'Deactivate ' : 'Reactivate ').$target->name.' | Fenster'" sidebar-label="Menu">
    <div class="admin-workspace min-w-0 max-w-3xl">
        <a class="admin-back [overflow-wrap:anywhere]" href="{{ route('office.workspace.users.show', $target) }}">← {{ $target->name }}</a>
        <header><p class="eyebrow">Account status</p><h1 class="admin-title">{{ $target->is_active ? 'Deactivate User' : 'Reactivate User' }}</h1><p class="admin-intro">Review this account before confirming the change.</p></header>
        @include('office.partials.feedback')
        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-label="Account to update">
            <h2 class="text-xl font-bold text-slate-950 [overflow-wrap:anywhere]">{{ $target->name }}</h2><p class="mt-1 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $target->email }}</p><p class="mt-3 text-sm font-semibold text-slate-800">{{ $target->portalRole?->name ?? 'Role unavailable' }}</p><div class="mt-3">@include('office.partials.status', ['active' => $target->is_active])</div>
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950"><p>{{ $target->is_active ? 'This user will be unable to access the portal. Assignments and history will be retained.' : ($target->isFensterOfficeStaff() ? 'This Office account will regain global Office access. No customer or site assignment is required.' : 'This user can sign in again, but site access still depends on an active customer and active assigned sites.') }}</p></div>
            <form class="mt-5" method="POST" action="{{ route('portal.office.users.'.($target->is_active ? 'deactivate' : 'reactivate'), $target) }}">@csrf<input type="hidden" name="lock_version" value="{{ $target->lock_version }}"><div class="flex flex-col gap-3 sm:flex-row"><button class="{{ $target->is_active ? 'reject-button' : 'primary-button' }}" type="submit">Confirm {{ $target->is_active ? 'Deactivation' : 'Reactivation' }}</button><a class="secondary-button" href="{{ route('office.workspace.users.show', $target) }}">Cancel</a></div></form>
        </section>
    </div>
</x-layouts.portal>
