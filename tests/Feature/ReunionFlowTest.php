<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\ReunionEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReunionFlowTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bkash.base_url' => self::BASE,
            'bkash.username' => 'u', 'bkash.password' => 'p',
            'bkash.app_key' => 'k', 'bkash.app_secret' => 's',
        ]);
    }

    private function event(array $attrs = []): ReunionEvent
    {
        return ReunionEvent::create($attrs + ['title' => 'Reunion', 'fee' => 1500, 'is_active' => true]);
    }

    private function fakeBkash(array $execute, ?array $query = null): void
    {
        Http::fake([
            self::BASE.'/tokenized/checkout/token/grant' => Http::response(['id_token' => 'TOKEN', 'expires_in' => 3600]),
            self::BASE.'/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000', 'paymentID' => 'PAY123', 'bkashURL' => 'https://sandbox.bka.sh/checkout/PAY123',
            ]),
            self::BASE.'/tokenized/checkout/execute' => Http::response($execute),
            self::BASE.'/tokenized/checkout/payment/status' => Http::response($query ?? ['transactionStatus' => 'Initiated']),
        ]);
    }

    public function test_student_can_register_and_starts_pending(): void
    {
        $this->post('/register', [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '01712345678', 'ssc_batch' => 2010,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'rahim@example.com', 'status' => 'pending', 'role' => 'student']);
    }

    public function test_student_can_login_with_phone(): void
    {
        $user = User::factory()->create(['phone' => '01811111111']);

        $this->post('/login', ['login' => '01811111111', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_student_cannot_pay(): void
    {
        $event = $this->event();
        $user = User::factory()->pending()->create();

        $this->actingAs($user)->post(route('portal.pay', $event))->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_successful_bkash_payment_is_recorded_once(): void
    {
        $event = $this->event();
        $user = User::factory()->create();

        $this->fakeBkash(execute: [
            'statusCode' => '0000', 'transactionStatus' => 'Completed', 'trxID' => 'TRX9',
            'amount' => '1500.00', 'customerMsisdn' => '01770618575',
        ]);

        $this->actingAs($user)->post(route('portal.pay', $event))
            ->assertRedirect('https://sandbox.bka.sh/checkout/PAY123');

        $this->get('/payment/bkash/callback?paymentID=PAY123&status=success')
            ->assertRedirect(route('portal.payments'))->assertSessionHas('success');

        $payment = Payment::first();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('TRX9', $payment->trx_id);
        $this->assertTrue($user->hasPaidFor($event));

        // Refreshing the callback must not call execute again.
        $this->get('/payment/bkash/callback?paymentID=PAY123&status=success')->assertSessionHas('success');
        Http::assertSentCount(3); // grant + create + execute

        // And a second pay attempt is blocked.
        $this->actingAs($user)->post(route('portal.pay', $event))->assertSessionHas('success');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $event = $this->event();
        $user = User::factory()->create();
        $this->fakeBkash(execute: ['statusCode' => '0000', 'transactionStatus' => 'Completed', 'trxID' => 'TRX1', 'amount' => '10.00']);

        $this->actingAs($user)->post(route('portal.pay', $event));
        $this->get('/payment/bkash/callback?paymentID=PAY123&status=success')->assertSessionHas('error');

        $this->assertSame('failed', Payment::first()->status);
    }

    public function test_execute_timeout_falls_back_to_query(): void
    {
        $event = $this->event();
        $user = User::factory()->create();
        $this->fakeBkash(
            execute: ['statusCode' => '9999', 'statusMessage' => 'System error'],
            query: ['transactionStatus' => 'Completed', 'trxID' => 'TRXQ', 'amount' => '1500.00'],
        );

        $this->actingAs($user)->post(route('portal.pay', $event));
        $this->get('/payment/bkash/callback?paymentID=PAY123&status=success');

        $this->assertSame('TRXQ', Payment::first()->trx_id);
    }

    public function test_cancelled_payment_is_marked_cancelled(): void
    {
        $event = $this->event();
        $user = User::factory()->create();
        $this->fakeBkash(execute: []);

        $this->actingAs($user)->post(route('portal.pay', $event));
        $this->get('/payment/bkash/callback?paymentID=PAY123&status=cancel')->assertSessionHas('error');

        $this->assertSame('cancelled', Payment::first()->status);
    }

    public function test_only_admin_reaches_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_admin_can_approve_and_post_announcement(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('admin.students.status', $student), ['status' => 'approved']);
        $this->assertSame('approved', $student->fresh()->status);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Venue final', 'body' => 'It will be held at the school field', 'published_at' => now()->subMinute()->toDateTimeString(),
        ]);

        $this->actingAs($student->fresh())->get(route('portal.dashboard'))->assertSee('Venue final');
    }

    public function test_scheduled_announcement_is_hidden_until_publish_time(): void
    {
        Announcement::create(['title' => 'Future notice', 'body' => 'x', 'published_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create())->get(route('portal.announcements'))->assertDontSee('Future notice');
    }

    public function test_student_can_register_with_photo_and_social_links(): void
    {
        Storage::fake('public');

        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'company_name' => 'Acme Ltd', 'linkedin_url' => 'https://www.linkedin.com/in/karim',
            'facebook_url' => 'https://www.facebook.com/karim', 'photo' => UploadedFile::fake()->image('me.jpg'),
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $user = User::where('email', 'karim@example.com')->first();
        $this->assertSame('Acme Ltd', $user->company_name);
        Storage::disk('public')->assertExists($user->photo);
    }

    public function test_registration_rejects_invalid_social_url(): void
    {
        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'linkedin_url' => 'not a url', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('linkedin_url');
    }

    public function test_admin_dashboard_lists_new_students_with_contact_details(): void
    {
        User::factory()->create([
            'name' => 'Nusrat', 'phone' => '01911111111', 'company_name' => 'Grameenphone',
            'linkedin_url' => 'https://www.linkedin.com/in/nusrat', 'facebook_url' => 'https://www.facebook.com/nusrat',
        ]);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertSee('Nusrat')->assertSee('Grameenphone')->assertSee('01911111111')
            ->assertSee('https://www.linkedin.com/in/nusrat')->assertSee('https://www.facebook.com/nusrat');
    }

    public function test_welcome_page_lists_all_approved_students_with_contact_details(): void
    {
        $students = User::factory()->count(120)->create();
        User::factory()->create([
            'name' => 'Nusrat', 'phone' => '01911111111', 'profession' => 'Engineer', 'company_name' => 'Grameenphone',
            'linkedin_url' => 'https://www.linkedin.com/in/nusrat',
        ]);
        User::factory()->pending()->create(['name' => 'Pending Person']);
        User::factory()->admin()->create(['name' => 'Admin Person']);

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('Registered alumni')->assertSee('(121)')
            ->assertSee('Nusrat')->assertSee('Engineer at Grameenphone')->assertSee('01911111111')
            ->assertSee('https://www.linkedin.com/in/nusrat')
            ->assertDontSee('Pending Person')->assertDontSee('Admin Person');

        foreach ($students as $student) {
            $response->assertSee($student->phone);
        }
    }

    public function test_rejected_student_is_removed_from_welcome_page(): void
    {
        $student = User::factory()->create(['name' => 'Rejected Rahim']);
        $this->get(route('home'))->assertSee('Rejected Rahim');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.status', $student), ['status' => User::STATUS_REJECTED])
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_REJECTED, $student->fresh()->status);
        $this->flushSession()->get(route('home'))->assertDontSee('Rejected Rahim');
    }

    public function test_admin_cannot_reject_student_who_already_paid(): void
    {
        $student = User::factory()->create();
        Payment::create(['user_id' => $student->id, 'reunion_event_id' => $this->event()->id,
            'amount' => 1500, 'invoice_no' => 'INV1', 'status' => Payment::STATUS_COMPLETED]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.status', $student), ['status' => User::STATUS_REJECTED])
            ->assertSessionHas('error', "{$student->name} has already paid, so they cannot be rejected.");

        $this->assertSame(User::STATUS_APPROVED, $student->fresh()->status);
    }

    public function test_admin_can_change_fee_after_payments_without_altering_paid_amounts(): void
    {
        $event = $this->event();
        $payment = Payment::create(['user_id' => User::factory()->create()->id, 'reunion_event_id' => $event->id,
            'amount' => 1500, 'invoice_no' => 'INV1', 'status' => Payment::STATUS_COMPLETED]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.events.update', $event), ['title' => 'Iftar', 'fee' => 550, 'is_active' => '1'])
            ->assertRedirect(route('admin.events.index'))->assertSessionHas('success');

        $this->assertEquals(550, $event->fresh()->fee);
        $this->assertEquals(1500, $payment->fresh()->amount);
    }

    public function test_admin_can_delete_event_and_payment_records_are_kept(): void
    {
        $event = $this->event(['title' => 'Old Reunion']);
        $student = User::factory()->create();
        $payment = Payment::create(['user_id' => $student->id, 'reunion_event_id' => $event->id,
            'amount' => 1500, 'invoice_no' => 'INV1', 'status' => Payment::STATUS_COMPLETED]);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.events.destroy', $event))
            ->assertRedirect(route('admin.events.index'))->assertSessionHas('success');

        $this->assertSoftDeleted($event);
        $this->assertNull(ReunionEvent::current());
        $this->assertModelExists($payment);
        $this->actingAs($student)->get(route('portal.payments'))->assertSee('Old Reunion');
    }

    public function test_student_cannot_delete_event(): void
    {
        $event = $this->event();

        $this->actingAs(User::factory()->create())->delete(route('admin.events.destroy', $event))->assertForbidden();
        $this->assertNotSoftDeleted($event);
    }

    public function test_welcome_page_shows_default_hero_until_admin_edits_it(): void
    {
        $this->get(route('home'))->assertSee(Setting::DEFAULTS['hero_title']);
    }

    public function test_admin_can_edit_welcome_page_hero_text_and_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'hero_title' => 'Iftar Mahfil 2027',
            'hero_subtitle' => 'Join us for iftar with your batchmates.',
            'hero_banner' => UploadedFile::fake()->image('iftar.jpg', 1600, 600),
        ])->assertSessionHas('success');

        $banner = Setting::values()['hero_banner'];
        Storage::disk('public')->assertExists($banner);

        $this->get(route('home'))->assertSee('Iftar Mahfil 2027')->assertSee('Join us for iftar with your batchmates.')
            ->assertSee(Setting::bannerUrl($banner))->assertDontSee(Setting::DEFAULTS['hero_title']);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['hero_title' => 'Iftar Mahfil 2027', 'remove_banner' => '1']);

        Storage::disk('public')->assertMissing($banner);
        $this->assertNull(Setting::values()['hero_banner']);
    }

    public function test_student_cannot_edit_welcome_page(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.settings.update'), ['hero_title' => 'Hacked'])->assertForbidden();
    }

    public function test_admin_can_add_and_remove_announcement_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Picnic', 'body' => 'Details', 'published_at' => now()->toDateTimeString(),
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        ])->assertRedirect(route('admin.announcements.index'));

        $announcement = Announcement::first();
        Storage::disk('public')->assertExists($announcement->banner);

        $this->actingAs(User::factory()->create())->get(route('portal.announcements.show', $announcement))
            ->assertSee($announcement->bannerUrl());

        $banner = $announcement->banner;
        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Picnic', 'body' => 'Details', 'published_at' => now()->toDateTimeString(), 'remove_banner' => 1,
        ]);

        $this->assertNull($announcement->fresh()->banner);
        Storage::disk('public')->assertMissing($banner);
    }
}
