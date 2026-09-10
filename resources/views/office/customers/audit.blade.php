<x-layouts.portal :title="'Customer activity | '.$customer['name']" sidebar-label="Menu">
    <div class="admin-workspace">
        <a class="admin-back" href="{{ route('office.workspace.customers.show', $customer['uuid']) }}">← {{ $customer['name'] }}</a>
        <header><p class="eyebrow">Customer</p><h1 class="admin-title">Activity history</h1></header>
        @include('office.partials.audit')
    </div>
</x-layouts.portal>
