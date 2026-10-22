@extends('layouts.app')
@section('title', 'SMS members')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white rounded-2xl border p-6 md:p-8">
        <h1 class="text-2xl font-bold">SMS members</h1>
        <p class="mt-1 text-sm text-slate-500">Sends one text to every member's mobile number through Alpha SMS. Members can't reply to it.</p>

        @if (! $configured)
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200">
                SMS isn't set up yet. Add <code>SMS_API_KEY</code> (from portal.sms.net.bd → API) to Vercel's environment variables as a Secret, then redeploy.
            </p>
        @else
            <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                @if ($balance !== null)
                    Balance: <strong>৳{{ number_format($balance, 2) }}</strong> · about ৳{{ number_format($rate, 2) }} per SMS.
                @else
                    Couldn't read the SMS balance right now; it's checked again when you send.
                @endif
            </p>
        @endif

        <form method="POST" action="{{ route('admin.member-sms.store') }}" class="mt-6 space-y-4"
              data-confirm="Send this SMS to the selected members? It costs money and can't be undone.">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="audience" class="block text-sm font-semibold text-slate-700 mb-1.5">Send to <span class="text-rose-600">*</span></label>
                    <select id="audience" name="audience" required data-sms-audience class="w-full rounded-xl border-0 bg-white px-3.5 py-2.5 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500">
                        @foreach (\App\Models\MemberEmail::AUDIENCES as $value => $label)
                            <option value="{{ $value }}" data-count="{{ $audienceCounts[$value] }}" @selected(old('audience', 'all') === $value)>{{ $label }} ({{ $audienceCounts[$value] }})</option>
                        @endforeach
                    </select>
                    @error('audience')<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ssc_batch" class="block text-sm font-semibold text-slate-700 mb-1.5">SSC batch</label>
                    <select id="ssc_batch" name="ssc_batch" data-sms-batch class="w-full rounded-xl border-0 bg-white px-3.5 py-2.5 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500">
                        <option value="">All batches</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch }}" @selected((string) old('ssc_batch') === (string) $batch)>{{ $batch }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <x-field name="body" label="Message" type="textarea" required maxlength="1000" data-sms-body data-sms-rate="{{ $rate }}"
                     hint="English: 160 characters per SMS. Bangla: 70 characters per SMS, so keep Bangla messages short." />
            <p class="text-sm text-slate-600" data-sms-counter aria-live="polite"></p>
            <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5 disabled:opacity-50" @disabled(! $configured)>Send SMS</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border p-6 md:p-8">
        <h2 class="text-lg font-bold">Recently sent</h2>
        @forelse ($history as $sms)
            <div class="mt-4 border-t border-slate-100 pt-4 first:border-0 first:pt-0">
                <div class="text-slate-900 break-words whitespace-pre-line">{{ $sms->body }}</div>
                <div class="text-xs text-slate-500 mt-1">
                    {{ \App\Models\MemberEmail::AUDIENCES[$sms->audience] ?? $sms->audience }}{{ $sms->ssc_batch ? ' · SSC '.$sms->ssc_batch : '' }}
                    · {{ $sms->recipient_count }} {{ str('member')->plural($sms->recipient_count) }} · {{ $sms->parts }} SMS each
                    · by {{ $sms->sender?->name ?? 'a former admin' }} · {{ $sms->created_at->diffForHumans() }}
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-slate-500">No SMS sent yet.</p>
        @endforelse
    </div>
</div>
@endsection
