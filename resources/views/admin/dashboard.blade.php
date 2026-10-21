@extends('layouts.app')
@section('title', 'Admin dashboard')

@section('content')
<h1 class="text-2xl font-bold">Admin dashboard</h1>
<p class="text-sm text-gray-500">{{ $event?->title ?? 'No active event' }}</p>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
    @foreach ([
        ['Total registered', $totalStudents, route('admin.students.index')],
        ['Pending approval', $pendingStudents, route('admin.students.index', ['status' => 'pending'])],
        ['Paid (current event)', $paidCount, route('admin.payments.index', ['status' => 'completed'])],
        ['Collected', '৳'.number_format($collected), route('admin.payments.index', ['status' => 'completed'])],
    ] as [$label, $value, $link])
        <a href="{{ $link }}" class="bg-white rounded-xl border p-5 hover:border-brand-600">
            <div class="text-xs text-gray-500">{{ $label }}</div>
            <div class="text-2xl font-bold mt-1">{{ $value }}</div>
        </a>
    @endforeach
</div>

<section class="bg-white rounded-xl border p-5 mt-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold">New students</h2>
        <a href="{{ route('admin.students.index') }}" class="text-sm text-brand-700 underline">View all</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr><th class="py-2">Student</th><th>Company</th><th>Mobile</th><th>LinkedIn</th><th>Facebook</th><th>Joined</th></tr>
            </thead>
            <tbody class="divide-y">
            @forelse ($newStudents as $s)
                <tr>
                    <td class="py-2">
                        <a href="{{ route('admin.students.show', $s) }}" class="flex items-center gap-3">
                            <x-avatar :user="$s" class="w-9 h-9" />
                            <span><span class="text-brand-700 underline">{{ $s->name }}</span> <span class="text-gray-400">({{ $s->ssc_batch }})</span></span>
                        </a>
                    </td>
                    <td>{{ $s->company_name ?: '—' }}</td>
                    <td><a href="tel:{{ $s->phone }}" class="whitespace-nowrap">{{ $s->phone }}</a></td>
                    <td>@if($s->linkedin_url)<a href="{{ $s->linkedin_url }}" target="_blank" rel="noopener noreferrer" class="text-brand-700 underline">Profile</a>@else — @endif</td>
                    <td>@if($s->facebook_url)<a href="{{ $s->facebook_url }}" target="_blank" rel="noopener noreferrer" class="text-brand-700 underline">Profile</a>@else — @endif</td>
                    <td class="text-gray-500 whitespace-nowrap">{{ $s->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-gray-500">No registrations yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="grid md:grid-cols-2 gap-6 mt-6">
    <section class="bg-white rounded-xl border p-5">
        <h2 class="font-semibold mb-3">Registrations by batch</h2>
        @php($max = max(1, $byBatch->max('total') ?? 1))
        <div class="space-y-2 text-sm">
            @forelse ($byBatch as $b)
                <a href="{{ route('admin.students.index', ['batch' => $b->ssc_batch]) }}" class="flex items-center gap-3">
                    <span class="w-14 text-gray-600">{{ $b->ssc_batch ?? '—' }}</span>
                    <span class="flex-1 bg-gray-100 rounded-sm h-4"><span class="block bg-brand-600 h-4 rounded-sm" style="width: {{ $b->total / $max * 100 }}%"></span></span>
                    <span class="w-8 text-right">{{ $b->total }}</span>
                </a>
            @empty
                <p class="text-gray-500">No registrations yet.</p>
            @endforelse
        </div>
    </section>

    <section class="bg-white rounded-xl border p-5">
        <h2 class="font-semibold mb-3">Recent payments</h2>
        <div class="divide-y text-sm">
            @forelse ($recentPayments as $p)
                <div class="py-2 flex justify-between gap-3">
                    <span>{{ $p->user?->name }} <span class="text-gray-400">({{ $p->user?->ssc_batch }})</span></span>
                    <span class="font-mono text-xs text-gray-500">{{ $p->trx_id }}</span>
                    <span>৳{{ number_format($p->amount) }}</span>
                </div>
            @empty
                <p class="text-gray-500">No payments yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
