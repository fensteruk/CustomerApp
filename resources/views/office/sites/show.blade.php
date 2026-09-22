<x-layouts.portal :title="$site['name'].' | Site Details | Fenster'" sidebar-label="Menu">
    <div class="admin-workspace">
        <a class="admin-back" href="{{ route('office.workspace.customers.show', $site['customer']['uuid']) }}">← {{ $site['customer']['name'] }}</a>
        <header class="admin-page-header">
            <div><p class="eyebrow">Site Details</p><h1 class="admin-title">{{ $site['name'] }}</h1><p class="admin-intro">{{ $site['location'] ?: 'No location added' }}</p></div>
            <div class="admin-actions">
                @if (app(\App\SourceImport\Integration\WaldPilotAvailability::class)->enabled() && $site['effective_is_active'])
                    <a class="primary-button" href="{{ route('office.workspace.imports', ['site' => $site['uuid']]) }}">Import Source Data <span class="sr-only">— weekend pilot</span></a>
                @elseif (Route::has('development.import-studio.site'))
                    <a class="primary-button" href="{{ route('development.import-studio.site', [$site['customer']['uuid'], $site['uuid']]) }}">Import Source Data <span class="sr-only">— demo only</span></a>
                @endif
                <a class="secondary-button" href="{{ route('office.workspace.sites.edit', [$site['customer']['uuid'], $site['uuid']]) }}">Edit Site</a>
                <a class="admin-link-button" href="{{ route('office.workspace.sites.lifecycle', [$site['customer']['uuid'], $site['uuid']]) }}">{{ $site['is_active'] ? 'Deactivate' : 'Reactivate' }}</a>
            </div>
        </header>
        @include('office.partials.feedback')
        <div class="border-t border-slate-200 pt-4 text-sm"><p>Deactivate keeps this site, its access relationships and history. Permanent deletion removes eligible disposable data and cannot be undone.</p><a class="mt-2 inline-block font-semibold text-red-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" href="{{ route('office.workspace.sites.delete-preview', [$site['customer']['uuid'], $site['uuid']]) }}">Delete permanently</a></div>
        @unless ($site['effective_is_active'])
            <div class="admin-notice">{{ ! $site['customer']['is_active'] ? 'The customer is inactive. External access to this site is blocked, even if the site itself is active.' : 'This site is inactive. External access is blocked.' }} Its records and history are retained.</div>
        @endunless
        <nav class="flex flex-wrap gap-2 border-b border-slate-300 pb-4" aria-label="Site sections">
            @foreach (['overview' => 'Overview', 'plots' => 'Plots', 'users' => 'Users', 'source' => 'Source Binding', 'imports' => 'Import History', 'audit' => 'Activity'] as $key => $label)
                <a href="{{ route('office.workspace.sites.show', [$site['customer']['uuid'], $site['uuid'], 'section' => $key]) }}" @class(['admin-section-link', 'bg-sky-700 text-white' => $section === $key, 'bg-white text-slate-700 hover:bg-sky-50' => $section !== $key]) @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @include('office.sites.'.$section)
    </div>
</x-layouts.portal>
