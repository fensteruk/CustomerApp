<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="portal-notifications-index" content="{{ route('portal.notifications.index') }}">
    <meta name="portal-notifications-read-all" content="{{ route('portal.notifications.read-all') }}">
    <title>{{ $title ?? 'Fenster Customer Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-slate-900 antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="border-b border-slate-800 bg-slate-900 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-baseline gap-2 rounded focus:outline-none focus:ring-2 focus:ring-sky-300">
                <span class="text-3xl font-light tracking-tight">fenster</span>
                <span class="text-sm font-medium text-sky-300">Customer Portal</span>
            </a>
            @auth
                <div class="flex items-center gap-3" x-data="notificationBell({{ (int) ($notificationUnreadCount ?? 0) }})" @keydown.escape.window="close()">
                    <div class="relative">
                        <button x-ref="bellButton" type="button" class="relative grid min-h-12 min-w-12 place-items-center rounded-lg text-slate-100 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-300" @click="toggle()" x-effect="$el.setAttribute('aria-label', ariaLabel())" :aria-expanded="open.toString()" aria-controls="notification-panel" aria-label="Notifications, {{ $notificationUnreadCount > 0 ? $notificationUnreadCount.' unread' : 'no unread notifications' }}">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9a6 6 0 0 0-12 0v.75a8.967 8.967 0 0 1-2.31 6.022c1.733.64 3.55 1.08 5.454 1.31m5.713 0a24.255 24.255 0 0 1-5.713 0m5.713 0a3 3 0 1 1-5.713 0" />
                            </svg>
                            <span x-cloak x-show="unreadCount > 0" x-text="badgeLabel()" class="absolute right-0 top-0 min-w-5 rounded-full bg-amber-400 px-1.5 py-0.5 text-center text-xs font-extrabold leading-4 text-slate-950" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                        </button>
                        <noscript>
                            <a href="{{ route('portal.notifications.centre') }}" class="ml-2 rounded px-2 py-3 text-sm font-bold text-sky-200 underline underline-offset-2">Notifications{{ $notificationUnreadCount > 0 ? ' ('.$notificationUnreadCount.' unread)' : '' }}</a>
                        </noscript>

                        <div id="notification-panel" x-cloak x-show="open" x-transition @click.outside="close()" class="absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-xl" role="region" aria-label="Recent notifications">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                                <div>
                                    <h2 class="text-base font-bold">Notifications</h2>
                                    <p class="text-xs text-slate-600" x-text="unreadCount ? `${unreadCount} unread` : 'All caught up'"></p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="min-h-11 rounded px-2 text-sm font-bold text-sky-800 hover:bg-sky-50" x-show="unreadCount > 0" @click="markAllRead()">Mark all read</button>
                                    <button x-ref="closeButton" type="button" class="grid min-h-11 min-w-11 place-items-center rounded text-slate-600 hover:bg-slate-100 hover:text-slate-900" @click="close()" aria-label="Close notifications">
                                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-[min(24rem,60vh)] overflow-y-auto p-2">
                                <p x-show="loading" class="px-3 py-6 text-center text-sm text-slate-600">Loading notifications…</p>
                                <p x-show="!loading && notifications.length === 0" class="px-3 py-6 text-center text-sm text-slate-600">You have no notifications.</p>
                                <template x-for="notification in notifications" :key="notification.uuid">
                                    <article class="rounded-lg p-3" :class="notification.read_at ? 'bg-white' : 'bg-sky-50'">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="notification.read_at ? 'bg-slate-300' : 'bg-sky-700'" aria-hidden="true"></span>
                                            <div class="min-w-0 flex-1">
                                                <span class="sr-only" x-text="notification.read_at ? 'Read notification' : 'Unread notification'"></span>
                                                <p class="text-xs font-extrabold uppercase tracking-wide text-slate-600" x-text="notification.type_label"></p>
                                                <a :href="notification.open_url" @click="close()" class="mt-1 block text-sm font-bold leading-5 text-slate-900 underline decoration-slate-300 underline-offset-2 hover:decoration-sky-700" x-text="notification.message"></a>
                                                <time class="mt-1 block text-xs text-slate-600" :datetime="notification.created_at" x-text="notification.created_at_label"></time>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <button type="button" x-show="!notification.read_at" @click="markRead(notification.uuid)" class="min-h-10 rounded px-2 text-xs font-bold text-sky-800 hover:bg-sky-100">Mark as read</button>
                                                    <button type="button" @click="dismiss(notification.uuid)" class="min-h-10 rounded px-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Dismiss</button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>
                            <a href="{{ route('portal.notifications.centre') }}" class="block border-t border-slate-200 px-4 py-3 text-center text-sm font-bold text-sky-800 hover:bg-sky-50">View all notifications</a>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-12 rounded px-2 text-sm font-semibold text-slate-200 hover:text-white focus:outline-none focus:ring-2 focus:ring-sky-300">Log out</button>
                    </form>
                </div>
            @else
                <span class="hidden text-sm text-slate-300 sm:block">Simple call-off requests and updates</span>
            @endauth
        </div>
    </header>
    <main id="main-content">{{ $slot }}</main>
</body>
</html>
