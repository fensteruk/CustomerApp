<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="portal-notifications-index" content="{{ route('portal.notifications.index') }}">
    <meta name="portal-notifications-read-all" content="{{ route('portal.notifications.read-all') }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="min-h-screen bg-stone-100 text-slate-900 antialiased"
    @auth
        x-data="portalShell()"
        @keydown.escape.window="closeSidebar()"
    @endauth
>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    @auth
        <div
            x-cloak
            x-show="sidebarOpen && !desktop"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-slate-950/60 xl:hidden"
            @click="closeSidebar()"
            aria-hidden="true"
        ></div>

        <aside
            id="portal-sidebar"
            data-portal-sidebar
            x-ref="sidebar"
            class="fixed inset-y-0 left-0 z-50 w-[14.5rem] -translate-x-full overflow-hidden border-r border-slate-800 bg-slate-900 text-white shadow-2xl transition-transform duration-200 ease-out xl:translate-x-0 xl:shadow-none"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            :aria-hidden="(!desktop && !sidebarOpen).toString()"
            :inert="!desktop && !sidebarOpen"
            :role="desktop ? null : 'dialog'"
            :aria-modal="desktop ? null : 'true'"
            aria-label="Portal navigation and filters"
            @keydown="handleSidebarKeydown($event)"
        >
            @include('layouts.partials.portal-sidebar')
        </aside>

        <div class="min-h-screen xl:pl-[14.5rem]">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
                <div class="flex min-h-16 items-center gap-2 px-3 sm:px-4 xl:px-5">
                    <button
                        data-sidebar-trigger
                        x-ref="sidebarButton"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-bold text-slate-800 hover:bg-slate-100 xl:hidden"
                        @click="openSidebar()"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-controls="portal-sidebar"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        <span>{{ $sidebarLabel }}</span>
                        @if ($sidebarBadge !== null && (int) $sidebarBadge > 0)
                            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-extrabold text-sky-900">{{ $sidebarBadge }}</span>
                        @endif
                    </button>

                    <a href="{{ route('dashboard') }}" class="ml-1 hidden rounded min-[360px]:block xl:hidden">
                        <span class="text-xl font-light tracking-tight text-slate-950">fenster</span>
                    </a>

                    <div class="ml-auto flex items-center gap-1 sm:gap-2">
                        <div class="hidden text-right lg:block">
                            <p class="text-sm font-bold text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->portalRole?->name }}</p>
                        </div>

                        @include('layouts.partials.notification-bell')

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-lg px-3 text-sm font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-sky-600">Log out</button>
                        </form>
                    </div>
                </div>
            </header>
            <main id="main-content">{{ $slot }}</main>
        </div>
    @else
        <header class="border-b border-slate-800 bg-slate-900 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('login') }}" class="flex items-baseline gap-2 rounded focus:outline-none focus:ring-2 focus:ring-sky-300">
                    <span class="text-3xl font-light tracking-tight">fenster</span>
                    <span class="text-sm font-medium text-sky-300">Customer Portal</span>
                </a>
                <span class="hidden text-sm text-slate-300 sm:block">Simple call-off requests and updates</span>
            </div>
        </header>
        <main id="main-content">{{ $slot }}</main>
    @endauth
</body>
</html>
