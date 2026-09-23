<x-layouts.portal title="Users | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace bg-slate-50" data-users-workspace>
        <header class="admin-page-header items-center">
            <div><p class="eyebrow">Office administration</p><h1 class="admin-title">Users</h1><p class="admin-intro">Manage portal users and their access to customers and sites.</p></div>
            <a class="primary-button" href="{{ route('office.workspace.users.create') }}"><span aria-hidden="true" class="text-xl">+</span> Add user</a>
        </header>
        @include('office.partials.feedback')
        <section aria-label="All user accounts" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([['total', 'Total users', 'people'], ['office', 'Office users', 'office'], ['external', 'External users', 'person'], ['attention', 'Needs attention', 'warning']] as [$key, $label, $icon])
                <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                    <span @class(['hidden h-11 w-11 shrink-0 items-center justify-center rounded-lg min-[390px]:flex', 'bg-amber-50 text-amber-700' => $key === 'attention', 'bg-sky-50 text-sky-700' => $key !== 'attention'])>@include('office.users.icon', ['icon' => $icon])</span>
                    <dl class="min-w-0"><dt class="text-xs font-medium text-slate-600 sm:text-sm">{{ $label }}</dt><dd class="mt-0.5 text-2xl font-bold tracking-tight text-slate-950" data-user-summary="{{ $key }}">{{ number_format($summary[$key]) }}</dd></dl>
                </div>
            @endforeach
        </section>
        <p class="!mt-2 text-xs leading-5 text-slate-500">All accounts, including inactive users. Needs attention counts external users without a customer or site assignment.</p>
        <section aria-label="Users and access" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-5">
            <form method="GET" action="{{ route('office.workspace.users.index') }}" role="search" aria-label="Filter users" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0"><label class="form-label" for="user-search">Search users</label><input class="form-input !mt-1 min-w-0" type="search" id="user-search" name="search" maxlength="100" value="{{ $search }}" placeholder="Name or email"></div>
                <div class="min-w-0"><label class="form-label" for="user-role-filter">Role</label><select class="form-input !mt-1 min-w-0" id="user-role-filter" name="role"><option value="">All roles</option>@foreach (\App\Enums\PortalRoleIdentifier::cases() as $option)<option value="{{ $option->value }}" @selected($role === $option->value)>{{ $option->label() }}</option>@endforeach</select></div>
                <div class="min-w-0"><label class="form-label" for="user-customer-filter">Customer</label><select class="form-input !mt-1 min-w-0" id="user-customer-filter" name="customer"><option value="">All customers</option>@foreach ($customers as $customer)<option value="{{ $customer->uuid }}" @selected($customerFilter === $customer->uuid)>{{ $customer->name }}{{ $customer->is_active ? '' : ' — inactive' }}</option>@endforeach</select></div>
                <div class="min-w-0"><label class="form-label" for="user-active">Status</label><select class="form-input !mt-1" id="user-active" name="active"><option value="all" @selected($active === 'all')>All statuses</option><option value="active" @selected($active === 'active')>Active</option><option value="inactive" @selected($active === 'inactive')>Inactive</option></select></div>
                <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                    <button class="secondary-button !min-h-11 !px-4 !py-2 !text-sm" type="submit">Apply filters</button>
                    <a class="admin-link-button" href="{{ route('office.workspace.users.index') }}">Clear filters</a>
                    <p class="w-full text-sm text-slate-500 sm:ml-auto sm:w-auto" role="status">{{ number_format($users->total()) }} {{ Str::plural('user', $users->total()) }} found @if ($users->total() > 0) <span class="whitespace-nowrap">· Showing {{ $users->firstItem() }}–{{ $users->lastItem() }}</span>@endif</p>
                </div>
            </form>
            <div class="mt-5 grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-user-cards>
                @forelse ($users as $user)
                    @include('office.users.card')
                @empty
                    <div class="empty-state md:col-span-2 xl:col-span-3"><h2 class="section-title">No users found</h2><p class="mt-2 text-sm leading-6">Try another search or clear your filters to see all users.</p></div>
                @endforelse
            </div>
            @if ($users->hasPages())<div class="mt-6 border-t border-slate-100 pt-5">{{ $users->links() }}</div>@endif
        </section>
    </div>
</x-layouts.portal>
