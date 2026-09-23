<article class="imports-card" aria-labelledby="import-{{ $import['uuid'] }}">
    <div class="imports-card-heading"><div><p class="imports-type">{{ $import['type'] }}</p><h3 id="import-{{ $import['uuid'] }}">{{ $import['title'] }}</h3></div><span class="imports-status imports-status-{{ $import['tone'] }}">{{ $import['status'] }}</span></div>
    <p class="imports-byline">Uploaded <time datetime="{{ Illuminate\Support\Carbon::parse($import['created_at'])->toIso8601String() }}">{{ Illuminate\Support\Carbon::parse($import['created_at'])->format('j M Y, H:i') }}</time> by {{ $import['uploader'] }}</p>
    <p class="imports-byline">Export {{ Illuminate\Support\Carbon::parse($import['export_date'])->format('j M') }} · {{ str($import['export_slot'])->title() }} · Revision {{ $import['revision'] }}</p>
    <dl class="imports-metrics">@foreach (['rows' => 'Total rows', 'sites' => 'Source sites detected', 'plots' => 'Plots in analysed site', 'included' => 'Included rows', 'excluded' => 'Excluded rows', 'blocked_rows' => 'Blocked rows'] as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $import[$key] === null ? '—' : number_format($import[$key]) }}@if ($import[$key] === null)<span class="sr-only"> Not known at this stage</span>@endif</dd></div>@endforeach</dl>
    @if ($import['needs_refresh'])<p class="imports-card-notice">Earlier analysis or preview needs refreshing. Continue review for the current checks.</p>@elseif ($import['blocked_rows'] > 0 || $import['preview_issues'] > 0)<p class="imports-card-notice">{{ $import['blocked_rows'] ?? 0 }} blocked rows in analysed selections.@if($import['preview_issues'] > 0) {{ $import['preview_issues'] }} selected-site previews also need attention.@endif Open the import to review the issues.</p>@endif
    @if ($import['applied'] > 0)<p class="imports-progress">{{ $import['applied'] }} of {{ $import['sites'] ?? 'the detected' }} source sites applied.</p>@endif
    <div class="imports-preview">
        @if ($import['preview_rows']->isNotEmpty())
            <h4>Content preview <span>First {{ $import['preview_rows']->count() }} included rows</span></h4>
            <table><caption class="sr-only">Analysed row sample for {{ $import['title'] }}</caption><thead><tr><th>Plot</th><th>Call type / service</th></tr></thead><tbody>@foreach ($import['preview_rows'] as $row)<tr><td>{{ $row['plot'] }}</td><td>{{ $row['call_type'] }} <span>· {{ $row['service'] }}</span></td></tr>@endforeach</tbody></table>
        @elseif ($import['preview_sites']->isNotEmpty())
            <h4>Source sites <span>First {{ $import['preview_sites']->count() }} of {{ $import['sites'] }}</span></h4>
            <ul>@foreach ($import['preview_sites'] as $source)<li>{{ $source }}</li>@endforeach</ul>
            @if ($import['more_sites'])<p class="imports-more">+{{ number_format($import['more_sites']) }} more sites</p>@endif
            @if ($import['sites'] === 1)<p class="imports-preview-note">Plot and service preview appears after this site is analysed.</p>@endif
        @else
            <h4>Workbook contents</h4><p class="imports-preview-note">Content details are not available at this stage. Open the import to see its current checks.</p>
        @endif
    </div>
    <a class="imports-primary" href="{{ $import['url'] }}" aria-label="{{ $import['action'] }}: {{ $import['title'] }}, revision {{ $import['revision'] }}">{{ $import['action'] }} <span aria-hidden="true">→</span></a>
</article>
