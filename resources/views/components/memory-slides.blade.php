{{--
    A memory's photos as a slideshow (resources/js/slideshow.js plays it; styles are in resources/css/app.css).
    Card ($large = false): photos fill a 4:3 frame, slide every 4 s, small dots, the photo links to the post.
    Post page ($large = true): whole photos on a dark backdrop, slide every 5 s, thumbnails, pause and full-size buttons.
    One photo shows as a plain picture, with no controls.
--}}
@props(['memory', 'large' => false])

@php($photos = $memory->photos)
@php($hasMany = $photos->count() > 1)
<div @if ($hasMany) data-slideshow data-interval="{{ $large ? 5000 : 4000 }}" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Photos of {{ $memory->title }}" @endif
     {{ $attributes->class(['group/slides select-none outline-none focus-visible:ring-4 focus-visible:ring-brand-300', 'rounded-3xl' => $large]) }}>
    <div @class(['relative aspect-[4/3] overflow-hidden', 'slides-contain rounded-3xl bg-slate-950' => $large, 'bg-slate-100' => ! $large])>
        @unless ($large)<a href="{{ route('memories.show', $memory) }}" tabindex="-1" aria-hidden="true" class="absolute inset-0">@endunless
            @foreach ($photos as $photo)
                <img src="{{ $photo->url() }}" alt="{{ $memory->title }} — photo {{ $loop->iteration }} of {{ $photos->count() }}" draggable="false"
                     data-slide @class(['is-active' => $loop->first]) @if (! $loop->first) aria-hidden="true" @endif
                     loading="{{ $loop->first && $large ? 'eager' : 'lazy' }}" decoding="async">
            @endforeach
        @unless ($large)</a>@endunless

        @if ($hasMany)
            {{-- Arrows appear on hover with a mouse; on touch screens (no hover) they are always visible. --}}
            @php($arrow = 'absolute top-1/2 -translate-y-1/2 z-10 grid place-items-center rounded-full bg-white/85 text-slate-900 shadow-lg backdrop-blur-sm transition hover:bg-white [@media(hover:hover)]:opacity-0 group-hover/slides:opacity-100 group-focus-within/slides:opacity-100 '.($large ? 'w-11 h-11' : 'w-9 h-9'))
            <button type="button" data-slide-prev aria-label="Previous photo" class="{{ $arrow }} left-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
            </button>
            <button type="button" data-slide-next aria-label="Next photo" class="{{ $arrow }} right-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>

            <span class="absolute right-3 top-3 z-10 rounded-full bg-black/55 px-2.5 py-1 text-xs font-bold text-white backdrop-blur-sm">
                <span data-slide-number>1</span> / {{ $photos->count() }}
            </span>

            {{-- Fills up while a photo is shown, so people can see the next one is coming. --}}
            <span class="absolute inset-x-0 bottom-0 z-10 h-1 bg-white/20" aria-hidden="true"><span data-slide-progress class="block h-full bg-white/90"></span></span>

            @if ($large)
                <div class="absolute left-3 top-3 z-10 flex gap-2">
                    <button type="button" data-slide-pause aria-pressed="false" class="rounded-full bg-black/55 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-sm hover:bg-black/70">❚❚ Pause</button>
                    <a href="{{ $photos->first()->url() }}" data-slide-open target="_blank" rel="noopener" class="rounded-full bg-black/55 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-sm hover:bg-black/70">⤢ Full size</a>
                </div>
            @else
                <div class="absolute inset-x-0 bottom-3 z-10 flex justify-center gap-1.5">
                    @foreach ($photos as $photo)
                        <button type="button" data-slide-dot aria-label="Show photo {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif
                                class="h-2 w-2 rounded-full bg-white/60 shadow-sm transition-all aria-[current=true]:w-5 aria-[current=true]:bg-white"></button>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    @if ($large && $hasMany)
        {{-- On the post page the thumbnails are the dots: tap one to jump to it. --}}
        <div class="mt-3 flex gap-2 overflow-x-auto p-1">
            @foreach ($photos as $photo)
                <button type="button" data-slide-dot aria-label="Show photo {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif
                        class="shrink-0 overflow-hidden rounded-xl ring-2 ring-transparent opacity-60 transition hover:opacity-100 aria-[current=true]:opacity-100 aria-[current=true]:ring-brand-500">
                    <img src="{{ $photo->url() }}" alt="" loading="lazy" decoding="async" draggable="false" class="h-16 w-20 object-cover">
                </button>
            @endforeach
        </div>
    @endif
</div>
