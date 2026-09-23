<x-layouts.portal title="Site dashboard | Fenster Customer Portal" sidebar-label="Filters" :sidebar-badge="$activeFilterCount">
    <x-slot:sidebar>
        <section aria-labelledby="sidebar-site-heading">
            <div class="flex items-center justify-between gap-3 px-3">
                <h2 id="sidebar-site-heading" class="text-xs font-extrabold uppercase tracking-[0.16em] text-slate-400">Active site</h2>
            </div>
            <form method="POST" action="{{ route('sites.active.store') }}" class="mt-3 space-y-3">
                @csrf
                <label for="sidebar-site" class="sr-only">Active site</label>
                <select id="sidebar-site" name="site_id" class="sidebar-input mt-0">
                    @foreach ($assignedSites as $site)
                        <option value="{{ $site->id }}" @selected($site->is($activeSite))>{{ $site->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="sidebar-clear">Switch site</button>
            </form>
        </section>

        <section class="mt-5 border-t border-slate-700 pt-5" aria-labelledby="sidebar-filters-heading">
            <div class="flex items-center justify-between gap-3 px-3">
                <h2 id="sidebar-filters-heading" class="text-xs font-extrabold uppercase tracking-[0.16em] text-slate-400">Filters</h2>
                @if ($activeFilterCount > 0)
                    <span class="rounded-full bg-sky-600 px-2 py-0.5 text-xs font-extrabold text-white">{{ $activeFilterCount }} active</span>
                @endif
            </div>

            <form method="GET" action="{{ route('portal.site-dashboard') }}" class="mt-4 space-y-5" aria-label="Filter plot overview">
                <div>
                    <label for="plot" class="sidebar-label">Find a plot</label>
                    <input id="plot" name="plot" type="search" value="{{ $filters['plot'] }}" class="sidebar-input" placeholder="e.g. Plot 101">
                </div>

                <fieldset>
                    <legend class="sidebar-label">Overall status</legend>
                    <div class="mt-2 space-y-1">
                        @foreach ($overallStatuses as $overallStatus)
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold text-slate-100 hover:bg-slate-800">
                                <input type="checkbox" name="overall_status[]" value="{{ $overallStatus->value }}" @checked(in_array($overallStatus->value, $filters['overall_status'], true)) class="sidebar-check">
                                <span>{{ $overallStatus->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div>
                    <label for="service" class="sidebar-label">Service activity</label>
                    <select id="service" name="service" class="sidebar-input">
                        <option value="">All services</option>
                        @foreach ($serviceTypes as $serviceType)
                            <option value="{{ $serviceType->value }}" @selected($filters['service'] === $serviceType->value)>{{ $serviceType->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <details @if ($filters['status'] !== '') open @endif class="rounded-lg border border-slate-700 bg-slate-800/40 p-3">
                    <summary class="min-h-11 cursor-pointer text-sm font-bold text-slate-100">Advanced filters</summary>
                    <div class="mt-3">
                        <label for="status" class="sidebar-label">Service status</label>
                        <select id="status" name="status" class="sidebar-input">
                            <option value="">All statuses</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->value }}" @selected($filters['status'] === $state->value)>{{ $state->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-400">Matches any service, or the service selected above.</p>
                    </div>
                </details>

                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-slate-700 px-3 py-2 text-sm font-bold text-slate-100 hover:bg-slate-800">
                    <input type="checkbox" name="show_completed" value="1" @checked($filters['show_completed']) class="sidebar-check">
                    Show Completed
                </label>

                <div class="space-y-2">
                    <button type="submit" class="sidebar-button">Apply filters</button>
                    @if ($activeFilterCount > 0)
                        <a href="{{ route('portal.site-dashboard') }}" class="sidebar-clear">Clear filters</a>
                    @endif
                </div>
            </form>
        </section>
    </x-slot:sidebar>

    @include('site-workspace.styles')
    <div class="site-workspace">
        <a class="sw-back" href="{{ route('sites.select') }}">← Assigned sites</a>
        <header class="sw-header">
            <div><h1 id="page-title">{{ $activeSite->name }}</h1><p class="sw-customer">{{ $activeSite->customerOrganisation->name }}</p><p class="sw-muted">{{ $activeSite->location }}</p><span class="sw-badge sw-active">Active site</span></div>
            <div class="sw-actions"><a href="{{ route('portal.call-offs.create') }}" class="sw-button sw-primary">Request a call-off</a><a href="{{ route('portal.call-offs.trash') }}" class="sw-button">Trash</a></div>
        </header>
        @if (session('status'))<div class="admin-notice" role="status">{{ session('status') }}</div>@endif
        @include('portal.call-offs._undo-notice')
        @if ($errors->any())<div class="admin-notice" role="alert">{{ $errors->first() }}</div>@endif
        @include('site-workspace.summary')
        <section class="sw-section" aria-labelledby="plots-heading">
            <div class="sw-section-heading"><div><h2 id="plots-heading">Plots &amp; call-offs</h2><p class="sw-muted">Find a plot to view its services and dates.</p></div><span class="sw-badge">{{ $plots->total() }} {{ Str::plural('plot', $plots->total()) }} shown</span></div>
            <form method="GET" action="{{ route('portal.site-dashboard') }}" class="sw-filters" aria-label="Find site plots">
                <div class="sw-field"><label for="site-plot-search">Find a plot</label><input type="search" id="site-plot-search" name="plot" value="{{ $filters['plot'] }}" maxlength="100" placeholder="Plot number or reference"></div>
                <div class="sw-field"><label for="site-plot-status">Plot status</label><select id="site-plot-status" name="overall_status[]"><option value="">All current plots</option>@foreach ($overallStatuses as $status)<option value="{{ $status->value }}" @selected(in_array($status->value, $filters['overall_status'], true))>{{ $status->label() }}</option>@endforeach</select></div>
                @if ($filters['service'])<input type="hidden" name="service" value="{{ $filters['service'] }}">@endif
                @if ($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                @if ($filters['show_completed'])<input type="hidden" name="show_completed" value="1">@endif
                <button type="submit" class="sw-button sw-primary">Find plots</button>
                @if ($activeFilterCount)<a href="{{ route('portal.site-dashboard') }}" class="sw-button">Clear filters</a>@endif
            </form>
            <p class="sw-muted">Fully completed plots are hidden by default. Use Show Completed in Filters to include them.</p>
            @if ($activeFilterCount)<p class="sw-muted" role="status">{{ $activeFilterCount }} {{ Str::plural('filter', $activeFilterCount) }} active</p>@endif
            @if ($plots->isNotEmpty())
                <form method="POST" action="{{ route('portal.call-offs.dashboard-selection') }}" x-data="{ selected: [] }">
                    @csrf
                    @include('site-workspace.cards')
                    <div class="sw-selection"><p aria-live="polite"><strong x-text="selected.length">0</strong> <span x-text="selected.length === 1 ? 'plot selected' : 'plots selected'">plots selected</span> <span class="sw-muted">· This page</span></p><button type="submit" class="sw-button sw-primary" :disabled="selected.length === 0">Call Off Selected</button></div>
                </form>
            @else
                <div class="empty-state mt-5">
                    @if (! $hasProjectedPlots)<h3 class="section-title">No plots yet</h3><p>There are no plots available for this assigned site yet.</p>
                    @elseif (! $filters['show_completed'] && collect($filters)->except('show_completed')->filter()->isEmpty())<h3 class="section-title">All current plots are fully completed</h3><p>Use Show Completed to view them.</p>
                    @else<h3 class="section-title">No plots match your filters</h3><p>Try changing or clearing the filters to see other plots for this assigned site.</p>@endif
                </div>
            @endif
            @if ($plots->hasPages())<nav class="mt-5" aria-label="Plot pages"><p class="sw-muted">Showing {{ $plots->firstItem() }}–{{ $plots->lastItem() }} of {{ $plots->total() }} plots</p>{{ $plots->links() }}</nav>@endif
        </section>
        @include('site-workspace.upcoming')
        <details class="sw-disclosure"><summary>About this site’s information</summary><p class="sw-muted">{{ $lastSynchronisedAt ? 'Last updated: '.$lastSynchronisedAt->format('j M Y, H:i') : 'Source data not yet synchronised' }}</p>@if ($missingSourceCount)<p class="sw-muted">Some plot information is showing the most recently received update.</p>@endif</details>
            @if ($legacyRequests->isNotEmpty())
                <details class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer text-base font-bold text-slate-900">Manage existing call-offs</summary>
                    <p class="mt-3 text-sm leading-6 text-slate-700">Withdrawal, resubmission and Trash remain available for existing call-offs. The plot overview above stays focused on the current service position.</p>
                    <form method="POST" action="{{ route('portal.call-offs.lifecycle.confirm') }}" class="mt-5 space-y-3" x-data="lifecycleSelection()">
                        @csrf
                        @foreach ($legacyRequests as $callOffRequest)
                            @php($service = $callOffRequest->effectiveServiceIdentifier())
                            <article class="flex flex-col gap-3 rounded-lg border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div><h3 class="font-bold text-slate-950">{{ $callOffRequest->projectedPlot->plot_reference }} · {{ $service?->label() ?? 'Call-off' }}</h3><p class="mt-1 text-sm text-slate-600">{{ $callOffRequest->isLegacyDateAgreed() ? 'Date Agreed' : $callOffRequest->status->label() }}</p></div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($callOffRequest->status === App\Enums\CallOffRequestStatus::Rejected && Gate::allows('resubmit-call-off', $callOffRequest))
                                        <a href="{{ route('portal.call-offs.resubmit.create', $callOffRequest) }}" class="secondary-button min-h-11 px-3 py-2 text-sm">Resubmit</a>
                                    @endif
                                    @if (in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::Submitted, App\Enums\CallOffRequestStatus::AwaitingFenster, App\Enums\CallOffRequestStatus::AwaitingSiteUser, App\Enums\CallOffRequestStatus::Rejected, App\Enums\CallOffRequestStatus::Withdrawn], true))
                                        <label class="flex min-h-11 items-center gap-2 rounded-lg bg-slate-100 px-3 text-sm font-bold text-slate-800"><input type="checkbox" name="requests[]" value="{{ $callOffRequest->uuid }}" data-withdraw="{{ in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::Submitted, App\Enums\CallOffRequestStatus::AwaitingFenster, App\Enums\CallOffRequestStatus::AwaitingSiteUser], true) ? 'true' : 'false' }}" data-trash="{{ in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::Rejected, App\Enums\CallOffRequestStatus::Withdrawn], true) ? 'true' : 'false' }}" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" @change="update($event)"> Select</label>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                        <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm font-bold text-slate-800" aria-live="polite"><span x-text="count"></span> selected</p><div class="mt-3 grid gap-3 sm:grid-cols-2"><button type="submit" name="operation" value="withdraw" class="secondary-button w-full" :disabled="!can('withdraw')" :aria-disabled="(!can('withdraw')).toString()">Withdraw selected</button><button type="submit" name="operation" value="trash" class="secondary-button w-full" :disabled="!can('trash')" :aria-disabled="(!can('trash')).toString()">Move selected to Trash</button></div></div>
                    </form>
                    @if ($legacyRequests->hasPages())<div class="mt-5">{{ $legacyRequests->links() }}</div>@endif
                </details>
            @endif

    </div>
</x-layouts.portal>
