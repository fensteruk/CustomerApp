<dl class="sw-summary" aria-label="Site summary">
    <div class="sw-metric"><dt>Total plots</dt><dd>{{ number_format($siteSummary['total']) }}</dd></div>
    <div class="sw-metric"><dt>Plots needing your response</dt><dd>{{ number_format($siteSummary['attention']) }}</dd></div>
    <div class="sw-metric"><dt>Next call-off date</dt><dd class="sw-date-value">{{ ($siteSummary['upcoming']->first()['date'] ?? null)?->format('j M Y') ?? 'None scheduled' }}</dd><p class="sw-muted">{{ $siteSummary['upcoming']->first()['label'] ?? 'Agreed and requested dates appear here' }}</p></div>
</dl>
