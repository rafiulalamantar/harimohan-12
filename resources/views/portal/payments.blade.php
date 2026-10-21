@extends('layouts.app')
@section('title', 'My payments')

@section('content')
<h1 class="text-2xl font-bold mb-4">My payments</h1>
{{-- Phones: one card per payment --}}
<div class="md:hidden space-y-3">
    @forelse ($payments as $p)
        <div class="bg-white rounded-2xl border p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold text-slate-900">{{ $p->event?->title }}</div>
                    <div class="text-xs text-gray-500">{{ ($p->paid_at ?? $p->created_at)->format('d M Y, h:i A') }}</div>
                </div>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $p->statusColor() }}">{{ ucfirst($p->status) }}</span>
            </div>
            <div class="mt-3 text-xl font-bold text-slate-900">৳{{ number_format($p->amount, 2) }}</div>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-gray-500">bKash TrxID</dt>
                <dd class="font-mono break-all">{{ $p->trx_id ?? '—' }}</dd>
                <dt class="text-gray-500">Invoice</dt>
                <dd class="font-mono text-xs break-all self-center">{{ $p->invoice_no }}</dd>
            </dl>
        </div>
    @empty
        <div class="bg-white rounded-2xl border p-6 text-center text-gray-500">No payments yet. <a href="{{ route('portal.dashboard') }}" class="text-brand-700 underline">Pay now</a></div>
    @endforelse
</div>

{{-- Larger screens: table --}}
<div class="hidden md:block bg-white rounded-2xl border overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-600">
            <tr><th class="p-3">Date</th><th class="p-3">Event</th><th class="p-3">Amount</th><th class="p-3">Status</th><th class="p-3">bKash TrxID</th><th class="p-3">Invoice</th></tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($payments as $p)
            <tr>
                <td class="p-3 whitespace-nowrap">{{ ($p->paid_at ?? $p->created_at)->format('d M Y, h:i A') }}</td>
                <td class="p-3">{{ $p->event?->title }}</td>
                <td class="p-3">৳{{ number_format($p->amount, 2) }}</td>
                <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $p->statusColor() }}">{{ ucfirst($p->status) }}</span></td>
                <td class="p-3 font-mono">{{ $p->trx_id ?? '—' }}</td>
                <td class="p-3 font-mono text-xs">{{ $p->invoice_no }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-500">No payments yet. <a href="{{ route('portal.dashboard') }}" class="text-brand-700 underline">Pay now</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
