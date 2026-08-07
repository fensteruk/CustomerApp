<x-layouts.portal title="Review request | Fenster Customer Portal">
    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $callOffRequest->batch->site->name }}</p>
                <h1 id="page-title" class="page-title">Review {{ $callOffRequest->projectedPlot->plot_reference }}</h1>
                <p class="page-intro">Check the request before publishing an approval or rejection.</p>
            </div>
            <a href="{{ route('portal.review-requests') }}" class="secondary-button">Back to queue</a>
        </div>

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-950" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <article class="mt-8 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-600">{{ $callOffRequest->batch->site->customerOrganisation->name }}</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $callOffRequest->projectedPlot->plot_reference }}</h2>
                </div>
                <span class="status status-{{ $callOffRequest->status->tone() }}">{{ $callOffRequest->status->label() }}</span>
            </div>

            <dl class="mt-6 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt>Site</dt>
                    <dd>{{ $callOffRequest->batch->site->name }}</dd>
                </div>
                <div>
                    <dt>Service</dt>
                    <dd>{{ $callOffRequest->batch->service_identifier->label() }}</dd>
                </div>
                <div>
                    <dt>Requested date</dt>
                    <dd>{{ $callOffRequest->batch->requested_date->format('j M Y') }}</dd>
                </div>
                <div>
                    <dt>Submitter</dt>
                    <dd>{{ $callOffRequest->batch->submittedBy->name }}</dd>
                </div>
                <div>
                    <dt>Submitted timestamp</dt>
                    <dd>{{ $callOffRequest->batch->submitted_at->format('j M Y, H:i') }}</dd>
                </div>
                <div>
                    <dt>Current status</dt>
                    <dd>{{ $callOffRequest->status->label() }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt>Customer-facing submission text</dt>
                    <dd>{{ $callOffRequest->batch->customer_response ?: 'Not provided' }}</dd>
                </div>
            </dl>
        </article>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="history-heading">
            <h2 id="history-heading" class="section-title">Request history</h2>
            <div class="mt-4 space-y-4">
                @forelse ($callOffRequest->histories as $history)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <h3 class="font-bold text-slate-900">{{ $history->event_type->label() }}</h3>
                            <p class="text-sm text-slate-600">{{ $history->performed_at->format('j M Y, H:i') }}</p>
                        </div>
                        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                            <div>
                                <dt>Performed by</dt>
                                <dd>{{ $history->performedBy->name }}</dd>
                            </div>
                            <div>
                                <dt>Status change</dt>
                                <dd>{{ $history->previous_status?->label() ?? 'None' }} to {{ $history->new_status?->label() ?? 'None' }}</dd>
                            </div>
                            @if ($history->customer_response)
                                <div>
                                    <dt>Customer response</dt>
                                    <dd>{{ $history->customer_response }}</dd>
                                </div>
                            @endif
                            @if ($history->internal_reason)
                                <div>
                                    <dt>Internal reason</dt>
                                    <dd>{{ $history->internal_reason }}</dd>
                                </div>
                            @endif
                        </dl>
                    </article>
                @empty
                    <div class="empty-state">
                        <h3 class="text-lg font-bold text-slate-900">No history yet</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-700">No customer-visible history has been recorded for this request.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($callOffRequest->status === App\Enums\CallOffRequestStatus::Submitted)
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('portal.review-requests.approve', $callOffRequest) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 p-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <h2 class="text-lg font-bold text-emerald-950">Approve request</h2>
                    <div class="mt-4">
                        <label for="approve-customer-response" class="form-label">Customer response <span class="font-normal text-slate-600">(optional)</span></label>
                        <textarea id="approve-customer-response" name="customer_response" rows="4" class="form-input">{{ old('customer_response') }}</textarea>
                    </div>
                    <div class="mt-4">
                        <label for="approve-internal-reason" class="form-label">Internal reason <span class="font-normal text-slate-600">(optional)</span></label>
                        <textarea id="approve-internal-reason" name="internal_reason" rows="3" class="form-input">{{ old('internal_reason') }}</textarea>
                    </div>
                    <button type="submit" class="approve-button mt-5 w-full" :disabled="submitting" x-text="submitting ? 'Approving' : 'Approve request'"></button>
                </form>

                <form method="POST" action="{{ route('portal.review-requests.reject', $callOffRequest) }}" class="rounded-lg border border-rose-200 bg-rose-50 p-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <h2 class="text-lg font-bold text-rose-950">Reject request</h2>
                    <div class="mt-4">
                        <label for="reject-customer-response" class="form-label">Customer-visible rejection response</label>
                        <textarea id="reject-customer-response" name="customer_response" rows="4" required class="form-input">{{ old('customer_response') }}</textarea>
                    </div>
                    <div class="mt-4">
                        <label for="reject-internal-reason" class="form-label">Internal reason <span class="font-normal text-slate-600">(optional)</span></label>
                        <textarea id="reject-internal-reason" name="internal_reason" rows="3" class="form-input">{{ old('internal_reason') }}</textarea>
                    </div>
                    <button type="submit" class="reject-button mt-5 w-full" :disabled="submitting" x-text="submitting ? 'Rejecting' : 'Reject request'"></button>
                </form>
            </div>
        @else
            <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-800" role="status">
                This request has already been decided or moved out of the submitted queue.
            </div>
        @endif
    </section>
</x-layouts.portal>
