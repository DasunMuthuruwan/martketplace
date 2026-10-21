<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * S8.5: session fixation and host safety. A session id known before signing in (planted by an
 * attacker) must not survive the login; logout ends the session and its CSRF token; password-reset
 * links always point at APP_URL, whatever host the request used.
 */
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_new_session_id(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com', 'password' => 'correct-horse-battery']);
        $this->get('/login')->assertOk();
        $planted = session()->getId();
        $tokenBefore = session()->token();

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'correct-horse-battery'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($planted, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token(), 'the CSRF token changes with the session');
    }

    public function test_a_failed_login_does_not_sign_in_the_planted_session(): void
    {
        User::factory()->create(['email' => 'seller@example.com', 'password' => 'correct-horse-battery']);
        $this->get('/login');

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong-password-here']);

        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_its_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account')->assertOk();
        session()->put('marker', 'signed-in data');
        $id = session()->getId();
        $token = session()->token();

        $this->post('/logout')->assertRedirect();

        $this->assertGuest();
        $this->assertNotSame($id, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertNull(session('marker'));
    }

    public function test_reset_links_use_app_url_even_through_an_alias_host(): void
    {
        Notification::fake();
        config(['marketplace.app_aliases' => ['old.example.com']]);
        $user = User::factory()->create(['email' => 'seller@example.com']);

        $this->post('http://old.example.com/forgot-password', ['email' => 'seller@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = (string) $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith(rtrim((string) config('app.url'), '/').'/reset-password/', $url);
            $this->assertStringNotContainsString('old.example.com', $url);

            return true;
        });
    }

    public function test_a_forged_host_cannot_request_a_reset_link(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'seller@example.com']);

        $this->post('http://attacker.example/forgot-password', ['email' => 'seller@example.com'])->assertStatus(400);

        Notification::assertNothingSent();
    }
}
