<x-layouts.portal title="Review Requests | Fenster Customer Portal">
    <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="eyebrow">Fenster Office Staff</p>
                <h1 id="page-title" class="page-title">Review Requests</h1>
                <p class="page-intro">Review the requests that need a Fenster response. Use the status filter to check customer responses, agreed dates and completed records.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-950" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="GET" action="{{ route('portal.review-requests') }}" class="mt-8 grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-4" aria-label="Review request filters">
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="site" class="form-label">Assigned site</label>
                <select id="site" name="site" class="form-input">
                    <option value="">All assigned sites</option>
                    @foreach ($assignedSites as $site)
                        <option value="{{ $site->id }}" @selected((string) $filters['site'] === (string) $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="service" class="form-label">Service type</label>
                <select id="service" name="service" class="form-input">
                    <option value="">All services</option>
                    @foreach ($serviceTypes as $serviceType)
                        <option value="{{ $serviceType->value }}" @selected($filters['service'] === $serviceType->value)>{{ $serviceType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="primary-button w-full">Apply filters</button>
            </div>
        </form>

        <div class="mt-6 grid gap-4">
            @forelse ($requests as $callOffRequest)
                <article class="review-card" aria-labelledby="review-request-{{ $callOffRequest->uuid }}">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-600">{{ $callOffRequest->batch->site->name }}</p>
                            <h2 id="review-request-{{ $callOffRequest->uuid }}" class="mt-1 text-xl font-bold text-slate-900">{{ $callOffRequest->projectedPlot->plot_reference }}</h2>
                        </div>
                        <span class="status status-{{ $callOffRequest->status->tone() }}">{{ $callOffRequest->status->label() }}</span>
                    </div>

                    <dl class="mt-5 grid gap-4 text-sm md:grid-cols-3">
                        <div>
                            <dt>Service type</dt>
                            <dd>{{ $callOffRequest->batch->service_identifier->label() }}</dd>
                        </div>
                        <div>
                            <dt>Requested date</dt>
                            <dd>{{ $callOffRequest->batch->requested_date->format('j M Y') }}</dd>
                        </div>
                        <div>
                            <dt>Submitting user</dt>
                            <dd>{{ $callOffRequest->batch->submittedBy->name }}</dd>
                        </div>
                        <div>
                            <dt>Submission date</dt>
                            <dd>{{ $callOffRequest->batch->submitted_at->format('j M Y, H:i') }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt>Customer-facing submission text</dt>
                            <dd>{{ $callOffRequest->batch->customer_response ?: 'Not provided' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        @if ($callOffRequest->status === App\Enums\CallOffRequestStatus::AwaitingFenster)
                            <p class="mr-auto text-sm font-bold text-amber-900">Fenster action required</p>
                        @elseif ($callOffRequest->status === App\Enums\CallOffRequestStatus::AwaitingSiteUser)
                            <p class="mr-auto text-sm font-bold text-sky-900">Waiting for customer response</p>
                        @elseif (in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::DateAgreed, App\Enums\CallOffRequestStatus::Approved], true))
                            <p class="mr-auto text-sm font-bold text-emerald-900">Date agreed</p>
                        @elseif ($callOffRequest->status === App\Enums\CallOffRequestStatus::Completed)
                            <p class="mr-auto text-sm font-bold text-slate-700">Completed record</p>
                        @endif
                        <a href="{{ route('portal.review-requests.show', $callOffRequest) }}" class="secondary-button">Review request</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <h2 class="text-lg font-bold text-slate-900">No requests match these filters</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-700">There are no call-offs in your assigned review scope for the selected filters.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $requests->links() }}
        </div>
    </section>
</x-layouts.portal>
