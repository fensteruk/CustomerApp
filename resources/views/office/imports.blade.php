<x-layouts.portal title="Import Source Data | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace">
        <a class="admin-back" href="{{ $site ? route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid']]) : route('office.workspace.customers.index') }}">← {{ $site ? $site['name'] : 'Customers' }}</a>
        <header><p class="eyebrow">Imports</p><h1 class="admin-title">Import Source Data</h1></header>
        @if (Route::has('development.import-studio.show') && config('import-demo.enabled'))
            <section class="admin-card max-w-2xl border-2 border-orange-400" aria-labelledby="import-demo-title">
                <div class="flex flex-wrap gap-2"><span class="status status-red">DEMO ONLY</span><span class="status status-slate">SYNTHETIC DATA</span></div>
                <h2 id="import-demo-title" class="section-title mt-4">Explore the synthetic Import Studio walkthrough</h2>
                <p class="mt-3 leading-7 text-slate-600">A precomputed fictional example demonstrates the future review journey. There is no upload, persistence or commit.</p>
                <a class="primary-button mt-5" href="{{ route('development.import-studio.show') }}">New Import <span class="sr-only">— synthetic demo</span></a>
                <p class="mt-3 text-sm font-bold text-orange-900">NO DATA WILL BE SAVED</p>
            </section>
        @else
        <section class="admin-card max-w-2xl" aria-labelledby="import-coming-title">
            <span class="status status-slate">Not available yet</span>
            <h2 id="import-coming-title" class="section-title mt-4">Import Studio is being prepared</h2>
            <p class="mt-3 leading-7 text-slate-600">You’ll be able to upload a RedZebra export, review Wald’s interpretation and preview changes before committing.</p>
            @if ($site)<p class="mt-4 [overflow-wrap:anywhere]">Site: <strong>{{ $site['name'] }}</strong><br>Customer: {{ $site['customer']['name'] }}</p>@endif
            <p class="mt-4 text-sm font-semibold text-slate-700">Uploading and committing source data are not available here yet.</p>
        </section>
        @endif
    </div>
</x-layouts.portal>
