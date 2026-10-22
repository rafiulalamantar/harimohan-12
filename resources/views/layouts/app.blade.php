<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#064e3b">
    <title>@yield('title', 'Alumni Portal') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f6f7f6] text-slate-800 font-sans min-h-screen flex flex-col selection:bg-brand-200">
<div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[420px] bg-[radial-gradient(60%_60%_at_50%_0%,rgb(16_185_129/.12),transparent)]"></div>

@php
    $user = auth()->user();
    // A student who is also an admin sees the portal menu on portal pages and the admin menu everywhere else.
    $showPortalNav = $user && (! $user->isAdmin() || ($user->isStudent() && request()->routeIs('portal.*')));
    $navLinks = $user
        ? (! $showPortalNav
            ? [
                ['admin.dashboard', 'Dashboard', 'admin.dashboard'],
                ['admin.students.index', 'Students', 'admin.students.*'],
                ['admin.student-fields.index', 'Student fields', 'admin.student-fields.*'],
                ['admin.announcements.index', 'Announcements', 'admin.announcements.*'],
                ['admin.events.index', 'Events', 'admin.events.*'],
                ['admin.payments.index', 'Payments', 'admin.payments.*'],
                ['admin.settings.edit', 'Welcome page', 'admin.settings.*'],
                ...($user->isStudent() ? [['portal.dashboard', 'My portal', 'portal.*']] : []),
            ]
            : [
                ['portal.dashboard', 'Home', 'portal.dashboard'],
                ['portal.announcements', 'Notice', 'portal.announcements*'],
                ['portal.payments', 'My Payments', 'portal.payments'],
                ['portal.profile', 'Profile', 'portal.profile'],
                ...($user->isAdmin() ? [['admin.dashboard', 'Admin panel', 'admin.*']] : []),
            ])
        : [];
@endphp

<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/75 backdrop-blur-xl">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
            <span class="grid place-items-center w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-brand-800 text-white text-sm font-extrabold shadow-xs shadow-brand-900/20">H</span>
            <span class="font-extrabold tracking-tight text-slate-900 text-lg">{{ config('app.name') }}</span>
        </a>

        <nav class="hidden xl:flex items-center text-sm font-medium">
            @foreach ($navLinks as [$route, $label, $pattern])
                <a href="{{ route($route) }}"
                   class="whitespace-nowrap rounded-full px-1.5 py-1.5 transition {{ request()->routeIs($pattern) ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2 text-sm font-semibold">
            @auth
                @if ($user->isAdmin())
                    <x-registration-bell :pending="$pendingRegistrations" :registrations="$latestRegistrations" />
                @endif
                @if ($badge = $user->adminBadge())
                    <span title="Logged in as {{ $user->name }}" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 whitespace-nowrap">🛡️<span class="sr-only min-[400px]:not-sr-only">{{ $badge }}</span></span>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf
                    <button class="rounded-full border border-slate-200 bg-white px-4 py-1.5 text-slate-700 hover:bg-slate-50 transition">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-full px-4 py-1.5 text-slate-700 hover:bg-slate-100 transition">Login</a>
                <a href="{{ route('register') }}" class="rounded-full bg-slate-900 px-4 py-1.5 text-white hover:bg-brand-800 transition">Register</a>
            @endauth

            @if ($navLinks)
                <details class="relative xl:hidden" data-dropdown>
                    <summary class="list-none cursor-pointer grid place-items-center w-9 h-9 rounded-full border border-slate-200 bg-white text-slate-700" aria-label="Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <div class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                        <div class="border-b border-slate-100 mb-2 px-3 pt-1 pb-2">
                            <div class="text-xs text-slate-500">Logged in as</div>
                            <div class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                            @if ($user->adminBadge())
                                <div class="text-xs font-semibold text-brand-700">🛡️ {{ $user->adminBadge() }}</div>
                            @endif
                        </div>
                        @foreach ($navLinks as [$route, $label, $pattern])
                            <a href="{{ route($route) }}"
                               class="block rounded-xl px-3 py-2 font-medium {{ request()->routeIs($pattern) ? 'bg-brand-50 text-brand-800' : 'text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
                        @endforeach
                        <form method="POST" action="{{ route('logout') }}" class="sm:hidden border-t border-slate-100 mt-2 pt-2">@csrf
                            <button class="w-full text-left rounded-xl px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Logout</button>
                        </form>
                    </div>
                </details>
            @endif
        </div>
    </div>
</header>

<main class="max-w-6xl w-full mx-auto px-4 py-8 flex-1">
    @foreach (['success' => 'bg-brand-50 border-brand-200 text-brand-900', 'info' => 'bg-sky-50 border-sky-200 text-sky-900', 'error' => 'bg-rose-50 border-rose-200 text-rose-800'] as $key => $cls)
        @if (session($key))
            <div class="mb-6 flex items-start gap-3 border rounded-2xl px-4 py-3 text-sm font-medium {{ $cls }}">
                @if ($key === 'success')
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @else
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v5m0 3h.01M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
                @endif
                <span>{{ session($key) }}</span>
            </div>
        @endif
    @endforeach
    @yield('content')
</main>

<footer class="border-t border-slate-200/70">
    <div class="max-w-6xl mx-auto px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
        <span>© {{ date('Y') }} {{ config('app.name') }} · Alumni Association</span>
        <span>Made with care for every batch · <a href="https://github.com/rafiulalamantar/harimohan-12/releases/tag/v{{ config('app.version') }}" class="hover:text-brand-700 hover:underline">v{{ config('app.version') }}</a></span>
    </div>
</footer>
</body>
</html>
