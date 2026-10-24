<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#064e3b">
    <title>@yield('title', 'Alumni Portal') · {{ config('app.name') }}</title>
    {{-- Fonts are self-hosted by the Vite build (vite.config.js): no wait on another website before text shows. --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f6f7f6] text-slate-800 font-sans min-h-screen flex flex-col selection:bg-brand-200">
<div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[420px] bg-[radial-gradient(60%_60%_at_50%_0%,rgb(16_185_129/.12),transparent)]"></div>

@php($user = auth()->user())

<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/75 backdrop-blur-xl">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
            <span class="grid place-items-center w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-brand-800 text-white text-sm font-extrabold shadow-xs shadow-brand-900/20">H</span>
            <span class="font-extrabold tracking-tight text-slate-900 text-lg">{{ config('app.name') }}</span>
        </a>

        {{--
            Wide screens: plain links, and a dropdown for each labelled section. The links come from App\Services\MainMenu.
            Guests have no menu bar: their few links sit next to Login on the right instead.
        --}}
        @auth
        <nav class="hidden lg:flex items-center gap-0.5 text-sm font-medium" aria-label="Main">
            @foreach ($menuSections as $section)
                @if ($section['label'] === null)
                    @foreach ($section['links'] as $link)
                        <x-menu-link :link="$link" variant="bar" />
                    @endforeach
                @else
                    <details class="relative" data-dropdown>
                        <summary class="list-none cursor-pointer inline-flex items-center gap-1 whitespace-nowrap rounded-full px-3 py-1.5 transition {{ $section['isActive'] ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                            {{ $section['label'] }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="absolute left-0 mt-2 w-52 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl z-50">
                            @foreach ($section['links'] as $link)
                                <x-menu-link :link="$link" variant="dropdown" />
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>
        @endauth

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
                {{-- Guest links (Memories ⚡), then Login. On phones both move into the ☰ menu, so Register and ☰ fit next to the name. --}}
                @foreach ($menuSections as $section)
                    @foreach ($section['links'] as $link)
                        <x-menu-link :link="$link" variant="beside-login" />
                    @endforeach
                @endforeach
                <a href="{{ route('login') }}" class="hidden sm:inline-block rounded-full px-4 py-1.5 text-slate-700 hover:bg-slate-100 transition">Login</a>
                <a href="{{ route('register') }}" class="rounded-full bg-slate-900 px-4 py-1.5 text-white hover:bg-brand-800 transition">Register</a>
            @endauth

            {{-- The same links in the ☰ menu, labelled sections as headed groups: up to tablets when logged in, only on phones for guests. --}}
            <details @class(['relative', 'lg:hidden' => $user, 'sm:hidden' => ! $user]) data-dropdown>
                <summary class="list-none cursor-pointer grid place-items-center w-9 h-9 rounded-full border border-slate-200 bg-white text-slate-700" aria-label="Menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </summary>
                <div class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                    @auth
                        <div class="border-b border-slate-100 mb-2 px-3 pt-1 pb-2">
                            <div class="text-xs text-slate-500">Logged in as</div>
                            <div class="break-words text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                            @if ($user->adminBadge())
                                <div class="text-xs font-semibold text-brand-700">🛡️ {{ $user->adminBadge() }}</div>
                            @endif
                        </div>
                    @endauth
                    @foreach ($menuSections as $section)
                        @if ($section['label'] !== null)
                            <div class="px-3 pt-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $section['label'] }}</div>
                        @endif
                        @foreach ($section['links'] as $link)
                            <x-menu-link :link="$link" variant="panel" />
                        @endforeach
                    @endforeach
                    @auth
                        <form method="POST" action="{{ route('logout') }}" class="sm:hidden border-t border-slate-100 mt-2 pt-2">@csrf
                            <button class="w-full text-left rounded-xl px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Logout</button>
                        </form>
                    @else
                        <div class="sm:hidden border-t border-slate-100 mt-2 pt-2">
                            <a href="{{ route('login') }}" class="block rounded-xl px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Login</a>
                        </div>
                    @endauth
                </div>
            </details>
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
{{-- Connection notices from resources/js/network-status.js (offline, back online, form not sent). --}}
<div data-network-status hidden role="status" aria-live="polite" class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-md rounded-2xl px-4 py-3 text-sm font-medium shadow-xl"></div>
</body>
</html>
