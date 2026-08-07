<x-layouts.portal title="Development role preview | Fenster Customer Portal">
    <div class="min-h-[calc(100vh-73px)] bg-slate-900 px-4 py-8 text-white sm:px-6 sm:py-12">
        <section class="mx-auto max-w-2xl" aria-labelledby="page-title">
            <div class="preview-notice" role="note">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m14.7 6.3 3 3M4.9 19.1l5.7-5.7m-4-5.7 2.5 2.5m4.5 4.5 2.5 2.5M14 4l6 6-4 4-6-6 4-4Z" stroke-width="1.8" stroke-linecap="round"/></svg>
                <span><strong>Development preview</strong><span aria-hidden="true"> - </span>uses controlled preview users only</span>
            </div>

            <h1 id="page-title" class="mt-7 text-3xl font-bold tracking-tight sm:text-4xl">Choose how you are viewing the Customer Portal</h1>
            <p class="mt-3 max-w-xl text-base leading-6 text-slate-200">Select a portal role to sign in as a local preview user.</p>

            <div class="mt-8">
                <h2 class="text-sm font-semibold text-slate-200">Demonstration role</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2" aria-label="Demonstration role">
                    @foreach ([
                        ['site_manager', 'Site Manager', 'Choose an assigned site and submit call-offs.', 'M'],
                        ['assistant_site_manager', 'Assistant Site Manager', 'Choose an assigned site and submit call-offs.', 'A'],
                        ['finishing_foreman', 'Finishing Foreman', 'Choose an assigned site and submit call-offs.', 'F'],
                        ['fenster_office_staff', 'Fenster Office Staff', 'Review submitted call-offs and publish a decision.', 'O'],
                    ] as [$value, $label, $description, $initial])
                        <form method="POST" action="{{ route('development.role-preview.store') }}">
                            @csrf
                            <input type="hidden" name="role" value="{{ $value }}">
                            <button type="submit" class="role-card w-full text-left">
                                <span class="role-initial" aria-hidden="true">{{ $initial }}</span>
                                <span>
                                    <span class="block font-semibold">{{ $label }}</span>
                                    <span class="mt-1 block text-sm leading-5 text-slate-600">{{ $description }}</span>
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-layouts.portal>
