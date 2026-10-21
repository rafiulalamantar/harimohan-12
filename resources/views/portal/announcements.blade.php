@extends('layouts.app')
@section('title', 'Notice board')

@section('content')
<h1 class="text-2xl font-bold mb-4">Notice Board</h1>
<div class="space-y-3">
    @forelse ($announcements as $a)
        <a href="{{ route('portal.announcements.show', $a) }}" class="block bg-white rounded-xl border p-5 hover:border-brand-600">
            @if ($a->banner)<img src="{{ $a->bannerUrl() }}" alt="" class="w-full h-40 object-cover rounded-lg mb-3">@endif
            <div class="flex items-center gap-2">@if($a->is_pinned)<span>📌</span>@endif<h2 class="font-semibold">{{ $a->title }}</h2></div>
            <p class="text-sm text-gray-600 mt-1">{{ \Illuminate\Support\Str::limit($a->body, 220) }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ $a->published_at->format('d M Y, h:i A') }}</p>
        </a>
    @empty
        <p class="text-gray-500">No notices.</p>
    @endforelse
</div>
<div class="mt-4">{{ $announcements->links() }}</div>
@endsection
