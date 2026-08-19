<x-layouts.portal title="Review Resubmission | Fenster Customer Portal">
    <section class="mx-auto max-w-4xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="eyebrow">{{ $activeSite->name }}</p><h1 id="page-title" class="page-title">Review Resubmission</h1><p class="page-intro">Check the new request before it is submitted for review.</p></div>
            <a href="{{ route('portal.call-offs.resubmit.create', $sourceRequest) }}" class="secondary-button">Back to edit</a>
        </div>

        <div class="mt-8 rounded-lg border border-sky-300 bg-sky-50 p-4 text-sm leading-6 text-sky-950" role="status"><strong>The original rejected call-off will remain in history.</strong> This will create a new Submitted request.</div>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="review-details-heading">
            <h2 id="review-details-heading" class="text-lg font-bold text-slate-900">Request details</h2>
            <dl class="mt-4 grid gap-5 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold text-slate-500">Site</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $activeSite->name }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Plot</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $sourceRequest->projectedPlot->plot_reference }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Service</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $sourceRequest->batch->service_identifier->label() }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Previous rejected date</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $sourceRequest->batch->requested_date->format('j M Y') }}</dd></div>
                <div><dt class="font-semibold text-slate-500">New requested date</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $requestedDate->format('j M Y') }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-semibold text-slate-500">Previous rejection response</dt><dd class="mt-1 rounded-lg bg-rose-50 p-3 text-slate-900">{{ $sourceRequest->histories->first()?->customer_response ?: 'Not provided.' }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-semibold text-slate-500">New submission message</dt><dd class="mt-1 rounded-lg bg-slate-50 p-3 text-slate-900">{{ $customerResponse ?: 'No message provided.' }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('portal.call-offs.resubmit.store', $sourceRequest) }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
            @csrf
            <input type="hidden" name="requested_date" value="{{ $requestedDate->toDateString() }}">
            <input type="hidden" name="customer_response" value="{{ $customerResponse }}">
            <input type="hidden" name="confirmation_signature" value="{{ $confirmationSignature }}">
            <a href="{{ route('portal.call-offs.resubmit.create', $sourceRequest) }}" class="secondary-button">Edit details</a>
            <button type="submit" class="primary-button">Submit resubmission</button>
        </form>
    </section>
</x-layouts.portal>
