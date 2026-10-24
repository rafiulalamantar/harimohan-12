@extends('layouts.app')
@section('title', 'Memories')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Memories</h1>
    <a href="{{ route('admin.memories.create') }}" class="bg-brand-600 text-white rounded-lg px-4 py-2 text-sm">+ New memory</a>
</div>
<div class="bg-white rounded-xl border divide-y">
    @forelse ($memories as $m)
        <div class="p-4 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                @if ($m->photos->isNotEmpty())<img src="{{ $m->photos->first()->url() }}" alt="" data-fallback class="w-24 h-18 object-cover rounded-sm border">@endif
                <div class="min-w-0">
                    <div class="font-semibold break-words">{{ $m->title }}</div>
                    <div class="text-xs text-gray-500">{{ $m->kindLabel() }} · {{ $m->photos->count() }} photos · {{ $m->created_at->format('d M Y, h:i A') }}</div>
                </div>
            </div>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('memories.show', $m) }}" class="border rounded-sm px-3 py-1">View</a>
                <a href="{{ route('admin.memories.edit', $m) }}" class="border rounded-sm px-3 py-1">Edit</a>
                <form method="POST" action="{{ route('admin.memories.destroy', $m) }}" data-confirm="Delete this memory and its photos?">@csrf @method('DELETE')
                    <button class="border border-red-300 text-red-700 rounded-sm px-3 py-1">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <p class="p-6 text-gray-500">No memories yet. Share an old photo or a funny moment from school days.</p>
    @endforelse
</div>
<div class="mt-4">{{ $memories->links() }}</div>
@endsection
