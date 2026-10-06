<x-layouts.portal title="Wald Integrity Check | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace min-w-0">
        <a class="secondary-button w-fit" href="{{ route('office.workspace.settings.wald') }}">Back to Wald settings</a>
        <header><p class="eyebrow">Office only · Read-only</p><h1 class="admin-title">Wald integrity check</h1><p class="admin-intro">Check source bindings against the customer/site hierarchy and current retained evidence. This check changes no data.</p></header>
        <dl class="grid gap-4 sm:grid-cols-3">
            @foreach(['healthy' => 'Healthy bindings', 'warnings' => 'Warnings', 'blocking' => 'Blocking issues'] as $key => $label)
                <div class="admin-card"><dt class="text-sm font-semibold">{{ $label }}</dt><dd class="mt-2 text-3xl font-bold">{{ $report[$key] }}</dd></div>
            @endforeach
        </dl>
        <p class="text-sm text-slate-600">An absent source unit in a partial export is not evidence of deletion. Unverifiable or stale evidence is shown for review.</p>
        <div class="space-y-4">
            @forelse($items as $item)
                <article class="admin-card min-w-0 [overflow-wrap:anywhere]">
                    <div class="flex flex-wrap justify-between gap-3"><h2 class="section-title">CustomerCode {{ $item['customer_code'] }}</h2><span class="status {{ $item['blocking'] ? 'status-red' : ($item['category'] === 'HEALTHY' ? 'status-green' : 'status-slate') }}">{{ str($item['category'])->replace('_', ' ')->title() }}</span></div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-sm text-slate-600">Bound customer / site</dt><dd>{{ $item['customer'] ?? 'Missing customer' }} / {{ $item['site'] ?? 'Missing site' }}</dd></div><div><dt class="text-sm text-slate-600">Parsed source customer / site</dt><dd>{{ $item['source']['customer'] ?? 'Unavailable' }} / {{ $item['source']['site'] ?? 'Unavailable' }}</dd></div></dl>
                    <p class="mt-3 text-sm">{{ $item['reason'] }}</p><p class="mt-2 font-semibold">{{ $item['action'] }}</p>
                    @if($item['upload_uuid'] && $item['category'] !== 'HEALTHY')<a class="secondary-button mt-4" href="{{ route('office.workspace.pilot-import.show', $item['upload_uuid']) }}">Review stored import</a>@endif
                </article>
            @empty<p class="admin-card">No CustomerCode bindings require inspection.</p>@endforelse
        </div>
        {{ $items->links() }}
    </div>
</x-layouts.portal>
