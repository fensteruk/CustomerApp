<x-layouts.portal title="Import SiteApp Spreadsheet | Fenster Customer Portal">
    <section
        class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8"
        aria-labelledby="source-import-title"
        x-data="manualSourceImport(@js([
            'endpoints' => [
                'bindings' => route('portal.source-site-bindings.index'),
                'preview' => route('portal.source-imports.previews.store'),
                'interpretation' => route('portal.source-imports.previews.interpretation', ['manualSourceImportPreview' => '__PREVIEW__']),
                'commit' => route('portal.source-imports.previews.commit', ['manualSourceImportPreview' => '__PREVIEW__']),
            ],
            'portalSites' => $portalSites,
            'officeUserName' => $officeUserName,
            'reviewUrl' => route('portal.review-requests'),
            'maxUploadBytes' => 10 * 1024 * 1024,
        ]))"
        x-init="init()"
    >
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="eyebrow">Fenster Office Staff</p>
                <h1 id="source-import-title" class="page-title" x-ref="pageTitle" tabindex="-1">Import SiteApp Spreadsheet</h1>
                <p class="page-intro">Analyse a SiteApp Excel export, check exactly what will change, then confirm the import.</p>
            </div>
            <a href="{{ route('portal.review-requests') }}" class="secondary-button shrink-0">Return to Office Review</a>
        </div>

        <div class="mt-7 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
            <div class="flex gap-3">
                <svg aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0 text-sky-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 10v6m0-9h.01" />
                </svg>
                <p><strong>Nothing is imported during analysis.</strong> The spreadsheet will be checked first. Source data changes only after you review the preview and select Confirm Import.</p>
            </div>
        </div>

        <nav class="mt-7 overflow-x-auto pb-2" aria-label="Import progress">
            <ol class="flex min-w-[58rem] gap-2">
                @foreach (['Upload', 'Structure', 'Columns', 'Sites', 'Preview', 'Confirm', 'Results'] as $position => $label)
                    <li class="flex min-w-28 flex-1 items-center gap-2">
                        <span
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full border text-sm font-extrabold"
                            :class="step > {{ $position + 1 }} ? 'border-emerald-700 bg-emerald-700 text-white' : (step === {{ $position + 1 }} ? 'border-sky-700 bg-sky-700 text-white' : 'border-slate-300 bg-white text-slate-600')"
                            :aria-current="step === {{ $position + 1 }} ? 'step' : null"
                        >{{ $position + 1 }}</span>
                        <span class="text-sm font-bold" :class="step === {{ $position + 1 }} ? 'text-sky-900' : 'text-slate-600'">{{ $label }}</span>
                        @if (! $loop->last)
                            <span aria-hidden="true" class="ml-auto h-px min-w-3 flex-1 bg-slate-300"></span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        <div
            x-cloak
            x-show="error"
            x-ref="errorSummary"
            tabindex="-1"
            class="mt-5 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-950"
            role="alert"
        >
            <p class="font-extrabold">This step needs attention</p>
            <p class="mt-1" x-text="error"></p>
        </div>

        <div
            x-show="successMessage"
            x-ref="statusMessage"
            tabindex="-1"
            class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950"
            role="status"
        >
            <span x-text="successMessage"></span>
        </div>

        <p class="sr-only" aria-live="polite" x-text="busy ? 'Working. Please wait.' : ''"></p>

        {{-- Step 1: Upload --}}
        <div x-show="step === 1" class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <form @submit.prevent="upload" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" novalidate>
                <h2 class="section-title">Upload Source File</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Choose the XLSX export supplied by SiteApp. The maximum file size is 10 MB.</p>

                <div class="mt-6">
                    <label for="source-workbook" class="form-label">SiteApp spreadsheet</label>
                    <input
                        id="source-workbook"
                        x-ref="workbook"
                        type="file"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        class="form-input file:mr-4 file:rounded-md file:border-0 file:bg-sky-100 file:px-4 file:py-2 file:font-bold file:text-sky-900 hover:file:bg-sky-200"
                        :aria-invalid="fileError ? 'true' : 'false'"
                        aria-describedby="source-workbook-help source-workbook-error"
                        @change="chooseFile"
                    >
                    <p id="source-workbook-help" class="mt-2 text-sm text-slate-600">Accepted format: Excel workbook (.xlsx). Macros are not accepted.</p>
                    <p id="source-workbook-error" x-show="fileError" x-text="fileError" class="form-error"></p>
                </div>

                <fieldset class="mt-7">
                    <legend class="text-base font-extrabold text-slate-900">Import Scope</legend>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Filtered / Partial Export is the safe default for normal SiteApp exports.</p>

                    <div class="mt-4 grid gap-3">
                        <label class="flex min-h-14 cursor-pointer gap-3 rounded-lg border p-4" :class="scope === 'PARTIAL_FILTERED_EXPORT' ? 'border-sky-600 bg-sky-50 ring-1 ring-sky-600' : 'border-slate-300 bg-white'">
                            <input type="radio" x-model="scope" value="PARTIAL_FILTERED_EXPORT" class="mt-1 h-5 w-5 border-slate-400 text-sky-700 focus:ring-sky-600" @change="confirmScope = false; completeSiteIdentifiers = []">
                            <span><strong class="block text-slate-900">Filtered / Partial Export</strong><span class="mt-1 block text-sm text-slate-600">Assumes the spreadsheet contains only records included in the export filters. Missing rows are not treated as absent.</span></span>
                        </label>

                        <details class="rounded-lg border border-slate-300 bg-slate-50 p-4">
                            <summary class="min-h-11 cursor-pointer py-2 font-bold text-slate-800">Advanced complete-export options</summary>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Use a complete option only when you know the spreadsheet contains every relevant record for that scope.</p>
                            <div class="mt-4 grid gap-3">
                                <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-lg border border-slate-300 bg-white p-3">
                                    <input type="radio" x-model="scope" value="SITE_COMPLETE_SNAPSHOT" class="mt-0.5 h-5 w-5 text-sky-700 focus:ring-sky-600">
                                    <span><strong class="block">Complete export for selected site(s)</strong><span class="block text-sm text-slate-600">Only the selected, mapped source sites are compared for missing records.</span></span>
                                </label>
                                <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-lg border border-slate-300 bg-white p-3">
                                    <input type="radio" x-model="scope" value="GLOBAL_COMPLETE_SNAPSHOT" class="mt-0.5 h-5 w-5 text-sky-700 focus:ring-sky-600">
                                    <span><strong class="block">Complete global export</strong><span class="block text-sm text-slate-600">Compares the entire SiteApp spreadsheet source. Use only for a confirmed full export.</span></span>
                                </label>
                            </div>

                            <div x-show="scope === 'SITE_COMPLETE_SNAPSHOT'" class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4">
                                <p class="font-bold text-amber-950">Select every source site confirmed complete</p>
                                <p x-show="bindingsLoading" class="mt-2 text-sm text-amber-900">Loading mapped source sites…</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <template x-for="binding in bindings" :key="binding.binding_uuid">
                                        <label class="flex min-h-11 items-center gap-3 rounded-md bg-white px-3 py-2 text-sm">
                                            <input type="checkbox" :value="binding.source_site_key" x-model="completeSiteIdentifiers" class="h-5 w-5 rounded border-slate-400 text-sky-700 focus:ring-sky-600">
                                            <span><span class="font-bold" x-text="binding.original_name"></span><span class="block text-xs text-slate-600" x-text="binding.portal_site.name"></span></span>
                                        </label>
                                    </template>
                                </div>
                                <p x-show="!bindingsLoading && bindings.length === 0" class="mt-2 text-sm text-amber-900">No source sites have been mapped yet. Use the partial export option for the first import.</p>
                            </div>

                            <label x-show="scope !== 'PARTIAL_FILTERED_EXPORT'" class="mt-5 flex min-h-12 cursor-pointer items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                                <input type="checkbox" x-model="confirmScope" class="mt-0.5 h-5 w-5 rounded border-amber-500 text-amber-700 focus:ring-amber-600">
                                <span><strong class="block">I confirm this is a complete export for the selected scope</strong><span class="mt-1 block">Records absent from that scope may be flagged for reconciliation, but they will not be deleted.</span></span>
                            </label>
                        </details>
                    </div>
                </fieldset>

                <div class="mt-7 flex justify-end">
                    <button type="submit" class="primary-button w-full sm:w-auto" :disabled="busy || !scopeReady()">
                        <svg x-show="busy" aria-hidden="true" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                        <span x-text="busy ? 'Analysing spreadsheet…' : 'Analyse spreadsheet'"></span>
                    </button>
                </div>
            </form>

            <aside class="rounded-xl border border-slate-200 bg-slate-50 p-5" aria-labelledby="before-upload-title">
                <h2 id="before-upload-title" class="text-lg font-extrabold text-slate-900">Before you upload</h2>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                    <li class="flex gap-2"><span aria-hidden="true" class="font-bold text-emerald-700">✓</span><span>Use the original <strong>.xlsx</strong> export.</span></li>
                    <li class="flex gap-2"><span aria-hidden="true" class="font-bold text-emerald-700">✓</span><span>Portal call-off and date-negotiation history will not be overwritten.</span></li>
                    <li class="flex gap-2"><span aria-hidden="true" class="font-bold text-emerald-700">✓</span><span>You can cancel at any point before confirmation.</span></li>
                    <li class="flex gap-2"><span aria-hidden="true" class="font-bold text-emerald-700">✓</span><span>Site Value and other commercial fields are not shown to customers.</span></li>
                </ul>
            </aside>
        </div>

        {{-- Step 2: Detected structure --}}
        <div x-cloak x-show="step === 2" class="mt-7 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="eyebrow">Step 2</p>
                    <h2 class="section-title mt-1">Detected Spreadsheet Structure</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Check that the worksheet, header row and record count look right before confirming the columns.</p>
                </div>
                <span class="status" :class="confidenceStatusClass(interpretation?.overall_confidence ?? 0)" x-text="`${interpretation?.overall_confidence ?? 0}% confidence`"></span>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">File</dt><dd class="mt-1 break-all font-bold" x-text="preview?.metadata?.original_filename"></dd></div>
                <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">Worksheet</dt><dd class="mt-1 font-bold" x-text="interpretation?.selected_sheet ?? preview?.metadata?.worksheet ?? 'Needs confirmation'"></dd></div>
                <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">Header row</dt><dd class="mt-1 font-bold" x-text="interpretation?.header_row ?? 'Needs confirmation'"></dd></div>
                <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">Rows found</dt><dd class="mt-1 font-bold" x-text="preview?.metadata?.row_count ?? 0"></dd></div>
            </dl>

            <div class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-4">
                <p class="text-sm font-bold text-sky-950">Import Scope</p>
                <p class="mt-1 text-sm text-sky-900" x-text="preview?.import_scope?.label"></p>
            </div>

            <div class="mt-6">
                <h3 class="font-extrabold text-slate-900">Detected source sites</h3>
                <div x-show="detectedSourceSites.length" class="mt-3 flex flex-wrap gap-2">
                    <template x-for="site in detectedSourceSites" :key="site.key"><span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-800" x-text="site.name"></span></template>
                </div>
                <p x-show="!detectedSourceSites.length" class="mt-2 text-sm text-slate-600">Source sites will be listed after the column mapping is confirmed.</p>
            </div>

            <div x-show="interpretation?.issues?.length" class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                <h3 class="font-extrabold text-amber-950">Structure checks</h3>
                <ul class="mt-2 space-y-2 text-sm text-amber-950">
                    <template x-for="issue in interpretation?.issues ?? []" :key="issue.code"><li><strong x-text="issue.blocking ? 'Needs confirmation: ' : 'Note: '"></strong><span x-text="issue.message"></span></li></template>
                </ul>
            </div>

            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <button type="button" class="secondary-button" @click="cancelPreview">Cancel preview</button>
                <button type="button" class="primary-button" @click="goToMapping">Review column mappings</button>
            </div>
        </div>

        {{-- Step 3: Column mapping --}}
        <div x-cloak x-show="step === 3" class="mt-7 space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <p class="eyebrow">Step 3</p>
                <h2 class="section-title mt-1">Confirm Column Mappings</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Confirm what each source column means. Unknown columns must be deliberately ignored or mapped to an approved meaning.</p>

                <div class="mt-4 flex flex-wrap gap-2" aria-label="Mapping status key">
                    <span class="status status-green">Confirmed</span>
                    <span class="status status-amber">Needs confirmation</span>
                    <span class="status status-slate">Ignored</span>
                    <span class="status status-red">Unknown</span>
                    <span class="self-center text-xs font-semibold text-slate-600">Low confidence items always need review.</span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="mapping-sheet" class="form-label">Worksheet</label>
                        <select id="mapping-sheet" x-model="mapping.sheet" @change="changeSheet" class="form-input">
                            <template x-for="sheet in interpretation?.sheets ?? []" :key="sheet.sheet"><option :value="sheet.sheet" x-text="`${sheet.sheet}${sheet.visible ? '' : ' (hidden)'}`"></option></template>
                        </select>
                    </div>
                    <div>
                        <label for="mapping-header-row" class="form-label">Header row</label>
                        <input id="mapping-header-row" type="number" min="1" max="1000" x-model.number="mapping.header_row" class="form-input">
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Detected spreadsheet columns and their proposed meanings</caption>
                        <thead class="bg-slate-50 text-xs font-extrabold uppercase tracking-wide text-slate-600">
                            <tr><th class="px-4 py-3">Source header</th><th class="px-4 py-3">Interpreted meaning</th><th class="px-4 py-3">Product code</th><th class="px-4 py-3">Confidence</th><th class="px-4 py-3">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="column in mapping.columns" :key="column.source_index">
                                <tr>
                                    <th scope="row" class="max-w-56 px-4 py-4 font-bold text-slate-900" x-text="column.original_header"></th>
                                    <td class="min-w-64 px-4 py-4"><label class="sr-only" :for="`role-${column.source_index}`" x-text="`Meaning for ${column.original_header}`"></label><select :id="`role-${column.source_index}`" x-model="column.semantic_role" @change="if (column.semantic_role !== 'PRODUCT_QUANTITY') column.subtype = null" class="form-input mt-0 min-h-11 py-2"><template x-for="role in roleOptions" :key="role.value"><option :value="role.value" x-text="role.label"></option></template></select></td>
                                    <td class="min-w-40 px-4 py-4"><template x-if="column.semantic_role === 'PRODUCT_QUANTITY'"><div><label class="sr-only" :for="`product-${column.source_index}`" x-text="`Product code for ${column.original_header}`"></label><select :id="`product-${column.source_index}`" x-model="column.subtype" class="form-input mt-0 min-h-11 py-2"><option value="">Choose code</option><template x-for="code in productCodes" :key="code"><option :value="code" x-text="code"></option></template></select></div></template><span x-show="column.semantic_role !== 'PRODUCT_QUANTITY'" class="text-slate-400">Not applicable</span></td>
                                    <td class="px-4 py-4"><span class="font-bold" x-text="`${column.score}%`"></span><span class="block text-xs text-slate-600" x-text="confidenceLabel(column.score)"></span></td>
                                    <td class="px-4 py-4"><span class="status" :class="column.semantic_role === 'UNKNOWN' || column.confirmation_needed ? 'status-amber' : (column.semantic_role === 'IGNORE' ? 'status-slate' : 'status-green')" x-text="mappingStatus(column)"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 p-4 lg:hidden">
                    <template x-for="column in mapping.columns" :key="column.source_index">
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3"><h3 class="font-extrabold" x-text="column.original_header"></h3><span class="text-sm font-bold text-slate-600" x-text="`${column.score}%`"></span></div>
                            <label class="form-label mt-4" :for="`mobile-role-${column.source_index}`">Interpreted meaning</label>
                            <select :id="`mobile-role-${column.source_index}`" x-model="column.semantic_role" class="form-input"><template x-for="role in roleOptions" :key="role.value"><option :value="role.value" x-text="role.label"></option></template></select>
                            <div x-show="column.semantic_role === 'PRODUCT_QUANTITY'" class="mt-4"><label class="form-label" :for="`mobile-product-${column.source_index}`">Product code</label><select :id="`mobile-product-${column.source_index}`" x-model="column.subtype" class="form-input"><option value="">Choose code</option><template x-for="code in productCodes" :key="code"><option :value="code" x-text="code"></option></template></select></div>
                            <p class="mt-3 text-sm font-bold text-slate-700" x-text="mappingStatus(column)"></p>
                        </article>
                    </template>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-4"><h3 class="font-extrabold">Windows</h3><p class="mt-2 text-sm text-slate-600">VS, TT, BAY, ALI, AOV, FI</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-4"><h3 class="font-extrabold">Doors</h3><p class="mt-2 text-sm text-slate-600">PSU, PSG, CDF, CDU, CDG, PSP, BF</p><p class="mt-2 text-xs font-bold text-amber-800">BF remains identifiable because it affects lead time.</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-4"><h3 class="font-extrabold">Excluded from customer totals</h3><p class="mt-2 text-sm text-slate-600">CAS, FLU, PFD, GLS, WP, MISC</p></div>
            </div>

            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <p><strong>Call Type checks:</strong> CC! is shown as an unknown possible typo for CC1 and is never corrected automatically. CM1 and CM2 are shown as CML-related revisits, not separate Portal services.</p>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <button type="button" class="secondary-button" @click="step = 2">Back</button>
                <button type="button" class="primary-button" :disabled="busy || !mappingIsComplete()" @click="confirmMapping()"><span x-text="busy ? 'Saving mappings…' : 'Confirm column mappings'"></span></button>
            </div>
        </div>

        {{-- Step 4: Site mapping --}}
        <div x-cloak x-show="step === 4" class="mt-7 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <p class="eyebrow">Step 4</p>
            <h2 class="section-title mt-1">Map Source Sites</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Link each exact source Site Name to an existing Portal site. Sites are never matched automatically by similar names.</p>

            <div class="mt-6 grid gap-4">
                <template x-for="sourceSite in mappedSourceSites" :key="sourceSite.key">
                    <div class="grid gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,1fr)] lg:items-center">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Source Site Name</p>
                            <p class="mt-1 break-words text-lg font-extrabold" x-text="sourceSite.name"></p>
                        </div>
                        <div>
                            <span class="status status-green">Mapped</span>
                            <p class="mt-2 font-bold" x-text="`${sourceSite.portalSite.customer} — ${sourceSite.portalSite.name}`"></p>
                        </div>
                    </div>
                </template>
                <template x-for="(sourceSite, index) in unmappedSourceSites" :key="sourceSite.key">
                    <div class="grid gap-4 rounded-lg border border-slate-200 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,1fr)] lg:items-end">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Source Site Name</p>
                            <p class="mt-1 break-words text-lg font-extrabold" x-text="sourceSite.name"></p>
                            <p class="mt-1 text-xs text-slate-600">Exact source key: <span class="font-mono" x-text="sourceSite.key"></span></p>
                        </div>
                        <div>
                            <span class="status status-amber">Mapping required</span>
                            <label class="form-label" :for="`portal-site-${index}`">Mapped Portal site</label>
                            <select :id="`portal-site-${index}`" x-model="bindingSelections[sourceSite.key]" class="form-input">
                                <option value="">Choose an existing site</option>
                                <template x-for="site in configuration.portalSites" :key="site.uuid"><option :value="site.uuid" x-text="`${site.customer} — ${site.name}`"></option></template>
                            </select>
                            <p x-show="configuration.portalSites.length === 0" class="form-error">No Portal sites with a permanent identifier are available. Ask Backend to correct the site data before continuing.</p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
                A permanent Site ID will become the preferred source identifier once it is added to the export. Until then, this exact Site Name binding is retained for future imports.
            </div>

            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <button type="button" class="secondary-button" @click="step = 3">Back</button>
                <button x-show="unmappedSourceSites.length" type="button" class="primary-button" :disabled="busy" @click="saveSiteMappings"><span x-text="busy ? 'Saving site mappings…' : 'Save site mappings'"></span></button>
                <button x-show="!unmappedSourceSites.length" type="button" class="primary-button" @click="step = 5; announce('Site mappings checked. Review the dry-run preview.')">Continue to dry-run preview</button>
            </div>
        </div>

        {{-- Step 5: Dry-run --}}
        <div x-cloak x-show="step === 5" class="mt-7 space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><p class="eyebrow">Step 5</p><h2 class="section-title mt-1">Dry-Run Preview</h2><p class="mt-2 text-sm leading-6 text-slate-600">These are proposed source-data changes only. Portal workflow history is not being edited.</p></div>
                    <span class="status" :class="preview?.can_commit ? 'status-green' : 'status-red'" x-text="preview?.can_commit ? 'Ready to confirm' : 'Import blocked'"></span>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('NEW')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">New</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('UNCHANGED')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Unchanged</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('UPDATED')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Updated</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('COMPLETED')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Completed</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('COMPLETION_REVERSED')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Completion reversed</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('RECONCILIATION_REQUIRED')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Reconciliation needed</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('SITE_MAPPING_REQUIRED')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Mapping required</span></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="summaryCount('INVALID')"></strong><span class="mt-1 block text-xs font-bold text-slate-600">Invalid</span></div>
                </div>

                <div x-show="!preview?.can_commit" class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-950" role="alert">
                    <p class="font-extrabold">The import cannot be confirmed yet.</p>
                    <p class="mt-1"><span x-text="preview?.blocking_error_count ?? 0"></span> blocking issue(s) must be corrected in the source spreadsheet or site mappings, then analysed again.</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <fieldset>
                    <legend class="font-extrabold text-slate-900">Filter preview rows</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="filter in [{v:'changed',l:'Changed only'},{v:'all',l:'All rows'},{v:'errors',l:'Errors'},{v:'warnings',l:'Warnings'},{v:'mapping',l:'Mapping required'},{v:'completed',l:'Completed'},{v:'reversed',l:'Reversed'}]" :key="filter.v">
                            <label class="cursor-pointer"><input type="radio" x-model="rowFilter" :value="filter.v" class="peer sr-only"><span class="filter-button inline-flex items-center peer-checked:border-sky-700 peer-checked:bg-sky-700 peer-checked:text-white" x-text="filter.l"></span></label>
                        </template>
                    </div>
                </fieldset>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden overflow-x-auto xl:block">
                    <table class="min-w-[78rem] divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Dry-run source import changes</caption>
                        <thead class="bg-slate-50 text-xs font-extrabold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Call No.</th><th class="px-4 py-3">Site / plot</th><th class="px-4 py-3">Call type</th><th class="px-4 py-3">Portal service</th><th class="px-4 py-3">Current source state</th><th class="px-4 py-3">Incoming source state</th><th class="px-4 py-3">Change</th><th class="px-4 py-3">Checks</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="row in filteredRows" :key="`${row.row_number}-${row.call_number}-${row.diff_category}`">
                                <tr class="align-top">
                                    <td class="px-4 py-4 font-mono font-bold" x-text="row.call_number || 'Not supplied'"></td>
                                    <td class="px-4 py-4"><span class="font-bold" x-text="row.source_site_name || row.source_site_key"></span><span class="block text-slate-600" x-text="row.plot_reference || 'No plot reference'"></span></td>
                                    <td class="px-4 py-4"><span class="font-bold" x-text="row.call_type || 'Not supplied'"></span><span x-show="row.call_type === 'CC!'" class="mt-1 block text-xs font-bold text-rose-700">Possible typo — CC1</span></td>
                                    <td class="px-4 py-4" x-text="row.mapped_service || 'Not mapped'"></td>
                                    <td class="max-w-56 px-4 py-4 text-slate-600" x-text="stateSummary(row.current_state)"></td>
                                    <td class="max-w-56 px-4 py-4"><span x-text="stateSummary(row.incoming_state)"></span><span class="mt-2 block text-xs text-slate-600" x-text="positiveProducts(row.products)"></span></td>
                                    <td class="px-4 py-4"><span class="status" :class="`status-${categoryTone(row.diff_category) === 'emerald' ? 'green' : (categoryTone(row.diff_category) === 'rose' ? 'red' : categoryTone(row.diff_category))}`" x-text="categoryLabel(row.diff_category)"></span></td>
                                    <td class="max-w-64 px-4 py-4"><ul class="space-y-2"><template x-for="message in rowMessages(row)" :key="`${message.code}-${message.message}`"><li class="text-xs" :class="message.blocking ? 'font-bold text-rose-800' : 'text-amber-900'" x-text="message.message"></li></template></ul><span x-show="!(row.errors?.length || row.warnings?.length)" class="text-slate-500">No issues</span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 p-4 xl:hidden">
                    <template x-for="row in filteredRows" :key="`${row.row_number}-${row.call_number}-${row.diff_category}`">
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-sm font-bold" x-text="row.call_number || 'No Call No.'"></p><h3 class="mt-1 text-lg font-extrabold" x-text="`${row.source_site_name || row.source_site_key} — ${row.plot_reference || 'No plot'}`"></h3></div><span class="status" :class="`status-${categoryTone(row.diff_category) === 'emerald' ? 'green' : (categoryTone(row.diff_category) === 'rose' ? 'red' : categoryTone(row.diff_category))}`" x-text="categoryLabel(row.diff_category)"></span></div>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="font-semibold text-slate-500">Call Type</dt><dd class="mt-1 font-bold" x-text="row.call_type || 'Not supplied'"></dd><dd x-show="row.call_type === 'CC!'" class="text-xs font-bold text-rose-700">Possible typo — CC1</dd></div><div><dt class="font-semibold text-slate-500">Portal service</dt><dd class="mt-1 font-bold" x-text="row.mapped_service || 'Not mapped'"></dd></div><div><dt class="font-semibold text-slate-500">Current source state</dt><dd class="mt-1" x-text="stateSummary(row.current_state)"></dd></div><div><dt class="font-semibold text-slate-500">Incoming source state</dt><dd class="mt-1" x-text="stateSummary(row.incoming_state)"></dd></div><div class="sm:col-span-2"><dt class="font-semibold text-slate-500">Product quantities</dt><dd class="mt-1" x-text="positiveProducts(row.products)"></dd></div></dl>
                            <ul x-show="row.errors?.length || row.warnings?.length" class="mt-4 space-y-2 border-t border-slate-200 pt-4"><template x-for="message in rowMessages(row)" :key="`${message.code}-${message.message}`"><li class="text-sm" :class="message.blocking ? 'font-bold text-rose-800' : 'text-amber-900'" x-text="message.message"></li></template></ul>
                        </article>
                    </template>
                </div>

                <div x-show="filteredRows.length === 0" class="empty-state m-4"><h3 class="font-extrabold">No rows match this filter</h3><p class="mt-1 text-sm">Choose another filter to review the preview.</p></div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <button type="button" class="secondary-button" @click="cancelPreview">Cancel preview</button>
                <button type="button" class="primary-button" :disabled="!preview?.can_commit" @click="goToConfirmation">Continue to confirmation</button>
            </div>
        </div>

        {{-- Step 6: Confirmation --}}
        <div x-cloak x-show="step === 6" class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <p class="eyebrow">Step 6</p>
                <h2 class="section-title mt-1">Confirm Import</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Review this summary carefully. The server will recheck the spreadsheet before applying any changes.</p>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">File</dt><dd class="mt-1 break-all font-bold" x-text="preview?.metadata?.original_filename"></dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">Rows</dt><dd class="mt-1 font-bold" x-text="preview?.metadata?.row_count ?? 0"></dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">Mapped source sites</dt><dd class="mt-1 font-bold" x-text="detectedSourceSites.length"></dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-semibold text-slate-600">Scope</dt><dd class="mt-1 font-bold" x-text="preview?.import_scope?.value === 'PARTIAL_FILTERED_EXPORT' ? 'Filtered / Partial Export' : preview?.import_scope?.label"></dd></div>
                </dl>

                <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <summary class="min-h-11 cursor-pointer py-2 font-bold text-slate-800">File verification details</summary>
                    <p class="mt-2 text-slate-600">SHA-256 fingerprint</p>
                    <p class="mt-1 break-all font-mono text-xs font-bold text-slate-800" x-text="preview?.metadata?.sha256"></p>
                </details>

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-3"><strong class="text-2xl" x-text="summaryCount('NEW')"></strong><span class="block text-xs font-bold text-slate-600">New</span></div>
                    <div class="rounded-lg border border-slate-200 p-3"><strong class="text-2xl" x-text="summaryCount('UPDATED')"></strong><span class="block text-xs font-bold text-slate-600">Updated</span></div>
                    <div class="rounded-lg border border-slate-200 p-3"><strong class="text-2xl" x-text="summaryCount('COMPLETED')"></strong><span class="block text-xs font-bold text-slate-600">Completed</span></div>
                    <div class="rounded-lg border border-slate-200 p-3"><strong class="text-2xl" x-text="warningCount"></strong><span class="block text-xs font-bold text-slate-600">Warnings</span></div>
                </div>

                <label class="mt-7 flex min-h-16 cursor-pointer items-start gap-3 rounded-lg border border-sky-300 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
                    <input type="checkbox" x-model="importConfirmed" class="mt-0.5 h-5 w-5 rounded border-sky-500 text-sky-700 focus:ring-sky-600">
                    <span><strong class="block">I have reviewed this preview and want to confirm the import</strong><span class="mt-1 block">This will update the Customer Portal's imported source data. Customer call-off and date-negotiation history will not be overwritten.</span></span>
                </label>

                <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <button type="button" class="secondary-button" @click="step = 5">Back to preview</button>
                    <button type="button" class="primary-button" :disabled="busy || !preview?.can_commit || !importConfirmed" @click="commit"><span x-text="busy ? 'Importing…' : 'Confirm Import'"></span></button>
                </div>
            </div>

            <aside class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                <h2 class="text-lg font-extrabold">Final check</h2>
                <p class="mt-3">Blocking errors: <strong x-text="preview?.blocking_error_count ?? 0"></strong></p>
                <p class="mt-2">Warnings: <strong x-text="warningCount"></strong></p>
                <p class="mt-2">Missing-source records: <strong x-text="summaryCount('MISSING_FROM_SOURCE')"></strong></p>
                <p class="mt-4 border-t border-amber-300 pt-4">The uploaded workbook is checked again at confirmation. If it or the Portal source data has changed, the import will stop safely.</p>
            </aside>
        </div>

        {{-- Step 7: Results --}}
        <div x-cloak x-show="step === 7" class="mt-7 space-y-6">
            <div class="rounded-xl border p-5 shadow-sm sm:p-7" :class="result?.status === 'failed' ? 'border-rose-300 bg-rose-50' : 'border-emerald-300 bg-emerald-50'">
                <div class="flex gap-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-2xl font-extrabold" :class="result?.status === 'failed' ? 'bg-rose-700 text-white' : 'bg-emerald-700 text-white'" aria-hidden="true" x-text="result?.status === 'failed' ? '!' : '✓'"></span>
                    <div><p class="eyebrow">Step 7</p><h2 class="section-title mt-1" x-text="result?.status === 'failed' ? 'Import failed' : 'Import complete'"></h2><p class="mt-2 text-sm leading-6" x-text="result?.status === 'failed' ? 'No successful import was recorded. Review the result before trying again.' : 'The source data was imported and the audit result is ready.'"></p></div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Import result sections">
                <button type="button" role="tab" :aria-selected="resultTab === 'summary'" class="filter-button" :class="resultTab === 'summary' ? 'filter-button-active' : ''" @click="resultTab = 'summary'">Import Summary</button>
                <button type="button" role="tab" :aria-selected="resultTab === 'reconciliation'" class="filter-button" :class="resultTab === 'reconciliation' ? 'filter-button-active' : ''" @click="resultTab = 'reconciliation'">View Reconciliation <span x-text="`(${result?.reconciliation?.length ?? 0})`"></span></button>
            </div>

            <div x-show="resultTab === 'summary'" role="tabpanel" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-sm font-semibold text-slate-600">Source run reference</dt><dd class="mt-1 break-all font-mono text-sm font-bold" x-text="result?.result_uuid"></dd></div>
                    <div><dt class="text-sm font-semibold text-slate-600">Imported at</dt><dd class="mt-1 font-bold" x-text="formatDateTime(result?.finished_at)"></dd></div>
                    <div><dt class="text-sm font-semibold text-slate-600">Initiated by</dt><dd class="mt-1 font-bold" x-text="configuration.officeUserName"></dd></div>
                    <div><dt class="text-sm font-semibold text-slate-600">Scope</dt><dd class="mt-1 font-bold" x-text="result?.import_scope?.label"></dd></div>
                </dl>

                <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                    <template x-for="item in [{k:'seen',l:'Processed'},{k:'created',l:'New'},{k:'updated',l:'Updated'},{k:'unchanged',l:'Unchanged'},{k:'missing',l:'Missing'},{k:'rejected',l:'Rejected'},{k:'reconciliation',l:'Reconciliation'}]" :key="item.k"><div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><strong class="block text-2xl" x-text="result?.counts?.[item.k] ?? 0"></strong><span class="text-xs font-bold text-slate-600" x-text="item.l"></span></div></template>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:max-w-md">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3"><strong class="block text-2xl" x-text="resultCategoryCount('COMPLETED')"></strong><span class="text-xs font-bold text-emerald-900">Completed</span></div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3"><strong class="block text-2xl" x-text="resultCategoryCount('COMPLETION_REVERSED')"></strong><span class="text-xs font-bold text-amber-900">Completion reversed</span></div>
                </div>
            </div>

            <div x-show="resultTab === 'reconciliation'" role="tabpanel" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="section-title">Reconciliation</h2>
                <p class="mt-2 text-sm text-slate-600">Internal source checks from this import. These are not shown to customers.</p>
                <div class="mt-5 grid gap-4">
                    <template x-for="issue in result?.reconciliation ?? []" :key="issue.issue_uuid">
                        <article class="rounded-lg border p-4" :class="issue.severity === 'error' ? 'border-rose-300 bg-rose-50' : 'border-amber-300 bg-amber-50'">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-extrabold uppercase tracking-wide" :class="issue.severity === 'error' ? 'text-rose-800' : 'text-amber-800'" x-text="issue.severity"></p><h3 class="mt-1 font-extrabold" x-text="issue.message"></h3></div><span class="status" :class="issue.resolved ? 'status-green' : (issue.severity === 'error' ? 'status-red' : 'status-amber')" x-text="issue.resolved ? 'Resolved' : 'Open'"></span></div>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt class="font-semibold text-slate-600">Call No.</dt><dd class="mt-1 font-bold" x-text="issue.call_number || 'Not available'"></dd></div><div><dt class="font-semibold text-slate-600">Site</dt><dd class="mt-1 font-bold" x-text="issue.source_site_key || 'Not available'"></dd></div><div><dt class="font-semibold text-slate-600">Plot</dt><dd class="mt-1 font-bold" x-text="issue.plot_reference || 'Not available'"></dd></div><div><dt class="font-semibold text-slate-600">Source row</dt><dd class="mt-1 font-bold" x-text="issue.source_row_number || 'Not available'"></dd></div></dl>
                        </article>
                    </template>
                </div>
                <div x-show="!(result?.reconciliation?.length)" class="empty-state mt-5"><h3 class="font-extrabold">No reconciliation issues</h3><p class="mt-1 text-sm">This import did not create any internal source checks.</p></div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="secondary-button" @click="reset">Import Another File</button>
                <a :href="configuration.reviewUrl" class="primary-button">Return to Office Review</a>
            </div>
        </div>
    </section>
</x-layouts.portal>
