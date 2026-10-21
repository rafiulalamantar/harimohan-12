<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const LINK_SENT = 'If that email is registered, we have sent a password reset link to it. Check your inbox (and spam folder).';

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'));
    }

    public function test_registered_student_is_emailed_a_reset_link(): void
    {
        $student = User::factory()->create(['email' => 'rahim@example.com']);
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'rahim@example.com'])
            ->assertSessionHas('success', self::LINK_SENT);

        Notification::assertSentTo($student, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_reply_and_no_email(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('success', self::LINK_SENT)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_student_can_set_a_new_password_with_a_valid_link(): void
    {
        $student = User::factory()->create(['email' => 'rahim@example.com']);
        $token = Password::createToken($student);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'rahim@example.com',
            'password' => 'newsecret123', 'password_confirmation' => 'newsecret123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been reset. You can log in now.');

        $this->assertTrue(Hash::check('newsecret123', $student->fresh()->password));
        $this->post('/login', ['login' => 'rahim@example.com', 'password' => 'newsecret123'])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_invalid_reset_link_does_not_change_the_password(): void
    {
        $student = User::factory()->create(['email' => 'rahim@example.com']);
        $oldHash = $student->password;

        $this->post(route('password.update'), [
            'token' => 'wrong-token', 'email' => 'rahim@example.com',
            'password' => 'newsecret123', 'password_confirmation' => 'newsecret123',
        ])->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Please request a new one.']);

        $this->assertSame($oldHash, $student->fresh()->password);
    }

    public function test_new_password_must_be_at_least_8_characters(): void
    {
        $student = User::factory()->create(['email' => 'rahim@example.com']);

        $this->post(route('password.update'), [
            'token' => Password::createToken($student), 'email' => 'rahim@example.com',
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
