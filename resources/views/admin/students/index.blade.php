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
        @foreach (\App\Models\User::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <select name="batch" class="border rounded-lg px-3 py-2">
        <option value="">All batch</option>
        @foreach ($batches as $b)<option value="{{ $b }}" @selected((string) request('batch') === (string) $b)>{{ $b }}</option>@endforeach
    </select>
    @foreach ($choiceFields as $f)
        <select name="answer[{{ $f->id }}]" aria-label="{{ $f->label }}" class="border rounded-lg px-3 py-2 max-w-full">
            <option value="">{{ $f->label }}: any</option>
            @foreach ($f->options ?? [] as $option)<option value="{{ $option }}" @selected(($answerFilters[$f->id] ?? null) === $option)>{{ $f->label }}: {{ $option }}</option>@endforeach
        </select>
    @endforeach
    <button class="bg-brand-600 text-white rounded-lg px-4 py-2">Filter</button>
    @if (request()->hasAny(['q', 'status', 'batch', 'answer']))
        <a href="{{ route('admin.students.index') }}" class="self-center text-brand-700 underline">Clear</a>
    @endif
</form>

@if ($choiceCounts)
    <section class="bg-white rounded-xl border p-4 mb-4" aria-label="Answer totals">
        <h2 class="text-sm font-semibold text-gray-700">Answer totals <span class="font-normal text-gray-500">for the {{ $students->total() }} {{ str('student')->plural($students->total()) }} listed</span></h2>
        <div class="mt-2 space-y-2">
            @foreach ($choiceFields as $f)
                <div class="flex flex-wrap items-center gap-1.5 text-sm">
                    <span class="mr-1 font-medium break-words">{{ $f->label }}:</span>
                    @foreach ($choiceCounts[$f->id] as $option => $count)
                        <a href="{{ request()->fullUrlWithQuery(['answer' => array_replace($answerFilters, [$f->id => $option]), 'page' => null]) }}"
                           class="rounded-full px-2.5 py-0.5 ring-1 {{ ($answerFilters[$f->id] ?? null) === $option ? 'bg-brand-600 text-white ring-brand-600' : 'bg-gray-50 text-gray-700 ring-gray-200 hover:bg-gray-100' }}">{{ $option }} <strong>{{ $count }}</strong></a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- Phones: one card per student --}}
<div class="md:hidden space-y-3">
    @forelse ($students as $s)
        <div class="bg-white rounded-xl border p-4">
            <div class="flex items-start gap-3">
                <x-avatar :user="$s" class="w-10 h-10 shrink-0" />
                <div class="min-w-0 flex-1">
                    <a class="font-semibold text-brand-700 underline" href="{{ route('admin.students.show', $s) }}">{{ $s->name }}</a>@if($s->isAdmin()) <span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800 no-underline">Admin</span>@endif
                    <div class="text-xs text-gray-500 break-all">{{ $s->email }}</div>
                </div>
                <span class="shrink-0 text-xs font-medium text-gray-600">{{ ucfirst($s->status) }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-gray-500">Batch</dt><dd>{{ $s->ssc_batch }}</dd>
                <dt class="text-gray-500">Phone</dt><dd><a href="tel:{{ $s->phone }}">{{ $s->phone }}</a></dd>
                <dt class="text-gray-500">Paid</dt><dd>{{ $s->paid_count ? '✅' : '—' }}</dd>
                @php($answers = $s->readableFieldAnswers($fields))
                @foreach ($fields as $f)
                    <dt class="text-gray-500 break-words">{{ $f->label }}</dt><dd class="break-words min-w-0">{{ $answers[$f->id] ?? '—' }}</dd>
                @endforeach
            </dl>
            <div class="mt-3 flex gap-2">
                @if (! $s->isApproved())
                    <form method="POST" action="{{ route('admin.students.status', $s) }}" class="flex-1">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ \App\Models\User::STATUS_APPROVED }}">
                        <button class="w-full text-sm bg-green-600 text-white rounded-lg px-3 py-2">Approve</button>
                    </form>
                @endif
                @if ($s->status !== \App\Models\User::STATUS_REJECTED && ! $s->isAdmin())
                    <form method="POST" action="{{ route('admin.students.status', $s) }}" data-confirm="Reject this student?" class="flex-1">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ \App\Models\User::STATUS_REJECTED }}">
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
            <tr><th class="p-3">Name</th><th class="p-3">Batch</th><th class="p-3">Phone</th><th class="p-3">Status</th><th class="p-3">Paid</th>@foreach ($fields as $f)<th class="p-3">{{ $f->label }}</th>@endforeach<th class="p-3">Action</th></tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($students as $s)
            <tr>
                <td class="p-3"><div class="flex items-center gap-3"><x-avatar :user="$s" class="w-9 h-9" /><div><a class="text-brand-700 underline" href="{{ route('admin.students.show', $s) }}">{{ $s->name }}</a>@if($s->isAdmin()) <span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800 no-underline">Admin</span>@endif<div class="text-xs text-gray-500">{{ $s->email }}</div></div></div></td>
                <td class="p-3">{{ $s->ssc_batch }}</td>
                <td class="p-3">{{ $s->phone }}</td>
                <td class="p-3">{{ ucfirst($s->status) }}</td>
                <td class="p-3">{{ $s->paid_count ? '✅' : '—' }}</td>
                @php($answers = $s->readableFieldAnswers($fields))
                @foreach ($fields as $f)
                    <td class="p-3 min-w-24">{{ $answers[$f->id] ?? '—' }}</td>
                @endforeach
                <td class="p-3">
                    <div class="flex gap-2">
                        @if ($s->isAdmin() && $s->isApproved())
                            <span class="text-gray-400">—</span>
                        @endif
                        @if (! $s->isApproved())
                            <form method="POST" action="{{ route('admin.students.status', $s) }}">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Models\User::STATUS_APPROVED }}">
                                <button class="text-xs bg-green-600 text-white rounded-sm px-2 py-1">Approve</button>
                            </form>
                        @endif
                        @if ($s->status !== \App\Models\User::STATUS_REJECTED && ! $s->isAdmin())
                            <form method="POST" action="{{ route('admin.students.status', $s) }}" data-confirm="Reject this student?">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Models\User::STATUS_REJECTED }}">
                                <button class="text-xs bg-red-600 text-white rounded-sm px-2 py-1">Reject</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ 6 + $fields->count() }}" class="p-6 text-center text-gray-500">No students found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $students->links() }}</div>
@endsection
