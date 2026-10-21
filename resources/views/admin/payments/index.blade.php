@extends('layouts.app')
@section('title', 'Payments')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-2xl font-bold">Payments</h1>
    <a href="{{ route('admin.payments.export', request()->only('status', 'event')) }}" class="text-sm bg-white border rounded-lg px-4 py-2">⬇ Export CSV</a>
</div>

<form class="flex flex-wrap gap-2 mb-4 text-sm">
    <input name="q" value="{{ request('q') }}" placeholder="TrxID / invoice / name / phone" class="border rounded-lg px-3 py-2">
    <select name="status" class="border rounded-lg px-3 py-2">
        <option value="">All status</option>
        @foreach (['completed', 'initiated', 'failed', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <select name="event" class="border rounded-lg px-3 py-2">
        <option value="">All events</option>
        @foreach ($events as $e)<option value="{{ $e->id }}" @selected((string) request('event') === (string) $e->id)>{{ $e->title }}</option>@endforeach
    </select>
    <button class="bg-brand-600 text-white rounded-lg px-4 py-2">Filter</button>
</form>

{{-- Phones: one card per payment --}}
<div class="md:hidden space-y-3">
    @forelse ($payments as $p)
        <div class="bg-white rounded-xl border p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    @if ($p->user)<a class="font-semibold text-brand-700 underline" href="{{ route('admin.students.show', $p->user) }}">{{ $p->user->name }}</a> <span class="text-gray-400">({{ $p->user->ssc_batch }})</span>@endif
                    <div class="text-xs text-gray-500">{{ $p->event?->title }} · {{ ($p->paid_at ?? $p->created_at)->format('d M Y, h:i A') }}</div>
                </div>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $p->statusColor() }}">{{ ucfirst($p->status) }}</span>
            </div>
            <div class="mt-2 text-lg font-bold">৳{{ number_format($p->amount, 2) }}</div>
            <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-gray-500">TrxID</dt><dd class="font-mono break-all">{{ $p->trx_id ?? '—' }}</dd>
                <dt class="text-gray-500">Payer</dt><dd>{{ $p->payer_msisdn ?? '—' }}</dd>
                <dt class="text-gray-500">Invoice</dt><dd class="font-mono text-xs break-all self-center">{{ $p->invoice_no }}</dd>
            </dl>
        </div>
    @empty
        <div class="bg-white rounded-xl border p-6 text-center text-gray-500">No payments found.</div>
    @endforelse
</div>

{{-- Larger screens: table --}}
<div class="hidden md:block bg-white rounded-xl border overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-600">
            <tr><th class="p-3">Date</th><th class="p-3">Student</th><th class="p-3">Event</th><th class="p-3">Amount</th><th class="p-3">Status</th><th class="p-3">TrxID</th><th class="p-3">Payer</th><th class="p-3">Invoice</th></tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($payments as $p)
            <tr>
                <td class="p-3 whitespace-nowrap">{{ ($p->paid_at ?? $p->created_at)->format('d M Y, h:i A') }}</td>
                <td class="p-3">@if($p->user)<a class="text-brand-700 underline" href="{{ route('admin.students.show', $p->user) }}">{{ $p->user->name }}</a> <span class="text-gray-400">({{ $p->user->ssc_batch }})</span>@endif</td>
                <td class="p-3">{{ $p->event?->title }}</td>
                <td class="p-3">৳{{ number_format($p->amount, 2) }}</td>
                <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $p->statusColor() }}">{{ ucfirst($p->status) }}</span></td>
                <td class="p-3 font-mono">{{ $p->trx_id ?? '—' }}</td>
                <td class="p-3">{{ $p->payer_msisdn ?? '—' }}</td>
                <td class="p-3 font-mono text-xs">{{ $p->invoice_no }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="p-6 text-center text-gray-500">No payments found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
