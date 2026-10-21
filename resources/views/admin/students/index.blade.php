@extends('layouts.app')
@section('title', 'Students')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-2xl font-bold">Registered students</h1>
    <a href="{{ route('admin.students.export') }}" class="text-sm bg-white border rounded-lg px-4 py-2">⬇ Export CSV</a>
</div>

<form class="flex flex-wrap gap-2 mb-4 text-sm">
    <input name="q" value="{{ request('q') }}" placeholder="Name / email / phone" class="border rounded-lg px-3 py-2">
    <select name="status" class="border rounded-lg px-3 py-2">
        <option value="">All status</option>
        @foreach (['pending', 'approved', 'rejected'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <select name="batch" class="border rounded-lg px-3 py-2">
        <option value="">All batch</option>
        @foreach ($batches as $b)<option value="{{ $b }}" @selected((string) request('batch') === (string) $b)>{{ $b }}</option>@endforeach
    </select>
    <button class="bg-brand-600 text-white rounded-lg px-4 py-2">Filter</button>
</form>

{{-- Phones: one card per student --}}
<div class="md:hidden space-y-3">
    @forelse ($students as $s)
        <div class="bg-white rounded-xl border p-4">
            <div class="flex items-start gap-3">
                <x-avatar :user="$s" class="w-10 h-10 shrink-0" />
                <div class="min-w-0 flex-1">
                    <a class="font-semibold text-brand-700 underline" href="{{ route('admin.students.show', $s) }}">{{ $s->name }}</a>
                    <div class="text-xs text-gray-500 break-all">{{ $s->email }}</div>
                </div>
                <span class="shrink-0 text-xs font-medium text-gray-600">{{ ucfirst($s->status) }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-gray-500">Batch</dt><dd>{{ $s->ssc_batch }}</dd>
                <dt class="text-gray-500">Phone</dt><dd><a href="tel:{{ $s->phone }}">{{ $s->phone }}</a></dd>
                <dt class="text-gray-500">Paid</dt><dd>{{ $s->paid_count ? '✅' : '—' }}</dd>
            </dl>
            <div class="mt-3 flex gap-2">
                @if ($s->status !== 'approved')
                    <form method="POST" action="{{ route('admin.students.status', $s) }}" class="flex-1">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="approved">
                        <button class="w-full text-sm bg-green-600 text-white rounded-lg px-3 py-2">Approve</button>
                    </form>
                @endif
                @if ($s->status !== 'rejected')
                    <form method="POST" action="{{ route('admin.students.status', $s) }}" data-confirm="Reject this student?" class="flex-1">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="rejected">
                        <button class="w-full text-sm bg-red-600 text-white rounded-lg px-3 py-2">Reject</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border p-6 text-center text-gray-500">No students found.</div>
    @endforelse
</div>

{{-- Larger screens: table --}}
<div class="hidden md:block bg-white rounded-xl border overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-600">
            <tr><th class="p-3">Name</th><th class="p-3">Batch</th><th class="p-3">Phone</th><th class="p-3">Status</th><th class="p-3">Paid</th><th class="p-3">Action</th></tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($students as $s)
            <tr>
                <td class="p-3"><div class="flex items-center gap-3"><x-avatar :user="$s" class="w-9 h-9" /><div><a class="text-brand-700 underline" href="{{ route('admin.students.show', $s) }}">{{ $s->name }}</a><div class="text-xs text-gray-500">{{ $s->email }}</div></div></div></td>
                <td class="p-3">{{ $s->ssc_batch }}</td>
                <td class="p-3">{{ $s->phone }}</td>
                <td class="p-3">{{ ucfirst($s->status) }}</td>
                <td class="p-3">{{ $s->paid_count ? '✅' : '—' }}</td>
                <td class="p-3">
                    <div class="flex gap-2">
                        @if ($s->status !== 'approved')
                            <form method="POST" action="{{ route('admin.students.status', $s) }}">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="approved">
                                <button class="text-xs bg-green-600 text-white rounded-sm px-2 py-1">Approve</button>
                            </form>
                        @endif
                        @if ($s->status !== 'rejected')
                            <form method="POST" action="{{ route('admin.students.status', $s) }}" data-confirm="Reject this student?">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="rejected">
                                <button class="text-xs bg-red-600 text-white rounded-sm px-2 py-1">Reject</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-500">No students found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $students->links() }}</div>
@endsection
