<x-layouts.portal title="Site dashboard | Fenster Customer Portal">
    <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">Plot overview</h1>
                <p class="page-intro">See each plot and its current Cavity Closers, Windows, Snagging and CML status.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('portal.call-offs.create') }}" class="primary-button">New Call Off</a>
                <a href="{{ route('portal.call-offs.trash') }}" class="secondary-button">Trash</a>
                <a href="{{ route('sites.select') }}" class="secondary-button">Change site</a>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950" role="status">{{ session('status') }}</div>
        @endif
        @include('portal.call-offs._undo-notice')
        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }}</div>
        @endif

        <dl class="mt-8 grid gap-4 sm:grid-cols-3" aria-label="Site context">
            <div class="summary-card"><dt class="text-sm font-medium text-slate-600">Customer</dt><dd class="text-xl font-bold text-slate-900">{{ $activeSite->customerOrganisation->name }}</dd></div>
            <div class="summary-card"><dt class="text-sm font-medium text-slate-600">Active site user</dt><dd class="text-xl font-bold text-slate-900">{{ $signedInUser->name }}</dd></div>
            <div class="summary-card"><dt class="text-sm font-medium text-slate-600">Plots shown</dt><dd class="text-4xl font-bold tracking-tight text-slate-900">{{ $plots->total() }}</dd></div>
        </dl>

        <div class="mt-6 flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
            @if ($lastSynchronisedAt !== null)
                <p><span class="font-bold text-slate-800">Last updated:</span> {{ $lastSynchronisedAt->format('j M Y, H:i') }}</p>
            @else
                <p>Source data not yet synchronised</p>
            @endif
            @if ($missingSourceCount > 0)
                <p class="rounded-md bg-slate-100 px-3 py-2">Some plot information is showing the most recently received update.</p>
            @endif
        </div>

        <section class="mt-8" aria-labelledby="plots-heading">
            <div>
                <h2 id="plots-heading" class="section-title">Plots at {{ $activeSite->name }}</h2>
                <p class="mt-1 text-sm text-slate-600">One row per plot. Fully completed plots are hidden until you choose to show them.</p>
            </div>

            <form method="GET" action="{{ route('portal.site-dashboard') }}" class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-5" aria-label="Filter plot overview">
                <div class="sm:col-span-2 xl:col-span-1">
                    <label for="plot" class="form-label">Find a plot</label>
                    <input id="plot" name="plot" type="search" value="{{ $filters['plot'] }}" class="form-input" placeholder="e.g. Plot 101">
                </div>
                <div>
                    <label for="service" class="form-label">Service activity</label>
                    <select id="service" name="service" class="form-input">
                        <option value="">All services</option>
                        @foreach ($serviceTypes as $serviceType)
                            <option value="{{ $serviceType->value }}" @selected($filters['service'] === $serviceType->value)>{{ $serviceType->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs leading-4 text-slate-600">Shows plots with a current call-off or completion for this service.</p>
                </div>
                <div>
                    <label for="status" class="form-label">Service status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">All statuses</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->value }}" @selected($filters['status'] === $state->value)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs leading-4 text-slate-600">Matches any service, or the service selected above.</p>
                </div>
                <label class="flex min-h-12 items-center gap-3 rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-800 xl:mt-7">
                    <input type="checkbox" name="show_completed" value="1" @checked($filters['show_completed']) class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700">
                    Show Completed
                </label>
                <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row xl:col-span-1 xl:flex-col xl:justify-end">
                    <button type="submit" class="primary-button w-full">Apply filters</button>
                    @if (collect($filters)->except('show_completed')->filter()->isNotEmpty() || $filters['show_completed'])
                        <a href="{{ route('portal.site-dashboard') }}" class="secondary-button w-full">Clear filters</a>
                    @endif
                </div>
            </form>

            @if ($plots->isNotEmpty())
                <form method="POST" action="{{ route('portal.call-offs.dashboard-selection') }}" class="mt-5 hidden lg:block" x-data="{ selected: [] }">
                    @csrf
                    <div class="mb-3 flex items-center justify-between gap-4 rounded-lg bg-sky-50 p-3"><p class="text-sm text-slate-700"><strong x-text="selected.length">0</strong> <span x-text="selected.length === 1 ? 'plot selected' : 'plots selected'">plots selected</span><span class="block">Selections are page-scoped and clear when you change page or filters.</span></p><button type="submit" class="primary-button" x-bind:disabled="selected.length === 0" x-bind:aria-disabled="(selected.length === 0).toString()">Call Off Selected</button></div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-left">
                            <caption class="sr-only">Plot overview for {{ $activeSite->name }}</caption>
                            <thead class="bg-slate-900 text-white">
                                <tr>
                                    <th scope="col" class="px-4 py-4 text-sm font-bold">Plot</th>
                                    @foreach ($serviceTypes as $serviceType)<th scope="col" class="min-w-44 px-3 py-4 text-sm font-bold">{{ $serviceType->label() }}</th>@endforeach
                                    <th scope="col" class="min-w-44 px-3 py-4 text-sm font-bold">Overall Status</th>
                                    <th scope="col" class="px-4 py-4 text-sm font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($plots as $overview)
                                    <tr @class(['bg-indigo-50' => $overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted, 'bg-white' => $overview->overallStatus !== App\Enums\PlotOverallStatus::FullyCompleted])>
                                        <th scope="row" class="px-4 py-5 align-top text-base font-bold text-slate-950"><label class="flex items-center gap-3"><input type="checkbox" name="plots[]" value="{{ $overview->plot->uuid }}" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" x-model="selected" @disabled($overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted)><span>{{ $overview->plot->plot_reference }}</span></label></th>
                                        @foreach ($serviceTypes as $serviceType)<td class="px-3 py-4 align-top"><x-plot-service-status :service="$overview->services[$serviceType->value]" /></td>@endforeach
                                        <td class="px-3 py-4 align-top"><x-plot-overall-status :status="$overview->overallStatus" /></td>
                                        <td class="px-4 py-4 align-top"><div class="flex min-w-28 flex-col gap-2"><a href="{{ route('portal.plots.show', $overview->plot) }}" class="secondary-button min-h-11 px-3 py-2 text-sm">View details</a><a href="{{ route('portal.call-offs.create', ['plots' => [$overview->plot->uuid]]) }}" class="secondary-button min-h-11 px-3 py-2 text-sm" aria-label="Call off for {{ $overview->plot->plot_reference }}. Choose service in the next step.">Call Off</a></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                </form>

                <form method="POST" action="{{ route('portal.call-offs.dashboard-selection') }}" class="mt-5 space-y-4 lg:hidden" x-data="{ selected: [] }">
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
                                    @if (in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::Submitted, App\Enums\CallOffRequestStatus::Rejected, App\Enums\CallOffRequestStatus::Withdrawn], true))
                                        <label class="flex min-h-11 items-center gap-2 rounded-lg bg-slate-100 px-3 text-sm font-bold text-slate-800"><input type="checkbox" name="requests[]" value="{{ $callOffRequest->uuid }}" data-withdraw="{{ $callOffRequest->status === App\Enums\CallOffRequestStatus::Submitted ? 'true' : 'false' }}" data-trash="{{ in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::Rejected, App\Enums\CallOffRequestStatus::Withdrawn], true) ? 'true' : 'false' }}" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" @change="update($event)"> Select</label>
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
