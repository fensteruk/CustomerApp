@foreach ($amendments as $amendment)
    <section class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5" aria-label="Date change request">
        <h2 class="text-lg font-bold">{{ $amendmentHeading ?? 'Date change requested' }}</h2>
        <p class="mt-2 font-bold">{{ $amendment->progressLabel() }}</p>
        <p class="mt-2">{{ $amendment->requester_name }}, {{ $amendment->requester_role }} · {{ $amendment->opened_at->format('j M Y, H:i:s') }}</p>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt class="font-bold">Previous agreed date</dt><dd>{{ $amendment->prior_agreed_date?->format('j M Y') }}</dd></div>
            <div><dt class="font-bold">Requested new date</dt><dd>{{ $amendment->requested_date?->format('j M Y') }}</dd></div>
            <div><dt class="font-bold">Reason</dt><dd>{{ $amendment->reason_label }}</dd></div>
            @if ($amendment->customer_response)<div><dt class="font-bold">Explanation</dt><dd>{{ $amendment->customer_response }}</dd></div>@endif
        </dl>
        @if ($amendment->is_urgent)<p class="mt-3 font-bold">Urgent / Late Amendment</p>@endif
        @if ($amendment->status->isOpen())
            <p class="mt-3 font-bold">On Hold — Date Change Requested</p>
        @elseif ($amendment->resulting_agreed_date)
            <p class="mt-3 font-bold">New Date Agreed — {{ $amendment->resulting_agreed_date->format('j M Y') }}</p>
        @else
            <p class="mt-3 font-bold">Date change closed by source completion. The previous date has not been reinstated.</p>
        @endif
        @if ($amendment->closed_at)<p class="mt-1 text-sm">Resolved {{ $amendment->closed_at->format('j M Y, H:i:s') }}</p>@endif
    </section>
@endforeach
