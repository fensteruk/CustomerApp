<x-layouts.portal title="Import review | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.pilot-import.detail-styles')
    <div class="admin-workspace wald-detail">
        <a class="admin-back" href="{{ route('office.workspace.imports') }}">← Back to Imports</a>
        <header class="wald-heading"><div><p class="eyebrow">RedZebra export · {{ Illuminate\Support\Carbon::parse($import['export_date'])->format('j M Y') }} · {{ str($import['export_slot'])->title() }} · Revision {{ $import['revision'] }}</p><h1 class="admin-title">Import review</h1><p class="page-intro">Check what Wald found, then review and apply one site at a time.</p></div><a class="secondary-button" href="{{ route('office.workspace.pilot-import.reconciliation', $import['upload']) }}">Master import reconciliation</a></header>
        <div class="wald-badges"><span>WEEKEND PILOT</span><span>OFFICE USE ONLY</span><span>ONE SITE AT A TIME</span></div>
        <section class="wald-metrics" aria-label="Whole workbook summary">
            @foreach(['record_count'=>'Source rows', 'source_count'=>'Source sites', 'included_count'=>'Included rows', 'excluded_count'=>'Ignored rows'] as $key=>$label)<article><span>{{ $label }}</span><strong>{{ isset($import['manifest'][$key]) ? number_format($import['manifest'][$key]) : 'Not known' }}</strong><small>Whole workbook</small></article>@endforeach
        </section>
        @php($activeTab = request()->query('tab') === 'ignored' ? 'ignored' : 'review')
        <nav class="wald-links" aria-label="Import review sections">
            <a class="{{ $activeTab === 'review' ? 'primary-button' : 'secondary-button' }}" href="{{ route('office.workspace.pilot-import.show', $import['upload']) }}" @if($activeTab === 'review') aria-current="page" @endif>Approved list and site review</a>
            <a class="{{ $activeTab === 'ignored' ? 'primary-button' : 'secondary-button' }}" href="{{ route('office.workspace.pilot-import.show', ['upload' => $import['upload'], 'tab' => 'ignored']) }}" @if($activeTab === 'ignored') aria-current="page" @endif>Ignored CustomerCodes ({{ number_format($import['ignored_code_count']) }})</a>
        </nav>
        <div class="wald-notice"><strong>Uploaded does not mean imported.</strong><p>Each selected site needs its own analysis, review, approval and Apply. Other sites in this master workbook are not applied automatically.</p></div>
        @if ($errors->any())<div class="wald-notice wald-danger" role="alert"><strong>This action could not be completed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(session('status'))<div class="wald-notice" role="status">{{ session('status') }}</div>@endif
        @if($activeTab === 'review')
        @include('office.pilot-import.lifecycle')
        @if($import['state'] === 'NEEDS_CLARIFICATION')
            <section class="wald-panel wald-question" id="workbook-question"><h2 class="section-title">Check the workbook layout</h2><p>Wald needs you to confirm the detected header and site list before a site can be selected. This confirms the layout only; it does not apply any data.</p><dl class="wald-facts"><div><dt>Worksheet</dt><dd>{{ $import['manifest']['sheet'] ?? 'Not identified' }}</dd></div><div><dt>Header rows</dt><dd>{{ implode(' to ', $import['manifest']['header'] ?? []) ?: 'Not identified' }}</dd></div><div><dt>Detected table</dt><dd>{{ $import['manifest']['logical_table'] ?? 'Not identified' }}</dd></div></dl><p>Compare these locations and the source sites below with your workbook.</p>
                <form id="confirm-workbook" method="POST" action="{{ route('office.workspace.pilot-import.confirm-structure', $import['upload']) }}">@csrf<input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}"><label class="wald-check"><input type="checkbox" name="confirmation" value="CONFIRM DETECTED HEADER AND SITE LIST" required><span>I have reviewed the detected structure and site list.</span></label><button class="primary-button" type="submit">Confirm structure</button></form>
            </section>
        @endif
        @if($import['selections'])<section aria-labelledby="reviews-title"><div class="wald-section-heading"><div><p class="eyebrow">Selected sites</p><h2 id="reviews-title" class="section-title">Your site reviews</h2></div><a class="secondary-button" href="#detected-sites-title">Link or select another site</a></div>
            @foreach($import['selections'] as $selection)
                @php($item = $details[$selection['uuid']])
                @php($run = $item['run'])
                @php($ui = App\View\Presenters\ImportReviewPresentation::forSelection($import, $item))
                @include('office.pilot-import.site-review')
            @endforeach
        </section>@elseif(!in_array($import['state'], ['FAILED','SUPERSEDED']))<div class="wald-panel"><h2 class="section-title">Next: link and select a site</h2><p>Choose the exact CustomerApp site for each CustomerCode below. Every plot for that source will inherit the selected site.</p><a class="secondary-button" href="#detected-sites-title">Review source sites</a></div>@endif
        @include('office.pilot-import.source-sites')
        @else
            @include('office.pilot-import.ignored-rows')
        @endif
    </div>
</x-layouts.portal>
