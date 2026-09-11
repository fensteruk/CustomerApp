<form method="GET" action="{{ $action }}" class="space-y-4" aria-label="{{ $label }}">
    <h2 class="sidebar-label">{{ $label }}</h2>
    <div>
        <label class="sidebar-label" for="admin-search">Search</label>
        <input class="sidebar-input" id="admin-search" name="search" type="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="{{ $placeholder ?? 'Search by name' }}">
    </div>
    <div>
        <label class="sidebar-label" for="admin-active">Status</label>
        <select class="sidebar-input" id="admin-active" name="active">
            <option value="all" @selected($filters['active'] === null)>All statuses</option>
            <option value="1" @selected($filters['active'] === true)>Active</option>
            <option value="0" @selected($filters['active'] === false)>Inactive</option>
        </select>
    </div>
    <button class="sidebar-button" type="submit">Apply filters</button>
    <a class="sidebar-clear" href="{{ $action }}">Clear filters</a>
</form>
