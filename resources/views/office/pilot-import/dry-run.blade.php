<x-layouts.portal title="Analyse only | Fenster Customer Portal" sidebar-label="Menu">
    <style>@media print { aside, body > div > header, .dry-run-controls { display: none !important; } body > .min-h-screen, main, .admin-workspace { margin: 0 !important; padding: 0 !important; } article { break-inside: avoid; } }</style>
    <div class="admin-workspace min-w-0 [overflow-wrap:anywhere]">
        <header><p class="eyebrow">Office only</p><h1 class="admin-title">Analyse only — no changes applied</h1><p class="admin-intro">Check a workbook against current CustomerApp records. Customers, sites, plots, products, services, requests and source bindings stay unchanged.</p></header>
        <section class="admin-card dry-run-controls">
            <h2 class="section-title">Run a dry run</h2><p class="mt-2 text-sm text-slate-600">Choose the workbook each time. The analysis uses current rules and records; it does not save an import or approve an Apply action. The temporary private copy is removed after analysis.</p>
            @if($errors->any())<div role="alert" class="mt-4 rounded-xl border border-rose-300 bg-rose-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <form method="POST" action="{{ route('office.workspace.imports.dry-run.analyse') }}" enctype="multipart/form-data" class="mt-5 space-y-4">@csrf
                <div><label for="dry-run-workbook" class="form-label">RedZebra workbook</label><input id="dry-run-workbook" type="file" name="workbook" required accept=".xls,.xlsx,.csv" class="form-input mt-2 min-w-0"><p class="mt-2 text-sm">XLS, XLSX or CSV; maximum 20 MB.</p></div>
                <div class="flex flex-col gap-3 sm:flex-row"><button type="submit" class="primary-button">Analyse without applying</button><a class="secondary-button" href="{{ route('office.workspace.imports') }}">Normal import review and Apply</a></div>
            </form>
        </section>
        @if($report)
            @php($summary = $report['content']['summary'])
            <p class="mt-3 text-sm">{{ $report['content']['capabilities']['call_type'] ? 'Plot register with optional source call-off data.' : 'Plot register only: this export has no Call Type column.' }} Counts describe this export; missing call data does not reverse previously recorded call-offs.</p>
            <section class="admin-card" aria-labelledby="dry-run-report-title">
                <div class="flex flex-wrap justify-between gap-3"><h2 id="dry-run-report-title" class="section-title">Dry-run report</h2><span class="status status-slate">No data applied</span></div>
                <p class="mt-3 text-sm">{{ $summary['automatic'] }} / {{ $summary['source_units'] }} source units resolved automatically with no detected blockers. {{ $summary['manual_review'] }} need Office review; {{ $summary['blocking_units'] }} have blocking issues.</p>
                <p class="mt-2 text-sm">This is an inspection report, not an Apply approval. A normal import must still pass clarification, current binding, source ordering, preview and explicit approval checks.</p>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach(['plots_discovered' => 'Plots discovered', 'base_plot_rows' => 'Rows with no call-off assertion', 'plots_without_source_calloff_in_export' => 'Plots with no source call-off in this export', 'recognised_calloff_rows' => 'Recognised call-off rows', 'unknown_call_type_rows' => 'Unknown nonblank Call Types', 'total_rows' => 'Total rows', 'included' => 'Included rows', 'excluded' => 'Excluded rows', 'source_units' => 'Source units', 'unknown_rows' => 'Unknown rows', 'malformed_groups' => 'Malformed groups', 'hierarchy_conflicts' => 'Hierarchy conflicts', 'existing_bindings' => 'Existing exact bindings', 'exact_matches_without_binding' => 'Exact matches needing a binding', 'new_site_proposals' => 'New site proposals', 'new_customer_site_proposals' => 'New customer and site proposals', 'plots_create' => 'Plot create candidates', 'plots_reuse' => 'Plot reuse candidates'] as $key => $label)
                        <div><dt class="text-sm text-slate-600">{{ $label }}</dt><dd class="mt-1 text-xl font-bold">{{ number_format($summary[$key]) }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-sm">Analysis: {{ number_format($report['metrics']['duration_ms']) }} ms · Peak process memory: {{ number_format($report['metrics']['peak_memory_bytes'] / 1048576, 1) }} MB.</p>
                <p class="mt-2 text-xs text-slate-600">Report fingerprint: {{ $report['fingerprint'] }}</p>
                <button type="button" x-data @click="window.print()" class="secondary-button mt-4 dry-run-controls">Print or save report as PDF</button>
            </section>
            <section class="admin-card"><h2 class="section-title">Binding integrity</h2><p class="mt-2">{{ $report['content']['integrity_summary']['healthy'] }} healthy · {{ $report['content']['integrity_summary']['warnings'] }} warnings · {{ $report['content']['integrity_summary']['blocking'] }} blocking issues across retained bindings.</p><a class="secondary-button mt-4 dry-run-controls" href="{{ route('office.workspace.settings.wald.integrity') }}">Open integrity check</a></section>
            <section class="space-y-4"><h2 class="section-title">Source units</h2>
                @foreach($report['content']['units'] as $unit)
                    <article class="admin-card min-w-0"><div class="flex flex-wrap justify-between gap-3"><h3 class="section-title">CustomerCode {{ $unit['customer_code'] }}</h3><span class="status {{ $unit['issues'] ? 'status-red' : 'status-slate' }}">{{ str($unit['state'])->replace('_', ' ')->title() }}</span></div>
                        <p class="mt-3">{{ $unit['customer'] ?? 'Customer unresolved' }} / {{ $unit['site'] ?? 'Site unresolved' }}</p><p class="mt-2 text-sm">{{ $unit['rows'] }} rows · {{ $unit['plot_count'] }} plot candidates · {{ $unit['plots_create'] }} create · {{ $unit['plots_reuse'] }} reuse.</p>
                        @if($unit['issues'])<div class="mt-3 rounded-xl border border-rose-300 bg-rose-50 p-4"><p class="font-semibold">Needs review before Apply</p><ul class="mt-2 list-disc pl-5">@foreach($unit['issues'] as $issue)<li>{{ str($issue)->replace('_', ' ')->lower()->ucfirst() }}.</li>@endforeach</ul></div>@else<p class="mt-3 font-semibold">No blocker detected by this dry run. Normal Apply checks still required.</p>@endif
                        @foreach($unit['integrity'] as $integrity)@if($integrity['category'] !== 'HEALTHY')<p class="mt-2 text-sm">Binding: {{ str($integrity['category'])->replace('_', ' ')->title() }} — {{ $integrity['reason'] }}</p>@endif @endforeach
                    </article>
                @endforeach
            </section>
            @if($report['content']['unknown_rows'])<section class="admin-card"><h2 class="section-title">Unknown / unclassified rows</h2><ul class="mt-3 space-y-2">@foreach($report['content']['unknown_rows'] as $row)<li>Source row {{ $row['row_number'] }} · Call {{ $row['call_no'] }} · {{ str($row['issue'])->replace('_', ' ')->lower()->ucfirst() }}.</li>@endforeach</ul><p class="mt-3 text-sm">These rows remain outside projection.</p></section>@endif
            <p class="text-sm text-slate-600">Every export is partial. Absent rows, sites, visits or quantities have no deletion or reversal effect. This report never assigns users or changes customer dates/history.</p>
        @endif
    </div>
</x-layouts.portal>
