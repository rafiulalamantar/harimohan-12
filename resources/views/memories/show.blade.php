@extends('layouts.app')
@section('title', $memory->title)

@section('content')
<article class="max-w-3xl mx-auto">
    <a href="{{ route('memories.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">← All memories</a>
    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
        <x-memory-kind :memory="$memory" />
        <span>{{ $memory->created_at->format('d M Y') }}</span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 break-words">{{ $memory->title }}</h1>

    @if ($memory->photos->isNotEmpty())
        <x-memory-slides :memory="$memory" large class="mt-6" />
    @endif

    @if ($memory->story)
        {{-- whitespace-pre-line keeps the admin's line breaks; {{ }} still escapes the text. --}}
        <p class="mt-6 text-base leading-relaxed text-slate-700 whitespace-pre-line break-words">{{ $memory->story }}</p>
    @endif

</article>
@endsection
