@extends('layouts.app')
@section('title', 'Email members')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white rounded-2xl border p-6 md:p-8">
        <h1 class="text-2xl font-bold">Email members</h1>
        <p class="mt-1 text-sm text-slate-500">Each member gets a private copy (Bcc), so nobody sees anyone else's address. Replies come to your email, {{ auth()->user()->email }}.</p>
        <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
            <strong>{{ $remainingToday }}</strong> of {{ \App\Models\MemberEmail::DAILY_LIMIT }} emails left today (Brevo free plan limit; every member counts as one email).
        </p>

        <form method="POST" action="{{ route('admin.member-emails.store') }}" class="mt-6 space-y-4"
              data-confirm="Send this email to the selected members? It can't be undone.">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="audience" class="block text-sm font-semibold text-slate-700 mb-1.5">Send to <span class="text-rose-600">*</span></label>
                    <select id="audience" name="audience" required class="w-full rounded-xl border-0 bg-white px-3.5 py-2.5 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500">
                        @foreach (\App\Models\MemberEmail::AUDIENCES as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', 'all') === $value)>{{ $label }} ({{ $audienceCounts[$value] }})</option>
                        @endforeach
                    </select>
                    @error('audience')<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ssc_batch" class="block text-sm font-semibold text-slate-700 mb-1.5">SSC batch</label>
                    <select id="ssc_batch" name="ssc_batch" class="w-full rounded-xl border-0 bg-white px-3.5 py-2.5 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500">
                        <option value="">All batches</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch }}" @selected((string) old('ssc_batch') === (string) $batch)>{{ $batch }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <x-field name="subject" label="Subject" required maxlength="150" />
            <x-field name="body" label="Message" type="textarea" required maxlength="10000"
                     hint="Plain text. Line breaks are kept." />
            <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Send email</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border p-6 md:p-8">
        <h2 class="text-lg font-bold">Recently sent</h2>
        @forelse ($history as $email)
            <div class="mt-4 border-t border-slate-100 pt-4 first:border-0 first:pt-0">
                <div class="font-semibold text-slate-900 break-words">{{ $email->subject }}</div>
                <div class="text-xs text-slate-500 mt-0.5">
                    {{ \App\Models\MemberEmail::AUDIENCES[$email->audience] ?? $email->audience }}{{ $email->ssc_batch ? ' · SSC '.$email->ssc_batch : '' }}
                    · {{ $email->recipient_count }} {{ str('member')->plural($email->recipient_count) }}
                    · by {{ $email->sender?->name ?? 'a former admin' }} · {{ $email->created_at->diffForHumans() }}
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-slate-500">No emails sent yet.</p>
        @endforelse
    </div>
</div>
@endsection
