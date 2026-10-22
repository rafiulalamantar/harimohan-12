<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberEmail;
use App\Models\MemberSms;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class MemberSmsController extends Controller
{
    public function create(SmsGateway $gateway): View
    {
        $configured = SmsGateway::isConfigured();

        return view('admin.member-sms.create', [
            'configured' => $configured,
            'balance' => $configured ? $gateway->balance() : null,
            'rate' => (float) config('sms.rate'),
            'audienceCounts' => collect(MemberEmail::AUDIENCES)->map(fn ($label, $audience) => MemberSms::recipients($audience)->count()),
            'batches' => User::where('role', User::ROLE_STUDENT)->whereNotNull('ssc_batch')->distinct()->orderByDesc('ssc_batch')->pluck('ssc_batch'),
            'history' => MemberSms::with('sender')->latest()->take(10)->get(),
        ]);
    }

    public function store(Request $request, SmsGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(MemberEmail::AUDIENCES))],
            'ssc_batch' => ['nullable', 'integer'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        if (! SmsGateway::isConfigured()) {
            return back()->withInput()->with('error', 'SMS is not set up yet: add SMS_API_KEY in Vercel, then redeploy.');
        }

        $numbers = MemberSms::recipients($data['audience'], $data['ssc_batch'] ?? null)->pluck('phone')->unique()->values();

        if ($numbers->isEmpty()) {
            return back()->withInput()->with('error', 'No members with a mobile number match that group, so nothing was sent.');
        }

        $parts = MemberSms::parts($data['body']);
        $cost = round($numbers->count() * $parts * (float) config('sms.rate'), 2);
        $costText = number_format($cost, 2);
        $balance = $gateway->balance();

        if ($balance !== null && $balance < $cost) {
            return back()->withInput()->with('error', "Not enough SMS balance: this needs about ৳{$costText} but only ৳".number_format($balance, 2).' is left. Recharge at portal.sms.net.bd, then try again.');
        }

        try {
            $requestIds = $gateway->send($numbers->all(), $data['body']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        MemberSms::create($data + [
            'user_id' => $request->user()->id,
            'recipient_count' => $numbers->count(),
            'parts' => $parts,
            'request_ids' => implode(',', array_filter($requestIds)),
        ]);

        return redirect()->route('admin.member-sms.create')->with('success',
            "SMS sent to {$numbers->count()} ".str('member')->plural($numbers->count())." ({$parts} SMS each, about ৳{$costText}).");
    }
}
