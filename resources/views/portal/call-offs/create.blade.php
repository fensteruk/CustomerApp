<x-layouts.portal title="New Call Off | Fenster Customer Portal">
    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="eyebrow">{{ $activeSite->name }}</p><h1 id="page-title" class="page-title">New Call Off</h1><p class="page-intro">Choose plots, then one or more services and a requested date for each service.</p></div>
            <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to dashboard</a>
        </div>
        @if ($errors->any())<div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }}</div>@endif
        @php($oldServiceDates = old('service_dates', []))
        <ol class="mt-7 grid gap-2 text-sm font-bold text-slate-700 sm:grid-cols-4" aria-label="Call off progress"><li class="rounded bg-sky-700 px-3 py-2 text-white">1. Select plots</li><li class="rounded bg-slate-100 px-3 py-2">2. Dates</li><li class="rounded bg-slate-100 px-3 py-2">3. Check</li><li class="rounded bg-slate-100 px-3 py-2">4. Submit</li></ol>
        <form method="POST" action="{{ route('portal.call-offs.matrix') }}" class="mt-8 space-y-8" x-data="{ selectedServices: @js(array_keys($oldServiceDates)), selectedPlots: @js(old('plots', $selectedPlotUuids)) }">
            @csrf
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><legend class="text-lg font-bold text-slate-900">Plots</legend><p class="mt-1 text-sm text-slate-600">Selection is limited to this page and this assigned site.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @forelse ($plots as $plot)
                        <label @class(['flex min-h-14 items-center gap-3 rounded-lg border p-4 text-sm font-bold', 'border-slate-200 text-slate-500' => $plot->is_completed, 'border-slate-200 text-slate-900' => ! $plot->is_completed])><input type="checkbox" name="plots[]" value="{{ $plot->uuid }}" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" x-model="selectedPlots" @checked(in_array($plot->uuid, old('plots', $selectedPlotUuids), true)) @disabled($plot->is_completed)><span>{{ $plot->plot_reference }} @if ($plot->is_completed)<span class="font-normal">(fully completed — not available)</span>@endif</span></label>
                    @empty
                        <div class="empty-state sm:col-span-2"><h2 class="text-lg font-bold text-slate-900">No plots available</h2><p class="mt-2 text-sm text-slate-700">There are no projected plots for this site yet.</p></div>
                    @endforelse
                </div>
                <p class="mt-4 text-sm font-bold text-slate-800"><span x-text="selectedPlots.length">0</span> <span x-text="selectedPlots.length === 1 ? 'plot selected' : 'plots selected'">plots selected</span></p></fieldset>
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><legend class="text-lg font-bold text-slate-900">Services and requested dates</legend><p class="mt-1 text-sm text-slate-600">Each date applies to every selected plot. You can exclude individual combinations next.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ($serviceTypes as $service)
                        <div class="rounded-lg border border-slate-200 p-4"><label class="flex items-center gap-3 font-bold text-slate-900"><input type="checkbox" value="{{ $service->value }}" class="h-5 w-5" x-model="selectedServices"><span>{{ $service->label() }}</span></label><label class="mt-3 block text-sm font-semibold text-slate-700" for="date-{{ $service->value }}">Requested date<input id="date-{{ $service->value }}" name="service_dates[{{ $service->value }}]" type="date" class="form-input mt-1" value="{{ old('service_dates.'.$service->value) }}" x-bind:disabled="! selectedServices.includes(@js($service->value))"></label></div>
                    @endforeach
                </div>
                <div class="mt-5"><label for="customer_response" class="form-label">Message to Fenster</label><textarea id="customer_response" name="customer_response" rows="4" class="form-input">{{ old('customer_response') }}</textarea></div>
            </fieldset>
            <div class="flex justify-end"><button type="submit" class="primary-button">Check selected combinations</button></div>
        </form>
    </section>
</x-layouts.portal>
