<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_known_and_unknown_emails_get_the_same_answer(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'seller@example.com']);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect('/forgot-password')
                ->assertSessionHas('status', __('passwords.sent_generic'))
                ->assertSessionHasNoErrors();
        }

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_json_requests_also_get_a_generic_answer(): void
    {
        $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['message' => __('passwords.sent_generic')]);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);
        $this->post('/forgot-password', ['email' => 'seller@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.$user->email)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])->assertSessionHasNoErrors()->assertRedirect('/login');

            return true;
        });

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    public function test_a_reset_signs_the_account_out_everywhere(): void
    {
        // S-04: whoever may be using the account (the reason for a reset) loses access.
        $user = User::factory()->create(['email' => 'seller@example.com', 'remember_token' => 'old-remember-token']);
        $other = User::factory()->create();
        foreach (['laptop' => $user->id, 'stolen' => $user->id, 'someone-else' => $other->id] as $id => $owner) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner, 'payload' => '', 'last_activity' => time()]);
        }
        $this->post('/forgot-password', ['email' => 'seller@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_reset_with_an_invalid_token_fails(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => "user{$i}@example.com"])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'user9@example.com'])->assertStatus(429);
    }
}
