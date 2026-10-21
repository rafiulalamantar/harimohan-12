@extends('layouts.app')
@section('title', 'Events')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Events</h1>
    <a href="{{ route('admin.events.create') }}" class="bg-brand-600 text-white rounded-lg px-4 py-2 text-sm">+ New event</a>
</div>
<p class="text-sm text-gray-500 mb-4">The student portal shows only the active event with the latest date.</p>
{{-- Phones: one card per event --}}
<div class="md:hidden space-y-3">
    @forelse ($events as $e)
        <div class="bg-white rounded-xl border p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold">{{ $e->title }}</div>
                    <div class="text-xs text-gray-500">{{ $e->event_date?->format('d M Y') ?? 'No date' }}</div>
                </div>
                <span class="shrink-0 text-xs">{{ $e->is_active ? '✅ Active' : 'Inactive' }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                <div><dt class="text-xs text-gray-500">Fee</dt><dd class="font-medium">৳{{ $e->formattedFee() }}</dd></div>
                <div><dt class="text-xs text-gray-500">Paid</dt><dd class="font-medium">{{ $e->paid_count }}</dd></div>
                <div><dt class="text-xs text-gray-500">Collected</dt><dd class="font-medium">৳{{ number_format($e->collected ?? 0) }}</dd></div>
            </dl>
            <div class="mt-3 flex gap-2">
                <a href="{{ route('admin.events.edit', $e) }}" class="flex-1 text-center text-sm border rounded-lg px-3 py-2">Edit</a>
                <form method="POST" action="{{ route('admin.events.destroy', $e) }}" data-confirm="Delete this event? Payment records will be kept." class="flex-1">@csrf @method('DELETE')
                    <button class="w-full text-sm border border-red-300 text-red-700 rounded-lg px-3 py-2">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border p-6 text-center text-gray-500">No events.</div>
    @endforelse
</div>

{{-- Larger screens: table --}}
<div class="hidden md:block bg-white rounded-xl border overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-600">
            <tr><th class="p-3">Title</th><th class="p-3">Date</th><th class="p-3">Fee</th><th class="p-3">Paid</th><th class="p-3">Collected</th><th class="p-3">Active</th><th class="p-3"></th></tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($events as $e)
            <tr>
                <td class="p-3 font-medium">{{ $e->title }}</td>
                <td class="p-3">{{ $e->event_date?->format('d M Y') }}</td>
                <td class="p-3">৳{{ $e->formattedFee() }}</td>
                <td class="p-3">{{ $e->paid_count }}</td>
                <td class="p-3">৳{{ number_format($e->collected ?? 0) }}</td>
                <td class="p-3">{{ $e->is_active ? '✅' : '—' }}</td>
                <td class="p-3">
                    <div class="flex gap-2">
                        <a href="{{ route('admin.events.edit', $e) }}" class="border rounded-sm px-3 py-1">Edit</a>
                        <form method="POST" action="{{ route('admin.events.destroy', $e) }}" data-confirm="Delete this event? Payment records will be kept.">@csrf @method('DELETE')
                            <button class="border border-red-300 text-red-700 rounded-sm px-3 py-1">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-6 text-center text-gray-500">No events.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
