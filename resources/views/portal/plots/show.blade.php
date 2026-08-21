<x-layouts.portal title="{{ $overview->plot->plot_reference }} | Fenster Customer Portal">
    <section class="mx-auto max-w-5xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to plot overview</a>

        <div class="mt-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">{{ $overview->plot->plot_reference }}</h1>
                <p class="page-intro">Plot details and current customer-facing service status.</p>
            </div>
            <x-plot-overall-status :status="$overview->overallStatus" />
        </div>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="products-heading">
            <h2 id="products-heading" class="section-title">Products</h2>
            @if ($products->isNotEmpty())
                <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm font-bold text-slate-700">{{ $product->product_code }}</dt><dd class="mt-1 text-2xl font-bold text-slate-950">{{ rtrim(rtrim(number_format((float) $product->quantity, 3, '.', ''), '0'), '.') }}</dd></div>
                    @endforeach
                </dl>
            @else
                <p class="mt-3 text-sm leading-6 text-slate-700">No customer-visible product quantities are available for this plot.</p>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="services-heading">
            <div><h2 id="services-heading" class="section-title">Services</h2><p class="mt-1 text-sm text-slate-600">Services are shown in the normal site sequence. Each can be called off independently when eligible.</p></div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @foreach (App\Enums\CallOffServiceType::cases() as $serviceType)
                    @php($service = $overview->services[$serviceType->value])
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="service-{{ $serviceType->value }}">
                        <h3 id="service-{{ $serviceType->value }}" class="text-lg font-bold text-slate-950">{{ $serviceType->label() }}</h3>
                        <x-plot-service-status :service="$service" class="mt-4" />
                        <p class="mt-4 text-sm leading-6 text-slate-600">Detailed service history will be available here in a later Portal update.</p>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row"><a href="{{ route('portal.call-offs.create') }}" class="primary-button">Call Off</a><a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to plot overview</a></div>
    </section>
</x-layouts.portal>
