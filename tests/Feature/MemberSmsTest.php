<?php

namespace Tests\Feature;

use App\Models\MemberEmail;
use App\Models\MemberSms;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MemberSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.api_key' => 'test-key', 'sms.rate' => 0.40]);
        Http::preventStrayRequests();
    }

    private function fakeGateway(float $balance = 100, array $send = ['error' => 0, 'msg' => 'Request successfully submitted', 'data' => ['request_id' => 1234]]): void
    {
        Http::fake([
            'api.sms.net.bd/user/balance/*' => Http::response(['error' => 0, 'msg' => 'Success', 'data' => ['balance' => (string) $balance]]),
            'api.sms.net.bd/sendsms' => Http::response($send),
        ]);
    }

    private function send(array $overrides = [])
    {
        return $this->actingAs(User::factory()->admin()->create())->post(route('admin.member-sms.store'), $overrides + [
            'audience' => MemberEmail::AUDIENCE_ALL,
            'body' => 'Iftar 2027 is at the school field.',
        ]);
    }

    public function test_students_cannot_send_sms(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.member-sms.store'), ['audience' => 'all', 'body' => 'Hi'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_until_the_api_key_is_set(): void
    {
        config(['sms.api_key' => null]);
        User::factory()->create();

        $this->send()->assertSessionHas('error', 'SMS is not set up yet: add SMS_API_KEY in Vercel, then redeploy.');

        Http::assertNothingSent();
    }

    public function test_one_request_texts_approved_and_pending_members_but_not_rejected_or_admin_only_accounts(): void
    {
        $this->fakeGateway();
        User::factory()->create(['phone' => '01711111111']);
        User::factory()->pending()->create(['phone' => '01822222222']);
        User::factory()->create(['phone' => '01933333333', 'status' => User::STATUS_REJECTED]);

        $this->send()->assertRedirect(route('admin.member-sms.create'))
            ->assertSessionHas('success', 'SMS sent to 2 members (1 SMS each, about ৳0.80).');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sms.net.bd/sendsms'
            && $request['api_key'] === 'test-key'
            && $request['to'] === '01711111111,01822222222'
            && $request['msg'] === 'Iftar 2027 is at the school field.');
        $this->assertDatabaseHas('member_sms', ['audience' => 'all', 'recipient_count' => 2, 'parts' => 1, 'request_ids' => '1234']);
    }

    public function test_large_groups_are_split_into_requests_of_100_numbers(): void
    {
        $this->fakeGateway();
        User::factory()->count(101)->create();

        $this->send()->assertSessionHas('success');

        Http::assertSentCount(3); // balance check + 2 send requests
    }

    public function test_the_gateways_error_is_shown_and_nothing_is_logged(): void
    {
        $this->fakeGateway(send: ['error' => 421, 'msg' => 'You can only send sms to your registered phone number until first balance recharge']);
        User::factory()->create();

        $this->send()->assertSessionHas('error', 'The SMS gateway refused the message: You can only send sms to your registered phone number until first balance recharge (error 421).');

        $this->assertDatabaseCount('member_sms', 0);
    }

    public function test_nothing_is_sent_without_enough_balance(): void
    {
        $this->fakeGateway(balance: 0.5);
        User::factory()->count(2)->create();

        $this->send()->assertSessionHas('error', 'Not enough SMS balance: this needs about ৳0.80 but only ৳0.50 is left. Recharge at portal.sms.net.bd, then try again.');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendsms'));
    }

    #[TestWith([160, 'a', 1])]
    #[TestWith([161, 'a', 2])]
    #[TestWith([306, 'a', 2])]
    #[TestWith([70, 'আ', 1])]
    #[TestWith([71, 'আ', 2])]
    public function test_sms_parts_follow_the_english_and_bangla_length_limits(int $length, string $character, int $parts): void
    {
        $this->assertSame($parts, MemberSms::parts(str_repeat($character, $length)));
    }

    public function test_the_page_shows_balance_and_member_counts(): void
    {
        $this->fakeGateway(balance: 2.5);
        User::factory()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.member-sms.create'))
            ->assertOk()
            ->assertSeeText('Balance: ৳2.50')
            ->assertSee('All members (approved + pending) (3)')
            ->assertSee('No SMS sent yet.');
    }
}
