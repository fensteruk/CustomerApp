<x-layouts.portal title="{{ $overview->plot->plot_reference }} | Fenster Customer Portal">
    <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">← Back to plot overview</a>
        <header class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="eyebrow break-words">{{ $activeSite->name }}</p>
                    <h1 id="page-title" class="page-title break-words">{{ $overview->plot->plot_reference }}</h1>
                    <p class="page-intro">Your products, call-offs and latest requested dates in one place.</p>
                </div>
                <x-plot-overall-status :status="$overview->overallStatus" />
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-5 sm:max-w-lg sm:gap-8">
                @foreach (['Windows' => $totals?->totalWindows, 'Doors' => $totals?->totalDoors] as $label => $quantity)
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-sm font-semibold text-slate-600">{{ $label }} total</dt>
                        <dd class="mt-1 text-3xl font-bold tracking-tight text-slate-950">{{ $quantity === null ? '—' : rtrim(rtrim($quantity, '0'), '.') }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-xs leading-5 text-slate-500">Totals reflect recorded product quantities. A dash means no total is available.</p>
        </header>
        <div class="mt-7 grid items-start gap-7 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0">
                <section id="services" class="scroll-mt-6" aria-labelledby="services-heading">
                    <h2 id="services-heading" class="section-title">Services &amp; call-offs</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Each service is independent. You can amend an active request while Fenster is reviewing it.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach (App\Enums\CallOffServiceType::cases() as $serviceType)
                            @php
                                $service = $overview->services[$serviceType->value];
                                $card = $requestCards->get($serviceType->value);
                                $dateView = $card['dates'] ?? null;
                                $callOffRequest = $card['request'] ?? null;
                                $latestAmendment = $dateView ? $dateView['amendments']->sortByDesc('id')->first() : null;
                            @endphp
                            <article class="flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="service-{{ $serviceType->value }}">
                                <h3 id="service-{{ $serviceType->value }}" class="text-lg font-bold text-slate-950">{{ $serviceType->label() }}</h3>
                                @if ($dateView)
                                    <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800">{{ $dateView['statusLabel'] }}</p>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div><dt class="text-slate-500">Current requested date</dt><dd class="mt-1 text-lg font-bold text-slate-950">{{ $dateView['requestDate']?->format('j F Y') ?? 'Not recorded' }}</dd></div>
                                        @if ($dateView['agreedDate'])
                                            <div><dt class="text-slate-500">Date Agreed</dt><dd class="font-semibold text-slate-950">{{ $dateView['agreedDate']->format('j F Y') }}</dd></div>
                                        @elseif ($dateView['activeAmendment']?->prior_agreed_date)
                                            <div><dt class="text-slate-500">Previous agreement — on hold</dt><dd class="font-semibold text-slate-700">{{ $dateView['activeAmendment']->prior_agreed_date->format('j F Y') }}</dd></div>
                                        @endif
                                        @if ($latestAmendment)
                                            <div><dt class="text-slate-500">Amended</dt><dd class="font-medium text-slate-700">{{ $latestAmendment->opened_at->format('j M Y, H:i') }}</dd></div>
                                        @endif
                                    </dl>
                                    <div class="mt-auto space-y-2 pt-5">
                                        @if ($dateView['canAmend'])
                                            <a href="{{ route('portal.call-offs.amendments.create', $callOffRequest) }}" class="primary-button w-full" aria-label="Amend {{ $serviceType->label() }} request">Amend request</a>
                                        @endif
                                        <a href="{{ route('portal.call-offs.show', $callOffRequest) }}" class="secondary-button w-full">{{ $dateView['awaitingSiteUser'] ? 'Respond to alternative' : 'View request & history' }}</a>
                                    </div>
                                @else
                                    <x-plot-service-status :service="$service" class="mt-3" />
                                    <p class="mt-4 text-sm leading-6 text-slate-600">{{ $service->state === App\Enums\PlotServicePresentationState::Completed ? 'Completion is recorded from the source. This service cannot be reopened by an amendment.' : 'No active call-off. Start a request when you are ready for this service.' }}</p>
                                    @if ($service->state !== App\Enums\PlotServicePresentationState::Completed)
                                        <div class="mt-auto pt-4"><a href="{{ route('portal.call-offs.create') }}" class="secondary-button w-full">Start a call-off</a></div>
                                    @endif
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
                <section id="amendments" class="mt-7 scroll-mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="amendments-heading">
                    <h2 id="amendments-heading" class="section-title">Recent amendments</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">The latest amendment is the requested date. Earlier submissions stay in the request history.</p>
                    <ol class="mt-4 divide-y divide-slate-100">
                        @forelse ($amendments as $amendment)
                            <li class="py-4 first:pt-0">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="font-bold text-slate-900">{{ $amendment->callOffRequest->effectiveServiceIdentifier()->label() }} · {{ $amendment->requested_date?->format('j M Y') }}</p>
                                    <span class="text-sm font-semibold text-slate-600">{{ $amendment->progressLabel() }}</span>
                                </div>
                                <p class="mt-1 break-words text-sm text-slate-600">{{ $amendment->requester_name ?? 'Site User' }} · {{ $amendment->opened_at->format('j M Y, H:i') }}</p>
                                <a href="{{ route('portal.call-offs.show', $amendment->callOffRequest) }}" class="secondary-button mt-3">View amendment history</a>
                            </li>
                        @empty
                            <li class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No amendments have been submitted for this plot.</li>
                        @endforelse
                    </ol>
                    <div class="mt-4">{{ $amendments->links() }}</div>
                </section>
            </div>
            <aside class="min-w-0 space-y-5" aria-label="Plot information">
                <section class="rounded-xl border border-sky-200 bg-sky-50 p-5">
                    <h2 class="text-lg font-bold text-slate-950">Need to change a date?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-700">Use <strong>Amend request</strong> on the service card. You do not need to wait for Fenster to respond first.</p>
                    <p class="mt-3 text-sm leading-6 text-slate-700">Review your new date and reason before submitting. An earlier date needs an Early Date Reason and Fenster's agreement.</p>
                    <a href="#services" class="primary-button mt-4 w-full">Choose a service to amend</a>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-bold text-slate-950">Call-off information</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">A requested date is awaiting agreement. If you change an agreed date, the previous agreement is placed on hold while the amendment is reviewed.</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Fenster Office reviews amendments and updates RedZebra manually. This portal does not write dates back to RedZebra.</p>
                    <a href="{{ route('portal.call-offs.create') }}" class="secondary-button mt-4 w-full">Create a call-off</a>
                </section>
                <details class="rounded-xl border border-slate-200 bg-white p-5">
                    <summary class="cursor-pointer rounded font-bold text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700">Products &amp; source information</summary>
                    <section aria-labelledby="products-heading">
                    <h2 id="products-heading" class="mt-4 text-sm font-bold text-slate-800">Products</h2>
                    @if ($products->isNotEmpty())
                        <dl class="mt-2 divide-y divide-slate-100">
                            @foreach ($products as $product)
                                <div class="flex gap-3 py-2 text-sm"><dt class="min-w-0 flex-1 break-words text-slate-600">{{ $product['label'] }}</dt><dd class="font-bold text-slate-900">{{ $product['quantity'] }}</dd></div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-2 text-sm leading-6 text-slate-600">No customer-visible product quantities are available for this plot.</p>
                    @endif
                    </section>
                    <p class="mt-4 text-sm leading-6 text-slate-600">Source information and portal date requests are separate. An import does not replace your requested dates or history.</p>
                    @if ($overview->plot->synchronised_at)
                        <p class="mt-2 text-xs text-slate-500">Last source update: {{ $overview->plot->synchronised_at->format('j M Y, H:i') }}</p>
                    @endif
                </details>
            </aside>
        </div>
    </section>
</x-layouts.portal>
