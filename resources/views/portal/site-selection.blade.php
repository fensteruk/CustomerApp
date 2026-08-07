<x-layouts.portal title="Choose a site | Fenster Customer Portal">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8" aria-labelledby="page-title">
        <p class="eyebrow">Assigned sites</p>
        <h1 id="page-title" class="page-title">Choose your site</h1>
        <p class="page-intro">Select the site you are working on. Your dashboard will only use sites assigned to your portal account.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">
                We could not open that site. Please choose one of your assigned sites.
            </div>
        @endif

        @if ($activeSite)
            <aside class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-4" aria-label="Current active site">
                <p class="text-sm font-semibold text-sky-950">Current active site</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $activeSite->name }}</p>
            </aside>
        @endif

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($sites as $site)
                <form method="POST" action="{{ route('sites.active.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="site_id" value="{{ $site->id }}">
                    <button type="submit" class="site-card group w-full text-left" :disabled="submitting" :aria-busy="submitting">
                        <span class="site-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-bold">{{ $site->name }}</span>
                            <span class="mt-1 block text-sm text-slate-600">{{ $site->location ?? 'Assigned site' }}</span>
                            <span class="mt-2 block text-sm font-semibold text-slate-700">{{ $site->outstanding_projected_plots_count }} outstanding projected {{ Str::plural('plot', $site->outstanding_projected_plots_count) }}</span>
                        </span>
                        <span class="text-sm font-bold text-sky-800" x-text="submitting ? 'Loading' : 'Open'"></span>
                    </button>
                </form>
            @empty
                <div class="sm:col-span-2">
                    <div class="empty-state">
                        <h2 class="text-lg font-bold text-slate-900">No assigned sites</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Your account is active, but no sites have been assigned yet. Fenster will need to assign a site before a site dashboard can be shown.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.portal>
