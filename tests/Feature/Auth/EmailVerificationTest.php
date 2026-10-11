<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_sent_to_the_verification_notice_for_verified_only_pages(): void
    {
        Route::middleware(['web', 'auth', 'verified'])->get('/_test/post-ad', fn () => 'ok');

        $this->actingAs(User::factory()->unverified()->create())
            ->get('/_test/post-ad')
            ->assertRedirect('/email/verify');

        $this->actingAs(User::factory()->create())->get('/_test/post-ad')->assertOk();
    }

    public function test_verification_notice_renders(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/email/verify')
            ->assertOk()
            ->assertSee(__('auth.ui.verify_title'));
    }

    public function test_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_link_for_another_users_email_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $other->id, 'hash' => sha1($other->email)]);

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url.'x')->assertForbidden();
    }

    public function test_verification_email_can_be_resent_and_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('/email/verification-notification');
        }
        $this->actingAs($user)->post('/email/verification-notification')->assertStatus(429);
    }
}
