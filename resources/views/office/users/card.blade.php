@php
    $office = $user->isFensterOfficeStaff();
    $external = $user->isSiteRole();
    $missingCustomer = $external && ! $user->customerOrganisation;
    $missingSites = $external && $user->assigned_sites_count === 0;
    $attention = $missingCustomer || $missingSites;
    $initials = Str::of($user->name)->squish()->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<article @class(['flex min-w-0 flex-col rounded-xl border p-4 transition sm:p-5', 'border-rose-200 bg-rose-50/30' => $attention, 'border-slate-200 bg-white' => ! $attention]) aria-labelledby="user-{{ $user->uuid }}-name" data-user-card="{{ $user->uuid }}">
    <div class="flex min-w-0 items-start gap-3">
        <span aria-hidden="true" @class(['flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold uppercase', 'bg-rose-100 text-rose-900' => $attention, 'bg-sky-100 text-sky-900' => ! $attention && $office, 'bg-emerald-50 text-emerald-900' => ! $attention && ! $office])>{{ $initials }}</span>
        <div class="min-w-0 flex-1"><h2 id="user-{{ $user->uuid }}-name" class="text-base font-bold leading-6 text-slate-950 [overflow-wrap:anywhere]">{{ $user->name }}</h2><p class="mt-1 text-sm leading-5 text-slate-600 [overflow-wrap:anywhere]">{{ $user->email }}</p></div>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
        <p class="flex min-w-0 items-center gap-2 text-sm font-bold text-slate-800">@include('office.users.icon', ['icon' => $office ? 'office' : 'person'])<span>{{ $user->portalRole?->name ?? 'Role unavailable' }}</span></p>
        <span @class(['rounded-full px-2.5 py-1 text-xs font-bold', 'bg-emerald-50 text-emerald-800' => $user->is_active, 'bg-slate-100 text-slate-600' => ! $user->is_active])>{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
    </div>
    <dl class="mt-4 space-y-4 border-t border-slate-100 pt-4 text-sm">
        @if ($office)
            <div class="flex gap-3">@include('office.users.icon', ['icon' => 'globe'])<div class="min-w-0"><dt class="text-xs font-semibold text-slate-500">Access level</dt><dd class="mt-1 font-semibold text-slate-900">Global Office access</dd><dd class="mt-1 leading-6 text-slate-600">{{ $user->is_active ? 'Can manage all customers, sites and users.' : 'Access is paused while this account is inactive.' }}</dd></div></div>
        @else
            <div class="flex gap-3">@include('office.users.icon', ['icon' => 'office'])<div class="min-w-0"><dt class="text-xs font-semibold text-slate-500">Customer</dt><dd class="mt-1 leading-6 text-slate-800 [overflow-wrap:anywhere]">{{ $user->customerOrganisation?->name ?? 'No customer assigned' }}@if ($user->customerOrganisation && ! $user->customerOrganisation->is_active)<span class="block text-xs text-slate-500">Customer inactive — site access paused</span>@endif</dd></div></div>
            <div class="flex gap-3">@include('office.users.icon', ['icon' => 'sites'])<div class="min-w-0"><dt class="text-xs font-semibold text-slate-500">Assigned sites</dt><dd class="mt-1 font-semibold text-slate-800">{{ $user->assigned_sites_count }} {{ Str::plural('site', $user->assigned_sites_count) }}</dd><dd class="mt-1 leading-6 text-slate-600 [overflow-wrap:anywhere]">@foreach ($user->assignedSites as $site)<span class="block">{{ $site->name }}{{ $site->is_active ? '' : ' — inactive' }}</span>@endforeach @if ($user->assigned_sites_count > $user->assignedSites->count())<span class="mt-1 block text-xs">+{{ $user->assigned_sites_count - $user->assignedSites->count() }} more · see Manage user</span>@endif</dd></div></div>
        @endif
    </dl>
    <div class="mt-auto pt-5">
        @if ($attention)
            <div class="mb-3 flex gap-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-900">@include('office.users.icon', ['icon' => 'warning'])<div class="min-w-0"><p class="font-bold">{{ $missingCustomer ? 'No customer assigned' : 'No sites assigned' }}</p><p class="mt-1 leading-5">This user cannot access a site yet.</p><a class="mt-1 inline-flex min-h-11 items-center gap-2 rounded font-bold underline underline-offset-4" href="{{ route('office.workspace.users.edit', $user) }}" aria-label="{{ $missingCustomer ? 'Configure access for ' : 'Assign site to ' }}{{ $user->name }}">{{ $missingCustomer ? 'Configure access' : 'Assign site' }} <span aria-hidden="true">→</span></a></div></div>
        @elseif (! $user->is_active)
            <p class="mb-3 text-sm leading-6 text-slate-500">Account inactive. This user cannot sign in.</p>
        @elseif ($office)
            <p class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-600"><span aria-hidden="true" class="text-emerald-700">✓</span> Access configured</p>
        @endif
        <a class="flex min-h-11 w-full items-center justify-center gap-3 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-sm font-bold text-sky-800 hover:border-sky-300 hover:bg-sky-100" href="{{ route('office.workspace.users.show', $user) }}" aria-label="Manage user {{ $user->name }}">Manage user <span aria-hidden="true">→</span></a>
    </div>
</article>
