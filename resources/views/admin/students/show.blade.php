@extends('layouts.app')
@section('title', $student->name)

@section('content')
<a href="{{ route('admin.students.index') }}" class="text-sm text-brand-700 underline">← Students</a>
<div class="grid md:grid-cols-3 gap-6 mt-3">
    <section class="bg-white rounded-xl border p-6">
        <x-avatar :user="$student" class="w-24 h-24 text-3xl" />
        <h1 class="text-xl font-bold mt-3">{{ $student->name }}</h1>
        <dl class="mt-4 text-sm space-y-2">
            @foreach (['Email' => $student->email, 'Phone' => $student->phone, 'SSC batch' => $student->ssc_batch, 'Blood group' => $student->blood_group,
                       'Profession' => $student->profession, 'Company' => $student->company_name, 'Address' => $student->present_address, 'Status' => ucfirst($student->status),
                       'Registered' => $student->created_at->format('d M Y')] as $k => $v)
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $k }}</dt><dd class="text-right">{{ $v ?: '—' }}</dd></div>
            @endforeach
            @php($answers = $student->fieldAnswers())
            @foreach ($fields as $f)
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $f->label }}@unless($f->is_active) <span class="text-xs">(off)</span>@endunless</dt><dd class="text-right whitespace-pre-line">{{ ($answers[$f->id] ?? null) ?: '—' }}</dd></div>
            @endforeach
            @foreach (['LinkedIn' => $student->linkedin_url, 'Facebook' => $student->facebook_url] as $k => $url)
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $k }}</dt>
                    <dd class="text-right">@if($url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-brand-700 underline">Profile</a>@else — @endif</dd></div>
            @endforeach
        </dl>
        <form method="POST" action="{{ route('admin.students.status', $student) }}" class="mt-5 flex gap-2">@csrf @method('PATCH')
            <select name="status" class="border rounded-lg px-3 py-2 text-sm flex-1">
                @foreach (['pending', 'approved', 'rejected'] as $s)<option value="{{ $s }}" @selected($student->status === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <button class="bg-brand-600 text-white rounded-lg px-4 text-sm">Update</button>
        </form>
        @if ($student->isAdmin())
            <p class="mt-4 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800">🛡️ {{ $student->isSuperAdmin() ? 'Super admin' : 'Admin' }}: can open the admin panel.</p>
        @endif
        @if (auth()->user()->isSuperAdmin() && ! $student->isSuperAdmin())
            @if ($student->isAdmin())
                <form method="POST" action="{{ route('admin.students.admin', $student) }}" data-confirm="Remove {{ $student->name }}'s admin access?" class="mt-2">@csrf @method('PATCH')
                    <input type="hidden" name="admin" value="0">
                    <button class="w-full border border-red-200 text-red-700 rounded-lg px-4 py-2 text-sm hover:bg-red-50">Remove admin</button>
                </form>
            @elseif ($student->isApproved())
                <form method="POST" action="{{ route('admin.students.admin', $student) }}" data-confirm="Make {{ $student->name }} an admin? They will get the full admin panel: students and their phone numbers (including CSV export), notices, events, payments and the welcome page. Only super admins can make admins." class="mt-4">@csrf @method('PATCH')
                    <input type="hidden" name="admin" value="1">
                    <button class="w-full border border-brand-200 text-brand-800 rounded-lg px-4 py-2 text-sm hover:bg-brand-50">🛡️ Make admin</button>
                </form>
            @endif
        @endif
    </section>
    <section class="md:col-span-2 bg-white rounded-xl border p-6">
        <h2 class="font-semibold mb-3">Payment history</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500"><tr><th class="py-2">Date</th><th>Event</th><th>Amount</th><th>Status</th><th>TrxID</th></tr></thead>
            <tbody class="divide-y">
            @forelse ($student->payments->sortByDesc('created_at') as $p)
                <tr><td class="py-2">{{ $p->created_at->format('d M Y') }}</td><td>{{ $p->event?->title }}</td><td>৳{{ number_format($p->amount) }}</td>
                    <td><span class="rounded-full px-2 py-0.5 text-xs {{ $p->statusColor() }}">{{ ucfirst($p->status) }}</span></td><td class="font-mono">{{ $p->trx_id ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">No payments.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
