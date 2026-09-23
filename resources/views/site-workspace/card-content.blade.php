@php($overview = $card['overview'])
<span class="sw-card-top"><span class="sw-plot-title min-w-0 [overflow-wrap:anywhere]" role="heading" aria-level="3">{{ $card['label'] }}</span><span class="sw-chevron" aria-hidden="true">›</span></span>
<span class="sw-badge">{{ $overview->overallStatus->label() }}</span>
@if ($card['attention'])<span class="sw-badge sw-attention">Your response needed</span>@endif
@if ($card['amendment'])<span class="sw-badge sw-amendment">Date amendment</span>@endif
@if ($card['early'])<span class="sw-badge sw-attention">Early date requested</span>@endif
<span class="sw-products">
    @foreach (['windows' => 'Windows', 'doors' => 'Doors'] as $key => $label)
        <span class="sw-product"><svg aria-hidden="true" viewBox="0 0 24 26" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2" width="16" height="22" rx="1"/>@if ($key === 'windows')<path d="M12 2v22M4 13h16"/>@else<path d="M16 13h1"/>@endif</svg><span><span class="sw-product-name">{{ $label }}</span><span @class(['sw-quantity', 'sw-unknown' => $card[$key] === 'Not supplied'])>{{ $card[$key] }}</span></span></span>
    @endforeach
</span>
<span class="sw-services">
    @foreach ($overview->services as $service)
        <span class="sw-service"><span class="sw-service-name">{{ $service->service->label() }}</span><span class="sw-service-state">{{ $service->state->label() }}@if ($service->date) · {{ $service->date->format('j M Y') }}@endif</span></span>
    @endforeach
</span>
@if ($card['next'])<span class="sw-card-date">{{ $card['next']['service'] }} · {{ $card['next']['label'] }}<strong>{{ $card['next']['date']->format('j M Y') }}</strong></span>@endif
<span class="sw-card-link">{{ $isOffice ? 'Inspect plot information' : 'Open plot' }} <span aria-hidden="true">→</span></span>
