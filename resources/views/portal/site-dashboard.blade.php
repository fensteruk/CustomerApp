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
                    <summary class="min-h-8 cursor-pointer text-sm font-bold text-slate-100">Advanced filters</summary>
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

    <section class="w-full px-3 py-5 sm:px-4 xl:px-3 2xl:px-5" aria-labelledby="page-title">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Plots &amp; call-offs</h1>
                <p class="mt-1 text-sm leading-6 text-slate-600">Cavity Closers, Windows, Snagging and CML status for every plot.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('portal.call-offs.create') }}" class="primary-button min-h-11 px-4 py-2 text-sm">New Call Off</a>
                <a href="{{ route('portal.call-offs.trash') }}" class="secondary-button min-h-11 px-4 py-2 text-sm">Trash</a>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950" role="status">{{ session('status') }}</div>
        @endif
        @include('portal.call-offs._undo-notice')
        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }}</div>
        @endif

        <dl class="mt-5 flex flex-wrap gap-x-8 gap-y-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm" aria-label="Site context">
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Customer</dt><dd class="mt-0.5 font-bold text-slate-950">{{ $activeSite->customerOrganisation->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Signed in as</dt><dd class="mt-0.5 font-bold text-slate-950">{{ $signedInUser->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Plots shown</dt><dd class="mt-0.5 font-bold text-slate-950">{{ $plots->total() }}</dd></div>
        </dl>

        <div class="mt-3 flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
            @if ($lastSynchronisedAt !== null)
                <p><span class="font-bold text-slate-800">Last updated:</span> {{ $lastSynchronisedAt->format('j M Y, H:i') }}</p>
            @else
                <p>Source data not yet synchronised</p>
            @endif
            @if ($missingSourceCount > 0)
                <p class="rounded-md bg-slate-100 px-3 py-2">Some plot information is showing the most recently received update.</p>
            @endif
        </div>

        <section class="mt-5" aria-labelledby="plots-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="plots-heading" class="section-title">Plots at {{ $activeSite->name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">One row per plot. Fully completed plots are hidden by default.</p>
                </div>
                @if ($activeFilterCount > 0)
                    <p class="text-sm font-bold text-sky-800" role="status">{{ $activeFilterCount }} {{ Illuminate\Support\Str::plural('filter', $activeFilterCount) }} active</p>
                @endif
            </div>

            @if ($plots->isNotEmpty())
                <form method="POST" action="{{ route('portal.call-offs.dashboard-selection') }}" class="mt-4 hidden xl:block" x-data="{ selected: [] }">
                    @csrf
                    <div class="mb-2 flex items-center justify-between gap-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2"><p class="text-sm text-slate-700"><strong x-text="selected.length">0</strong> <span x-text="selected.length === 1 ? 'plot selected' : 'plots selected'">plots selected</span><span class="ml-2 text-xs text-slate-500">Page-scoped</span></p><button type="submit" class="primary-button min-h-10 px-4 py-2 text-sm" x-bind:disabled="selected.length === 0" x-bind:aria-disabled="(selected.length === 0).toString()">Call Off Selected</button></div>
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div>
                        <table class="min-w-[62rem] w-full border-collapse text-left">
                            <caption class="sr-only">Plot overview for {{ $activeSite->name }}</caption>
                            <thead class="sticky top-16 z-10 bg-slate-900 text-white shadow-sm">
                                <tr>
                                    <th scope="col" class="min-w-28 px-3 py-3 text-sm font-bold">Plot</th>
                                    @foreach ($serviceTypes as $serviceType)<th scope="col" class="min-w-36 px-2 py-3 text-sm font-bold">{{ $serviceType->label() }}</th>@endforeach
                                    <th scope="col" class="min-w-40 px-2 py-3 text-sm font-bold">Overall Status</th>
                                    <th scope="col" class="min-w-32 px-3 py-3 text-sm font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($plots as $overview)
                                    <tr @class(['bg-indigo-50' => $overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted, 'bg-white' => $overview->overallStatus !== App\Enums\PlotOverallStatus::FullyCompleted])>
                                        <th scope="row" class="px-3 py-3 align-top text-sm font-extrabold text-slate-950"><label class="flex min-h-10 items-center gap-2"><input type="checkbox" name="plots[]" value="{{ $overview->plot->uuid }}" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" x-model="selected" @disabled($overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted)><span>{{ $overview->plot->plot_reference }}</span></label></th>
                                        @foreach ($serviceTypes as $serviceType)<td class="px-2 py-3 align-top"><x-plot-service-status :service="$overview->services[$serviceType->value]" compact /></td>@endforeach
                                        <td class="px-2 py-3 align-top"><x-plot-overall-status :status="$overview->overallStatus" class="px-2 py-1 text-xs" /></td>
                                        <td class="px-3 py-3 align-top"><div class="flex min-w-28 flex-col gap-1.5"><a href="{{ route('portal.plots.show', $overview->plot) }}" class="secondary-button min-h-10 px-2 py-1.5 text-xs">View details</a><a href="{{ route('portal.call-offs.create', ['plots' => [$overview->plot->uuid]]) }}" class="secondary-button min-h-10 px-2 py-1.5 text-xs" aria-label="Call off for {{ $overview->plot->plot_reference }}. Choose service in the next step.">Call Off</a></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                </form>

                <form method="POST" action="{{ route('portal.call-offs.dashboard-selection') }}" class="mt-4 space-y-3 xl:hidden" x-data="{ selected: [] }">
                    @csrf
                    @foreach ($plots as $overview)
                        <article @class(['rounded-xl border p-4 shadow-sm' => true, 'border-indigo-300 bg-indigo-50' => $overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted, 'border-slate-200 bg-white' => $overview->overallStatus !== App\Enums\PlotOverallStatus::FullyCompleted]) aria-labelledby="plot-{{ $overview->plot->uuid }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div class="flex items-start gap-3">@if ($overview->overallStatus !== App\Enums\PlotOverallStatus::FullyCompleted)<input type="checkbox" name="plots[]" value="{{ $overview->plot->uuid }}" class="mt-1 h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" x-model="selected" aria-label="Select {{ $overview->plot->plot_reference }} for a call off">@endif<h3 id="plot-{{ $overview->plot->uuid }}" class="text-xl font-bold text-slate-950">{{ $overview->plot->plot_reference }}</h3></div><x-plot-overall-status :status="$overview->overallStatus" /></div>
                            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                                @foreach ($serviceTypes as $serviceType)
                                    <div><dt class="mb-1 text-sm font-bold text-slate-700">{{ $serviceType->label() }}</dt><dd><x-plot-service-status :service="$overview->services[$serviceType->value]" /></dd></div>
                                @endforeach
                            </dl>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2"><a href="{{ route('portal.plots.show', $overview->plot) }}" class="secondary-button w-full">View details</a><a href="{{ route('portal.call-offs.create', ['plots' => [$overview->plot->uuid]]) }}" class="primary-button w-full" aria-label="Call off for {{ $overview->plot->plot_reference }}. Choose service in the next step.">Call Off</a></div>
                        </article>
                    @endforeach
                    <div class="sticky bottom-3 rounded-xl border border-sky-200 bg-sky-50 p-3 shadow-lg"><p class="mb-2 text-sm font-bold text-slate-900"><span x-text="selected.length">0</span> <span x-text="selected.length === 1 ? 'plot selected' : 'plots selected'">plots selected</span></p><button type="submit" class="primary-button w-full" x-bind:disabled="selected.length === 0" x-bind:aria-disabled="(selected.length === 0).toString()">Call Off Selected</button></div>
                </form>
            @else
                <div class="empty-state mt-5">
                    @if (! $hasProjectedPlots)
                        <h3 class="text-lg font-bold text-slate-900">No projected plots</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-700">There are no plots available for this assigned site yet.</p>
                    @elseif (! $filters['show_completed'] && collect($filters)->except('show_completed')->filter()->isEmpty())
                        <h3 class="text-lg font-bold text-slate-900">All current plots are fully completed</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Use Show Completed to view them.</p>
                    @else
                        <h3 class="text-lg font-bold text-slate-900">No plots match your filters</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Try changing or clearing the filters to see other plots for this assigned site.</p>
                    @endif
                </div>
            @endif

            @if ($plots->hasPages())
                <nav class="mt-6 rounded-lg border border-slate-200 bg-white p-3 shadow-sm" aria-label="Plot pages">
                    <p class="mb-3 text-center text-sm font-semibold text-slate-700">Showing {{ $plots->firstItem() }}–{{ $plots->lastItem() }} of {{ $plots->total() }} plots</p>
                    {{ $plots->links() }}
                </nav>
            @endif

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
        </section>
    </section>
</x-layouts.portal>
