<x-layouts.portal title="Paired workbook analysis | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace min-w-0 [overflow-wrap:anywhere]">
        <header><p class="eyebrow">Office only · Analyse only</p><h1 class="admin-title">Master inventory and call-off report</h1><p class="admin-intro">Compare the full plot register with call-off activity. This report does not create or change customers, sites, plots, services, bindings or Portal history.</p></header>
        <section class="admin-card mt-4">
            <h2 class="section-title">Choose both source workbooks</h2>
            <p class="mt-2 text-sm text-slate-600">Upload the master plot register and the matching call-off report together. Each XLS is checked independently and its fingerprint is shown in the report.</p>
            @if($errors->any())<div role="alert" class="mt-4 rounded-xl border border-rose-300 bg-rose-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <form method="POST" action="{{ route('office.workspace.imports.paired-analyse.run') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
                <div><label for="master-register" class="form-label">Master plot register (.xls or .xlsx)</label><input id="master-register" name="master_register" type="file" accept=".xls,.xlsx" required class="form-input mt-2 min-w-0"><p class="mt-2 text-sm text-slate-600">Maximum 5 MB.</p></div>
                <div><label for="call-off-report" class="form-label">Call-off report (.xls or .xlsx)</label><input id="call-off-report" name="call_off_report" type="file" accept=".xls,.xlsx" required class="form-input mt-2 min-w-0"><p class="mt-2 text-sm text-slate-600">Maximum 5 MB.</p></div>
                <div class="md:col-span-2 flex flex-col gap-3 sm:flex-row"><button type="submit" class="primary-button">Analyse both workbooks</button><a class="secondary-button" href="{{ route('office.workspace.imports') }}">Back to imports</a></div>
            </form>
        </section>
        @if($report)
            @php($content = $report['content'])
            @php($summary = $content['summary'])
            <section class="admin-card mt-4" aria-labelledby="paired-summary-title">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="paired-summary-title" class="section-title">Reconciliation summary</h2><p class="mt-2 text-sm">The master table’s Customer Number prefix in column AA matches the call-off report’s Customer Number exactly; the suffix is the Plot Ref. Conflicting hierarchy evidence is held for review. All valid plots on a qualifying site are retained, including plots without call-offs.</p></div><span class="status status-slate">No data applied</span></div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach(['master_source_sites' => 'Master sites', 'qualifying_sites' => 'Sites with call-offs', 'zero_call_sites_excluded' => 'Zero-call sites excluded', 'sites_held_for_review' => 'Sites held for review', 'master_plots_on_qualifying_sites' => 'Master plots retained', 'qualifying_plots_with_calloffs' => 'Plots with call-offs', 'qualifying_plots_without_calloffs' => 'Inventory-only plots', 'call_only_plots_for_review' => 'Call-off plots missing from master', 'call_off_only_sites_held_for_review' => 'Call-off-only sites held for review', 'product_conflicts_for_review' => 'Product conflicts to review'] as $key => $label)
                        <div><dt class="text-sm text-slate-600">{{ $label }}</dt><dd class="mt-1 text-xl font-bold">{{ number_format($summary[$key]) }}</dd></div>
                    @endforeach
                </dl>
                @if($summary['call_off_only_sites_held_for_review'] > 0)<p class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm">A Customer Number appears in call-off activity but has no master-register site. It is held for review; no inventory is created for it.</p>@endif
                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    @foreach(['master' => 'Master register', 'call_off' => 'Call-off report'] as $key => $label)
                        <div class="rounded-xl border border-slate-200 p-4"><h3 class="font-semibold">{{ $label }}</h3><p class="mt-1 text-sm">{{ number_format($content['workbooks'][$key]['bytes']) }} bytes · {{ number_format($content['workbooks'][$key]['worksheet_rows']) }} observed rows · {{ $content['workbooks'][$key]['sheet'] }}</p><p class="mt-2 break-all font-mono text-xs">SHA-256 {{ $content['workbooks'][$key]['sha256'] }}</p><p class="mt-1 text-xs text-slate-600">Table {{ $content['workbooks'][$key]['table'] }}</p></div>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-slate-600">Report fingerprint: <span class="break-all font-mono">{{ $report['fingerprint'] }}</span></p>
            </section>
            @if($content['verified_examples'])
                <section class="admin-card mt-4"><h2 class="section-title">Matched source examples</h2><p class="mt-2 text-sm text-slate-600">These rows share the exact Customer Number and normalized Plot Ref; customer/site hierarchy text is checked separately for conflicts.</p><ul class="mt-3 space-y-2">@foreach($content['verified_examples'] as $example)<li class="rounded-lg border border-slate-200 p-3">Customer Number {{ $example['customer_code'] }} · Plot {{ $example['plot_ref'] }} · master row {{ $example['master_row'] }} · call-off rows {{ implode(', ', $example['call_rows']) }}</li>@endforeach</ul></section>
            @endif
            @if($content['product_conflicts'])
                <section class="admin-card mt-4"><h2 class="section-title">Conflicting product evidence</h2><p class="mt-2 text-sm">Conflicting explicit values are shown without selecting a winner. Each quantity is paired with its source row and Call No. Showing at most 100 conflicts in this page.</p><ul class="mt-3 space-y-2">@foreach(array_slice($content['product_conflicts'], 0, 100) as $conflict)<li class="rounded-lg border border-amber-300 bg-amber-50 p-3">Customer Number {{ $conflict['customer_code'] }} · Plot {{ $conflict['plot_ref'] }} · {{ $conflict['product_code'] }}<ul class="ml-4 mt-1 list-disc">@foreach($conflict['assertions'] as $assertion)<li>{{ $assertion['source'] }} row {{ $assertion['row'] }} · quantity {{ $assertion['quantity'] }}@if($assertion['call']) · Call {{ $assertion['call'] }}@endif</li>@endforeach</ul></li>@endforeach</ul></section>
            @endif
            <section class="mt-4 space-y-3" aria-labelledby="paired-sites-title"><h2 id="paired-sites-title" class="section-title">Sites and plot inventory</h2>
                    @foreach($content['sites'] as $site)
                    <details class="admin-card"><summary class="cursor-pointer rounded-lg font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><span>{{ $site['customer_code'] ?? $site['master_site_reference'] ?? 'Source identity unresolved' }}</span> · {{ $site['customer'] ?? 'Hierarchy pending' }} / {{ $site['site'] ?? 'Hierarchy pending' }} <span class="ml-2 text-sm font-normal">{{ str($site['status'])->replace('_', ' ')->lower()->ucfirst() }}</span></summary>
                        @if($site['master_source_site_names'] || $site['call_off_source_site_names'])<p class="mt-3 text-sm text-slate-600">Master heading: {{ implode(' · ', $site['master_source_site_names']) ?: 'none' }}<br>Call-off Site Name: {{ implode(' · ', $site['call_off_source_site_names']) ?: 'none' }}</p>@endif
                        <p class="mt-3 text-sm">{{ number_format($site['master_plot_count']) }} master plots · {{ number_format($site['plots_with_calloffs']) }} with calls · {{ number_format($site['plots_without_calloffs']) }} inventory-only · {{ number_format($site['call_only_plot_count']) }} unmatched call-off plots · {{ number_format($site['call_count']) }} Call No rows</p>
                        @if($site['issues'])<div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3"><p class="font-semibold">{{ $site['status'] === 'REVIEW' ? 'Customer Number held for review' : 'Source rows need review' }}</p><p class="mt-1 text-sm">{{ $site['status'] === 'REVIEW' ? 'Confirm the conflicting identity or hierarchy before treating this site as eligible.' : 'These row-level findings do not change the site activity decision.' }}</p><ul class="mt-2 list-disc pl-5 text-sm">@foreach($site['issues'] as $kind => $items)<li>{{ str($kind)->replace('_', ' ')->title() }}: {{ count($items) }}</li>@endforeach</ul></div>@endif
                        @if($site['plots'])<div class="mt-3 overflow-x-auto"><table class="w-full min-w-[30rem] text-left text-sm"><thead><tr><th class="p-2">Plot Ref</th><th class="p-2">Inventory status</th><th class="p-2">Source rows</th></tr></thead><tbody>@foreach(array_slice($site['plots'], 0, 100) as $plot)<tr class="border-t"><td class="p-2">{{ $plot['plot_ref'] }}</td><td class="p-2">{{ $plot['has_calloff'] ? 'Call-off evidence' : 'Inventory only' }}</td><td class="p-2">Master {{ $plot['master_row'] }}@if($plot['call_rows']) · Calls {{ implode(', ', $plot['call_rows']) }}@endif</td></tr>@endforeach</tbody></table>@if(count($site['plots']) > 100)<p class="mt-2 text-xs text-slate-600">Showing first 100 of {{ number_format(count($site['plots'])) }} plots for this site.</p>@endif</div>@endif
                    </details>
                @endforeach
            </section>
            <p class="mt-4 text-sm text-slate-600">This is an Analyse-only report. Existing source bindings, customers, sites, plots, services, Portal dates and history remain unchanged. Grouped Apply/Commit is unavailable.</p>
        @endif
    </div>
</x-layouts.portal>
