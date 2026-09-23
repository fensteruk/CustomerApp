<div class="sw-grid">
    @foreach ($cards as $card)
        @php($overview = $card['overview'])
        <article @class(['sw-card', 'sw-card-needs' => $card['attention']])>
            @if ($isOffice)
                <details>
                    <summary class="sw-card-main">@include('site-workspace.card-content')</summary>
                    <div class="sw-source">
                        <p class="sw-muted">Source-managed · {{ $overview->plot->is_completed ? 'Completed' : 'Not fully completed' }}</p>
                        <p>Windows {{ $card['windows'] }} · Doors {{ $card['doors'] }} · Bifold {{ $card['bifold'] }}</p>
                        <p>Last synchronised: {{ $overview->plot->synchronised_at ? $overview->plot->synchronised_at->utc()->format('j M Y, H:i').' UTC' : 'Not recorded' }}</p>
                        @if ($card['request'])<a class="sw-button" href="{{ route('portal.review-requests.show', $card['request']) }}">Review request</a>@endif
                        <details x-data="{ message: '' }"><summary>Show full source reference</summary><p>{{ str($overview->plot->external_source)->headline() }}</p><code>{{ $overview->plot->external_identifier }}</code><button type="button" class="sw-button" data-source-reference="{{ $overview->plot->external_identifier }}" @click="navigator.clipboard.writeText($el.dataset.sourceReference).then(() => message = 'Source reference copied.').catch(() => message = 'Copy failed. Select the full reference above.')">Copy <span class="sr-only">full source reference for {{ $card['label'] }}</span></button><p role="status" x-text="message"></p></details>
                        <details><summary>Inspect source services</summary><ul>@forelse ($overview->plot->services as $service)<li>{{ $service->service_identifier->label() }} · {{ $service->isSourceCompleted() ? 'Completed' : ($service->source_present ? 'Not completed' : 'Not supplied') }}</li>@empty<li>No service information supplied.</li>@endforelse</ul></details>
                    </div>
                </details>
            @else
                <a class="sw-card-main" href="{{ route('portal.plots.show', $overview->plot) }}">@include('site-workspace.card-content')</a>
                <div class="sw-card-controls">
                    <label class="sw-select"><input type="checkbox" name="plots[]" value="{{ $overview->plot->uuid }}" x-model="selected" @disabled($overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted)>Select <span class="sr-only">{{ $card['label'] }} for call-off</span></label>
                    <a class="sw-button" href="{{ route('portal.call-offs.create', ['plots' => [$overview->plot->uuid]]) }}" aria-label="Call off for {{ $overview->plot->plot_reference }}. Choose service in the next step.">Call Off</a>
                </div>
            @endif
        </article>
    @endforeach
</div>
