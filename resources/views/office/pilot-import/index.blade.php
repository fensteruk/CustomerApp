<x-layouts.portal title="Imports | Fenster Customer Portal" sidebar-label="Menu">
    @include('office.pilot-import.workspace-styles')
    <div class="admin-workspace imports-workspace">
        <header class="imports-heading">
            <div><p class="eyebrow">Office workspace</p><h1 class="admin-title">Imports</h1><p class="imports-intro">Upload a RedZebra export. See what it contains, then review and apply one site at a time.</p></div>
            <div class="imports-badges"><span>Office use only</span><span>One site at a time</span></div>
        </header>
        @if ($errors->any())<div class="imports-notice imports-notice-danger" role="alert"><strong>The import was not started.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@if(session('existing_import_url'))<a href="{{ session('existing_import_url') }}">View existing import</a>@endif</div>@endif
        @if (session('status'))<div class="imports-notice" role="status">{{ session('status') }}</div>@endif
        <section class="imports-upload" aria-labelledby="upload-title">
            <div class="imports-upload-title"><span class="imports-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 17H5a4 4 0 0 1-.6-8A7 7 0 0 1 18 7a5 5 0 0 1 1 10h-2M12 21V10m-4 4 4-4 4 4"/></svg></span><div><h2 id="upload-title">Upload a RedZebra workbook</h2><p>One master export or a smaller site workbook.</p></div></div>
            @include('office.pilot-import.upload-form')
        </section>
        <p class="imports-notice imports-safety"><strong>Uploading does not apply data to CustomerApp.</strong> You will review the changes before anything is applied.</p>
        <section aria-labelledby="recent-imports-title" data-recent-imports>
            <div class="imports-section-heading"><div><h2 id="recent-imports-title">Recent imports</h2><p>Your three most recent uploads. Continue a review or check the latest result.</p></div></div>
            <div class="imports-recent-grid">@forelse ($recentImports as $import) @include('office.pilot-import.summary-card', ['import' => $import]) @empty <div class="imports-empty"><h3>No imports yet</h3><p>Choose a workbook above to check its source sites and rows.</p></div> @endforelse</div>
        </section>
        <section id="import-history" aria-labelledby="import-history-title" data-import-history>
            <div class="imports-section-heading"><div><h2 id="import-history-title">Older imports</h2><p>Previous uploads and revisions remain available here.</p></div>
                <form method="GET" action="{{ route('office.workspace.imports') }}#import-history" class="imports-filter"><label for="history-filter">Show</label><select id="history-filter" name="history_filter">@foreach($historyFilters as $value => $label)<option value="{{ $value }}" @selected($historyFilter === $value)>{{ $label }}</option>@endforeach</select><button class="imports-secondary" type="submit">Filter history</button></form>
            </div>
            @if ($historyFilter === 'applied')<p class="imports-filter-note">Includes workbooks with at least one applied site. Each status shows whether the whole workbook is applied.</p>@endif
            <div class="imports-history-wrap"><table class="imports-history-table">
                <caption class="sr-only">Older RedZebra uploads, newest first</caption>
                <thead><tr><th>Date / time</th><th>Import</th><th>Source sites</th><th>Rows</th><th>Included / excluded</th><th>Blocked rows</th><th>Status</th><th>Uploaded by</th><th>Action</th></tr></thead>
                <tbody>@forelse ($importHistory as $import)<tr>
                    <td data-label="Uploaded"><time datetime="{{ Illuminate\Support\Carbon::parse($import['created_at'])->toIso8601String() }}">{{ Illuminate\Support\Carbon::parse($import['created_at'])->format('j M Y, H:i') }}</time></td>
                    <th scope="row" data-label="Import">{{ $import['title'] }}<span class="imports-subtext">Revision {{ $import['revision'] }}</span></th>
                    <td data-label="Source sites">{{ $import['sites'] === null ? 'Not known' : number_format($import['sites']) }}</td>
                    <td data-label="Rows">{{ $import['rows'] === null ? 'Not known' : number_format($import['rows']) }}</td>
                    <td data-label="Included / excluded">{{ $import['included'] === null ? 'Not known' : number_format($import['included']) }} / {{ $import['excluded'] === null ? 'Not known' : number_format($import['excluded']) }}</td>
                    <td data-label="Blocked rows">{{ $import['blocked_rows'] === null ? 'Not checked' : number_format($import['blocked_rows']) }}</td>
                    <td data-label="Status"><span class="imports-status imports-status-{{ $import['tone'] }}">{{ $import['status'] }}</span></td>
                    <td data-label="Uploaded by">{{ $import['uploader'] }}</td>
                    <td data-label="Action"><a class="imports-history-action" href="{{ $import['url'] }}" aria-label="{{ $import['action'] }}: {{ $import['title'] }}, revision {{ $import['revision'] }}">{{ $import['action'] }}</a></td>
                </tr>@empty<tr><td colspan="9" class="imports-empty">{{ $historyFilter === 'all' ? 'No older imports yet.' : 'No older imports match this filter.' }}</td></tr>@endforelse</tbody>
            </table></div>
            <div class="imports-pagination">{{ $importHistory->onEachSide(1)->links() }}</div>
            <p class="imports-footnote">Row and source totals reflect upload discovery. Source sites detected are workbook identities, not confirmed CustomerApp matches. Blocked rows cover analysed selections only; preview issues are reviewed inside each import.</p>
        </section>
    </div>
</x-layouts.portal>
