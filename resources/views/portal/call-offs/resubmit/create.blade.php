<x-layouts.portal title="Resubmit Call Off | Fenster Customer Portal">
    <section class="mx-auto max-w-4xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="eyebrow">{{ $activeSite->name }}</p><h1 id="page-title" class="page-title">Resubmit Call Off</h1><p class="page-intro">This creates a new request. The original rejected call-off will stay in your history.</p></div>
            <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to dashboard</a>
        </div>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="original-call-off-heading">
            <h2 id="original-call-off-heading" class="text-lg font-bold text-slate-900">Original rejected call-off</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold text-slate-500">Site</dt><dd class="mt-1 font-bold text-slate-900">{{ $activeSite->name }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Plot</dt><dd class="mt-1 font-bold text-slate-900">{{ $sourceRequest->projectedPlot->plot_reference }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Service</dt><dd class="mt-1 font-bold text-slate-900">{{ $sourceRequest->batch->service_identifier->label() }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Previous requested date</dt><dd class="mt-1 font-bold text-slate-900">{{ $sourceRequest->batch->requested_date->format('j M Y') }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Original submission date</dt><dd class="mt-1 font-bold text-slate-900">{{ $sourceRequest->batch->submitted_at->format('j M Y, H:i') }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-semibold text-slate-500">Fenster rejection response</dt><dd class="mt-1 rounded-lg bg-rose-50 p-3 text-slate-900">{{ $sourceRequest->histories->first()?->customer_response ?: 'Not provided.' }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('portal.call-offs.resubmit.confirm', $sourceRequest) }}" class="mt-6 space-y-5">
            @csrf
            <fieldset class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <legend class="px-1 text-lg font-bold text-slate-900">New request details</legend>
                <p class="mt-1 text-sm leading-6 text-slate-600">Choose a new date and add any customer-facing information Fenster needs to review the new request.</p>
                <div class="mt-5">
                    <label for="requested_date" class="form-label">New requested date</label>
                    <input id="requested_date" name="requested_date" type="date" class="form-input" value="{{ old('requested_date', $sourceRequest->batch->requested_date->toDateString()) }}" required>
                    @error('requested_date')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="mt-5">
                    <label for="customer_response" class="form-label">Message to Fenster <span class="font-medium">(optional)</span></label>
                    <textarea id="customer_response" name="customer_response" rows="4" class="form-input" placeholder="Add updated customer-facing context.">{{ old('customer_response', $sourceRequest->batch->customer_response) }}</textarea>
                    @error('customer_response')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </fieldset>
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end"><a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Cancel</a><button type="submit" class="primary-button">Review resubmission</button></div>
        </form>
    </section>
</x-layouts.portal>
