{{-- One memory post as a card: its photos as a slideshow, then kind, title and the start of the story. --}}
@props(['memory'])

<article class="group flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200/80 shadow-soft hover:-translate-y-1 hover:shadow-xl transition duration-300">
    @if ($memory->photos->isNotEmpty())
        <x-memory-slides :memory="$memory" />
    @endif
    <div class="relative flex flex-1 flex-col p-6">
        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
            <x-memory-kind :memory="$memory" />
            <span>{{ $memory->created_at->diffForHumans() }}</span>
        </div>
        <h3 class="mt-3 text-lg font-bold leading-snug text-slate-900 break-words">
            {{-- The link covers the text part of the card (after:absolute); the slideshow links to the post on its own. --}}
            <a href="{{ route('memories.show', $memory) }}" class="after:absolute after:inset-0">{{ $memory->title }}</a>
        </h3>
        @if ($memory->story)
            <p class="mt-2 text-sm leading-relaxed text-slate-600 whitespace-pre-line break-words line-clamp-4">{{ $memory->story }}</p>
        @endif
    </div>
</article>
