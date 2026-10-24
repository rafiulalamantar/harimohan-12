{{-- One memory post as a card: first photo (with "+N" for the rest), kind, title and the start of the story. --}}
@props(['memory'])

@php($cover = $memory->photos->first())
<article class="group relative flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200/80 shadow-soft hover:-translate-y-1 hover:shadow-xl transition duration-300">
    @if ($cover)
        <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
            <img src="{{ $cover->url() }}" alt="{{ $memory->title }}" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            @if ($memory->photos->count() > 1)
                <span class="absolute right-3 bottom-3 rounded-full bg-black/60 px-2.5 py-1 text-xs font-bold text-white backdrop-blur-sm">+{{ $memory->photos->count() - 1 }} photos</span>
            @endif
        </div>
    @endif
    <div class="flex flex-1 flex-col p-6">
        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
            <span class="rounded-full bg-brand-50 px-2 py-0.5 font-semibold text-brand-800 ring-1 ring-brand-100">{{ $memory->kindLabel() }}</span>
            <span>{{ $memory->created_at->diffForHumans() }}</span>
        </div>
        <h3 class="mt-3 text-lg font-bold leading-snug text-slate-900 break-words">
            {{-- The link covers the whole card (after:absolute), so anywhere on it opens the post. --}}
            <a href="{{ route('memories.show', $memory) }}" class="after:absolute after:inset-0">{{ $memory->title }}</a>
        </h3>
        @if ($memory->story)
            <p class="mt-2 text-sm leading-relaxed text-slate-600 whitespace-pre-line break-words line-clamp-4">{{ $memory->story }}</p>
        @endif
    </div>
</article>
