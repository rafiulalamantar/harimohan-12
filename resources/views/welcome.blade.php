@extends('layouts.app')
@section('title', 'Welcome')

@section('content')
@php($heroBanner = \App\Models\Setting::bannerUrl($settings['hero_banner']))
<section class="relative overflow-hidden rounded-[2rem] bg-brand-950 text-white shadow-2xl shadow-brand-950/20 ring-1 ring-white/10">
    @if ($heroBanner)
        <img src="{{ $heroBanner }}" alt="" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-brand-950 via-brand-950/70 to-brand-950/10"></div>
    @else
        <div aria-hidden="true" class="absolute inset-0 bg-[radial-gradient(80%_70%_at_100%_0%,rgb(16_185_129/.45),transparent_60%),radial-gradient(60%_60%_at_0%_100%,rgb(5_150_105/.35),transparent_60%)]"></div>
        <div aria-hidden="true" class="absolute inset-0 opacity-[.07] [background-image:linear-gradient(white_1px,transparent_1px),linear-gradient(90deg,white_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]"></div>
    @endif

    <div class="relative px-6 py-12 sm:px-10 sm:py-16 md:px-14 {{ $heroBanner ? 'md:pt-56 lg:pt-72' : 'md:py-20' }}">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide ring-1 ring-white/20 backdrop-blur-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-300 animate-pulse"></span>
            {{ config('app.name') }}
        </span>
        <h1 class="mt-5 max-w-3xl text-3xl sm:text-5xl md:text-6xl font-extrabold tracking-tight leading-[1.05] [text-wrap:balance]">{{ $settings['hero_title'] }}</h1>
        @if ($settings['hero_subtitle'])
            <p class="mt-5 max-w-2xl text-base sm:text-lg text-white/75 leading-relaxed whitespace-pre-line">{{ $settings['hero_subtitle'] }}</p>
        @endif

        <div class="mt-8 flex flex-wrap items-center gap-3">
            @auth
                <a href="{{ route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'portal.dashboard') }}" class="group inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 font-semibold text-brand-900 shadow-lg shadow-black/10 hover:bg-brand-50 transition">
                    Go to dashboard
                    <svg class="w-4 h-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            @else
                <a href="{{ route('register') }}" class="group inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 font-semibold text-brand-900 shadow-lg shadow-black/10 hover:bg-brand-50 transition">
                    Register now
                    <svg class="w-4 h-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
                <a href="{{ route('login') }}" class="rounded-full px-6 py-3 font-semibold ring-1 ring-white/30 hover:bg-white/10 transition">Login</a>
            @endauth
        </div>

        @unless (config('bkash.enabled'))
            <div class="mt-5">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/20 px-3 py-1 text-sm font-semibold text-amber-200 ring-1 ring-amber-300/30">🚧 bKash Payment Coming Soon</span>
            </div>
        @endunless

        @if ($event)
            <div class="mt-10 inline-flex max-w-full items-center gap-4 rounded-2xl bg-white/10 p-3 pr-6 ring-1 ring-white/15 backdrop-blur-md">
                @if ($event->event_date)
                    <div class="flex flex-col items-center justify-center gap-1 w-16 h-16 shrink-0 rounded-xl bg-white text-brand-900 leading-none">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-brand-600">{{ $event->event_date->format('M') }}</span>
                        <span class="text-2xl font-extrabold">{{ $event->event_date->format('d') }}</span>
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wider text-brand-200">Upcoming event</div>
                    <div class="mt-0.5 font-bold text-lg truncate">{{ $event->title }}</div>
                    <div class="text-sm text-white/70">
                        {{ $event->event_date?->format('d M Y') }} @if($event->venue) · {{ $event->venue }} @endif · Fee ৳{{ $event->formattedFee() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

@if ($announcements->isNotEmpty())
    <section class="mt-16">
        <div class="flex items-end justify-between gap-4 mb-6">
            <div>
                <p class="text-sm font-semibold text-brand-700">Stay Updated</p>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Latest Notice</h2>
            </div>
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            @foreach ($announcements as $a)
                <article class="group flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200/80 shadow-soft hover:-translate-y-1 hover:shadow-xl transition duration-300">
                    @if ($a->banner)
                        <div class="aspect-[16/9] overflow-hidden bg-slate-100">
                            <img src="{{ $a->bannerUrl() }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col p-6">
                        <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                            @if($a->is_pinned)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700 ring-1 ring-amber-200">📌 Pinned</span>
                            @endif
                            <span>{{ $a->published_at->diffForHumans() }}</span>
                        </div>
                        <h3 class="mt-3 text-lg font-bold leading-snug text-slate-900">{{ $a->title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ \Illuminate\Support\Str::limit($a->body, 120) }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

@if ($totalAlumni > 0)
    <section id="alumni" class="mt-16 scroll-mt-24">
        <form method="GET" action="{{ route('home') }}#alumni" class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="text-sm font-semibold text-brand-700">Alumni Directory</p>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Registered Alumni <span class="text-slate-400 font-semibold">({{ $totalAlumni }})</span></h2>
            </div>
            <label class="relative w-full sm:w-80">
                <span class="sr-only">Search Alumni</span>
                <svg class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input name="q" type="search" value="{{ $search }}" placeholder="Search name, batch, job…"
                       class="w-full rounded-full border-0 bg-white py-3 pl-11 pr-4 text-sm ring-1 ring-slate-200 shadow-soft placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-brand-500">
                @if ($selectedBatch)<input type="hidden" name="batch" value="{{ $selectedBatch }}">@endif
            </label>
        </form>

        @if ($batches->count() > 1)
            @php($chipClass = 'shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold ring-1 transition')
            @php($activeChip = 'bg-slate-900 text-white ring-slate-900')
            @php($idleChip = 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')
            <nav aria-label="Filter by batch" class="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
                <a href="{{ route('home', array_filter(['q' => $search])) }}#alumni" class="{{ $chipClass }} {{ $selectedBatch ? $idleChip : $activeChip }}">All batches</a>
                @foreach ($batches as $batch)
                    <a href="{{ route('home', array_filter(['q' => $search, 'batch' => $batch])) }}#alumni" class="{{ $chipClass }} {{ $selectedBatch === (int) $batch ? $activeChip : $idleChip }}">SSC {{ $batch }}</a>
                @endforeach
            </nav>
        @endif

        @if ($students->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-500">No alumni match your search.</div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
                @foreach ($students as $s)
                    <article class="group flex flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-soft hover:-translate-y-1 hover:shadow-xl hover:ring-brand-300 transition duration-300">
                        <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-brand-100 via-brand-50 to-white">
                            @if ($s->photoUrl())
                                <img src="{{ $s->photoUrl() }}" alt="{{ $s->name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-105 transition duration-500">
                            @else
                                <span class="absolute inset-0 grid place-items-center text-5xl font-extrabold text-brand-700/70">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
                            @endif
                            @if ($s->ssc_batch)
                                <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-brand-800 shadow-xs backdrop-blur-sm">SSC {{ $s->ssc_batch }}</span>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-3 sm:p-4">
                            <h3 class="font-bold text-slate-900 leading-snug line-clamp-1" title="{{ $s->name }}">{{ $s->name }}</h3>
                            @php($job = collect([$s->profession, $s->company_name])->filter()->join(' at '))
                            @if ($job)
                                <p class="mt-0.5 text-xs sm:text-sm text-slate-500 break-words">{{ $job }}</p>
                            @endif
                            <div class="mt-auto pt-3 space-y-2">
                                @if ($s->linkedin_url || $s->facebook_url)
                                    <div class="flex items-center gap-2">
                                        @if ($s->linkedin_url)
                                            <a href="{{ $s->linkedin_url }}" target="_blank" rel="noopener noreferrer" title="LinkedIn profile" class="grid place-items-center w-9 h-9 shrink-0 rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-100 hover:bg-sky-100 transition">
                                                <span class="sr-only">LinkedIn</span>
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5a2.5 2.5 0 11-.01 5 2.5 2.5 0 01.01-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05C20.6 8.65 21 11.2 21 14.5V21h-4v-5.8c0-1.4-.03-3.2-1.95-3.2-1.95 0-2.25 1.52-2.25 3.1V21H9z"/></svg>
                                            </a>
                                        @endif
                                        @if ($s->facebook_url)
                                            <a href="{{ $s->facebook_url }}" target="_blank" rel="noopener noreferrer" title="Facebook profile" class="grid place-items-center w-9 h-9 shrink-0 rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 hover:bg-blue-100 transition">
                                                <span class="sr-only">Facebook</span>
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14 8.5V6.8c0-.8.2-1.3 1.4-1.3H17V2.2c-.3 0-1.3-.2-2.5-.2C12 2 10.4 3.5 10.4 6.3v2.2H7.6v3.3h2.8V22H14V11.8h2.8l.4-3.3z"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                                @if ($canSeeContacts)
                                    <a href="tel:{{ $s->phone }}" title="Call {{ $s->name }}" class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-2 py-2 text-xs font-semibold text-white hover:bg-brand-800 transition">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2"/></svg>
                                        <span class="truncate">{{ $s->phone }}</span>
                                    </a>
                                @elseif (! auth()->check())
                                    <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center rounded-xl bg-slate-100 px-2 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition">Log in to contact</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="mt-5 text-center text-sm text-slate-500">Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }} alumni</p>
            <div class="mt-4">{{ $students->links() }}</div>
        @endif
    </section>
@endif
@endsection
