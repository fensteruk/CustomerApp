<x-layouts.portal title="Request Date Change | Fenster Customer Portal">
    <section class="mx-auto max-w-3xl px-4 py-7 sm:px-6">
        <a href="{{ route('portal.call-offs.show', $callOffRequest) }}" class="secondary-button">Back to request</a>
        <h1 class="page-title mt-6">Request Date Change</h1>
        <p class="page-intro">Current agreed date: {{ $agreedDate?->format('j M Y') }}. This date will be put On Hold when you submit a change.</p>
        @if ($isUrgent)<p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4" role="status">Urgent / Late Amendment — the current agreed date is within three working days or has passed. You may still submit.</p>@endif
        @if ($errors->any())<p class="mt-4 text-rose-800" role="alert">{{ $errors->first() }}</p>@endif
        @if ($reasons === [])
            <p class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4" role="status">Date change reasons are awaiting confirmation. Please contact Fenster.</p>
        @else
            <form method="POST" action="{{ route('portal.call-offs.amendments.review', $callOffRequest) }}" class="mt-6 space-y-5" x-data="{ reasonCode: @js(old('reason_code', '')), requiredReason: @js($explanationRequiredReason) }">
                @csrf
                <div><label for="requested-date" class="form-label">New requested date</label><input id="requested-date" type="date" name="requested_date" value="{{ old('requested_date') }}" required class="form-input"></div>
                <div><label for="reason-code" class="form-label">Reason for date change</label><select id="reason-code" name="reason_code" x-model="reasonCode" required class="form-input"><option value="">Choose a reason</option>@foreach ($reasons as $code => $label)<option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>@endforeach</select></div>
                <div>
                    <label for="customer-response" class="form-label">Additional information <span class="font-normal" x-text="reasonCode === requiredReason ? '(required)' : '(optional)'" aria-live="polite"></span></label>
                    <p id="customer-response-help" class="mb-2 text-sm text-slate-600">Required when Other is selected; otherwise optional. Maximum 2,000 characters.</p>
                    <textarea id="customer-response" name="customer_response" maxlength="2000" rows="4" class="form-input" :required="reasonCode === requiredReason" :aria-required="reasonCode === requiredReason" aria-describedby="customer-response-help">{{ old('customer_response') }}</textarea>
                </div>
                <p class="text-sm text-slate-700">A date inside the normal lead time requires Fenster's acknowledgement. Very late changes remain allowed and will be marked Urgent / Late Amendment.</p>
                <button class="primary-button" type="submit">Review Date Change</button>
            </form>
        @endif
    </section>
</x-layouts.portal>
