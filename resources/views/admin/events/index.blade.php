@extends('layouts.app')
@section('title', 'Events')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Events</h1>
    <a href="{{ route('admin.events.create') }}" class="bg-brand-600 text-white rounded-lg px-4 py-2 text-sm">+ New event</a>
</div>
<p class="text-sm text-gray-500 mb-4">The student portal shows only the active event with the latest date.</p>
<div class="bg-white rounded-xl border overflow-x-auto">
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
                        <form method="POST" action="{{ route('admin.events.destroy', $e) }}" onsubmit="return confirm('Delete this event? Payment records will be kept.')">@csrf @method('DELETE')
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
