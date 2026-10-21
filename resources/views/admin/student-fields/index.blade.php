@extends('layouts.app')
@section('title', 'Student fields')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Student fields</h1>
    <a href="{{ route('admin.student-fields.create') }}" class="bg-brand-600 text-white rounded-lg px-4 py-2 text-sm">+ New field</a>
</div>
<p class="text-sm text-gray-500 mb-4">Extra information to collect from students, e.g. a T-shirt size with a size chart. Active fields appear on the registration form and on every student's profile page. Turn a field off to hide it while keeping the answers already collected.</p>
<div class="bg-white rounded-xl border overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-600">
            <tr><th class="p-3">Field</th><th class="p-3">Type</th><th class="p-3">Required</th><th class="p-3">Active</th><th class="p-3">Answers</th><th class="p-3"></th></tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($fields as $f)
            <tr>
                <td class="p-3">
                    <div class="flex items-center gap-3">
                        @if ($f->image)<img src="{{ $f->imageUrl() }}" alt="" class="w-12 h-12 object-contain rounded-sm border bg-white">@endif
                        <div>
                            <div class="font-medium">{{ $f->label }}</div>
                            @if ($f->type === 'select')<div class="text-xs text-gray-500">{{ implode(', ', $f->options ?? []) }}</div>@endif
                        </div>
                    </div>
                </td>
                <td class="p-3">{{ \App\Models\StudentField::TYPES[$f->type] ?? $f->type }}</td>
                <td class="p-3">{{ $f->is_required ? 'Yes' : '—' }}</td>
                <td class="p-3">{{ $f->is_active ? '✅' : 'Off' }}</td>
                <td class="p-3">{{ $f->answered_count }}</td>
                <td class="p-3">
                    <div class="flex gap-2">
                        <a href="{{ route('admin.student-fields.edit', $f) }}" class="border rounded-sm px-3 py-1">Edit</a>
                        <form method="POST" action="{{ route('admin.student-fields.destroy', $f) }}" data-confirm="Delete this field and all {{ $f->answered_count }} answers? To keep the answers, turn the field off instead.">@csrf @method('DELETE')
                            <button class="border border-red-300 text-red-700 rounded-sm px-3 py-1">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-500">No extra fields yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
