@if ($siteSummary['upcoming']->isNotEmpty())
<section class="sw-section" aria-labelledby="site-upcoming-title">
    <div class="sw-section-heading"><h2 id="site-upcoming-title">Upcoming call-offs</h2><span class="sw-muted">Next {{ $siteSummary['upcoming']->count() }}</span></div>
    <ul class="sw-upcoming">
        @foreach ($siteSummary['upcoming'] as $activity)
            <li><a href="{{ $isOffice ? route('portal.review-requests.show', $activity['request']) : route('portal.plots.show', $activity['request']->projectedPlot) }}"><time class="sw-date-tile" datetime="{{ $activity['date']->toDateString() }}"><b>{{ $activity['date']->format('j') }}</b>{{ $activity['date']->format('M') }}</time><span><strong>{{ $activity['request']->projectedPlot->plot_reference }} · {{ $activity['service'] }}</strong><span>{{ $activity['label'] }}</span></span><span aria-hidden="true">›</span></a></li>
        @endforeach
    </ul>
</section>
@endif
