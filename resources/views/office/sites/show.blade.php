<x-layouts.portal :title="$site['name'].' | Site Details | Fenster'" sidebar-label="Menu">
    @include('site-workspace.styles')
    <div class="site-workspace">
        <a class="sw-back" href="{{ route('office.workspace.customers.show', $site['customer']['uuid']) }}">← {{ $site['customer']['name'] }}</a>
        <header class="sw-header">
            <div><h1>{{ $site['name'] }}</h1><p class="sw-customer">{{ $site['customer']['name'] }}</p><p class="sw-muted">{{ $site['location'] ?: 'No location added' }}</p><span @class(['sw-badge', 'sw-active' => $site['effective_is_active']])>{{ $site['effective_is_active'] ? 'Active site' : 'Inactive · External access blocked' }}</span></div>
            <div class="sw-actions"><a class="sw-button sw-primary" href="{{ route('portal.review-requests', ['site' => $reviewSiteId]) }}">Review requests</a><a class="sw-button" href="{{ route('office.workspace.sites.edit', [$site['customer']['uuid'], $site['uuid']]) }}">Edit Site</a></div>
        </header>
        @include('office.partials.feedback')
        @unless ($site['effective_is_active'])<div class="admin-notice">{{ ! $site['customer']['is_active'] ? 'The customer is inactive. External access to this site is blocked, even if the site itself is active.' : 'This site is inactive. External access is blocked.' }} Its records and history are retained.</div>@endunless
        @include('site-workspace.summary')
        <nav class="sw-tabs" aria-label="Site sections">
            @foreach (['overview' => 'Overview', 'plots' => 'Plots', 'users' => 'Users', 'source' => 'Source Binding', 'imports' => 'Import History', 'audit' => 'Activity'] as $key => $label)
                <a href="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid'], 'section' => $key]) }}" @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @include('office.sites.'.$section)
        @if (in_array($section, ['overview', 'plots'], true))@include('site-workspace.upcoming')@endif
        <details class="sw-disclosure sw-section">
            <summary>Site administration <span aria-hidden="true">⌄</span></summary>
            <div class="sw-actions">
                @if (app(\App\SourceImport\Integration\WaldPilotAvailability::class)->enabled() && $site['effective_is_active'])
                    <a class="sw-button" href="{{ route('office.workspace.imports', ['site' => $site['uuid']]) }}">Import Source Data <span class="sr-only">— weekend pilot</span></a>
                @elseif (Route::has('development.import-studio.site'))
                    <a class="sw-button" href="{{ route('development.import-studio.site', [$site['customer']['uuid'], $site['uuid']]) }}">Import Source Data <span class="sr-only">— demo only</span></a>
                @endif
                <a class="sw-button" href="{{ route('office.workspace.sites.lifecycle', [$site['customer']['uuid'], $site['uuid']]) }}">{{ $site['is_active'] ? 'Deactivate' : 'Reactivate' }}</a>
            </div>
            <p class="sw-muted mt-4">Deactivate keeps this site, its access relationships and history. Permanent deletion removes eligible disposable data and cannot be undone.</p>
            <a class="sw-button mt-2" href="{{ route('office.workspace.sites.delete-preview', [$site['customer']['uuid'], $site['uuid']]) }}">Delete permanently</a>
            <p class="sw-muted mt-4"><strong>Demo/test cleanup only.</strong> Purge removes explicitly certified test data, including associated Wald evidence. Do not use this for genuine site records.</p>
            <a class="sw-button mt-2" href="{{ route('office.workspace.sites.demo-purge-preview', [$site['customer']['uuid'], $site['uuid']]) }}">Purge demo/test data</a>
        </details>
    </div>
</x-layouts.portal>
