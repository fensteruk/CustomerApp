<x-layouts.portal title="Customers | Fenster Customer Portal" sidebar-label="Menu">
    <section class="mx-auto w-full min-w-0 max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 sm:py-8" aria-labelledby="customers-title" data-customer-workspace>
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <p class="eyebrow">Office</p>
                <h1 id="customers-title" class="mt-1 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Customers</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600 sm:text-base">Manage customers and the sites belonging to them.</p>
                <p class="mt-2 flex flex-wrap gap-x-2 gap-y-1 text-sm font-medium text-slate-600" aria-label="Workspace totals">
                    <span>{{ number_format($summary['customers']) }} {{ Str::plural('customer', $summary['customers']) }}</span><span aria-hidden="true">·</span>
                    <span>{{ number_format($summary['sites']) }} {{ Str::plural('site', $summary['sites']) }}</span><span aria-hidden="true">·</span>
                    <span>{{ number_format($summary['active_sites']) }} active {{ Str::plural('site', $summary['active_sites']) }}</span>
                </p>
            </div>
            <a class="primary-button gap-2" href="{{ route('office.workspace.customers.create') }}"><span aria-hidden="true" class="text-xl leading-none">+</span> Add customer</a>
        </header>

        @include('office.partials.feedback')

        <form method="GET" action="{{ route('office.workspace.customers.index') }}" aria-label="Customer filters" x-data class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-slate-100/60 p-3 sm:p-4">
            <div class="min-w-0 basis-full sm:min-w-64 sm:flex-1 sm:basis-auto">
                <label for="customer-search" class="mb-1.5 block text-xs font-bold text-slate-600">Search customers</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">@include('office.customers.icon', ['icon' => 'search'])</span>
                    <input id="customer-search" name="search" type="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="Search customers by name…" class="min-h-11 w-full rounded-lg border-slate-300 bg-white pl-10 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>
            <div class="min-w-36 flex-1 sm:w-36 sm:flex-none">
                <label for="customer-status" class="mb-1.5 block text-xs font-bold text-slate-600">Status</label>
                <select id="customer-status" name="active" @change="$el.form.requestSubmit()" class="min-h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm">
                    <option value="all" @selected($filters['active'] === null)>All statuses</option>
                    <option value="1" @selected($filters['active'] === true)>Active</option>
                    <option value="0" @selected($filters['active'] === false)>Inactive</option>
                </select>
            </div>
            <div class="min-w-36 flex-1 sm:w-40 sm:flex-none">
                <label for="customer-sort" class="mb-1.5 block text-xs font-bold text-slate-600">Sort</label>
                <select id="customer-sort" name="sort" @change="$el.form.requestSubmit()" class="min-h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm">
                    <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name (A–Z)</option>
                    <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name (Z–A)</option>
                </select>
            </div>
            <button type="submit" class="secondary-button w-full sm:w-auto">Search</button>
            @if($filters['search'] !== '' || $filters['active'] !== null || $filters['sort'] !== 'name_asc')
                <a href="{{ route('office.workspace.customers.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-sky-800 hover:bg-sky-50">Clear filters</a>
            @endif
        </form>

        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
            <h2 class="text-sm font-semibold text-slate-700">{{ number_format($customers->total()) }} {{ Str::plural('customer', $customers->total()) }}{{ $filters['search'] !== '' || $filters['active'] !== null ? ' found' : '' }}</h2>
            <p id="customer-attention-help" class="max-w-lg text-xs leading-5 text-slate-500">Needs attention: requests awaiting a Fenster response at active customers and sites.</p>
        </div>

        <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-customer-grid>
            @forelse ($customers as $customer)
                @include('office.customers.card', ['customer' => $customer])
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center md:col-span-2 xl:col-span-3">
                    <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-sky-50 text-sky-700">@include('office.customers.icon', ['icon' => 'building'])</span>
                    <h2 class="text-xl font-bold text-slate-900">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'No matching customers' : 'No customers yet' }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'Try another customer name or clear the filters.' : 'Add your first customer to start organising their sites.' }}</p>
                    @if($filters['search'] !== '' || $filters['active'] !== null)
                        <a class="secondary-button mt-5" href="{{ route('office.workspace.customers.index') }}">Clear filters</a>
                    @endif
                </div>
            @endforelse
        </div>
        {{ $customers->links() }}
    </section>
</x-layouts.portal>
