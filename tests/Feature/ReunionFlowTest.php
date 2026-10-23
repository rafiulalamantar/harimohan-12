<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\ReunionEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class ReunionFlowTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bkash.enabled' => true,
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

    public function test_registration_no_longer_collects_roll_number(): void
    {
        $this->get('/register')->assertDontSee('name="roll_no"', false);

        $this->post('/register', [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '01712345678', 'ssc_batch' => 2010, 'roll_no' => '11',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertFalse(Schema::hasColumn('users', 'roll_no'));
    }

    public function test_directory_page_links_are_marked_for_in_place_paging(): void
    {
        User::factory()->count(25)->create();

        // resources/js/directory.js swaps #alumni for links inside [data-directory-pages] instead of reloading the page.
        $this->get('/')->assertSee('data-directory-pages', false)->assertSee('?page=2#alumni', false);
        $this->get('/?page=2')->assertOk()->assertSee('id="alumni"', false);
    }

    public function test_directory_shows_long_job_titles_in_full(): void
    {
        $job = 'Engineer (Deputy Manager-Electrical Maintenance) at Bangladesh India Friendship Power Company Limited';
        User::factory()->create(['profession' => 'Engineer (Deputy Manager-Electrical Maintenance)', 'company_name' => 'Bangladesh India Friendship Power Company Limited']);

        $this->get('/')->assertSee($job)->assertDontSee('line-clamp-2');
    }

    public function test_student_can_login_with_phone(): void
    {
        $user = User::factory()->create(['phone' => '01811111111']);

        $this->post('/login', ['login' => '01811111111', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_stays_active_for_ten_days_until_logout(): void
    {
        $user = User::factory()->create(['email' => 'stay@example.com']);
        $recaller = Auth::guard()->getRecallerName();

        $cookie = $this->post('/login', ['login' => 'stay@example.com', 'password' => 'password'])
            ->assertCookie($recaller)
            ->getCookie($recaller, false);

        // The remember cookie lasts 10 days; after that the member has to log in again.
        $this->assertEqualsWithDelta(now()->addDays(10)->getTimestamp(), $cookie->getExpiresTime(), 60);

        // A fresh session (idle timeout, browser restart) is logged back in by the remember cookie.
        Auth::forgetGuards();
        $this->flushSession();
        $this->withUnencryptedCookie($recaller, $cookie->getValue())
            ->get(route('portal.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'))->assertCookieExpired($recaller);

        // The old remember cookie no longer works once the member has logged out.
        Auth::forgetGuards();
        $this->flushSession();
        $this->withUnencryptedCookie($recaller, $cookie->getValue())
            ->get(route('portal.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_registration_keeps_the_new_member_logged_in(): void
    {
        $this->post('/register', [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '01712345678', 'ssc_batch' => 2010,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCookie(Auth::guard()->getRecallerName());
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
        $this->actingAs(User::factory()->create())->get('/admin')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('error', "You don't have access to the admin panel.");
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

    public function test_student_can_register_with_a_blood_group_shown_in_the_directory(): void
    {
        $this->get('/register')->assertSee('<option value="AB-"', false);

        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'blood_group' => 'AB-', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $user = User::where('email', 'karim@example.com')->first();
        $this->assertSame('AB-', $user->blood_group);

        auth()->logout();
        User::factory()->create(['blood_group' => 'O+']);
        $this->get(route('home'))->assertSee('🩸 O+');
    }

    public function test_registration_rejects_an_unknown_blood_group(): void
    {
        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'blood_group' => 'C+', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('blood_group');
    }

    public function test_student_can_change_blood_group_on_profile(): void
    {
        $user = User::factory()->create(['blood_group' => 'A+']);

        $this->actingAs($user)->get(route('portal.profile'))->assertSee('<option value="A+" selected', false);
        $this->actingAs($user)->put(route('portal.profile.update'), [
            'name' => $user->name, 'phone' => $user->phone, 'blood_group' => 'O-',
        ])->assertSessionHasNoErrors();

        $this->assertSame('O-', $user->fresh()->blood_group);
    }

    public function test_server_rejects_heic_photos_the_browser_did_not_convert(): void
    {
        Storage::fake('public');

        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'photo' => new UploadedFile(base_path('tests/fixtures/photo.heic'), 'IMG_0001.HEIC', 'image/heic', null, true),
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('users', ['email' => 'karim@example.com']);
        // The picker offers HEIC so resources/js/photos/process.js can convert it to JPEG before upload.
        $this->get('/register')->assertSee('accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.heic,.heif"', false);
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

    public function test_welcome_page_lists_approved_students_without_phone_numbers_for_guests(): void
    {
        User::factory()->create([
            'name' => 'Nusrat', 'phone' => '01911111111', 'profession' => 'Engineer', 'company_name' => 'Grameenphone',
            'linkedin_url' => 'https://www.linkedin.com/in/nusrat', 'facebook_url' => 'https://www.facebook.com/nusrat',
        ]);
        User::factory()->pending()->create(['name' => 'Pending Person']);
        User::factory()->admin()->create(['name' => 'Admin Person']);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Registered Alumni')->assertSee('(1)')
            ->assertSee('Nusrat')->assertSee('Engineer at Grameenphone')
            ->assertSee('https://www.linkedin.com/in/nusrat')->assertSee('https://www.facebook.com/nusrat')
            ->assertSee('Log in to contact')->assertDontSee('01911111111')
            ->assertDontSee('Pending Person')->assertDontSee('Admin Person');
    }

    public function test_approved_member_sees_alumni_phone_numbers(): void
    {
        User::factory()->create(['name' => 'Nusrat', 'phone' => '01911111111']);

        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertOk()->assertSee('tel:01911111111', false);
    }

    public function test_pending_member_does_not_see_alumni_phone_numbers(): void
    {
        User::factory()->create(['name' => 'Nusrat', 'phone' => '01911111111']);

        $this->actingAs(User::factory()->pending()->create())->get(route('home'))
            ->assertOk()->assertSee('Nusrat')->assertDontSee('01911111111');
    }

    public function test_welcome_directory_is_paginated(): void
    {
        User::factory()->count(30)->create();

        $this->get(route('home'))->assertOk()
            ->assertSee('(30)')->assertSee('Showing 1–24 of 30 alumni');
        $this->get(route('home', ['page' => 2]))->assertOk()
            ->assertSee('Showing 25–30 of 30 alumni');
    }

    public function test_pagination_shows_labelled_previous_and_next_buttons(): void
    {
        User::factory()->count(30)->create();

        // Laravel's default view hid these labels above the sm breakpoint, leaving only small chevrons.
        $this->get(route('home', ['page' => 2]))->assertOk()
            ->assertSeeInOrder(['rel="prev"', 'Previous', 'Page 2 of 2', 'Next'], false);
    }

    public function test_welcome_directory_filters_by_search_term_and_batch(): void
    {
        User::factory()->create(['name' => 'Nusrat Jahan', 'ssc_batch' => 2012]);
        User::factory()->create(['name' => 'Karim Uddin', 'ssc_batch' => 2012]);
        User::factory()->create(['name' => 'Nusrat Ara', 'ssc_batch' => 2010]);

        $this->get(route('home', ['q' => 'nusrat', 'batch' => 2012]))->assertOk()
            ->assertSee('Nusrat Jahan')->assertDontSee('Karim Uddin')->assertDontSee('Nusrat Ara');
    }

    public function test_guest_cannot_find_alumni_by_phone_number(): void
    {
        User::factory()->create(['name' => 'Nusrat', 'phone' => '01911111111']);

        $this->get(route('home', ['q' => '01911111111']))->assertOk()
            ->assertDontSee('Nusrat')->assertSee('No alumni match your search.');
    }

    public function test_rejected_student_is_logged_out_on_next_portal_request(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('portal.dashboard'))->assertOk();
        $student->forceFill(['status' => User::STATUS_REJECTED])->save();

        $response = $this->get(route('portal.profile'));

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => 'Your registration was rejected. Please contact an admin.']);
        $this->assertGuest();
    }

    public function test_rejecting_a_student_invalidates_their_remember_me_token(): void
    {
        $student = User::factory()->create(['remember_token' => 'old-remember-token']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.status', $student), ['status' => User::STATUS_REJECTED]);

        $this->assertNotSame('old-remember-token', $student->fresh()->remember_token);
    }

    public function test_admin_account_cannot_be_opened_as_a_student(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.show', $otherAdmin))
            ->assertNotFound();
    }

    public function test_student_can_login_with_country_code_and_spaced_phone(): void
    {
        $user = User::factory()->create(['phone' => '01811111111']);

        $this->post('/login', ['login' => '+880 1811-111111', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_stores_phone_without_country_code(): void
    {
        $this->post('/register', [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '+8801711111111', 'ssc_batch' => 2012,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'rahim@example.com', 'phone' => '01711111111']);
    }

    public function test_registration_rejects_names_with_symbols_but_accepts_bangla(): void
    {
        $form = ['email' => 'karim@example.com', 'phone' => '01711111111', 'ssc_batch' => 2012,
            'password' => 'secret123', 'password_confirmation' => 'secret123'];

        $this->post('/register', $form + ['name' => 'QA <b>Bold</b>'])
            ->assertSessionHasErrors(['name' => 'Name can only contain letters, spaces, dots, hyphens and apostrophes.']);
        $this->assertDatabaseCount('users', 0);

        $this->post('/register', $form + ['name' => "মো. রফিউল O'Brien-Alam"])->assertRedirect(route('portal.dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'karim@example.com', 'name' => "মো. রফিউল O'Brien-Alam"]);
    }

    public function test_profile_update_rejects_a_name_with_symbols(): void
    {
        $student = User::factory()->create(['name' => 'Karim Uddin']);

        $this->actingAs($student)->put(route('portal.profile.update'), ['name' => 'Karim <script>', 'phone' => $student->phone])
            ->assertSessionHasErrors(['name' => 'Name can only contain letters, spaces, dots, hyphens and apostrophes.']);
        $this->assertSame('Karim Uddin', $student->fresh()->name);
    }

    public function test_profile_update_replaces_photo_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $oldPhoto = UploadedFile::fake()->image('old.jpg')->store('photos', 'public');
        $student = User::factory()->create(['photo' => $oldPhoto]);

        $this->actingAs($student)->put(route('portal.profile.update'), [
            'name' => $student->name, 'phone' => $student->phone, 'photo' => UploadedFile::fake()->image('new.jpg'),
        ])->assertSessionHas('success');

        $newPhoto = $student->fresh()->photo;
        $this->assertNotSame($oldPhoto, $newPhoto);
        Storage::disk('public')->assertExists($newPhoto);
        Storage::disk('public')->assertMissing($oldPhoto);
    }

    public function test_csv_exports_neutralise_formula_cells(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['name' => '=HYPERLINK("http://evil.test","Click")']);
        Payment::create([
            'user_id' => $student->id, 'reunion_event_id' => $this->event()->id, 'amount' => 1500,
            'invoice_no' => 'INV-1', 'status' => Payment::STATUS_COMPLETED,
        ]);

        $studentsCsv = $this->actingAs($admin)->get(route('admin.students.export'))->streamedContent();
        $paymentsCsv = $this->actingAs($admin)->get(route('admin.payments.export'))->streamedContent();

        foreach ([$studentsCsv, $paymentsCsv] as $csv) {
            $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
            $this->assertStringNotContainsString('"=HYPERLINK(', $csv);
        }
    }

    public function test_payment_deadline_after_event_date_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.events.store'), [
            'title' => 'Reunion', 'fee' => 1500, 'event_date' => '2026-12-01', 'payment_deadline' => '2026-12-02',
        ])->assertSessionHasErrors(['payment_deadline' => 'The payment deadline must be on or before the event date.']);

        $this->assertDatabaseCount('reunion_events', 0);
    }

    public function test_fractional_fee_is_shown_to_the_paisa(): void
    {
        $this->event(['fee' => 500.50]);

        $this->actingAs(User::factory()->create())->get(route('portal.dashboard'))
            ->assertOk()->assertSee('Pay ৳500.50 with bKash');
    }

    public function test_responses_include_security_headers(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_responses_enforce_a_content_security_policy(): void
    {
        $this->withVite();
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));

        $policy = $this->get('/up')->assertOk()->headers->get('Content-Security-Policy');

        // WebAssembly + blob: workers for in-browser HEIC conversion, but never 'unsafe-eval'.
        $this->assertStringContainsString("script-src 'self' 'wasm-unsafe-eval';", $policy);
        $this->assertStringContainsString("worker-src 'self' blob:;", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringContainsString("form-action 'self' https://*.bka.sh https://*.bkash.com;", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function test_content_security_policy_allows_images_from_external_upload_storage(): void
    {
        $this->withVite();
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));
        config(['filesystems.disks.public.url' => 'https://pub-abc123.r2.dev/uploads']);

        $policy = $this->get('/up')->assertOk()->headers->get('Content-Security-Policy');

        // blob: is the photo cropper's preview of the framed photo.
        $this->assertStringContainsString("img-src 'self' data: blob: https://pub-abc123.r2.dev;", $policy);
    }

    public function test_content_security_policy_only_reports_in_local_environment(): void
    {
        $this->withVite();
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/up')->assertOk()
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_content_security_policy_is_skipped_while_vite_dev_server_runs(): void
    {
        $hotFile = storage_path('framework/testing/vite.hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://localhost:5173');
        $this->withVite();
        Vite::useHotFile($hotFile);

        try {
            $this->get('/up')->assertOk()->assertHeaderMissing('Content-Security-Policy');
        } finally {
            File::delete($hotFile);
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
        auth()->logout();
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

    public function test_payment_closes_after_the_event_day_even_if_the_deadline_is_later(): void
    {
        Http::preventStrayRequests();
        $event = $this->event(['event_date' => now()->subDay()->toDateString(), 'payment_deadline' => now()->addWeek()->toDateString()]);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.dashboard'))->assertSee('Payments are currently closed.');
        $this->actingAs($student)->post(route('portal.pay', $event))->assertSessionHas('error', 'Payments for this event are closed.');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_stays_open_on_the_event_day(): void
    {
        $this->assertTrue($this->event(['event_date' => now()->toDateString()])->isPaymentOpen());
    }

    public function test_super_admin_makes_a_student_an_admin_who_keeps_their_portal_and_directory_listing(): void
    {
        $student = User::factory()->create(['name' => 'Karim Uddin']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.admin', $student), ['admin' => '1'])
            ->assertSessionHas('success', 'Karim Uddin is now an admin.');

        $this->assertSame(User::ADMIN_LEVEL_ADMIN, $student->fresh()->admin_level);
        $this->actingAs($student->fresh())->get('/admin')->assertOk();
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Admin panel');
        auth()->logout();
        $this->get('/')->assertSee('Karim Uddin');
    }

    public function test_only_super_admins_see_the_make_admin_button(): void
    {
        $student = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.show', $student))->assertSee('Make admin');
        $this->actingAs(User::factory()->studentAdmin()->create())
            ->get(route('admin.students.show', $student))->assertOk()->assertDontSee('Make admin');
    }

    public function test_admin_only_account_is_sent_from_the_portal_to_the_admin_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('portal.profile'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error', 'The student portal is only for alumni accounts.');
        $this->actingAs($admin)->post(route('portal.pay', $this->event()))->assertForbidden();
        $this->actingAs(User::factory()->studentAdmin()->create())->get(route('portal.dashboard'))->assertOk();
    }

    public function test_admin_bell_counts_new_registrations_and_shows_who_approved(): void
    {
        $antar = User::factory()->admin()->create(['name' => 'Antar']);
        $otherAdmin = User::factory()->studentAdmin()->create();
        $this->post('/register', [
            'name' => 'Karim Uddin', 'email' => 'karim@example.com', 'phone' => '01711111111', 'ssc_batch' => 2012,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
        auth()->logout();
        $karim = User::where('email', 'karim@example.com')->firstOrFail();

        $this->actingAs($otherAdmin)->get('/admin')
            ->assertSee('Registrations: 1 waiting for approval')->assertSee('Karim Uddin');

        $this->actingAs($antar)->patch(route('admin.students.status', $karim), ['status' => User::STATUS_APPROVED]);

        $this->actingAs($otherAdmin)->get('/admin')
            ->assertSee('Registrations: 0 waiting for approval')
            ->assertSee('Approved by Antar');
    }

    public function test_second_admin_approving_an_already_approved_student_is_told_who_did_it(): void
    {
        $antar = User::factory()->admin()->create(['name' => 'Antar']);
        $student = User::factory()->pending()->create(['name' => 'Karim Uddin']);
        $this->actingAs($antar)->patch(route('admin.students.status', $student), ['status' => User::STATUS_APPROVED]);

        $this->actingAs(User::factory()->studentAdmin()->create())
            ->patch(route('admin.students.status', $student), ['status' => User::STATUS_APPROVED])
            ->assertSessionHas('info', 'Karim Uddin is already approved by Antar.');

        $this->assertSame($antar->id, $student->fresh()->reviewed_by);
    }

    public function test_admin_bell_and_menu_are_both_exclusive_dropdowns(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/admin')->getContent();

        // resources/js/dropdowns.js closes every other details[data-dropdown] when one opens, so they never overlap.
        // The bell, the three admin menu groups (Members, Events, Communication) and the ☰ menu.
        $this->assertSame(5, substr_count($html, ' data-dropdown>'));
    }

    public function test_admin_menu_groups_its_pages(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.payments.index'))
            ->assertSeeInOrder(['Dashboard', 'Members', 'Students', 'Student fields', 'Events', 'Events', 'Payments', 'Communication', 'Announcements', 'Email members', 'Welcome page']);

        // The group holding the current page is highlighted; the others are not.
        $this->assertMatchesRegularExpression('/bg-brand-50 text-brand-800">\s*Events\s*<svg/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/bg-brand-50 text-brand-800">\s*Members\s*<svg/', $response->getContent());
    }

    public function test_admin_bell_shows_an_empty_state_and_caps_the_count_at_99(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertSee('No registrations yet.');

        User::factory()->pending()->count(100)->create();
        $this->actingAs($admin)->get('/admin')->assertSee('99+')->assertSee('Registrations: 100 waiting for approval');
    }

    public function test_students_do_not_see_the_registration_bell(): void
    {
        $this->actingAs(User::factory()->create())->get(route('portal.dashboard'))->assertDontSee('waiting for approval');
    }

    public function test_header_shows_the_admin_level_after_login(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee('Super admin</span>', false);
        $this->actingAs(User::factory()->studentAdmin()->create())->get(route('portal.dashboard'))
            ->assertSee('Admin</span>', false)->assertDontSee('Super admin');
        $this->actingAs(User::factory()->create())->get(route('portal.dashboard'))->assertDontSee('🛡️');
    }

    public function test_student_admin_cannot_make_other_students_admins(): void
    {
        $student = User::factory()->create();

        $this->actingAs(User::factory()->studentAdmin()->create())
            ->patch(route('admin.students.admin', $student), ['admin' => '1'])
            ->assertForbidden();

        $this->assertNull($student->fresh()->admin_level);
    }

    public function test_pending_student_cannot_be_made_an_admin(): void
    {
        $student = User::factory()->pending()->create(['name' => 'Karim Uddin']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.admin', $student), ['admin' => '1'])
            ->assertSessionHas('error', 'Approve Karim Uddin before making them an admin.');

        $this->assertNull($student->fresh()->admin_level);
    }

    public function test_super_admin_removes_a_students_admin_access(): void
    {
        $student = User::factory()->studentAdmin()->create(['name' => 'Karim Uddin']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.admin', $student), ['admin' => '0'])
            ->assertSessionHas('success', 'Karim Uddin is no longer an admin.');

        $this->actingAs($student->fresh())->get('/admin')->assertRedirect(route('portal.dashboard'));
    }

    public function test_student_admin_must_lose_admin_access_before_being_rejected(): void
    {
        $student = User::factory()->studentAdmin()->create(['name' => 'Karim Uddin']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.status', $student), ['status' => User::STATUS_REJECTED])
            ->assertSessionHas('error', 'Karim Uddin is an admin. Remove their admin access first.');

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

    public function test_welcome_page_ignores_array_search_term(): void
    {
        User::factory()->create(['name' => 'Nusrat']);

        $this->get('/?q[]=x')->assertOk()->assertSee('Nusrat');
    }

    public function test_array_phone_is_rejected_by_validation(): void
    {
        $this->post('/register', [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => ['01711111111'], 'ssc_batch' => 2012,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('phone');

        $student = User::factory()->create();
        $this->actingAs($student)->put(route('portal.profile.update'), ['name' => $student->name, 'phone' => ['x']])
            ->assertSessionHasErrors('phone');
    }

    public function test_welcome_page_offers_dashboard_instead_of_register_to_logged_in_alumni(): void
    {
        User::factory()->create(['name' => 'Nusrat']);

        $this->get(route('home'))->assertSee('Register now')->assertSee('Nusrat')->assertDontSee('Go to dashboard');

        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertSee('Go to dashboard')->assertSee(route('portal.dashboard'))
            ->assertDontSee('Register now')->assertSee('Nusrat');
    }

    public function test_mobile_number_must_be_11_digits(): void
    {
        $this->post('/register', [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '0171111111', 'ssc_batch' => 2012,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['phone' => 'Mobile number must be 11 digits, like 01XXXXXXXXX.']);

        $student = User::factory()->create();
        $this->actingAs($student)->put(route('portal.profile.update'), ['name' => $student->name, 'phone' => '017111111111'])
            ->assertSessionHasErrors(['phone' => 'Mobile number must be 11 digits, like 01XXXXXXXXX.']);
    }

    public function test_plain_http_is_redirected_to_https_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('http://localhost/login?x=1')->assertRedirect('https://localhost/login?x=1')->assertStatus(301);
    }

    public function test_https_forwarded_by_a_trusted_proxy_is_not_redirected_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        TrustProxies::at('*');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://localhost/login');
        TrustProxies::flushState();

        $response->assertOk();
    }

    public function test_payment_shows_coming_soon_while_bkash_is_disabled(): void
    {
        config(['bkash.enabled' => false]);
        Http::preventStrayRequests();
        $this->get('/')->assertSee('bKash Payment Coming Soon');

        $event = $this->event();
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertSee('bKash Payment Coming Soon')
            ->assertDontSee('with bKash');

        $this->actingAs($student)->post(route('portal.pay', $event))
            ->assertSessionHas('error', 'bKash Payment Coming Soon');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_footer_shows_the_app_version(): void
    {
        config(['app.version' => '9.8.7']);

        $this->get('/')->assertSee('v9.8.7');
    }
}
