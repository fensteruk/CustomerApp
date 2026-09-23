<x-layouts.portal title="Call-off request | Fenster Customer Portal">

    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <a href="{{ route('portal.plots.show', $callOffRequest->projectedPlot) }}" class="secondary-button">Back to plot details</a>

        <div class="mt-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">{{ $callOffRequest->projectedPlot->plot_reference }} · {{ $callOffRequest->effectiveServiceIdentifier()?->label() ?? 'Call-off' }}</h1>
                <p class="page-intro">Requested date, date agreement and customer-safe request history.</p>
            </div>
            <span class="status status-{{ $isCompleted ? 'slate' : $callOffRequest->status->tone() }}">
                {{ $statusLabel }}
            </span>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-950" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="request-summary-heading">
            <h2 id="request-summary-heading" class="section-title">Request summary</h2>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="font-semibold text-slate-600">Requested date</dt><dd class="mt-1 text-base font-bold text-slate-950">{{ $requestDate?->format('j M Y') ?? 'Not available' }}</dd></div>
                <div><dt class="font-semibold text-slate-600">Submitted by</dt><dd class="mt-1 text-base font-bold text-slate-950">{{ $callOffRequest->batch->submittedBy->name }}</dd></div>
                <div><dt class="font-semibold text-slate-600">Submitted</dt><dd class="mt-1 text-base font-bold text-slate-950">{{ $callOffRequest->batch->submitted_at?->format('j M Y, H:i:s') ?? 'Not available' }}</dd></div>
                @if ($agreedDate !== null)
                    <div class="sm:col-span-2 lg:col-span-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4"><dt class="font-bold text-emerald-950">Date Agreed</dt><dd class="mt-1 text-xl font-bold text-emerald-950">{{ $agreedDate->format('j M Y') }}</dd></div>
                @endif
                @if ($isCompleted)
                    <div class="sm:col-span-2 lg:col-span-3 rounded-lg border border-slate-300 bg-slate-50 p-4"><dt class="font-bold text-slate-950">Completed</dt><dd class="mt-1 text-xl font-bold text-slate-950">{{ $callOffRequest->projectedPlotService?->source_completed_at?->format('j M Y') ?? 'Completion recorded' }}</dd></div>
                @endif
            </dl>
        </section>

        @include('portal.call-offs.amendments.history')

        @if ($awaitingFenster)
            <section class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5" aria-labelledby="awaiting-fenster-heading">
                <h2 id="awaiting-fenster-heading" class="text-lg font-bold text-amber-950">Fenster is reviewing your requested date</h2>
                <p class="mt-2 text-sm leading-6 text-amber-950">No action is needed from your site at the moment. We will let the original submitter know when Fenster responds.</p>
            </section>
        @elseif ($awaitingSiteUser)
            <section class="mt-6 rounded-xl border border-sky-300 bg-sky-50 p-5" aria-labelledby="alternative-heading">
                <h2 id="alternative-heading" class="text-xl font-bold text-sky-950">Fenster has proposed an alternative date</h2>
                <p class="mt-2 text-sm leading-6 text-sky-950">A currently assigned site user needs to accept or reject this date.</p>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold text-sky-900">Requested date in this review</dt><dd class="mt-1 text-lg font-bold text-sky-950">{{ $decisionDate?->format('j M Y') ?? 'Not available' }}</dd></div>
                    <div><dt class="font-semibold text-sky-900">Proposed alternative date</dt><dd class="mt-1 text-lg font-bold text-sky-950">{{ $currentProposal->proposed_date->format('j M Y') }}</dd></div>
                </dl>
                @if ($currentProposal->customer_response)
                    <div class="mt-4 rounded-lg border border-sky-200 bg-white p-4 text-sm leading-6 text-slate-800"><p class="font-bold text-slate-950">Message from Fenster</p><p class="mt-1">{{ $currentProposal->customer_response }}</p></div>
                @endif
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('portal.call-offs.alternative-dates.accept', [$callOffRequest, $currentProposal]) }}" class="rounded-lg border border-emerald-200 bg-white p-4" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <h3 class="font-bold text-emerald-950">Accept Date</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Accepting this will make <strong>{{ $currentProposal->proposed_date->format('j M Y') }}</strong> the Date Agreed.</p>
                        <button type="submit" class="approve-button mt-4 w-full" :disabled="submitting" x-text="submitting ? 'Accepting…' : 'Accept Date'"></button>
                    </form>
                    <form method="POST" action="{{ route('portal.call-offs.alternative-dates.reject', [$callOffRequest, $currentProposal]) }}" class="rounded-lg border border-rose-200 bg-white p-4" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <h3 class="font-bold text-rose-950">Reject Date</h3>
                        <label for="customer-response" class="form-label mt-3">Reason for rejecting this date <span aria-hidden="true">*</span></label>
                        <textarea id="customer-response" name="customer_response" rows="4" required aria-required="true" aria-describedby="customer-response-help" class="form-input">{{ old('customer_response') }}</textarea>
                        <p id="customer-response-help" class="mt-2 text-sm leading-5 text-slate-700">Fenster will review your response and may propose another date.</p>
                        <button type="submit" class="reject-button mt-4 w-full" :disabled="submitting" x-text="submitting ? 'Rejecting…' : 'Reject Date'"></button>
                    </form>
                </div>
            </section>
        @endif

        @if ($canWithdraw)
            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="withdraw-heading">
                <h2 id="withdraw-heading" class="text-lg font-bold text-slate-950">Need to cancel this request?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-700">You can withdraw while a date is awaiting a response. Once a date is agreed, changes will use a later amendment process.</p>
                <form method="POST" action="{{ route('portal.call-offs.lifecycle.confirm') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="operation" value="withdraw">
                    <input type="hidden" name="requests[]" value="{{ $callOffRequest->uuid }}">
                    <button type="submit" class="secondary-button">Withdraw request</button>
                </form>
            </section>
        @endif

        @include('portal.call-offs.amendments.source-history')
        @if ($canAmend)
            <div class="mt-6">
                @if ($amendmentReasonsReady)
                    <a class="primary-button" href="{{ route('portal.call-offs.amendments.create', $callOffRequest) }}">Request Date Change</a>
                @else
                    <p class="text-sm text-slate-700">Date change reasons are awaiting confirmation. Please contact Fenster.</p>
                @endif
            </div>
        @endif

        <section class="mt-8" aria-labelledby="history-heading">
            <div><h2 id="history-heading" class="section-title">Request history</h2><p class="mt-1 text-sm text-slate-600">The request and any proposed dates are retained here for your site.</p></div>
            <ol class="mt-5 space-y-4" aria-label="Customer-facing request history">
                @foreach ($callOffRequest->histories as $history)
                    <li class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><h3 class="font-bold text-slate-950">{{ $history->event_type->label() }}</h3><time class="text-sm text-slate-600" datetime="{{ $history->performed_at?->toIso8601String() }}">{{ $history->performed_at?->format('j M Y, H:i:s') ?? 'Time not available' }}</time></div>
                        <p class="mt-2 text-sm text-slate-700">{{ $history->recordedActorName() }}@if ($history->recordedActorRole()), {{ $history->recordedActorRole() }}@endif</p>
                        @if (in_array($history->event_type, [App\Enums\CallOffHistoryEventType::Submitted, App\Enums\CallOffHistoryEventType::DateRequested], true) && filled($history->after_state['requested_date'] ?? null))
                            <p class="mt-3 text-sm font-semibold">Original requested date — {{ $history->after_state['requested_date'] }}</p>
                        @elseif ($history->event_type === App\Enums\CallOffHistoryEventType::AmendmentRequested && filled($history->after_state['amendment_requested_date'] ?? null))
                            <p class="mt-3 text-sm font-semibold">Amended requested date — {{ $history->after_state['amendment_requested_date'] }}</p>
                        @endif
                        @if ($history->customer_response)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-800">{{ $history->customer_response }}</p>@endif
                        @if ($history->recordedAgreedDate())<p class="mt-3 text-sm font-bold text-emerald-800">Date Agreed — {{ $history->recordedAgreedDate() }}</p>@endif
                    </li>
                @endforeach

                @foreach ($proposals as $proposal)
                    @if ($proposal->proposal_type === App\Enums\CallOffDateProposalType::FensterAlternativeDate)
                        <li class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><h3 class="font-bold text-sky-950">Fenster proposed {{ $proposal->proposed_date->format('j M Y') }}</h3><time class="text-sm text-sky-900" datetime="{{ $proposal->proposed_at?->toIso8601String() }}">{{ $proposal->proposed_at?->format('j M Y, H:i:s') ?? 'Time not available' }}</time></div>
                            <p class="mt-2 text-sm text-sky-950">{{ $proposal->proposedBy?->name ?? 'Fenster' }}@if ($proposal->proposedBy?->portalRole), {{ $proposal->proposedBy->portalRole->name }}@endif</p>
                            @if ($proposal->customer_response && $proposal->status === App\Enums\CallOffDateProposalStatus::AwaitingResponse)<p class="mt-3 rounded-lg bg-white p-3 text-sm leading-6 text-slate-800">{{ $proposal->customer_response }}</p>@endif
                            @if ($proposal->status === App\Enums\CallOffDateProposalStatus::Rejected)<p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm leading-6 text-rose-950"><strong>Rejected by {{ $proposal->respondedBy?->name ?? 'a site user' }}</strong>@if ($proposal->respondedBy?->portalRole), {{ $proposal->respondedBy->portalRole->name }}@endif@if ($proposal->responded_at) on {{ $proposal->responded_at->format('j M Y, H:i:s') }}@endif.<br>Reason: {{ $proposal->customer_response }}</p>@endif
                            @if ($proposal->status === App\Enums\CallOffDateProposalStatus::Accepted)<p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-950">Accepted by {{ $proposal->respondedBy?->name ?? 'a site user' }}@if ($proposal->respondedBy?->portalRole), {{ $proposal->respondedBy->portalRole->name }}@endif@if ($proposal->responded_at) on {{ $proposal->responded_at->format('j M Y, H:i:s') }}@endif.</p>@endif
                        </li>
                    @endif
                @endforeach
            </ol>
        </section>
    </section>
</x-layouts.portal>
