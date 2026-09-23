<x-layouts.portal title="Review Date Change | Fenster Customer Portal">
    <section class="mx-auto max-w-3xl px-4 py-7 sm:px-6">
        <h1 class="page-title">Review Date Change</h1>
        <dl class="mt-6 space-y-4">
            <div><dt class="font-bold">Plot · Service</dt><dd>{{ $callOffRequest->projectedPlot->plot_reference }} · {{ $callOffRequest->effectiveServiceIdentifier()?->label() }}</dd></div>
            <div><dt class="font-bold">Current requested date</dt><dd>{{ $currentDate?->format('j M Y') }}</dd></div>
            @if ($agreedDate)<div><dt class="font-bold">Current agreed date — will be On Hold</dt><dd>{{ $agreedDate->format('j M Y') }}</dd></div>@endif
            <div><dt class="font-bold">New requested date</dt><dd>{{ $data['requested_date'] }}</dd></div>
            <div><dt class="font-bold">Reason</dt><dd>{{ $reasonLabel }}</dd></div>
            @if ($data['customer_response'] !== null)<div><dt class="font-bold">Additional information</dt><dd>{{ $data['customer_response'] }}</dd></div>@endif
        </dl>
        @if ($data['is_early_date_exception'])
            <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4" role="status"><p class="font-bold">{{ $data['working_days_early'] }} working {{ Str::plural('day', $data['working_days_early']) }} earlier than the standard date</p><p class="mt-2">Earliest standard amendment date: {{ $data['normal_earliest_date'] }}. Fenster must acknowledge this exception.</p><p class="mt-2 [overflow-wrap:anywhere]"><strong>Early Date Reason:</strong> {{ $data['early_date_reason'] }}</p></div>
        @endif
        <p class="mt-5 text-sm leading-6 text-slate-600">This becomes the current requested date on the same call-off. Earlier dates stay in the history. Fenster will review the amendment and handle any RedZebra update separately.</p>
        @if ($isUrgent)<p class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4" role="status">Urgent / Late Amendment — the current agreed date is within three working days or has passed. You may still submit.</p>@endif
        <form class="mt-6" method="POST" action="{{ route('portal.call-offs.amendments.store', $callOffRequest) }}">
            @csrf
            <input type="hidden" name="confirmation_token" value="{{ $token }}">
            <button type="submit" class="primary-button">Submit amendment</button>
            <a class="secondary-button" href="{{ route('portal.call-offs.amendments.create', $callOffRequest) }}">Go back</a>
        </form>
    </section>
</x-layouts.portal>
