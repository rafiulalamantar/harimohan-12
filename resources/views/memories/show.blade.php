@extends('layouts.app')
@section('title', $memory->title)

@section('content')
<article class="max-w-3xl mx-auto">
    <a href="{{ route('memories.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">← All memories</a>
    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
        <span class="rounded-full bg-brand-50 px-2 py-0.5 font-semibold text-brand-800 ring-1 ring-brand-100">{{ $memory->kindLabel() }}</span>
        <span>{{ $memory->created_at->format('d M Y') }}</span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 break-words">{{ $memory->title }}</h1>

    @if ($memory->story)
        {{-- whitespace-pre-line keeps the admin's line breaks; {{ }} still escapes the text. --}}
        <p class="mt-4 text-base leading-relaxed text-slate-700 whitespace-pre-line break-words">{{ $memory->story }}</p>
    @endif

    @if ($memory->photos->isNotEmpty())
        <div class="mt-6 grid items-start gap-4 {{ $memory->photos->count() > 1 ? 'sm:grid-cols-2' : '' }}">
            @foreach ($memory->photos as $photo)
                <a href="{{ $photo->url() }}" target="_blank" rel="noopener" title="Open full size" class="block overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200">
                    <img src="{{ $photo->url() }}" alt="{{ $memory->title }} photo {{ $loop->iteration }}" loading="lazy" decoding="async" class="w-full h-auto">
                </a>
            @endforeach
        </div>
    @endif
</article>
@endsection
