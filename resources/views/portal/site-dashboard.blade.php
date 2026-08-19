<x-layouts.portal title="Site dashboard | Fenster Customer Portal">
    <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">Site dashboard</h1>
                <p class="page-intro">Current call-offs and outstanding projected plots for this assigned site.</p>
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

        <dl class="mt-8 grid gap-4 md:grid-cols-3" aria-label="Site context">
            <div class="summary-card"><dt class="text-sm font-medium text-slate-600">Customer</dt><dd class="text-xl font-bold text-slate-900">{{ $activeSite->customerOrganisation->name }}</dd></div>
            <div class="summary-card"><dt class="text-sm font-medium text-slate-600">Assigned user</dt><dd class="text-xl font-bold text-slate-900">{{ $signedInUser->name }}</dd></div>
            <div class="summary-card"><dt class="text-sm font-medium text-slate-600">Outstanding projected plots</dt><dd class="text-4xl font-bold tracking-tight text-slate-900">{{ $outstandingPlotCount }}</dd></div>
        </dl>

        <div class="mt-8 grid gap-4 md:grid-cols-4" aria-label="Current call-off statuses">
            @foreach ($statusSummaries as $summary)
                <div class="status-summary status-summary-{{ $summary['tone'] }}"><span class="text-sm font-medium">{{ $summary['label'] }}</span><strong>{{ $summary['count'] }}</strong></div>
            @endforeach
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section aria-labelledby="requests-heading">
                <div>
                    <h2 id="requests-heading" class="section-title">Existing Call Off Requests</h2>
                    <p class="mt-1 text-sm text-slate-600">Showing requests for {{ $activeSite->name }} only.</p>
                </div>

                <form method="GET" action="{{ route('portal.site-dashboard') }}" class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" aria-label="Find call-offs">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="plot" class="form-label">Find a plot</label>
                        <input id="plot" name="plot" type="search" value="{{ $filters['plot'] }}" class="form-input" placeholder="e.g. Plot 101">
                    </div>
                    <div>
                        <label for="service" class="form-label">Service</label>
                        <select id="service" name="service" class="form-input"><option value="">All services</option>@foreach ($serviceTypes as $serviceType)<option value="{{ $serviceType->value }}" @selected($filters['service'] === $serviceType->value)>{{ $serviceType->label() }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-input"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end lg:flex-col lg:justify-end">
                        <button type="submit" class="primary-button w-full">Apply filters</button>
                        @if (collect($filters)->filter()->isNotEmpty())
                            <a href="{{ route('portal.site-dashboard') }}" class="secondary-button w-full">Clear filters</a>
                        @endif
                    </div>
                </form>

                <form method="POST" action="{{ route('portal.call-offs.lifecycle.confirm') }}" class="mt-4 space-y-3" x-data="lifecycleSelection()">
                    @csrf
                    @forelse ($callOffRequests as $callOffRequest)
                        @php($latestCustomerResponse = $callOffRequest->histories->first()?->customer_response)
                        <article class="request-card" aria-labelledby="request-{{ $callOffRequest->uuid }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0"><h3 id="request-{{ $callOffRequest->uuid }}" class="text-lg font-bold text-slate-900">{{ $callOffRequest->projectedPlot->plot_reference }}</h3><p class="mt-1 text-sm text-slate-600">{{ $callOffRequest->batch->service_identifier->label() }}</p></div>
                                <span class="status status-{{ $callOffRequest->status->tone() }}">{{ $callOffRequest->status->label() }}</span>
                            </div>
                            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                                <div><dt>Requested date</dt><dd>{{ $callOffRequest->batch->requested_date->format('j M Y') }}</dd></div>
                                <div><dt>Submitted by</dt><dd>{{ $callOffRequest->batch->submittedBy->name }}</dd></div>
                                <div><dt>Submission date</dt><dd>{{ $callOffRequest->batch->submitted_at->format('j M Y, H:i') }}</dd></div>
                                <div><dt>Customer response</dt><dd>{{ $latestCustomerResponse ?: 'Not provided yet' }}</dd></div>
                            </dl>
                            @if ($callOffRequest->status === App\Enums\CallOffRequestStatus::Rejected && Gate::allows('resubmit-call-off', $callOffRequest))
                                <div class="mt-5 border-t border-slate-200 pt-4"><a href="{{ route('portal.call-offs.resubmit.create', $callOffRequest) }}" class="secondary-button w-full sm:w-auto">Resubmit</a></div>
                            @endif
                            @if ($callOffRequest->status === App\Enums\CallOffRequestStatus::Submitted || $callOffRequest->status === App\Enums\CallOffRequestStatus::Rejected || $callOffRequest->status === App\Enums\CallOffRequestStatus::Withdrawn)
                                <label class="mt-5 flex min-h-12 items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800">
                                    <input type="checkbox" name="requests[]" value="{{ $callOffRequest->uuid }}" data-withdraw="{{ $callOffRequest->status === App\Enums\CallOffRequestStatus::Submitted ? 'true' : 'false' }}" data-trash="{{ in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::Rejected, App\Enums\CallOffRequestStatus::Withdrawn], true) ? 'true' : 'false' }}" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" @change="update($event)">
                                    Select this call-off for a lifecycle action
                                </label>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state">
                            <h3 class="text-lg font-bold text-slate-900">{{ collect($filters)->filter()->isNotEmpty() ? 'No call-offs match your filters' : 'No requests' }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ collect($filters)->filter()->isNotEmpty() ? 'Try changing or clearing the filters to see other call-offs for this assigned site.' : 'There are no call-off requests for this active site yet.' }}</p>
                        </div>
                    @endforelse

                    @if ($callOffRequests->isNotEmpty())
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-sm leading-6 text-slate-700">Select call-offs from one submission batch at a time. Approved call-offs cannot be changed here.</p>
                            <p class="mt-2 text-sm font-bold text-slate-800" aria-live="polite"><span x-text="count"></span> selected on this page</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <button type="submit" name="operation" value="withdraw" class="secondary-button w-full" :disabled="!can('withdraw')" :aria-disabled="(!can('withdraw')).toString()">Withdraw selected</button>
                                <button type="submit" name="operation" value="trash" class="secondary-button w-full" :disabled="!can('trash')" :aria-disabled="(!can('trash')).toString()">Move selected to Trash</button>
                            </div>
                        </div>
                    @endif
                </form>

                @if ($callOffRequests->hasPages())
                    <nav class="mt-6 rounded-lg border border-slate-200 bg-white p-3 shadow-sm" aria-label="Call-off request pages">
                        <p class="mb-3 text-center text-sm font-semibold text-slate-700">Showing {{ $callOffRequests->firstItem() }}–{{ $callOffRequests->lastItem() }} of {{ $callOffRequests->total() }} call-offs</p>
                        {{ $callOffRequests->links() }}
                    </nav>
                @endif
            </section>

            <aside aria-labelledby="plots-heading">
                <h2 id="plots-heading" class="section-title">Outstanding projected plots</h2>
                <div class="mt-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    @forelse ($outstandingPlots as $plot)<div class="plot-row">{{ $plot->plot_reference }}</div>@empty
                        <div class="empty-state border-0 bg-slate-50 p-0 shadow-none"><h3 class="text-base font-bold text-slate-900">No projected plots</h3><p class="mt-2 text-sm leading-6 text-slate-700">No outstanding projected plots are available for this site.</p></div>
                    @endforelse
                    @if ($outstandingPlotCount > $outstandingPlots->count())<p class="mt-4 text-sm font-semibold text-slate-600">{{ $outstandingPlotCount - $outstandingPlots->count() }} more outstanding projected {{ Str::plural('plot', $outstandingPlotCount - $outstandingPlots->count()) }}</p>@endif
                </div>
                <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="assigned-team-heading"><h2 id="assigned-team-heading" class="text-base font-bold text-slate-900">Assigned site users</h2><ul class="mt-3 space-y-2 text-sm text-slate-700">@foreach ($assignedUsers as $assignedUser)<li>{{ $assignedUser->name }}</li>@endforeach</ul></section>
            </aside>
        </div>
    </section>
</x-layouts.portal>
