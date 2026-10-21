@extends('layouts.app')
@section('title', 'My Portal')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-3">
        <x-avatar :user="$user" class="w-12 h-12 text-xl" />
        <div>
        <h1 class="text-2xl font-bold">Assalamu Alaikum, {{ $user->name }}</h1>
        <p class="text-sm text-gray-500">SSC Batch {{ $user->ssc_batch }} · {{ $user->phone }}</p>
        </div>
    </div>
    @php($badge = ['approved' => 'bg-green-100 text-green-800', 'pending' => 'bg-yellow-100 text-yellow-800', 'rejected' => 'bg-red-100 text-red-800'][$user->status] ?? '')
    <span class="text-sm rounded-full px-3 py-1 {{ $badge }}">Account: {{ ucfirst($user->status) }}</span>
</div>

@if ($missingFields->isNotEmpty())
    <div class="mt-4 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 text-sm">
        Please complete your profile: {{ $missingFields->pluck('label')->join(', ') }}.
        <a href="{{ route('portal.profile') }}" class="underline font-medium">Update Profile</a>
    </div>
@endif

<div class="grid md:grid-cols-3 gap-6 mt-6">
    {{-- Event + payment card --}}
    <section class="md:col-span-1 bg-white rounded-2xl border p-6">
        <h2 class="font-semibold text-lg">Event</h2>
        @if (! $event)
            <p class="text-sm text-gray-500 mt-2">No event has been announced yet.</p>
        @else
            <div class="mt-2">
                <div class="font-semibold">{{ $event->title }}</div>
                <div class="text-sm text-gray-600">{{ $event->event_date?->format('d M Y') }} @if($event->venue) · {{ $event->venue }} @endif</div>
                @if($event->description)<p class="text-sm text-gray-600 mt-2">{{ $event->description }}</p>@endif
                <div class="mt-4 text-3xl font-bold">৳{{ $event->formattedFee() }}</div>
                @if($event->payment_deadline)<div class="text-xs text-gray-500">Deadline: {{ $event->payment_deadline->format('d M Y') }}</div>@endif
            </div>

            <div class="mt-5">
                @if ($hasPaid)
                    <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">✅ Your payment is complete. See you at the event!</div>
                @elseif (! $user->isApproved())
                    <div class="rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 text-sm">You can pay once an admin verifies your registration.</div>
                @elseif (! $event->isPaymentOpen())
                    <div class="rounded-lg bg-gray-100 text-gray-700 px-4 py-3 text-sm">Payments are currently closed.</div>
                @else
                    <form method="POST" action="{{ route('portal.pay', $event) }}">
                        @csrf
                        <button class="w-full bg-bkash hover:opacity-90 text-white font-semibold rounded-lg py-3 flex items-center justify-center gap-2">
                            Pay ৳{{ $event->formattedFee() }} with bKash
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2 text-center">You will be taken to the bKash page. Confirm with your OTP and PIN.</p>
                @endif
            </div>
        @endif
    </section>

    {{-- Announcements --}}
    <section class="md:col-span-2 bg-white rounded-2xl border p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg">Notice board</h2>
            <a href="{{ route('portal.announcements') }}" class="text-sm text-brand-700 underline">View all</a>
        </div>
        <div class="divide-y mt-3">
            @forelse ($announcements as $a)
                <a href="{{ route('portal.announcements.show', $a) }}" class="block py-3 hover:bg-gray-50 -mx-2 px-2 rounded-sm">
                    <div class="flex items-center gap-2">
                        @if($a->is_pinned)<span>📌</span>@endif
                        <span class="font-medium">{{ $a->title }}</span>
                    </div>
                    <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($a->body, 140) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $a->published_at->format('d M Y, h:i A') }}</p>
                </a>
            @empty
                <p class="text-sm text-gray-500 py-3">No notices.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
