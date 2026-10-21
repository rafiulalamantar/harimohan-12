@extends('layouts.app')
@section('title', 'My payments')

@section('content')
<h1 class="text-2xl font-bold mb-4">My payments</h1>
<div class="bg-white rounded-2xl border overflow-x-auto">
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
