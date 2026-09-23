<div class="flex h-full min-h-0 flex-col">
    <div class="flex min-h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-700 px-4">
        <a href="{{ route('dashboard') }}" class="flex min-h-11 flex-col justify-center rounded focus:outline-none focus:ring-2 focus:ring-sky-300">
            <span class="block text-2xl font-light tracking-tight text-white">fenster</span>
            <span class="block text-xs font-bold uppercase tracking-[0.16em] text-sky-300">Customer Portal</span>
        </a>
        <button
            x-ref="sidebarClose"
            type="button"
            class="grid min-h-11 min-w-11 place-items-center rounded-lg text-slate-200 hover:bg-slate-800 hover:text-white xl:hidden"
            @click="closeSidebar()"
            aria-label="Close menu"
        >
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain px-3 py-4">
        <nav aria-label="Primary navigation">
            <p class="px-3 text-xs font-extrabold uppercase tracking-[0.16em] text-slate-400">Workspace</p>
            <ul class="mt-2 space-y-1">
                @if (auth()->user()->isFensterOfficeStaff())
                    <li>
                        <a href="{{ route('dashboard') }}"
                            @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('dashboard')])
                            @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                            <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a
                            href="{{ route('portal.review-requests') }}"
                            @class([
                                'portal-nav-link',
                                'portal-nav-link-active' => request()->routeIs('portal.review-requests*'),
                            ])
                            @if (request()->routeIs('portal.review-requests*')) aria-current="page" @endif
                        >
                            <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span>Review Requests</span>
                        </a>
                    </li>
                    @can('viewAny', \App\Models\CustomerOrganisation::class)
                        <li>
                            <a href="{{ route('office.workspace.amendments.index') }}" @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('office.workspace.amendments.*')]) @if(request()->routeIs('office.workspace.amendments.*')) aria-current="page" @endif>
                                <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6ZM14 2v6h6M8 13h8M8 17h5"/></svg><span>Amendments</span>
                            </a>
                        </li>
                        <li class="portal-nav-heading" role="presentation">Customers &amp; access</li>
                        <li>
                            <a
                                href="{{ route('office.workspace.customers.index') }}"
                                @class([
                                    'portal-nav-link',
                                    'portal-nav-link-active' => request()->routeIs('office.workspace.customers.*', 'office.workspace.sites.*') && ! request()->routeIs('development.import-studio.*'),
                                ])
                                @if (request()->routeIs('office.workspace.customers.*', 'office.workspace.sites.*') && ! request()->routeIs('development.import-studio.*')) aria-current="page" @endif
                            >
                                <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V3h10v18M15 9h4v12M8 7h4M8 11h4M8 15h4" /></svg>
                                <span>Customers</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('office.workspace.users.index') }}" @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('office.workspace.users.*')]) @if(request()->routeIs('office.workspace.users.*')) aria-current="page" @endif>
                                <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span>
                            </a>
                        </li>
                        <li class="portal-nav-heading" role="presentation">Tools</li>
                        @if (app(\App\SourceImport\Integration\WaldPilotAvailability::class)->enabled())
                            <li>
                                <a
                                    href="{{ route('office.workspace.imports') }}"
                                    @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('office.workspace.imports', 'office.workspace.pilot-import.*')])
                                    @if (request()->routeIs('office.workspace.imports', 'office.workspace.pilot-import.*')) aria-current="page" @endif
                                >
                                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m-4-4 4 4 4-4M4 15v5h16v-5" /></svg>
                                    <span>Imports</span>
                                </a>
                            </li>
                        @endif
                        <li>
                            <a
                                href="{{ route('office.workspace.settings.wald') }}"
                                @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('office.workspace.settings.*')])
                                @if (request()->routeIs('office.workspace.settings.*')) aria-current="page" @endif
                            >
                                <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 0 0-.1-1.22l2.03-1.58-2-3.46-2.5 1a7.54 7.54 0 0 0-2.1-1.22L14.45 3h-4l-.38 2.52a7.54 7.54 0 0 0-2.1 1.22l-2.5-1-2 3.46 2.03 1.58A7.5 7.5 0 0 0 5.4 12c0 .41.03.82.1 1.22L3.47 14.8l2 3.46 2.5-1a7.54 7.54 0 0 0 2.1 1.22l.38 2.52h4l.38-2.52a7.54 7.54 0 0 0 2.1-1.22l2.5 1 2-3.46-2.03-1.58c.07-.4.1-.81.1-1.22Z"/></svg>
                                <span>Settings</span>
                            </a>
                        </li>
                    @endcan
                @else
                    <li>
                        <a
                            href="{{ route('portal.site-dashboard') }}"
                            @class([
                                'portal-nav-link',
                                'portal-nav-link-active' => request()->routeIs('portal.site-dashboard', 'portal.plots.*', 'portal.call-offs.*') && ! request()->routeIs('portal.call-offs.trash'),
                            ])
                            @if (request()->routeIs('portal.site-dashboard', 'portal.plots.*', 'portal.call-offs.*') && ! request()->routeIs('portal.call-offs.trash')) aria-current="page" @endif
                        >
                            <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5v13.5H3.75zM3.75 9.75h16.5M8.25 9.75v9" /></svg>
                            <span>Plots &amp; Call-Offs</span>
                        </a>
                    </li>
                    <li>
                        <a
                            href="{{ route('portal.call-offs.trash') }}"
                            @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('portal.call-offs.trash')])
                            @if (request()->routeIs('portal.call-offs.trash')) aria-current="page" @endif
                        >
                            <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.1 1.02.16m-1.02-.16L18.16 19.67A2.25 2.25 0 0 1 15.92 21H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.11 48.11 0 0 0-3.48-.4m-12 .56c.34-.06.68-.11 1.02-.16m0 0a48.11 48.11 0 0 1 3.48-.4m7.5 0V4.48c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.91m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                            <span>Trash</span>
                        </a>
                    </li>
                @endif
                <li>
                    <a
                        href="{{ route('portal.notifications.centre') }}"
                        @class(['portal-nav-link', 'portal-nav-link-active' => request()->routeIs('portal.notifications.centre')])
                        @if (request()->routeIs('portal.notifications.centre')) aria-current="page" @endif
                    >
                        <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.86 17.08a23.85 23.85 0 0 0 5.45-1.31A8.97 8.97 0 0 1 18 9.75V9a6 6 0 0 0-12 0v.75a8.97 8.97 0 0 1-2.31 6.02 23.85 23.85 0 0 0 5.45 1.31m5.72 0a24.26 24.26 0 0 1-5.72 0m5.72 0a3 3 0 1 1-5.72 0" /></svg>
                        <span>Notifications</span>
                        @if ($notificationUnreadCount > 0)
                            <span class="ml-auto rounded-full bg-amber-300 px-2 py-0.5 text-xs font-extrabold text-slate-950" aria-label="{{ $notificationUnreadCount }} unread">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </nav>


    </div>

    <div class="portal-account shrink-0 border-t border-slate-700 p-4 text-sm" aria-label="Your account">
        <p class="font-bold text-white">{{ auth()->user()->name }}</p>
        <p class="mt-0.5 text-xs text-slate-400">{{ auth()->user()->portalRole?->name }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-600 px-3 py-2 text-sm font-bold text-slate-100 hover:bg-slate-800">Log out</button>
        </form>
    </div>
</div>
