<article class="group relative flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-sky-300 hover:shadow-md" data-customer-card="{{ $customer['uuid'] }}" aria-labelledby="customer-{{ $customer['uuid'] }}">
    <div class="grid grid-cols-[3rem_minmax(0,1fr)] items-start gap-x-3 p-5 pb-3 sm:flex sm:gap-3">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-800">@include('office.customers.icon', ['icon' => 'building'])</span>
        <h3 id="customer-{{ $customer['uuid'] }}" class="min-w-0 flex-1 pt-2 text-base font-bold leading-6 text-slate-950 [overflow-wrap:anywhere]">{{ $customer['name'] }}</h3>
        <span class="col-start-2 mt-2 shrink-0 justify-self-start rounded-full px-2.5 py-1 text-xs font-semibold {{ $customer['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $customer['is_active'] ? 'Active' : 'Inactive' }}</span>
    </div>

    @if((int) $customer['site_count'] === 0)
        <div class="flex flex-1 flex-col items-center justify-center px-5 pb-5 pt-2 text-center">
            <span class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg border border-dashed border-slate-300 text-slate-400">@include('office.customers.icon', ['icon' => 'building'])</span>
            <p class="text-sm font-semibold text-slate-600">No sites yet</p>
            <p class="mt-1 text-sm leading-5 text-slate-500">{{ $customer['is_active'] ? 'Get started by adding a site to this customer.' : 'Open this customer to review their status before adding sites.' }}</p>
            @if($customer['is_active'])
                <a href="{{ route('office.workspace.sites.create', $customer['uuid']) }}" class="relative z-10 mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg bg-sky-50 px-4 text-sm font-bold text-sky-800 hover:bg-sky-100" aria-label="Add site to {{ $customer['name'] }}"><span aria-hidden="true">+</span> Add site <span aria-hidden="true">→</span></a>
            @endif
        </div>
    @else
        <dl class="grid flex-1 grid-cols-2 gap-x-3 gap-y-4 p-5 pt-3">
            @foreach([
                ['label' => Str::plural('Site', $customer['site_count']), 'count' => $customer['site_count'], 'icon' => 'building', 'tone' => 'sky'],
                ['label' => 'Active sites', 'count' => $customer['active_site_count'], 'icon' => 'check', 'tone' => 'green'],
                ['label' => Str::plural('Plot', $customer['plot_count']), 'count' => $customer['plot_count'], 'icon' => 'plots', 'tone' => 'sky'],
                ['label' => 'Need attention', 'count' => $customer['attention_count'], 'icon' => $customer['attention_count'] > 0 ? 'attention' : 'check', 'tone' => $customer['attention_count'] > 0 ? 'amber' : 'slate'],
            ] as $stat)
                <div class="flex min-w-0 items-center gap-2 rounded-lg {{ $stat['tone'] === 'amber' ? 'bg-amber-50 ring-1 ring-amber-100' : '' }}" @if($stat['label'] === 'Need attention') aria-describedby="customer-attention-help" @endif>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ match($stat['tone']) { 'green' => 'bg-emerald-50 text-emerald-700', 'amber' => 'text-amber-700', 'slate' => 'bg-slate-50 text-slate-500', default => 'bg-sky-50 text-sky-800' } }}">@include('office.customers.icon', ['icon' => $stat['icon']])</span>
                    <div class="flex min-w-0 flex-col-reverse py-1">
                        <dt class="text-xs leading-5 text-slate-600">{{ $stat['label'] }}</dt>
                        <dd class="text-lg font-bold leading-6 text-slate-900">{{ number_format($stat['count']) }}</dd>
                    </div>
                </div>
            @endforeach
        </dl>
    @endif

    <div class="rounded-b-2xl border-t border-slate-100 bg-slate-50/70 px-5 text-center">
        <a href="{{ route('office.workspace.customers.show', $customer['uuid']) }}" class="inline-flex min-h-12 items-center justify-center gap-2 py-3 text-sm font-bold text-sky-700 after:absolute after:inset-0 after:rounded-2xl group-hover:text-sky-900 focus-visible:after:ring-2 focus-visible:after:ring-sky-600 focus-visible:after:ring-offset-2" aria-label="Open customer {{ $customer['name'] }}">Open customer @include('office.customers.icon', ['icon' => 'arrow'])</a>
    </div>
</article>
