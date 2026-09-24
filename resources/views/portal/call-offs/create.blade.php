<x-layouts.portal title="New Call Off | Fenster Customer Portal">
    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title" x-data="callOffSubmission(@js(route('portal.call-offs.matrix')), @js(route('portal.call-offs.review')), @js(route('portal.call-offs.store')))" x-init="selectedServices = @js(array_keys(old('service_dates', []))); selectedPlots = @js(old('plots', $selectedPlotUuids))">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="eyebrow">{{ $activeSite->name }}</p><h1 id="page-title" class="page-title">New Call Off</h1><p class="page-intro">Choose plots, then one or more services and a requested date for each service.</p></div>
            <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to dashboard</a>
        </div>
        @if ($errors->any())<div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }} @if($errors->has('cavity_early_reason'))<a href="#cavity-early-reason" class="underline focus-visible:outline-2">Go to Early Date Reason</a>@endif</div>@endif
        @php($oldServiceDates = old('service_dates', []))
        <ol class="mt-7 grid gap-2 text-sm font-bold text-slate-700 sm:grid-cols-2" aria-label="Call off progress"><li class="rounded bg-sky-700 px-3 py-2 text-white">1. Select combinations</li><li class="rounded bg-slate-100 px-3 py-2">2. Confirm call-off</li></ol>
        <form method="POST" action="{{ route('portal.call-offs.matrix') }}" class="mt-8 space-y-8" x-ref="selectionForm" @submit.prevent="openConfirmation()">
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
                        <div class="min-w-0 rounded-lg border border-slate-200 p-4" @if($service === App\Enums\CallOffServiceType::CavityClosers) x-data="{ selectedDate: @js(old('service_dates.'.$service->value, '')), early: false, days: 0, selectedDisplay: '', earliestDisplay: @js($cavityEarliestDate->format('l j F Y')), loading: false, error: false, async update() { if (!this.selectedDate) { this.early = false; return; } this.loading = true; this.error = false; try { const response = await fetch(@js(route('portal.call-offs.cavity-closer-date')) + '?date=' + encodeURIComponent(this.selectedDate), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }); if (!response.ok) throw new Error('date'); const result = await response.json(); if (result.selected_date !== this.selectedDate) return; this.early = result.is_early; this.days = result.working_days_early; this.selectedDisplay = result.selected_display; this.earliestDisplay = result.earliest_display; } catch (e) { this.error = true; this.early = false; } finally { this.loading = false; } } }" x-init="update()" @endif><label class="flex items-center gap-3 font-bold text-slate-900"><input type="checkbox" value="{{ $service->value }}" class="h-5 w-5" x-model="selectedServices"><span>{{ $service->label() }}</span></label>
                            @if($service === App\Enums\CallOffServiceType::CavityClosers)<div class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950"><p class="font-bold">Cavity Closer lead time: 15 working days</p><p class="mt-1">Earliest standard date: <span x-text="earliestDisplay">{{ $cavityEarliestDate->format('l j F Y') }}</span>.</p><p class="mt-1">You can still ask for an earlier date with a reason.</p></div>@endif
                            <label class="mt-3 block text-sm font-semibold text-slate-700" for="date-{{ $service->value }}">Requested date<input id="date-{{ $service->value }}" name="service_dates[{{ $service->value }}]" type="date" class="form-input mt-1" value="{{ old('service_dates.'.$service->value) }}" x-bind:disabled="! selectedServices.includes(@js($service->value))" @if($service === App\Enums\CallOffServiceType::CavityClosers) x-model="selectedDate" @change="update()" aria-describedby="cavity-lead-status" @endif></label>
                            @if($service === App\Enums\CallOffServiceType::CavityClosers)
                                <div id="cavity-lead-status" class="mt-3 text-sm" role="status" aria-live="polite"><p x-show="loading" x-cloak>Checking the selected date…</p><p x-show="error" x-cloak>Date guidance is unavailable. The next step will check your date.</p><p x-show="early && !loading" x-cloak class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-950"><strong>Early date request.</strong> Cavity Closers require 15 working days' notice. You selected <span x-text="selectedDisplay"></span>; the earliest standard date is <span x-text="earliestDisplay"></span>. This is <strong><span x-text="days"></span> working <span x-text="days === 1 ? 'day' : 'days'"></span> early</strong>. Please give an Early Date Reason.</p></div>
                                <div class="mt-3" x-show="early || error || !selectedDate"><label for="cavity-early-reason" class="form-label">Early Date Reason <span x-show="early">(required if early)</span></label><textarea id="cavity-early-reason" name="cavity_early_reason" rows="3" maxlength="2000" class="form-input" x-bind:required="early && selectedServices.includes(@js($service->value))" x-bind:disabled="! selectedServices.includes(@js($service->value))">{{ old('cavity_early_reason') }}</textarea></div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-5"><label for="customer_response" class="form-label">Message to Fenster</label><textarea id="customer_response" name="customer_response" rows="4" class="form-input">{{ old('customer_response') }}</textarea></div>
            </fieldset>
            <div x-show="error" x-cloak class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-950" role="alert" x-text="error"></div>
            <div class="flex justify-end"><button type="submit" class="primary-button min-w-32" x-ref="submitButton" :disabled="loading"><span x-show="!loading">Submit</span><span x-show="loading" x-cloak>Checking…</span></button></div>
        </form>

        <dialog x-ref="confirmation" @close="restoreFocus()" @cancel="if (confirming) $event.preventDefault()" aria-labelledby="call-off-confirmation-title" aria-describedby="call-off-confirmation-intro" class="w-[calc(100vw-2rem)] max-w-2xl max-h-[calc(100dvh-2rem)] overflow-hidden rounded-2xl border border-slate-200 p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/70">
            <div class="flex max-h-[calc(100dvh-2rem)] min-h-0 flex-col">
                <div class="shrink-0 border-b border-slate-200 bg-white px-5 py-4 sm:px-6"><p class="eyebrow">Final check</p><h2 id="call-off-confirmation-title" x-ref="confirmationTitle" tabindex="-1" class="mt-1 text-xl font-bold focus:outline-none">Confirm call-off</h2><p id="call-off-confirmation-intro" class="mt-2 text-sm text-slate-700">You are about to call off:</p></div>
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6">
                    <p class="text-sm font-semibold text-slate-700" aria-live="polite"><span x-text="selectedRows.length"></span> <span x-text="selectedRows.length === 1 ? 'combination selected' : 'combinations selected'"></span></p>
                    <template x-for="row in selectedRows" :key="row.key">
                        <article class="min-w-0 rounded-xl border border-slate-200 p-4">
                            <h3 class="font-bold text-slate-950 [overflow-wrap:anywhere]" x-text="row.plot_reference"></h3>
                            <p class="mt-1 font-semibold text-slate-800" x-text="row.service_label"></p>
                            <p class="mt-1 text-sm text-slate-700">Requested: <time :datetime="row.requested_date" x-text="row.requested_display"></time></p>
                            <p x-show="row.products_summary" class="mt-2 text-sm leading-6 text-slate-700 [overflow-wrap:anywhere]">Products: <span x-text="row.products_summary"></span></p>
                            <div x-show="row.is_early_exception" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950">
                                <p class="font-bold">Early date request</p><p>Standard earliest date: <span x-text="row.earliest_display"></span>.</p>
                                <p x-show="row.working_days_early"><strong x-text="row.working_days_early"></strong> working <span x-text="row.working_days_early === 1 ? 'day' : 'days'"></span> early.</p>
                                <label class="mt-2 block font-semibold">Early Date Reason <span class="font-normal">(required)</span><textarea rows="2" maxlength="2000" class="form-input mt-1" x-model="reasons[row.key]" :aria-label="'Early Date Reason for ' + row.plot_reference + ' ' + row.service_label"></textarea></label>
                            </div>
                        </article>
                    </template>
                    <p x-show="selectedRows.length === 0" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-950">Choose at least one combination to submit.</p>
                    <div x-show="message" class="rounded-lg bg-slate-50 p-4 text-sm text-slate-700"><h3 class="font-bold text-slate-900">Message to Fenster</h3><p class="mt-1 whitespace-pre-line [overflow-wrap:anywhere]" x-text="message"></p></div>
                    <details x-show="availableRows.length > 1" class="rounded-lg border border-slate-200 p-4"><summary class="cursor-pointer font-semibold text-slate-900">Change included combinations</summary><p class="mt-2 text-sm text-slate-600">Uncheck a combination to leave it out of this call-off.</p><div class="mt-3 space-y-2"><template x-for="row in availableRows" :key="row.key"><label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm"><input type="checkbox" class="h-5 w-5 shrink-0" x-model="includedKeys" :value="row.key"><span class="[overflow-wrap:anywhere]" x-text="row.plot_reference + ' · ' + row.service_label"></span></label></template></div></details>
                    <details x-show="unavailableRows.length" class="rounded-lg border border-slate-200 p-4"><summary class="cursor-pointer text-sm font-semibold text-slate-700"><span x-text="unavailableRows.length"></span> unavailable <span x-text="unavailableRows.length === 1 ? 'combination' : 'combinations'"></span> will not be submitted</summary><div class="mt-3 space-y-2"><template x-for="row in unavailableRows" :key="row.key"><p class="text-sm text-slate-700 [overflow-wrap:anywhere]"><strong x-text="row.plot_reference + ' · ' + row.service_label"></strong>: <span x-text="row.reason"></span></p></template></div></details>
                    <p class="text-sm font-semibold text-slate-800">Please confirm these details are correct.</p>
                    <p x-show="modalError" x-cloak x-ref="modalError" tabindex="-1" class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-950" role="alert" x-text="modalError"></p>
                </div>
                <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:justify-end sm:px-6"><button type="button" class="secondary-button" @click="closeConfirmation()" :disabled="confirming">Cancel</button><button type="button" class="primary-button" @click="confirm()" :disabled="confirming || !readyToConfirm"><span x-show="!confirming">Confirm call-off</span><span x-show="confirming" x-cloak>Submitting…</span></button></div>
            </div>
        </dialog>
    </section>
</x-layouts.portal>
