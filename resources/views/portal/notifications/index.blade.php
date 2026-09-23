<x-layouts.portal title="Notifications | Fenster Customer Portal">
    @include('office.partials.support-styles')
    <section class="support-workspace" aria-labelledby="page-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Customer Portal updates</p>
                <h1 id="page-title" class="page-title">Notifications</h1>
                <p class="support-intro">Follow requests and date decisions for the sites you can access. Open an update to see its current details.</p>
                <p class="support-count">{{ number_format($unreadCount) }} unread {{ str('update')->plural($unreadCount) }}</p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('portal.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="secondary-button w-full sm:w-auto">Mark all as read</button>
                </form>
            @endif
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($notifications->isEmpty())
            <div class="support-empty mt-8" role="status">
                <div class="flex items-start gap-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-sky-100 text-sky-800" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9a6 6 0 0 0-12 0v.75a8.967 8.967 0 0 1-2.31 6.022c1.733.64 3.55 1.08 5.454 1.31m5.713 0a24.255 24.255 0 0 1-5.713 0m5.713 0a3 3 0 1 1-5.713 0" /></svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">No notifications yet</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Updates about requests and date decisions will appear here.</p>
                        <a class="secondary-button mt-4" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </div>
            </div>
        @else
            <ol class="mt-8 space-y-4" aria-label="Your notifications">
                @foreach ($notifications as $notification)
                    <li>
                        <article class="support-notification {{ $notification['read_at'] ? 'support-notification-read' : 'support-notification-unread' }}" aria-labelledby="notification-{{ $notification['uuid'] }}">
                            <div class="flex items-start gap-4">
                                <span class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $notification['read_at'] ? 'bg-slate-300' : 'bg-sky-700' }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="text-xs font-extrabold uppercase tracking-wide text-slate-600">{{ $notification['type_label'] }}</p>
                                            <h2 id="notification-{{ $notification['uuid'] }}" class="mt-1 text-lg font-bold text-slate-900">{{ $notification['message'] }}</h2>
                                        </div>
                                        @if (! $notification['read_at'])
                                            <span class="status status-amber">Unread</span>
                                        @else
                                            <span class="status status-slate">Read</span>
                                        @endif
                                    </div>

                                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                        <div>
                                            <dt class="font-semibold text-slate-500">Site</dt>
                                            <dd class="mt-1 font-bold text-slate-900">{{ $notification['site_name'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold text-slate-500">Plot</dt>
                                            <dd class="mt-1 font-bold text-slate-900">{{ $notification['plot_reference'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold text-slate-500">Service</dt>
                                            <dd class="mt-1 font-bold text-slate-900">{{ \App\Enums\CallOffServiceType::tryFrom((string) $notification['service_identifier'])?->label() ?? 'Call-off' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold text-slate-500">Date and time</dt>
                                            <dd class="mt-1 font-bold text-slate-900"><time datetime="{{ $notification['created_at'] }}">{{ $notification['created_at_label'] }}</time></dd>
                                        </div>
                                    </dl>

                                    @if ($notification['customer_response'])
                                        <p class="mt-4 rounded-lg border border-slate-200 bg-white p-3 text-sm leading-6 text-slate-700"><span class="font-bold text-slate-900">Fenster response:</span> {{ $notification['customer_response'] }}</p>
                                    @endif

                                    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <a href="{{ $notification['open_url'] }}" class="primary-button w-full sm:w-auto">Open update<span class="sr-only">: {{ $notification['plot_reference'] }} · {{ $notification['site_name'] }}</span></a>
                                        @if (! $notification['read_at'])
                                            <form method="POST" action="{{ route('portal.notifications.read', $notification['uuid']) }}">
                                                @csrf
                                                <button type="submit" class="secondary-button w-full sm:w-auto">Mark as read</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('portal.notifications.dismiss', $notification['uuid']) }}">
                                            @csrf
                                            <button type="submit" class="min-h-12 w-full rounded-lg px-4 py-3 text-base font-bold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-600 sm:w-auto">Dismiss</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ol>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
</x-layouts.portal>
