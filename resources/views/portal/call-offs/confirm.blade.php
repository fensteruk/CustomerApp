<x-layouts.portal title="Review Call Off | Fenster Customer Portal">
    <section class="mx-auto max-w-4xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">Review Call Off</h1>
                <p class="page-intro">Check the details below before the call-off batch is created.</p>
            </div>

            <a href="{{ route('portal.call-offs.create') }}" class="secondary-button">Back to edit</a>
        </div>

        <div class="mt-8 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid gap-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-slate-500">Active Site</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-900">{{ $activeSite->name }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Service Type</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-900">{{ $serviceType->label() }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Requested Date</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-900">{{ $requestedDate->format('j M Y') }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Selected projected plots</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-900">{{ $selectedPlots->count() }}</dd>
                </div>
            </dl>

            <div class="mt-6">
                <h2 class="text-base font-bold text-slate-900">Plots included</h2>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($selectedPlots as $plot)
                        <li class="rounded-lg bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800">{{ $plot->plot_reference }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-6">
                <h2 class="text-base font-bold text-slate-900">Customer-facing submission text</h2>
                <p class="mt-2 rounded-lg bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ $customerResponse ?: 'No message provided.' }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('portal.call-offs.store') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="service_identifier" value="{{ $formData['service_identifier'] }}">
            <input type="hidden" name="requested_date" value="{{ $formData['requested_date'] }}">
            <input type="hidden" name="customer_response" value="{{ $formData['customer_response'] ?? '' }}">
            <input type="hidden" name="confirmation_signature" value="{{ $confirmationSignature }}">
            @foreach ($formData['projected_plots'] as $plotUuid)
                <input type="hidden" name="projected_plots[]" value="{{ $plotUuid }}">
            @endforeach

            <a href="{{ route('portal.call-offs.create') }}" class="secondary-button">Edit details</a>
            <button type="submit" class="primary-button" :disabled="submitting" :aria-busy="submitting">
                <span x-text="submitting ? 'Submitting' : 'Submit Call Off'"></span>
            </button>
        </form>
    </section>
</x-layouts.portal>
