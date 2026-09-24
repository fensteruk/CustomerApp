<x-layouts.portal title="CustomerCode review | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.pilot-import.detail-styles')
    <style>
        .wald-review-grid { display:grid; gap:20px; }
        .wald-review-target { order:-1; }
        .wald-review-desktop-action { display:none; }
        @media(min-width:1100px) {
            .wald-review-grid { grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr); align-items:start; }
            .wald-review-target { order:0; position:sticky; top:16px; }
            .wald-review-mobile-action { display:none; }
            .wald-review-desktop-action { display:block; }
        }
    </style>
    <div class="admin-workspace wald-detail">
        <a class="admin-back" href="{{ route('office.workspace.pilot-import.show', $overview['import']['upload']) }}">← Back to import review</a>
        <header class="wald-heading"><div><p class="eyebrow">RedZebra master export · Revision {{ $overview['import']['revision'] }}</p><h1 class="admin-title">Confirm source rows</h1><p class="page-intro">Review one CustomerCode at a time. All rows start selected; untick exceptions for the Unknown queue.</p></div></header>
        @if($errors->any())<div class="wald-notice wald-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if(session('status'))<div class="wald-notice" role="status">{{ session('status') }}</div>@endif
        <section class="wald-panel" aria-label="CustomerCode review progress">
            <p><strong>{{ $overview['reviewed_count'] }} reviewed</strong> · {{ count($overview['pending']) }} remaining · {{ $overview['total_manual'] }} groups need Office review</p>
            <p>{{ count($overview['automatic']) }} fully resolved groups were skipped automatically. <a class="underline" href="{{ route('office.workspace.pilot-import.show', $overview['import']['upload']) }}#detected-sites-title">Inspect all source sites</a>.</p>
            <progress class="w-full" max="{{ max(1, $overview['total_manual']) }}" value="{{ $overview['reviewed_count'] }}" aria-label="CustomerCode groups reviewed"></progress>
        </section>
        @if($current)
            @php($import = $overview['import'])
            @php($source = $current)
            @php($resolution = $current['resolution'])
            @php($selected = false)
            @php($reviewMode = true)
            @php($position = collect($overview['manual'])->search(fn (array $item): bool => $item['hash'] === $current['hash']) + 1)
            @php($previous = $position > 1 ? $overview['manual'][$position - 2] : null)
            @php($initialCustomer = $resolution['customer_uuid'] ?? null)
            @php($initialSite = $resolution['site_uuid'] ?? ($current['binding']['site_uuid'] ?? null))
            @php($rowData = $rows->map(fn (object $row): array => ['row' => (int) $row->row_number, 'code' => $row->customer_code, 'raw' => $row->raw_plot_ref, 'type' => $row->call_type, 'parsed_customer' => $row->parsed_customer, 'parsed_site' => $row->parsed_site, 'parsed_plot' => $row->parsed_plot, 'suggestion' => (new App\SourceImport\Integration\HierarchySuggestion)->forIssue((string) $row->customer_code, $row->raw_plot_ref), 'issue' => $row->issue, 'excluded' => $row->issue === 'OFFICE_UNTICKED'])->all())
            @php($customerData = $customers->map(fn (object $customer): array => ['id' => $customer->id, 'uuid' => $customer->uuid, 'name' => $customer->name])->all())
            @php($siteData = $sites->map(fn (object $site): array => ['uuid' => $site->uuid, 'name' => $site->name, 'customer_id' => $site->customer_organisation_id])->all())
            <section class="wald-panel" aria-labelledby="code-title">
                <p class="eyebrow">CustomerCode {{ $position }} of {{ $overview['total_manual'] }}</p>
                <h2 id="code-title" class="section-title">{{ $current['customer_code'] }} <span class="text-base font-normal">· {{ $rows->count() }} source rows</span></h2>
                <p>Wald found: <strong>{{ str_replace('_', ' ', $resolution['state']) }}</strong>. Confirm an exact Customer and Site once for the selected rows. A row whose plot remains unclear will wait in the Unknown queue.</p>
                @if($previous && $previous['reviewed'])<a class="secondary-button mt-3" href="{{ route('office.workspace.pilot-import.customer-codes.review', ['upload' => $import['upload'], 'source' => $previous['hash']]) }}">← Previous reviewed CustomerCode</a>@endif
                @if(in_array($resolution['state'], ['NEW_CUSTOMER_AND_SITE', 'EXACT_CUSTOMER_NEW_SITE'], true))
                    @include('office.pilot-import.proposal-approval')
                @endif
                <details class="wald-disclosure"><summary>Cannot choose a safe Customer and Site yet?</summary>
                    <p>Send this whole CustomerCode to the final Unknown queue. Its rows stay outside site preview and Apply until reviewed individually.</p>
                    <form method="POST" action="{{ route('office.workspace.pilot-import.customer-codes.defer', ['upload' => $import['upload'], 'sourceHash' => $current['hash']]) }}">
                        @csrf
                        <input type="hidden" name="source_manifest_hash" value="{{ $import['source_manifest_hash'] }}">
                        <input type="hidden" name="expected_epoch" value="{{ $import['epoch'] }}">
                        <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                        <label class="wald-check"><input type="checkbox" name="confirmation" value="SEND CUSTOMER CODE TO UNKNOWN" required><span>I cannot confirm a safe target for this CustomerCode now.</span></label>
                        <button type="submit" class="secondary-button">Send all rows to Unknown &amp; continue</button>
                    </form>
                </details>
            </section>
            <div x-data="reviewWizard(@js($rowData), @js($customerData), @js($siteData), @js($initialCustomer), @js($initialSite))" class="wald-review-grid">
                <section class="wald-panel" aria-labelledby="rows-title">
                    <div class="wald-section-heading"><div><h2 id="rows-title" class="section-title">Source rows for {{ $current['customer_code'] }}</h2><p x-text="`${selectedCount} of ${rows.length} selected`" aria-live="polite"></p></div><div class="wald-links"><button type="button" class="secondary-button" x-on:click="selectAll()">Select all</button><button type="button" class="secondary-button" x-on:click="clearAll()">Clear all</button></div></div>
                    <div class="space-y-2">
                        <template x-for="row in pageRows" :key="row.row">
                            <article class="rounded-lg border border-slate-200 p-3 sm:grid sm:grid-cols-[auto_65px_minmax(0,1fr)_minmax(0,0.7fr)] sm:items-start sm:gap-3">
                                <label class="flex items-center gap-2"><input type="checkbox" :aria-label="`Include source row ${row.row}, CustomerCode ${row.code}`" :checked="!excluded.has(row.row)" x-on:change="setSelected(row.row, $event.target.checked)" class="h-5 w-5"><span class="sm:sr-only">Select row <span x-text="row.row"></span></span></label>
                                <span class="text-xs font-semibold text-slate-500">Row <span x-text="row.row"></span></span>
                                <div class="break-words"><strong x-text="row.code"></strong><p x-text="row.raw || 'Blank Plot Ref'" class="text-sm"></p><small x-text="row.type ? `Call Type ${row.type}` : 'Blank Call Type'"></small></div>
                                <div class="break-words"><span class="font-semibold" x-text="proposal(row).plot ? `Plot ${proposal(row).plot}` : 'Needs manual plot review'"></span><p class="text-xs text-slate-600" x-text="proposal(row).issue || 'Ready for normal site review'"></p></div>
                            </article>
                        </template>
                    </div>
                    <nav class="wald-links" aria-label="Source row pages" x-show="pageCount > 1"><button type="button" class="secondary-button" x-on:click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous rows</button><span x-text="`Page ${page} of ${pageCount}`" class="self-center"></span><button type="button" class="secondary-button" x-on:click="page = Math.min(pageCount, page + 1)" :disabled="page === pageCount">Next rows</button></nav>
                </section>
                <section class="wald-panel wald-review-target" aria-labelledby="target-title">
                    <h2 id="target-title" class="section-title">Assign Customer and Site</h2>
                    <p class="text-sm">Exact matches are preselected. New structures use the existing approval above before confirmation.</p>
                    <form id="wald-review-form" method="POST" action="{{ route('office.workspace.pilot-import.customer-codes.confirm', ['upload' => $import['upload'], 'sourceHash' => $current['hash']]) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="source_manifest_hash" value="{{ $import['source_manifest_hash'] }}">
                        <input type="hidden" name="expected_epoch" value="{{ $import['epoch'] }}">
                        <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="excluded_rows_csv" :value="Array.from(excluded).sort((a,b) => a-b).join(',')">
                        <label class="form-label" for="review-customer">Customer</label>
                        <select id="review-customer" class="form-input" name="customer_uuid" x-model="customer" x-on:change="site = ''" required><option value="">Choose customer</option>@foreach($customers as $customer)<option value="{{ $customer->uuid }}">{{ $customer->name }}</option>@endforeach</select>
                        <label class="form-label" for="review-site">Site</label>
                        <select id="review-site" class="form-input" name="site_uuid" x-model="site" required><option value="">Choose site</option><template x-for="item in availableSites" :key="item.uuid"><option :value="item.uuid" x-text="item.name"></option></template></select>
                        <div class="wald-preview" role="status" aria-live="polite"><h3 class="wald-subtitle">What will happen</h3><p><strong x-text="selectedCount"></strong> rows selected · <strong x-text="safeCount"></strong> proposed plots · <strong x-text="reviewCount"></strong> rows to Unknown review</p><p class="text-sm">Proposed plots: <span x-text="proposedPlots"></span></p><p class="text-sm">No plot or service is created by this confirmation.</p></div>
                        <label class="wald-check"><input type="checkbox" name="confirmation" value="CONFIRM CUSTOMER CODE ROWS" required><span>I have checked this CustomerCode's target and selected rows.</span></label>
                        <button class="primary-button w-full wald-review-desktop-action" type="submit">Confirm &amp; Next CustomerCode →</button>
                    </form>
                </section>
            </div>
            <div class="wald-review-mobile-action wald-action-bar"><span>After checking the selected rows, confirm this CustomerCode.</span><button class="primary-button" type="submit" form="wald-review-form">Confirm &amp; Next CustomerCode →</button></div>
        @else
            <section class="wald-panel"><h2 class="section-title">CustomerCode review complete</h2><p>All groups needing Office review have been handled. Review the Unknown / Unclassified rows last, then continue to the existing site preview and Apply.</p><a class="primary-button" href="{{ route('office.workspace.pilot-import.unknown.show', $overview['import']['upload']) }}">Review Unknown / Unclassified rows ({{ $overview['unknown_count'] }})</a></section>
        @endif
    </div>
    <script>
        function reviewWizard(rows, customers, sites, initialCustomer, initialSite) {
            return {
                rows, customers, sites, customer: initialCustomer || '', site: initialSite || '', page: 1,
                excluded: new Set(rows.filter(row => row.excluded).map(row => row.row)),
                get pageCount() { return Math.max(1, Math.ceil(this.rows.length / 20)); },
                get pageRows() { return this.rows.slice((this.page - 1) * 20, this.page * 20); },
                get selectedCount() { return this.rows.length - this.excluded.size; },
                get availableSites() {
                    const customer = this.customers.find(item => item.uuid === this.customer);
                    return customer ? this.sites.filter(item => item.customer_id === customer.id) : [];
                },
                get customerName() { return this.customers.find(item => item.uuid === this.customer)?.name || ''; },
                get siteName() { return this.sites.find(item => item.uuid === this.site)?.name || ''; },
                get safeCount() { return this.rows.filter(row => !this.excluded.has(row.row) && this.proposal(row).plot).length; },
                get reviewCount() { return this.selectedCount - this.safeCount + this.excluded.size; },
                get proposedPlots() {
                    const plots = this.rows.filter(row => !this.excluded.has(row.row)).map(row => this.proposal(row).plot).filter(Boolean);
                    return plots.slice(0, 12).join(', ') + (plots.length > 12 ? ` and ${plots.length - 12} more` : '') || 'None yet';
                },
                setSelected(row, selected) { selected ? this.excluded.delete(row) : this.excluded.add(row); },
                selectAll() { this.excluded = new Set(); },
                clearAll() { this.excluded = new Set(this.rows.map(row => row.row)); },
                proposal(row) {
                    const customer = this.customerName, site = this.siteName;
                    if (!customer || !site) return {plot: null, issue: 'Choose Customer and Site'};
                    const normalize = value => String(value ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
                    const same = (a,b) => normalize(a) === normalize(b);
                    if (row.parsed_plot && same(row.parsed_customer, customer) && same(row.parsed_site, site)) return {plot: row.parsed_plot, issue: null};
                    if (row.suggestion && same(row.suggestion.customer, customer) && same(row.suggestion.site, site)) return {plot: row.suggestion.plot, issue: 'Exact source pattern; confirm this proposal'};
                    const escape = value => value.trim().split(/\s+/).map(part => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('\\s+');
                    const expression = new RegExp(`^\\s*${escape(customer)}\\s+[-–—]\\s+${escape(site)}\\s+[-–—]\\s+(.+?)\\s*$`, 'i');
                    const match = row.raw?.match(expression);
                    if (!match) return {plot: null, issue: 'Customer/Site text mismatch'};
                    const plot = match[1].replace(/^Plot\s+/i, '').trim();
                    if (!plot || /[-–—<>]/.test(plot) || plot.length > 200) return {plot: null, issue: 'Multiple or invalid plot fragments'};
                    return {plot, issue: null};
                },
            };
        }
    </script>
</x-layouts.portal>
