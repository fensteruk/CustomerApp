<x-layouts.portal title="Request Date Change | Fenster Customer Portal">
    <section class="mx-auto max-w-3xl px-4 py-7 sm:px-6">
        <a href="{{ route('portal.call-offs.show', $callOffRequest) }}" class="secondary-button">Back to request</a>
        <h1 class="page-title mt-6">Request Date Change</h1>
        <p class="page-intro">Correct your request whenever you need to. Fenster will review your latest date; every earlier request stays in the history.</p>
        <dl class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-slate-500">Plot · Service</dt><dd class="mt-1 font-bold [overflow-wrap:anywhere]">{{ $callOffRequest->projectedPlot->plot_reference }} · {{ $callOffRequest->effectiveServiceIdentifier()?->label() }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Current requested date</dt><dd class="mt-1 font-bold">{{ $currentDate?->format('j M Y') }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Current state</dt><dd class="mt-1 font-bold">{{ $currentState }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Earliest standard amendment date</dt><dd class="mt-1 font-bold">{{ $earliestDate->format('j M Y') }}</dd></div>
        </dl>
        @if ($agreedDate)<p class="mt-4 text-sm text-slate-600">Current agreed date: {{ $agreedDate->format('j M Y') }}. This date will be put On Hold when you submit a change.</p>@endif
        <p class="mt-3 text-sm text-slate-600">Amendments use the current four-week lead time, or five weeks where bifold doors are recorded. An earlier working day can be requested with an Early Date Reason.</p>
        @if ($isUrgent)<p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4" role="status">Urgent / Late Amendment — the current agreed date is within three working days or has passed. You may still submit.</p>@endif
        @if ($errors->any())<p class="mt-4 text-rose-800" role="alert">{{ $errors->first() }}</p>@endif
        @if ($reasons === [])
            <p class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4" role="status">Date change reasons are awaiting confirmation. Please contact Fenster.</p>
        @else
            <form method="POST" action="{{ route('portal.call-offs.amendments.review', $callOffRequest) }}" class="mt-6 space-y-5" x-data="{ reasonCode: @js(old('reason_code', '')), requiredReason: @js($explanationRequiredReason), selectedDate: @js(old('requested_date', '')), earliestDate: @js($earliestDate->toDateString()) }">
                @csrf
                <div><label for="requested-date" class="form-label">New requested date</label><input id="requested-date" type="date" name="requested_date" x-model="selectedDate" value="{{ old('requested_date') }}" min="{{ now()->addDay()->toDateString() }}" required class="form-input"></div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4" x-show="selectedDate && selectedDate < earliestDate">
                    <label for="early-date-reason" class="form-label">Early Date Reason</label>
                    <p id="early-date-help" class="mt-2 text-sm leading-6">Required for dates before {{ $earliestDate->format('j M Y') }}. The review will show how many working days early this is. Fenster must acknowledge the exception.</p>
                    <textarea id="early-date-reason" name="early_date_reason" maxlength="2000" rows="3" class="form-input" :required="selectedDate && selectedDate < earliestDate" aria-describedby="early-date-help">{{ old('early_date_reason') }}</textarea>
                </div>
                <div><label for="reason-code" class="form-label">Reason for date change</label><select id="reason-code" name="reason_code" x-model="reasonCode" required class="form-input"><option value="">Choose a reason</option>@foreach ($reasons as $code => $label)<option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>@endforeach</select></div>
                <div>
                    <label for="customer-response" class="form-label">Additional information <span class="font-normal" x-text="reasonCode === requiredReason ? '(required)' : '(optional)'" aria-live="polite"></span></label>
                    <p id="customer-response-help" class="mb-2 text-sm text-slate-600">Required when Other is selected; otherwise optional. Maximum 2,000 characters.</p>
                    <textarea id="customer-response" name="customer_response" maxlength="2000" rows="4" class="form-input" :required="reasonCode === requiredReason" :aria-required="reasonCode === requiredReason" aria-describedby="customer-response-help">{{ old('customer_response') }}</textarea>
                </div>
                <p class="text-sm text-slate-700">A date inside the normal lead time requires Fenster's acknowledgement. Very late changes remain allowed and will be marked Urgent / Late Amendment.</p>
                <button class="primary-button" type="submit">Review amendment <span aria-hidden="true">→</span></button>
            </form>
        @endif
    </section>
</x-layouts.portal>
