<x-layouts.portal title="Import Source Data | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace">
        <a class="admin-back" href="{{ $site ? route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid']]) : route('office.workspace.customers.index') }}">← {{ $site ? $site['name'] : 'Customers' }}</a>
        <header><p class="eyebrow">Coming next</p><h1 class="admin-title">Import Source Data</h1></header>
        <section class="admin-card max-w-2xl" aria-labelledby="import-coming-title">
            <span class="status status-slate">Not available yet</span>
            <h2 id="import-coming-title" class="section-title mt-4">Import Studio is being prepared</h2>
            <p class="mt-3 leading-7 text-slate-600">You’ll be able to upload a RedZebra export, review Wald’s interpretation and preview changes before committing.</p>
            @if ($site)<p class="mt-4 [overflow-wrap:anywhere]">Site: <strong>{{ $site['name'] }}</strong><br>Customer: {{ $site['customer']['name'] }}</p>@endif
            <p class="mt-4 text-sm font-semibold text-slate-700">Uploading and committing source data are not available here yet.</p>
        </section>
    </div>
</x-layouts.portal>
