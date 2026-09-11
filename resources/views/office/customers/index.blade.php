<x-layouts.portal title="Customers | Fenster Customer Portal" sidebar-label="Menu">
    <x-slot:sidebar>
        @include('office.partials.filters', ['action' => route('office.workspace.customers.index'), 'label' => 'Customer filters'])
    </x-slot:sidebar>
    <div class="admin-workspace">
        <header class="admin-page-header">
            <div><p class="eyebrow">Office</p><h1 class="admin-title">Customers</h1><p class="admin-intro">Find a customer to manage their sites.</p></div>
            <a class="primary-button" href="{{ route('office.workspace.customers.create') }}">Add Customer</a>
        </header>
        @include('office.partials.feedback')
        <p class="text-sm text-slate-600">{{ $customers->total() }} {{ Str::plural('customer', $customers->total()) }}</p>
        <div class="admin-card-grid">
            @forelse ($customers as $customer)
                <article class="admin-card">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="admin-card-title"><a class="admin-text-link" href="{{ route('office.workspace.customers.show', $customer['uuid']) }}">{{ $customer['name'] }}</a></h2>
                        @include('office.partials.status', ['active' => $customer['is_active']])
                    </div>
                    <p class="mt-3 text-sm text-slate-600">{{ $customer['site_count'] }} {{ Str::plural('site', $customer['site_count']) }} · {{ $customer['active_site_count'] }} active</p>
                    <div class="admin-actions mt-4">
                        <a class="admin-link-button" href="{{ route('office.workspace.customers.show', $customer['uuid']) }}" aria-label="View {{ $customer['name'] }}">View Customer</a>
                        <a class="admin-link-button" href="{{ route('office.workspace.customers.edit', $customer['uuid']) }}" aria-label="Edit {{ $customer['name'] }}">Edit</a>
                    </div>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3">
                    <h2 class="section-title">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'No matching customers' : 'No customers yet' }}</h2>
                    <p class="mt-2">{{ $filters['search'] !== '' || $filters['active'] !== null ? 'Try another name or clear the filters in the menu.' : 'Add a customer, then create their first site.' }}</p>
                </div>
            @endforelse
        </div>
        {{ $customers->links() }}
    </div>
</x-layouts.portal>
