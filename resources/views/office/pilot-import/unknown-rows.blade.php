<x-layouts.portal title="Unknown source rows | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.pilot-import.detail-styles')
    @php($siteData = $sites->map(fn (object $site): array => ['uuid' => $site->uuid, 'name' => $site->name, 'customer_id' => $site->customer_organisation_id])->all())
    @php($customerData = $customers->map(fn (object $customer): array => ['id' => $customer->id, 'uuid' => $customer->uuid])->all())
    <div class="admin-workspace wald-detail">
        <a class="admin-back" href="{{ route('office.workspace.pilot-import.customer-codes.review', $overview['import']['upload']) }}">← Back to CustomerCode review</a>
        <header class="wald-heading"><div><p class="eyebrow">Final source review · Revision {{ $overview['import']['revision'] }}</p><h1 class="admin-title">Unknown / Unclassified rows</h1><p class="page-intro">These rows need an exact decision after the normal CustomerCode groups. Resolve one, exclude it for this upload, or leave it for later.</p></div></header>
        @if($errors->any())<div class="wald-notice wald-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if(session('status'))<div class="wald-notice" role="status">{{ session('status') }}</div>@endif
        <section class="wald-metrics" aria-label="Final unknown row summary">
            <article><span>Unknown rows now</span><strong>{{ $counts['unresolved'] }}</strong><small>Need further review</small></article>
            <article><span>Explicitly excluded</span><strong>{{ $counts['excluded'] }}</strong><small>This upload only</small></article>
            <article><span>Resolved here</span><strong>{{ $counts['resolved_manually'] }}</strong><small>Manual or exact bulk review</small></article>
            <article><span>Automatic groups</span><strong>{{ count($overview['automatic']) }}</strong><small>No Office review required</small></article>
        </section>
        <div class="wald-notice"><strong>Unresolved rows are not applied.</strong><p>Other confirmed site units can continue through their own preview and Apply. A row without CustomerCode cannot be imported until that source identity is supplied in a new revision or it is explicitly excluded.</p></div>
        @if($unknownCodes->isNotEmpty())
            <section class="wald-panel" aria-labelledby="matching-code-title">
                <h2 id="matching-code-title" class="section-title">Review matching CustomerCode rows together</h2>
                <p>Choose one CustomerCode and its exact existing Customer and Site. Wald will resolve only rows with a safe plot pattern; the others stay in this queue.</p>
                <form method="POST" action="{{ route('office.workspace.pilot-import.unknown.resolve-code', $overview['import']['upload']) }}" x-data="unknownCodeReview(@js($customerData), @js($siteData), @js($recommendations), @js($rowsByCode))" class="grid gap-3 md:grid-cols-3">
                    @csrf
                    <input type="hidden" name="source_manifest_hash" value="{{ $overview['import']['source_manifest_hash'] }}">
                    <input type="hidden" name="expected_epoch" value="{{ $overview['import']['epoch'] }}">
                    <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="excluded_rows_csv" :value="Array.from(excluded).sort((a,b) => a-b).join(',')">
                    <label class="form-label">CustomerCode<select name="customer_code" class="form-input" x-model="code" x-on:change="chooseCode()" required><option value="">Choose one code</option>@foreach($unknownCodes as $item)<option value="{{ $item->customer_code }}">{{ $item->customer_code }} · {{ $item->row_count }} rows</option>@endforeach</select></label>
                    <label class="form-label">Customer<select name="customer_uuid" class="form-input" x-model="customer" x-on:change="site = ''" required><option value="">Choose customer</option>@foreach($customers as $customer)<option value="{{ $customer->uuid }}">{{ $customer->name }}</option>@endforeach</select></label>
                    <label class="form-label">Site<select name="site_uuid" class="form-input" x-model="site" x-ref="bulkSite" x-effect="$nextTick(() => { if (site) $el.value = site })" required><option value="">Choose site</option><template x-for="item in choices" :key="item.uuid"><option :value="item.uuid" x-text="item.name"></option></template></select></label>
                    <div class="md:col-span-3 rounded-lg border border-slate-200 p-3" x-show="code" x-cloak><div class="flex flex-wrap items-center justify-between gap-2"><strong>Rows to review: <span x-text="rowOptions.length - excluded.size"></span> of <span x-text="rowOptions.length"></span></strong><div class="flex gap-2"><button type="button" class="secondary-button" x-on:click="excluded = new Set()">Select all</button><button type="button" class="secondary-button" x-on:click="excluded = new Set(rowOptions.map(item => item.row))">Clear all</button></div></div><div class="mt-3 max-h-64 space-y-2 overflow-y-auto"><template x-for="item in rowOptions" :key="item.row"><label class="flex items-start gap-2 rounded border border-slate-100 p-2 text-sm"><input type="checkbox" class="mt-1 h-5 w-5" :checked="!excluded.has(item.row)" x-on:change="setSelected(item.row, $event.target.checked)" :aria-label="`Include workbook row ${item.row}`"><span><strong x-text="`Row ${item.row}`"></strong> · <span x-text="item.raw || 'Blank Plot Ref'"></span></span></label></template></div></div>
                    <div class="wald-notice wald-danger md:col-span-3" x-show="bindingConflict" x-cloak><strong>Binding conflict</strong><p>RedZebra says: <span x-text="recommendation.source_customer || 'Needs source review'"></span> → <span x-text="recommendation.source_site || 'Needs source review'"></span></p><p>Current binding says: <span x-text="recommendation.binding_customer"></span> → <span x-text="recommendation.binding_site"></span></p><label class="wald-check"><input type="checkbox" name="confirm_binding" value="1" :required="bindingConflict"><span>Confirm binding to the Customer and Site selected above. The old binding will be retained in history.</span></label></div>
                    <label class="wald-check md:col-span-3"><input type="checkbox" name="confirmation" value="REVIEW MATCHING CUSTOMER CODE ROWS" required><span>Review all currently unknown rows for this one CustomerCode using this Customer and Site.</span></label>
                    <button class="secondary-button md:col-span-3 md:justify-self-start" type="submit" :disabled="!code || rowOptions.length === excluded.size" x-text="bindingConflict ? 'Confirm binding and review selected rows' : 'Review selected rows'">Review selected rows</button>
                </form>
            </section>
        @endif
        @forelse($rows as $row)
            <article class="wald-panel" aria-labelledby="unknown-{{ $row->row_number }}">
                <div class="wald-section-heading"><div><p class="eyebrow">Workbook row {{ $row->row_number }} · Call No. {{ $row->call_no }}</p><h2 id="unknown-{{ $row->row_number }}" class="section-title">{{ $row->customer_code ?: 'Missing CustomerCode' }}</h2></div><span class="wald-status">{{ str_replace('_', ' ', $row->issue ?: 'Needs review') }}</span></div>
                <dl class="wald-facts"><div><dt>Raw Plot Ref</dt><dd>{{ $row->raw_plot_ref ?: 'Blank' }}</dd></div><div><dt>Call Type</dt><dd>{{ $row->call_type ?: 'Blank' }}</dd></div><div><dt>Source Site Name</dt><dd>{{ $row->source_site_name ?: 'Not supplied' }}</dd></div></dl>
                @if($row->disposition === 'REVIEWED_MISSING_CODE')<p class="wald-safety">Office recorded a target and plot, but CustomerCode is still missing. This row remains outside import.</p>@endif
                <div class="grid gap-5 lg:grid-cols-2">
                    <form method="POST" action="{{ route('office.workspace.pilot-import.unknown.resolve', ['upload' => $overview['import']['upload'], 'rowNumber' => $row->row_number]) }}" class="rounded-lg border border-slate-200 p-4" x-data="{customer:'', site:'', customers:@js($customerData), sites:@js($siteData), get choices(){const chosen=this.customers.find(item => item.uuid === this.customer); return chosen ? this.sites.filter(item => item.customer_id === chosen.id) : []}}">
                        @csrf
                        <input type="hidden" name="source_manifest_hash" value="{{ $overview['import']['source_manifest_hash'] }}">
                        <input type="hidden" name="expected_epoch" value="{{ $overview['import']['epoch'] }}">
                        <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                        <h3 class="font-semibold">Resolve manually</h3>
                        <label class="form-label" for="unknown-customer-{{ $row->row_number }}">Customer</label>
                        <select id="unknown-customer-{{ $row->row_number }}" name="customer_uuid" class="form-input" x-model="customer" x-on:change="site = ''" required><option value="">Choose customer</option>@foreach($customers as $customer)<option value="{{ $customer->uuid }}" data-id="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
                        <label class="form-label" for="unknown-site-{{ $row->row_number }}">Site</label>
                        <select id="unknown-site-{{ $row->row_number }}" name="site_uuid" class="form-input" x-model="site" required><option value="">Choose site</option><template x-for="item in choices" :key="item.uuid"><option :value="item.uuid" x-text="item.name"></option></template></select>
                        <label class="form-label" for="unknown-plot-{{ $row->row_number }}">Plot name/ref</label>
                        <input id="unknown-plot-{{ $row->row_number }}" name="plot" maxlength="200" class="form-input" placeholder="Leave blank only if the source text derives one safely">
                        <button class="primary-button" type="submit">Confirm this row</button>
                    </form>
                    <form method="POST" action="{{ route('office.workspace.pilot-import.unknown.exclude', ['upload' => $overview['import']['upload'], 'rowNumber' => $row->row_number]) }}" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        @csrf
                        <input type="hidden" name="source_manifest_hash" value="{{ $overview['import']['source_manifest_hash'] }}">
                        <input type="hidden" name="expected_epoch" value="{{ $overview['import']['epoch'] }}">
                        <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                        <h3 class="font-semibold">Exclude from this upload</h3><p class="text-sm">This records a row-specific Office decision. It does not change the Call Type dictionary.</p>
                        <label class="form-label" for="unknown-reason-{{ $row->row_number }}">Reason</label>
                        <input id="unknown-reason-{{ $row->row_number }}" name="reason" maxlength="100" required class="form-input" placeholder="Why this row is out of scope">
                        <button class="secondary-button" type="submit">Exclude this row</button>
                    </form>
                </div>
            </article>
        @empty
            <section class="wald-panel"><h2 class="section-title">No unresolved rows</h2><p>All rows in this queue have been resolved or explicitly excluded.</p></section>
        @endforelse
        <div class="mt-4 overflow-x-auto">{{ $rows->links() }}</div>
        <section class="wald-panel"><h2 class="section-title">Final review</h2>
            <p>{{ count($overview['automatic']) }} automatic source units · {{ $overview['reviewed_count'] - $overview['deferred_count'] }} manually confirmed CustomerCodes · {{ $overview['deferred_count'] }} deferred CustomerCodes · {{ $createdCustomers }} new customers approved · {{ $createdSites }} new sites approved.</p>
            <p>{{ $overview['import']['resolution_summary']['plots_create'] }} plots proposed for creation · {{ $overview['import']['resolution_summary']['plots_reuse'] }} same-site plots proposed for reuse.</p>
            <p>{{ $counts['resolved_manually'] }} Unknown rows resolved · {{ $counts['excluded'] }} Unknown rows excluded · {{ $counts['unresolved'] }} still unresolved · {{ $overview['import']['resolution_summary']['blockers'] }} source units with hierarchy blockers.</p>
            <p>Unresolved rows stay outside Apply. Continue to the existing one-site analysis, preview and Apply for safe source units.</p><a class="primary-button" href="{{ route('office.workspace.pilot-import.show', $overview['import']['upload']) }}">Continue to site review →</a></section>
    </div>
    <script>
        function unknownCodeReview(customers, sites, recommendations, rowsByCode) {
            return {
                code: '', customer: '', site: '', customers, sites, recommendations, rowsByCode, excluded: new Set(),
                get recommendation() { return this.recommendations[this.code] || {}; },
                get rowOptions() { return this.rowsByCode[this.code] || []; },
                get bindingConflict() { return !!this.recommendation.binding_site_uuid && !!this.site && this.recommendation.binding_site_uuid !== this.site; },
                get choices() {
                    const chosen = this.customers.find(item => item.uuid === this.customer);
                    return chosen ? this.sites.filter(item => item.customer_id === chosen.id) : [];
                },
                chooseCode() {
                    this.customer = this.recommendation.customer || '';
                    this.site = this.recommendation.site || '';
                    this.excluded = new Set();
                    this.$nextTick(() => { this.$refs.bulkSite.value = this.site; });
                },
                setSelected(row, selected) {
                    const next = new Set(this.excluded);
                    if (selected) next.delete(row); else next.add(row);
                    this.excluded = next;
                },
            };
        }
    </script>
</x-layouts.portal>
