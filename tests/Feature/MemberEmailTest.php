<?php

namespace Tests\Feature;

use App\Mail\MemberEmailMessage;
use App\Models\MemberEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberEmailTest extends TestCase
{
    use RefreshDatabase;

    private function send(User $admin, array $overrides = [])
    {
        return $this->actingAs($admin)->post(route('admin.member-emails.store'), $overrides + [
            'audience' => MemberEmail::AUDIENCE_ALL,
            'subject' => 'Iftar 2027 venue',
            'body' => "Dear all,\nThe venue is the school field.",
        ]);
    }

    public function test_students_cannot_email_members(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.member-emails.store'), ['audience' => 'all', 'subject' => 'Hi', 'body' => 'Hi'])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_subject_message_and_group_are_required(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.member-emails.store'), [])
            ->assertSessionHasErrors(['audience', 'subject', 'body']);

        Mail::assertNothingSent();
    }

    public function test_all_members_means_approved_and_pending_but_never_rejected_or_admin_only_accounts(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        User::factory()->create(['email' => 'approved@example.com']);
        User::factory()->pending()->create(['email' => 'pending@example.com']);
        User::factory()->create(['email' => 'rejected@example.com', 'status' => User::STATUS_REJECTED]);

        $this->send($admin)->assertRedirect(route('admin.member-emails.create'))
            ->assertSessionHas('success', 'Email sent to 2 members.');

        Mail::assertSent(MemberEmailMessage::class, fn (MemberEmailMessage $mail) => $mail->hasBcc('approved@example.com')
            && $mail->hasBcc('pending@example.com')
            && ! $mail->hasBcc('rejected@example.com')
            && ! $mail->hasBcc('admin@example.com')
            && $mail->hasTo(config('mail.from.address'))
            && $mail->hasReplyTo('admin@example.com')
            && $mail->hasSubject('Iftar 2027 venue'));
        $this->assertDatabaseHas('member_emails', ['user_id' => $admin->id, 'subject' => 'Iftar 2027 venue', 'audience' => 'all', 'recipient_count' => 2]);
    }

    public function test_an_admin_can_email_one_status_and_one_batch(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'approved-2012@example.com', 'ssc_batch' => 2012]);
        User::factory()->create(['email' => 'approved-2010@example.com', 'ssc_batch' => 2010]);
        User::factory()->pending()->create(['email' => 'pending-2012@example.com', 'ssc_batch' => 2012]);

        $this->send(User::factory()->admin()->create(), ['audience' => MemberEmail::AUDIENCE_APPROVED, 'ssc_batch' => 2012])
            ->assertSessionHas('success', 'Email sent to 1 member.');

        Mail::assertSent(MemberEmailMessage::class, fn (MemberEmailMessage $mail) => $mail->hasBcc('approved-2012@example.com')
            && ! $mail->hasBcc('approved-2010@example.com')
            && ! $mail->hasBcc('pending-2012@example.com'));
    }

    public function test_large_groups_are_split_into_messages_of_50_bcc_recipients(): void
    {
        Mail::fake();
        User::factory()->count(51)->create();

        $this->send(User::factory()->admin()->create())->assertSessionHas('success', 'Email sent to 51 members.');

        Mail::assertSentCount(2);
        Mail::assertSent(MemberEmailMessage::class, fn (MemberEmailMessage $mail) => count($mail->bcc) === 50);
        Mail::assertSent(MemberEmailMessage::class, fn (MemberEmailMessage $mail) => count($mail->bcc) === 1);
    }

    public function test_nothing_is_sent_when_it_would_pass_the_daily_brevo_limit(): void
    {
        Mail::fake();
        // Earlier today: 290 members + 6 "To" copies = 296 emails, leaving 4.
        MemberEmail::factory()->create(['recipient_count' => 290]);
        User::factory()->count(4)->create();

        // 4 members need 5 emails (4 Bcc + 1 "To" copy), one more than the 4 left.
        $this->send(User::factory()->admin()->create())
            ->assertSessionHas('error', "This would send 5 emails, but only 4 are left today (Brevo's free plan allows 300 a day). Pick a smaller group or try again tomorrow.");

        Mail::assertNothingSent();
        $this->assertDatabaseCount('member_emails', 1);
    }

    public function test_yesterdays_emails_do_not_count_against_today(): void
    {
        $this->freezeTime();
        MemberEmail::factory()->create(['recipient_count' => 250, 'created_at' => now()->subDay()]);
        MemberEmail::factory()->create(['recipient_count' => 20]);

        // Only today's 20 members + 1 "To" copy count.
        $this->assertSame(279, MemberEmail::remainingToday());
    }

    public function test_an_empty_group_sends_nothing(): void
    {
        Mail::fake();

        $this->send(User::factory()->admin()->create(), ['audience' => MemberEmail::AUDIENCE_PENDING])
            ->assertSessionHas('error', 'No members match that group, so nothing was sent.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('member_emails', 0);
    }

    public function test_the_message_is_escaped_and_keeps_line_breaks(): void
    {
        $mail = new MemberEmailMessage('<b>Hi</b>', "Line one\n<script>alert(1)</script>", User::factory()->make(['name' => 'Antar']));

        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSeeInHtml('<script>alert(1)</script>', false)
            ->assertSeeInHtml('white-space:pre-line', false)
            ->assertSeeInText("Line one\n<script>alert(1)</script>", false);
    }

    public function test_admins_see_the_page_with_member_counts_and_todays_remaining_emails(): void
    {
        User::factory()->count(3)->create();
        User::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.member-emails.create'))
            ->assertOk()
            ->assertSee('All members (approved + pending) (4)')
            ->assertSee('Approved members only (3)')
            ->assertSeeText('300 of 300 emails left today')
            ->assertSee('No emails sent yet.');
    }
}
