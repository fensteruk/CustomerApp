@php
    $bindingAvailable = ($items['availability'] ?? null) === 'AVAILABLE';
    $hasActiveBinding = $bindingAvailable && ($items['has_active_binding'] ?? false);
@endphp
<section aria-labelledby="overview-title">
    <h2 id="overview-title" class="section-title">Overview</h2>
    <dl class="admin-card mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <div><dt class="admin-term">Customer</dt><dd class="admin-value"><a class="admin-text-link" href="{{ route('office.workspace.customers.show', $site['customer']['uuid']) }}">{{ $site['customer']['name'] }}</a></dd></div>
        <div><dt class="admin-term">Site status</dt><dd class="mt-2">@include('office.partials.status', ['active' => $site['is_active']])</dd></div>
        <div><dt class="admin-term">External access</dt><dd class="admin-value">{{ $site['effective_is_active'] ? 'Available to active assigned users' : 'Blocked' }}</dd></div>
        <div><dt class="admin-term">Location</dt><dd class="admin-value">{{ $site['location'] ?: 'Not added' }}</dd></div>
        <div><dt class="admin-term">Plots</dt><dd class="admin-value">{{ $site['plot_count'] }}</dd></div>
        <div><dt class="admin-term">Assigned users</dt><dd class="admin-value">{{ $site['assignment_count'] }}</dd></div>
        <div><dt class="admin-term">Source reference</dt><dd class="admin-value [overflow-wrap:anywhere]">{{ $site['source_reference']['identifier'] ?? 'Not recorded' }}</dd></div>
        <div><dt class="admin-term">Source binding</dt><dd class="admin-value">{{ $bindingAvailable ? ($hasActiveBinding ? 'Linked' : 'Not linked') : 'Not available yet' }}</dd></div>
    </dl>
    <div class="admin-card mt-4">
        <h3 class="font-bold">Source binding</h3>
        @if (! $bindingAvailable)
            <p class="mt-2 text-sm text-slate-600">Source binding information is not available yet.</p>
        @elseif ($hasActiveBinding)
            <p class="mt-2 text-sm text-slate-600">Linked to source data. View Source Binding for connection details.</p>
        @else
            <p class="mt-2 text-sm text-slate-600">Not linked. This site can exist before its source data is connected.</p>
        @endif
        <a class="admin-link-button mt-2" href="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid'], 'section' => 'source']) }}">View Source Binding</a>
    </div>
</section>
