<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\MemberEmailMessage;
use App\Models\MemberEmail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberEmailController extends Controller
{
    public function create(): View
    {
        $members = User::where('role', User::ROLE_STUDENT);

        return view('admin.member-emails.create', [
            'audienceCounts' => collect(MemberEmail::AUDIENCES)->map(fn ($label, $audience) => MemberEmail::recipients($audience)->count()),
            'batches' => (clone $members)->whereNotNull('ssc_batch')->distinct()->orderByDesc('ssc_batch')->pluck('ssc_batch'),
            'remainingToday' => MemberEmail::remainingToday(),
            'history' => MemberEmail::with('sender')->latest()->take(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(MemberEmail::AUDIENCES))],
            'ssc_batch' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $emails = MemberEmail::recipients($data['audience'], $data['ssc_batch'] ?? null)->pluck('email');

        if ($emails->isEmpty()) {
            return back()->withInput()->with('error', 'No members match that group, so nothing was sent.');
        }

        $remaining = MemberEmail::remainingToday();
        if ($emails->count() > $remaining) {
            return back()->withInput()->with('error', "This would send {$emails->count()} emails, but only {$remaining} are left today (Brevo's free plan allows ".MemberEmail::DAILY_LIMIT.' a day). Pick a smaller group or try again tomorrow.');
        }

        $sender = $request->user();
        // Batches of Bcc recipients, so one SMTP call covers many members and stays inside Vercel's time limit.
        foreach ($emails->chunk(MemberEmail::RECIPIENTS_PER_MESSAGE) as $chunk) {
            // Bcc only: no visible "To" address, so members see neither each other nor the site's own mailbox.
            Mail::bcc($chunk->values()->all())
                ->send(new MemberEmailMessage($data['subject'], $data['body'], $sender));
        }

        MemberEmail::create($data + ['user_id' => $sender->id, 'recipient_count' => $emails->count()]);

        return redirect()->route('admin.member-emails.create')
            ->with('success', "Email sent to {$emails->count()} ".str('member')->plural($emails->count()).'.');
    }
}
