<x-layouts.portal title="Synthetic Import Studio Demo | Fenster Customer Portal" sidebar-label="Demo steps">
    <div class="admin-workspace" x-data="importStudioDemo({{ count($steps) }})">
        <a class="admin-back" href="{{ $launchContext['return_url'] ?? route('office.workspace.imports') }}">← {{ $launchContext['site'] ?? 'Imports' }}</a>

        <div class="rounded-xl border-2 border-orange-500 bg-orange-50 p-4 text-orange-950" role="note" aria-label="Demonstration safety notice">
            <div class="flex flex-wrap gap-2">
                <strong class="rounded-full bg-orange-900 px-3 py-1 text-sm text-white">DEMO ONLY</strong>
                <strong class="rounded-full bg-orange-200 px-3 py-1 text-sm">SYNTHETIC DATA</strong>
                <strong class="rounded-full bg-white px-3 py-1 text-sm ring-1 ring-orange-400">NO DATA WILL BE SAVED</strong>
            </div>
            <p class="mt-3 text-sm font-semibold">This is a precomputed product walkthrough. It does not upload, analyse, bind or commit anything.</p>
        </div>

        <header class="admin-page-header">
            <div>
                <p class="eyebrow">Import Studio concept</p>
                <h1 class="admin-title">{{ $scenario['title'] }}</h1>
                <p class="admin-intro">Walk through the proposed controlled-review experience using one fictional example.</p>
            </div>
            <span class="status status-slate">Manifest {{ $scenario['version'] }}</span>
        </header>

        @if ($launchContext)
            <p class="admin-notice"><strong>Opened from {{ $launchContext['site'] }}</strong> for {{ $launchContext['customer'] }}. The walkthrough still uses only the separate synthetic scenario below.</p>
        @endif

        <nav class="admin-card overflow-x-auto" aria-label="Import demonstration progress">
            <ol class="flex min-w-max gap-2">
                @foreach ($steps as $number => $label)
                    <li>
                        <button type="button" class="min-h-11 rounded-lg border px-3 py-2 text-left text-sm font-bold"
                            :class="step === {{ $number + 1 }} ? 'border-sky-700 bg-sky-700 text-white' : (step > {{ $number + 1 }} ? 'border-emerald-300 bg-emerald-50 text-emerald-950' : 'border-slate-200 bg-white text-slate-700')"
                            @click="goTo({{ $number + 1 }})" :aria-current="step === {{ $number + 1 }} ? 'step' : null">
                            <span class="block text-xs" x-text="step > {{ $number + 1 }} ? 'Complete' : (step === {{ $number + 1 }} ? 'Current' : 'Upcoming')"></span>
                            {{ $number + 1 }}. {{ $label }}
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="admin-card min-h-80" aria-live="polite">
            <section x-show="step === 1" aria-labelledby="import-demo-step-1">
                <p class="eyebrow">Step 1 of 12</p>
                <h2 id="import-demo-step-1" tabindex="-1" class="section-title mt-2">Upload / Select Example</h2>
                <p class="mt-3 max-w-2xl leading-7 text-slate-600">Real workbook upload is unavailable in this demo. Continue with the bundled fictional scenario.</p>
                <button type="button" class="primary-button mt-5" @click="next()">Use synthetic example spreadsheet</button>
                <p class="mt-3 text-sm font-semibold text-orange-900">Example label: {{ $scenario['safe_file_label'] }} — no file is opened or stored.</p>
            </section>

            <section x-cloak x-show="step === 2" aria-labelledby="import-demo-step-2">
                <p class="eyebrow">Step 2 of 12</p><h2 id="import-demo-step-2" tabindex="-1" class="section-title mt-2">Export Date</h2>
                <p class="mt-4 text-sm text-slate-600">Declared date for the fictional export</p><p class="mt-1 text-2xl font-bold">{{ $scenario['export_date'] }}</p>
                <p class="mt-4 admin-notice">In the future, Office Staff must confirm this date; it will not be inferred from a filename.</p>
            </section>

            <section x-cloak x-show="step === 3" aria-labelledby="import-demo-step-3">
                <p class="eyebrow">Step 3 of 12</p><h2 id="import-demo-step-3" tabindex="-1" class="section-title mt-2">MORNING / AFTERNOON</h2>
                <div class="mt-5 grid max-w-lg grid-cols-2 gap-3"><div class="rounded-lg border-2 border-sky-700 bg-sky-50 p-4"><strong>MORNING</strong><span class="mt-2 block text-sm">Selected in the scenario</span></div><div class="rounded-lg border border-slate-200 p-4 text-slate-500"><strong>AFTERNOON</strong><span class="mt-2 block text-sm">Not selected</span></div></div>
            </section>

            <section x-cloak x-show="step === 4" aria-labelledby="import-demo-step-4">
                <p class="eyebrow">Step 4 of 12</p><h2 id="import-demo-step-4" tabindex="-1" class="section-title mt-2">Uploader</h2>
                <p class="mt-4 text-sm text-slate-600">Shown automatically from the authenticated account</p><p class="mt-1 text-xl font-bold">{{ auth()->user()->name }}</p>
                <p class="mt-4 text-sm font-semibold">The demo does not create an uploader record or import audit.</p>
            </section>

            <section x-cloak x-show="step === 5" aria-labelledby="import-demo-step-5">
                <p class="eyebrow">Step 5 of 12</p><h2 id="import-demo-step-5" tabindex="-1" class="section-title mt-2">Latest Export Confirmation</h2>
                <div class="mt-5 rounded-lg border border-emerald-300 bg-emerald-50 p-5"><strong>Demonstration confirmation recorded on screen</strong><p class="mt-2">{{ $scenario['latest_confirmation'] }}</p></div>
                <p class="mt-3 text-sm text-slate-600">Refresh resets this walkthrough. Nothing is persisted.</p>
            </section>

            <section x-cloak x-show="step === 6" aria-labelledby="import-demo-step-6">
                <p class="eyebrow">Step 6 of 12</p><h2 id="import-demo-step-6" tabindex="-1" class="section-title mt-2">Wald Analysis</h2>
                <p class="admin-notice mt-4"><strong>{{ $scenario['analysis']['mode'] }}</strong></p>
                <dl class="mt-5 space-y-4"><div><dt class="admin-term">Confidence</dt><dd class="admin-value">{{ $scenario['analysis']['confidence'] }}</dd></div><div><dt class="admin-term">Evidence summary</dt><dd class="admin-value">{{ $scenario['analysis']['evidence'] }}</dd></div><div><dt class="admin-term">Detected columns</dt><dd class="mt-2 flex flex-wrap gap-2">@foreach ($scenario['analysis']['columns'] as $column)<span class="status status-slate">{{ $column }}</span>@endforeach</dd></div></dl>
            </section>

            <section x-cloak x-show="step === 7" aria-labelledby="import-demo-step-7">
                <p class="eyebrow">Step 7 of 12</p><h2 id="import-demo-step-7" tabindex="-1" class="section-title mt-2">Detected Site</h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2"><div><dt class="admin-term">Fictional customer</dt><dd class="admin-value">{{ $scenario['site']['customer'] }}</dd></div><div><dt class="admin-term">Detected site</dt><dd class="admin-value">{{ $scenario['site']['name'] }}</dd></div><div><dt class="admin-term">Source identity</dt><dd class="admin-value">{{ $scenario['site']['source_identity'] }}</dd></div></dl>
                <div class="mt-5 rounded-lg border border-dashed border-slate-300 p-4"><strong>Future WALD06 concept only</strong><p class="mt-2 text-sm text-slate-600">A future RedZebra export may contain multiple sites. Each proposed site review unit would require separate, explicit review. No splitting is implemented here.</p></div>
            </section>

            <section x-cloak x-show="step === 8" aria-labelledby="import-demo-step-8">
                <p class="eyebrow">Step 8 of 12</p><h2 id="import-demo-step-8" tabindex="-1" class="section-title mt-2">Source Binding</h2>
                <div class="mt-5 rounded-lg border border-sky-200 bg-sky-50 p-5"><span class="status status-amber">Review only</span><p class="mt-3 text-lg font-bold">{{ $scenario['site']['binding'] }}</p><p class="mt-2 text-sm">{{ $scenario['site']['source_identity'] }} → {{ $scenario['site']['name'] }}</p></div>
                <p class="mt-4 font-semibold text-orange-900">No source binding is created, activated or changed by this demo.</p>
            </section>

            <section x-cloak x-show="step === 9" aria-labelledby="import-demo-step-9">
                <p class="eyebrow">Step 9 of 12</p><h2 id="import-demo-step-9" tabindex="-1" class="section-title mt-2">Detected Records</h2>
                <p class="mt-3 text-sm text-slate-600">Seven precomputed fictional records selected for review.</p>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr class="border-b border-slate-300">@foreach (['Plot', 'Service', 'Call type', 'Products', 'Proposed result'] as $heading)<th class="px-3 py-3 font-bold">{{ $heading }}</th>@endforeach</tr></thead><tbody>@foreach ($scenario['records'] as $record)<tr class="border-b border-slate-100"><td class="px-3 py-3 font-semibold">{{ $record['plot'] }}</td><td class="px-3 py-3">{{ $record['service'] }}</td><td class="px-3 py-3">{{ $record['call'] }}</td><td class="px-3 py-3">{{ $record['products'] }}</td><td class="px-3 py-3">{{ $record['result'] }}</td></tr>@endforeach</tbody></table></div>
            </section>

            <section x-cloak x-show="step === 10" aria-labelledby="import-demo-step-10">
                <p class="eyebrow">Step 10 of 12</p><h2 id="import-demo-step-10" tabindex="-1" class="section-title mt-2">Clarifications</h2>
                <div class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-5"><p class="font-bold">{{ $scenario['clarification']['question'] }}</p><p class="mt-3">{{ $scenario['clarification']['answer'] }}</p><p class="mt-3 text-sm font-semibold">{{ $scenario['clarification']['safety'] }}</p></div>
                <p class="mt-4 text-sm text-slate-600">This is a prewritten example, not a saved response or learned mapping.</p>
            </section>

            <section x-cloak x-show="step === 11" aria-labelledby="import-demo-step-11">
                <p class="eyebrow">Step 11 of 12</p><h2 id="import-demo-step-11" tabindex="-1" class="section-title mt-2">Preview Changes</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2"><div><h3 class="font-bold">Product totals</h3><dl class="mt-3 space-y-2">@foreach ($scenario['totals'] as $label => $count)<div class="flex justify-between gap-4 border-b border-slate-100 py-2"><dt>{{ $label }}</dt><dd class="font-bold">{{ $count }}</dd></div>@endforeach</dl></div><div><h3 class="font-bold">Proposed effects</h3><ul class="mt-3 list-disc space-y-2 pl-5">@foreach ($scenario['changes'] as $change)<li>{{ $change }}</li>@endforeach</ul></div></div>
                <div class="admin-notice mt-5"><strong>Warnings</strong><ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($scenario['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>
            </section>

            <section x-cloak x-show="step === 12" aria-labelledby="import-demo-step-12">
                <p class="eyebrow">Step 12 of 12</p><h2 id="import-demo-step-12" tabindex="-1" class="section-title mt-2">Commit Summary</h2>
                <div class="mt-5 rounded-lg border-2 border-orange-500 bg-orange-50 p-5"><span class="status status-red">Commit unavailable in demo</span><h3 class="mt-4 text-xl font-bold">No data will be saved</h3><p class="mt-2 leading-7">The walkthrough ends before any real authorisation, dependency check, database transaction or receipt. Real Import Studio integration remains separately excluded.</p></div>
                <button type="button" class="primary-button mt-5" disabled aria-disabled="true">Commit unavailable in demo</button>
            </section>
        </div>

        <div class="admin-actions justify-between border-t border-slate-300 pt-4">
            <button type="button" class="secondary-button" @click="previous()" :disabled="step === 1">Previous step</button>
            <p class="text-sm font-bold" aria-live="polite">Step <span x-text="step">1</span> of {{ count($steps) }}</p>
            <button type="button" class="primary-button" @click="next()" :disabled="step === {{ count($steps) }}">Next step</button>
        </div>
    </div>
</x-layouts.portal>
