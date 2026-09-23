@php
    $office = $target->isFensterOfficeStaff();
    $external = $target->isSiteRole();
    $customer = $target->customerOrganisation;
    $availableSites = $target->assignedSites->filter(fn ($site) => $site->is_active && $site->customerOrganisation?->is_active && (int) $site->customer_organisation_id === (int) $target->customer_organisation_id);
    [$accessTitle, $accessHelp] = match (true) {
        ! $target->is_active => ['Access paused', 'This account is inactive. Assignments and history are retained.'],
        $office => ['Access configured', 'This active Office account can manage all customers, sites and users.'],
        ! $external => ['Role needs attention', 'Choose a valid Portal role before this user can access the portal.'],
        ! $customer => ['No customer assigned', 'Select a customer, then assign the sites this user needs.'],
        ! $customer->is_active => ['Customer inactive', 'Site access is paused while the customer is inactive.'],
        $target->assignedSites->isEmpty() => ['No sites assigned', 'This user cannot access a site yet.'],
        $availableSites->isEmpty() => ['No available sites', 'The assigned sites are inactive or do not belong to this customer. Review the assignments below.'],
        $availableSites->count() !== $target->assignedSites->count() => ['Some sites unavailable', 'Only active assigned sites belonging to this customer are available.'],
        default => ['Access configured', 'This user can access the active sites assigned below.'],
    };
    $ready = $accessTitle === 'Access configured';
    $initials = Str::of($target->name)->squish()->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<x-layouts.portal :title="$target->name.' | Users | Fenster'" sidebar-label="Menu">
    <div class="admin-workspace min-w-0" data-manage-user-workspace>
        <a class="admin-back" href="{{ route('office.workspace.users.index') }}">← Users</a>
        <header class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <span class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-full bg-sky-100 text-lg font-bold uppercase text-sky-900 min-[390px]:flex" aria-hidden="true">{{ $initials }}</span>
                <div class="min-w-0"><p class="eyebrow">Manage user</p><h1 class="admin-title [overflow-wrap:anywhere]">{{ $target->name }}</h1><p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $target->email }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2"><span class="text-sm font-semibold text-slate-800">{{ $target->portalRole?->name ?? 'Role unavailable' }}</span>@include('office.partials.status', ['active' => $target->is_active])</div>
                </div>
            </div>
            <a class="primary-button shrink-0" href="{{ route('office.workspace.users.edit', $target) }}">Edit User</a>
        </header>
        @include('office.partials.feedback')
        <div class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0 space-y-5">
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="access-heading">
                    <h2 id="access-heading" class="section-title">Access overview</h2>
                    <div @class(['mt-4 flex gap-3 rounded-xl border p-4', 'border-emerald-200 bg-emerald-50 text-emerald-950' => $ready, 'border-amber-200 bg-amber-50 text-amber-950' => ! $ready])>
                        @include('office.users.icon', ['icon' => $ready ? 'globe' : 'warning'])
                        <div class="min-w-0"><h3 class="font-bold">{{ $accessTitle }}</h3><p class="mt-1 text-sm leading-6">{{ $accessHelp }}</p></div>
                    </div>
                    <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2">
                        <div><dt class="font-medium text-slate-500">Access level</dt><dd class="mt-1 font-bold text-slate-900">{{ $office ? 'Global Office access' : ($external ? 'Customer + assigned sites' : 'Not configured') }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Portal role</dt><dd class="mt-1 font-semibold text-slate-900">{{ $target->portalRole?->name ?? 'Role unavailable' }}</dd></div>
                        @unless ($office)
                            <div><dt class="font-medium text-slate-500">Customer</dt><dd class="mt-1 font-semibold text-slate-900 [overflow-wrap:anywhere]">@if ($customer)<a class="admin-text-link" href="{{ route('office.workspace.customers.show', $customer) }}">{{ $customer->name }}</a>@else No customer assigned @endif</dd></div>
                            <div><dt class="font-medium text-slate-500">Assigned sites</dt><dd class="mt-1 font-semibold text-slate-900">{{ $target->assignedSites->count() }} assigned · {{ $availableSites->count() }} active for this customer</dd></div>
                        @endunless
                    </dl>
                    @if ($office)<p class="mt-4 text-sm leading-6 text-slate-600">Office access comes from the Fenster Office Staff role. A customer or site assignment is not required.</p>@endif
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="sites-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="sites-heading" class="section-title">{{ $office ? 'Recorded assignments' : 'Assigned sites' }}</h2><p class="mt-1 text-sm leading-6 text-slate-600">{{ $office ? 'Any historical assignments are retained; they do not limit global Office access.' : 'Site access is limited to the selected customer and the assignments below.' }}</p></div>
                        @unless ($office)<a class="secondary-button !min-h-11 !px-3 !py-2 !text-sm" href="{{ route('office.workspace.users.edit', $target) }}#user-access">Manage assignments</a>@endunless
                    </div>
                    <ul class="mt-4 max-h-96 space-y-3 overflow-y-auto" aria-label="{{ $office ? 'Recorded site assignments' : 'Assigned sites' }}">
                        @forelse ($target->assignedSites as $site)
                            <li class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2"><a class="admin-text-link min-w-0 [overflow-wrap:anywhere]" href="{{ route('office.workspace.sites.show', [$site->customerOrganisation, $site]) }}">{{ $site->name }}</a>@include('office.partials.status', ['active' => $site->is_active && $site->customerOrganisation->is_active])</div>
                                <p class="mt-2 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $site->customerOrganisation->name }}</p>
                                @if (! $office && (int) $site->customer_organisation_id !== (int) $target->customer_organisation_id)<p class="mt-2 text-sm font-bold text-amber-900">Different customer — review this assignment.</p>@endif
                            </li>
                        @empty
                            <li class="rounded-lg bg-slate-50 p-4"><h3 class="font-semibold text-slate-900">{{ $office ? 'No site assignments required' : 'No sites assigned' }}</h3><p class="mt-1 text-sm leading-6 text-slate-600">{{ $office ? 'This account uses global Office access.' : 'This user cannot access a site yet.' }}</p></li>
                        @endforelse
                    </ul>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="activity-heading">
                    <h2 id="activity-heading" class="section-title">Account activity</h2><p class="mt-1 text-sm leading-6 text-slate-600">Recorded changes to this account and its site assignments.</p>
                    <ol class="mt-4 divide-y divide-slate-100">@forelse ($audits as $audit)<li class="py-3"><p class="font-semibold text-slate-900">{{ Str::headline($audit->action->value) }}</p><p class="mt-1 text-sm text-slate-600 [overflow-wrap:anywhere]">{{ $audit->actor_name }} · <time datetime="{{ $audit->occurred_at->toIso8601String() }}">{{ $audit->occurred_at->format('j M Y, H:i') }}</time></p></li>@empty<li class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No account-management activity recorded yet.</li>@endforelse</ol>
                    @if ($audits->hasPages())<div class="mt-4">{{ $audits->links() }}</div>@endif
                </section>
            </div>
            <aside class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="status-heading">
                <h2 id="status-heading" class="section-title">Account status</h2><div class="mt-3">@include('office.partials.status', ['active' => $target->is_active])</div>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $target->is_active ? 'Deactivating this account stops portal access. Its assignments and history are retained.' : 'This user cannot sign in. Reactivating restores access according to their role and current assignments.' }}</p>
                @if (auth()->id() === $target->id && $target->is_active)<p class="mt-4 text-sm font-semibold text-slate-700">You cannot deactivate your own account.</p>@else<a class="secondary-button mt-4 w-full" href="{{ route('office.workspace.users.lifecycle', $target) }}">{{ $target->is_active ? 'Deactivate' : 'Reactivate' }} user</a>@endif
            </aside>
        </div>
    </div>
</x-layouts.portal>
