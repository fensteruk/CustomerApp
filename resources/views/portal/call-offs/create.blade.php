<x-layouts.portal title="New Call Off | Fenster Customer Portal">
    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">New Call Off</h1>
                <p class="page-intro">Request one service date for one or more eligible projected plots on this assigned site.</p>
            </div>

            <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to dashboard</a>
        </div>

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">
                Please check the highlighted details and try again.
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('portal.call-offs.confirm') }}"
            class="mt-8 space-y-8"
            x-data="{ service: @js(old('service_identifier', App\Enums\CallOffServiceType::Windows->value)), submitting: false }"
            @submit="submitting = true"
        >
            @csrf

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="service_identifier" class="form-label">Service Type</label>
                        <select id="service_identifier" name="service_identifier" class="form-input" x-model="service">
                            @foreach ($serviceTypes as $serviceType)
                                <option value="{{ $serviceType->value }}">{{ $serviceType->label() }}</option>
                            @endforeach
                        </select>
                        @error('service_identifier')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="requested_date" class="form-label">Requested Date</label>
                        <input id="requested_date" name="requested_date" type="date" class="form-input" value="{{ old('requested_date') }}">
                        @error('requested_date')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-5">
                    <label for="customer_response" class="form-label">Message to Fenster</label>
                    <textarea id="customer_response" name="customer_response" rows="4" class="form-input" placeholder="Add any customer-facing context for this submission.">{{ old('customer_response') }}</textarea>
                    <p class="mt-2 text-sm text-slate-600">This message is stored with the call-off batch and may be visible in the portal history.</p>
                    @error('customer_response')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <legend class="text-lg font-bold text-slate-900">Projected Plot Selection</legend>
                <p class="mt-1 text-sm leading-6 text-slate-600">Only outstanding projected plots that are eligible for the selected service are shown.</p>

                @error('projected_plots')
                    <p class="form-error">{{ $message }}</p>
                @enderror

                @php
                    $oldProjectedPlots = old('projected_plots', []);
                @endphp

                <div class="mt-5 space-y-4">
                    @foreach ($serviceTypes as $serviceType)
                        <div x-show="service === @js($serviceType->value)">
                            @forelse ($eligiblePlotsByService[$serviceType->value] as $plot)
                                <label class="mb-3 flex min-h-14 items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-900 transition hover:border-sky-400">
                                    <input
                                        type="checkbox"
                                        name="projected_plots[]"
                                        value="{{ $plot->uuid }}"
                                        class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700"
                                        x-bind:disabled="service !== @js($serviceType->value)"
                                        @checked(in_array($plot->uuid, $oldProjectedPlots, true))
                                    >
                                    <span>{{ $plot->plot_reference }}</span>
                                </label>
                            @empty
                                <div class="empty-state">
                                    <h2 class="text-lg font-bold text-slate-900">No eligible plots for {{ $serviceType->label() }}</h2>
                                    <p class="mt-2 text-sm leading-6 text-slate-700">There are no outstanding projected plots currently available for this service on {{ $activeSite->name }}.</p>
                                </div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Cancel</a>
                <button type="submit" class="primary-button" :disabled="submitting" :aria-busy="submitting">
                    <span x-text="submitting ? 'Checking request' : 'Review before submitting'"></span>
                </button>
            </div>
        </form>
    </section>
</x-layouts.portal>
