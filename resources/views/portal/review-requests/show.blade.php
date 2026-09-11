<x-layouts.portal title="Review request | Fenster Customer Portal">
    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $callOffRequest->batch->site->name }}</p>
                <h1 id="page-title" class="page-title">Review {{ $callOffRequest->projectedPlot->plot_reference }}</h1>
                <p class="page-intro">Review the requested date, any customer response and the complete date-agreement history.</p>
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
                <span class="status status-{{ $callOffRequest->status->tone() }}">{{ $statusLabel }}</span>
            </div>

            <dl class="mt-6 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt>Site</dt>
                    <dd>{{ $callOffRequest->batch->site->name }}</dd>
                </div>
                <div>
                    <dt>Service</dt>
                    <dd>{{ $callOffRequest->effectiveServiceIdentifier()?->label() ?? $callOffRequest->batch->service_identifier->label() }}</dd>
                </div>
                <div>
                    <dt>Requested date</dt>
                    <dd>{{ $callOffRequest->effectiveRequestedDate()?->format('j M Y') }}</dd>
                </div>
                <div>
                    <dt>Submitter</dt>
                    <dd>{{ $callOffRequest->batch->submittedBy->name }}</dd>
                </div>
                <div>
                    <dt>Submitted timestamp</dt>
                    <dd>{{ $callOffRequest->batch->submitted_at->format('j M Y, H:i:s') }}</dd>
                </div>
                <div>
                    <dt>Current status</dt>
                    <dd>{{ $statusLabel }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt>Customer-facing submission text</dt>
                    <dd>{{ $callOffRequest->batch->customer_response ?: 'Not provided' }}</dd>
                </div>
                @if ($agreedDate !== null)
                    <div class="md:col-span-2 rounded-lg border border-emerald-200 bg-emerald-50 p-4"><dt class="font-bold text-emerald-950">Date Agreed</dt><dd class="mt-1 text-lg font-bold text-emerald-950">{{ $agreedDate->format('j M Y') }}</dd></div>
                @endif
                @if ($isCompleted)
                    <div class="md:col-span-2 rounded-lg border border-slate-300 bg-slate-50 p-4"><dt class="font-bold text-slate-950">Completed</dt><dd class="mt-1 text-lg font-bold text-slate-950">{{ $callOffRequest->projectedPlotService?->source_completed_at?->format('j M Y') ?? 'Completion recorded' }}</dd></div>
                @endif
            </dl>
        </article>

        @include('portal.call-offs.amendments.history', ['amendmentHeading' => 'Amendment request'])

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="history-heading">
            <h2 id="history-heading" class="section-title">Request history</h2>
            <div class="mt-4 space-y-4">
                @forelse ($callOffRequest->histories as $history)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <h3 class="font-bold text-slate-900">{{ $history->event_type->label() }}</h3>@if ($history->recordedAgreedDate())<p class="font-bold">Date Agreed — {{ $history->recordedAgreedDate() }}</p>@endif
                            <p class="text-sm text-slate-600">{{ $history->performed_at->format('j M Y, H:i:s') }}</p>
                        </div>
                        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                            <div>
                                <dt>Performed by</dt>
                                <dd>{{ $history->recordedActorName() }}@if ($history->recordedActorRole()), {{ $history->recordedActorRole() }}@endif</dd>
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

        @if ($alternativeProposals->isNotEmpty())
            <section class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-5" aria-labelledby="alternatives-heading">
                <h2 id="alternatives-heading" class="section-title">Alternative date history</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($alternativeProposals as $proposal)
                        <article class="rounded-lg border border-sky-200 bg-white p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><h3 class="font-bold text-slate-950">Proposed {{ $proposal->proposed_date->format('j M Y') }}</h3><p class="text-sm text-slate-600">{{ $proposal->proposed_at?->format('j M Y, H:i:s') }}</p></div>
                            <p class="mt-2 text-sm text-slate-700">Proposed by {{ $proposal->proposedBy?->name ?? 'Fenster' }}@if ($proposal->proposedBy?->portalRole), {{ $proposal->proposedBy->portalRole->name }}@endif.</p>
                            @if ($proposal->status === App\Enums\CallOffDateProposalStatus::Rejected)<p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-950"><strong>Customer rejected this date.</strong> {{ $proposal->customer_response }}@if ($proposal->respondedBy) Responded by {{ $proposal->respondedBy->name }}@if ($proposal->respondedBy->portalRole), {{ $proposal->respondedBy->portalRole->name }}@endif.@endif</p>@endif
                            @if ($proposal->status === App\Enums\CallOffDateProposalStatus::Accepted)<p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-950">Customer accepted this date.</p>@endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @include('portal.call-offs.amendments.source-history')
        @if ($awaitingFenster)
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('portal.review-requests.agree-requested-date', $callOffRequest) }}" class="rounded-xl border border-emerald-200 bg-emerald-50 p-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @if ($activeAmendment)<input type="hidden" name="negotiation_uuid" value="{{ $activeAmendment->uuid }}">@endif
                    <h2 class="text-lg font-bold text-emerald-950">{{ $isOnHold ? 'Accept New Date' : 'Accept Requested Date' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-emerald-950">You are agreeing to <strong>{{ $decisionDate?->format('j M Y') }}</strong>.</p>
                    @if ($requiresEarlyAcknowledgement)
                        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"><p class="font-bold">Earlier Date Request</p><p class="mt-1">Normal earliest date: {{ $decisionEarliestDate?->format('j M Y') }}.</p><p class="mt-1">Customer reason: {{ $activeAmendment?->reason_label ?? $callOffRequest->early_date_reason }}</p><label class="mt-4 flex min-h-11 items-center gap-3 font-bold"><input type="checkbox" name="early_date_acknowledgement" value="1" required class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700">I acknowledge this date is inside the normal lead time.</label></div>
                    @endif
                    <button type="submit" class="approve-button mt-5 w-full" :disabled="submitting"><span x-show="!submitting">{{ $isOnHold ? 'Accept New Date' : 'Accept Requested Date' }}</span><span x-show="submitting" x-cloak>Accepting…</span></button>
                </form>
                <form method="POST" action="{{ route('portal.review-requests.propose-alternative-date', $callOffRequest) }}" class="rounded-xl border border-sky-200 bg-sky-50 p-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @if ($activeAmendment)<input type="hidden" name="negotiation_uuid" value="{{ $activeAmendment->uuid }}">@endif
                    <h2 class="text-lg font-bold text-sky-950">Propose Alternative Date</h2>
                    <p class="mt-2 text-sm leading-6 text-sky-950">Requested date: <strong>{{ $decisionDate?->format('j M Y') }}</strong>. The customer will need to accept or reject your alternative.</p>
                    <label for="proposed-date" class="form-label mt-4">Proposed alternative date</label><input id="proposed-date" name="proposed_date" type="date" required class="form-input mt-1" value="{{ old('proposed_date') }}">
                    @if ($activeAmendment)
                        <label class="mt-4 flex min-h-11 items-center gap-3"><input type="checkbox" name="early_date_acknowledgement" value="1" class="h-5 w-5">I acknowledge an alternative inside normal lead time, if applicable.</label>
                    @endif
                    <label for="alternative-message" class="form-label mt-4">Message to customer <span class="font-normal">(optional)</span></label><textarea id="alternative-message" name="customer_response" rows="3" class="form-input">{{ old('customer_response') }}</textarea>
                    <label for="alternative-internal-reason" class="form-label mt-4">Internal reason <span class="font-normal">(optional)</span></label><textarea id="alternative-internal-reason" name="internal_reason" rows="3" class="form-input">{{ old('internal_reason') }}</textarea>
                    <button type="submit" class="primary-button mt-5 w-full" :disabled="submitting" x-text="submitting ? 'Proposing…' : 'Propose Alternative Date'"></button>
                </form>
            </div>
        @elseif ($callOffRequest->status === App\Enums\CallOffRequestStatus::Submitted)
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
        @elseif ($awaitingSiteUser)
            <div class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950" role="status"><p class="font-bold">Waiting for customer response</p><p class="mt-1">Fenster has proposed an alternative date. The currently assigned site users can accept or reject it.</p></div>
        @elseif (in_array($callOffRequest->status, [App\Enums\CallOffRequestStatus::DateAgreed, App\Enums\CallOffRequestStatus::Approved], true))
            <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950" role="status"><p class="font-bold">Date Agreed</p><p class="mt-1">No further date-decision action is available for this request.</p></div>
        @elseif ($isCompleted)
            <div class="mt-6 rounded-lg border border-slate-300 bg-slate-50 p-5 text-sm text-slate-800" role="status"><p class="font-bold">Completed</p><p class="mt-1">The source record shows this service as completed. Date actions are no longer available.</p></div>
        @elseif ($isClosedAfterReversal)
            <p class="mt-6 rounded-lg border border-slate-200 p-5">{{ $statusLabel }}. No previous agreement or date change has been reinstated.</p>
        @else
            <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-800" role="status">
                This request has already been decided or moved out of the submitted queue.
            </div>
        @endif
    </section>
</x-layouts.portal>
