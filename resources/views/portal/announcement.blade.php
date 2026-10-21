@extends('layouts.app')
@section('title', $announcement->title)

@section('content')
<a href="{{ route('portal.announcements') }}" class="text-sm text-brand-700 underline">← Notice board</a>
<article class="bg-white rounded-2xl border p-6 md:p-8 mt-3">
    @if ($announcement->banner)
        <img src="{{ $announcement->bannerUrl() }}" alt="" class="w-full max-h-80 object-cover rounded-xl mb-5">
    @endif
    <h1 class="text-2xl font-bold">{{ $announcement->title }}</h1>
    <p class="text-xs text-gray-400 mt-1">{{ $announcement->published_at->format('d M Y, h:i A') }}</p>
    <div class="mt-5 leading-relaxed whitespace-pre-line">{{ $announcement->body }}</div>
</article>
@endsection
