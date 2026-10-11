<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee(__('auth.ui.login_title'));
    }

    public function test_user_can_log_in_and_last_login_is_recorded(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => '  Seller@EXAMPLE.com ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_generic_error(): void
    {
        User::factory()->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_log_in_and_sees_the_reason(): void
    {
        User::factory()->suspended(reason: 'Selling prohibited items.')->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.inactive', ['reason' => 'Selling prohibited items.'])]);

        $this->assertGuest();
    }

    public function test_expired_suspension_allows_login_again(): void
    {
        $user = User::factory()->suspended(now()->subDay())->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_banned_user_cannot_log_in(): void
    {
        User::factory()->banned()->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_account_reason_is_not_shown_for_a_wrong_password(): void
    {
        User::factory()->banned('Secret moderation note')->create(['email' => 'seller@example.com']);

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        User::factory()->create(['email' => 'seller@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_remember_me_sets_a_recaller_cookie(): void
    {
        User::factory()->create(['email' => 'seller@example.com']);

        $response = $this->post('/login', ['email' => 'seller@example.com', 'password' => 'password', 'remember' => '1']);

        $this->assertNotEmpty(collect($response->headers->getCookies())->filter(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
