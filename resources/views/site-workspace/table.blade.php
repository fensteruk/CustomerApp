<div class="sw-table-scroll" role="region" aria-label="Site Plot table" tabindex="0">
    <table class="sw-table">
        <caption class="sr-only">Plot and service status overview</caption>
        <thead>
            <tr>
                <th scope="col">Plot</th>
                @foreach (App\Enums\CallOffServiceType::cases() as $serviceType)
                    <th scope="col">{{ $serviceType->label() }}</th>
                @endforeach
                <th scope="col">Overall status</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cards as $card)
                @php($overview = $card['overview'])
                <tr>
                    <th scope="row">
                        @if ($isOffice)
                            <button
                                type="button"
                                class="sw-table-plot-button"
                                aria-label="Inspect plot information for {{ $card['label'] }}"
                                @click="const details = $el.closest('tr').querySelector('.sw-table-details'); details.open = true; details.querySelector('summary').focus()"
                            >{{ $card['label'] }}</button>
                        @else
                            <label class="sw-table-select">
                                <input type="checkbox" name="plots[]" value="{{ $overview->plot->uuid }}" x-model="selected" @disabled($overview->overallStatus === App\Enums\PlotOverallStatus::FullyCompleted)>
                                <a href="{{ route('portal.plots.show', $overview->plot) }}">{{ $card['label'] }}</a>
                            </label>
                        @endif
                    </th>
                    @foreach (App\Enums\CallOffServiceType::cases() as $serviceType)
                        <td><x-plot-service-status :service="$overview->services[$serviceType->value]" compact /></td>
                    @endforeach
                    <td><x-plot-overall-status :status="$overview->overallStatus" class="px-2 py-1 text-xs" /></td>
                    <td>
                        <div class="sw-table-actions">
                            @unless ($isOffice)
                                <a class="sw-button" href="{{ route('portal.call-offs.create', ['plots' => [$overview->plot->uuid]]) }}" aria-label="Call off for {{ $overview->plot->plot_reference }}. Choose service in the next step.">Call Off</a>
                            @endunless
                            @if ($isOffice && $card['request'])
                                <a class="sw-button" href="{{ route('portal.review-requests.show', $card['request']) }}">Review request</a>
                            @endif
                            <details class="sw-table-details">
                                <summary>{{ $isOffice ? 'Inspect plot information' : 'More information' }}</summary>
                                <div class="sw-table-detail-body">
                                    <p><strong>Products</strong> · Windows {{ $card['windows'] }} · Doors {{ $card['doors'] }}@if ($isOffice) · Bifold {{ $card['bifold'] }}@endif</p>
                                    @if ($card['attention'])<p><span class="sw-badge sw-attention">Your response needed</span></p>@endif
                                    @if ($card['amendment'])<p><span class="sw-badge sw-amendment">Date amendment</span></p>@endif
                                    @if ($card['early'])<p><span class="sw-badge sw-attention">Early date requested</span></p>@endif
                                    @if ($card['next'])<p>{{ $card['next']['service'] }} · {{ $card['next']['label'] }} · <strong>{{ $card['next']['date']->format('j M Y') }}</strong></p>@endif
                                    @if ($isOffice)
                                        <div class="sw-table-source">
                                            <p>Source-managed · {{ $overview->plot->is_completed ? 'Completed' : 'Not fully completed' }}</p>
                                            <p>Last synchronised: {{ $overview->plot->synchronised_at ? $overview->plot->synchronised_at->utc()->format('j M Y, H:i').' UTC' : 'Not recorded' }}</p>
                                            <details x-data="{ message: '' }">
                                                <summary>Show full source reference</summary>
                                                <p>{{ str($overview->plot->external_source)->headline() }}</p>
                                                <code>{{ $overview->plot->external_identifier }}</code>
                                                <button type="button" class="sw-button" data-source-reference="{{ $overview->plot->external_identifier }}" @click="navigator.clipboard.writeText($el.dataset.sourceReference).then(() => message = 'Source reference copied.').catch(() => message = 'Copy failed. Select the full reference above.')">Copy <span class="sr-only">full source reference for {{ $card['label'] }}</span></button>
                                                <p role="status" x-text="message"></p>
                                            </details>
                                            <details>
                                                <summary>Inspect source services</summary>
                                                <ul>
                                                    @forelse ($overview->plot->services as $service)
                                                        <li>{{ $service->service_identifier->label() }} · {{ $service->isSourceCompleted() ? 'Completed' : ($service->source_present ? 'Not completed' : 'Not supplied') }}</li>
                                                    @empty
                                                        <li>No service information supplied.</li>
                                                    @endforelse
                                                </ul>
                                            </details>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
