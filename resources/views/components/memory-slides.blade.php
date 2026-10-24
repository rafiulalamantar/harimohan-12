{{--
    A memory's photos as a slideshow. resources/js/slideshow.js makes it move; the slide styles are in resources/css/app.css.

    Two sizes:
    - Card (default): photos fill a 4:3 frame, small dots, tapping the photo opens the post
      (except Remembering cards, which show everything on the card and link nowhere).
    - Post page (large): whole photos on a dark backdrop, thumbnails, Pause and Full size buttons.
    Remembering posts have one photo, shown without the zoom (.slides-calm). A single photo shows with no controls.

    The data-* attributes are what slideshow.js looks for: data-slide (a photo), data-slide-prev / -next / -dot (buttons),
    data-slide-number, data-slide-progress, data-slide-pause, data-slide-open.
--}}
@props(['memory', 'large' => false])

@php
    $photos = $memory->photos;
    $isSlideshow = $photos->count() > 1;
    $photoLinksToPost = ! $large && $memory->linksToPostPage();
    $arrowClasses = 'absolute top-1/2 -translate-y-1/2 z-10 grid place-items-center rounded-full bg-white/85 text-slate-900 shadow-lg backdrop-blur-sm transition hover:bg-white '
        // Hidden until hover or keyboard focus with a mouse; always visible on touch screens, which can't hover.
        .'[@media(hover:hover)]:opacity-0 group-hover/slides:opacity-100 group-focus-within/slides:opacity-100 '
        .($large ? 'w-11 h-11' : 'w-9 h-9');
    $overlayButtonClasses = 'rounded-full bg-black/55 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-sm hover:bg-black/70';
@endphp

<div @if ($isSlideshow) data-slideshow data-interval="{{ $memory->slideInterval(onPostPage: $large) }}" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Photos of {{ $memory->title }}" @endif
     {{ $attributes->class(['group/slides select-none outline-none focus-visible:ring-4 focus-visible:ring-brand-300', 'rounded-3xl' => $large]) }}>

    {{-- The frame: photos stacked on top of each other; only the .is-active one is visible. --}}
    <div @class(['relative aspect-[4/3] overflow-hidden', 'slides-calm' => $memory->isRemembering(), 'slides-contain rounded-3xl bg-slate-950' => $large, 'bg-slate-100' => ! $large])>
        @if ($photoLinksToPost)<a href="{{ route('memories.show', $memory) }}" tabindex="-1" aria-hidden="true" class="absolute inset-0">@endif
            @foreach ($photos as $photo)
                <img src="{{ $photo->url() }}" alt="{{ $memory->title }} — photo {{ $loop->iteration }} of {{ $photos->count() }}" draggable="false" data-fallback
                     data-slide @class(['is-active' => $loop->first]) @if (! $loop->first) aria-hidden="true" @endif
                     loading="{{ $loop->first && $large ? 'eager' : 'lazy' }}" decoding="async">
            @endforeach
        @if ($photoLinksToPost)</a>@endif

        @if ($isSlideshow)
            {{-- Previous / next arrows --}}
            <button type="button" data-slide-prev aria-label="Previous photo" class="{{ $arrowClasses }} left-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
            </button>
            <button type="button" data-slide-next aria-label="Next photo" class="{{ $arrowClasses }} right-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>

            {{-- "2 / 5" counter --}}
            <span class="absolute right-3 top-3 z-10 rounded-full bg-black/55 px-2.5 py-1 text-xs font-bold text-white backdrop-blur-sm">
                <span data-slide-number>1</span> / {{ $photos->count() }}
            </span>

            {{-- Progress bar: fills up while a photo is shown, so people can see the next one is coming. --}}
            <span class="absolute inset-x-0 bottom-0 z-10 h-1 bg-white/20" aria-hidden="true"><span data-slide-progress class="block h-full bg-white/90"></span></span>

            @if ($large)
                {{-- Post page: Pause / Play and Full size (opens the photo currently shown) --}}
                <div class="absolute left-3 top-3 z-10 flex gap-2">
                    <button type="button" data-slide-pause aria-pressed="false" class="{{ $overlayButtonClasses }}">❚❚ Pause</button>
                    <a href="{{ $photos->first()->url() }}" data-slide-open target="_blank" rel="noopener" class="{{ $overlayButtonClasses }}">⤢ Full size</a>
                </div>
            @else
                {{-- Card: one dot per photo; the current one is a wider pill --}}
                <div class="absolute inset-x-0 bottom-3 z-10 flex justify-center gap-1.5">
                    @foreach ($photos as $photo)
                        <button type="button" data-slide-dot aria-label="Show photo {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif
                                class="h-2 w-2 rounded-full bg-white/60 shadow-sm transition-all aria-[current=true]:w-5 aria-[current=true]:bg-white"></button>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    @if ($large && $isSlideshow)
        {{-- Post page: thumbnails work like the card's dots; tap one to jump to it. --}}
        <div class="mt-3 flex gap-2 overflow-x-auto p-1">
            @foreach ($photos as $photo)
                <button type="button" data-slide-dot aria-label="Show photo {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif
                        class="shrink-0 overflow-hidden rounded-xl ring-2 ring-transparent opacity-60 transition hover:opacity-100 aria-[current=true]:opacity-100 aria-[current=true]:ring-brand-500">
                    <img src="{{ $photo->url() }}" alt="" loading="lazy" decoding="async" draggable="false" data-fallback class="h-16 w-20 object-cover">
                </button>
            @endforeach
        </div>
    @endif
</div>
