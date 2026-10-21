@extends('layouts.app')
@section('title', 'Announcements')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Announcements</h1>
    <a href="{{ route('admin.announcements.create') }}" class="bg-brand-600 text-white rounded-lg px-4 py-2 text-sm">+ New announcement</a>
</div>
<div class="bg-white rounded-xl border divide-y">
    @forelse ($announcements as $a)
        <div class="p-4 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                @if ($a->banner)<img src="{{ $a->bannerUrl() }}" alt="" class="w-24 h-14 object-cover rounded-sm border">@endif
                <div>
                <div class="font-semibold">@if($a->is_pinned)📌 @endif{{ $a->title }}</div>
                <div class="text-xs text-gray-500">
                    @if (! $a->published_at) Draft
                    @elseif ($a->published_at->isFuture()) Scheduled: {{ $a->published_at->format('d M Y, h:i A') }}
                    @else Published: {{ $a->published_at->format('d M Y, h:i A') }} @endif
                </div>
                </div>
            </div>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('admin.announcements.edit', $a) }}" class="border rounded-sm px-3 py-1">Edit</a>
                <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" onsubmit="return confirm('Delete this announcement?')">@csrf @method('DELETE')
                    <button class="border border-red-300 text-red-700 rounded-sm px-3 py-1">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <p class="p-6 text-gray-500">No announcements.</p>
    @endforelse
</div>
<div class="mt-4">{{ $announcements->links() }}</div>
@endsection
