<?php

namespace Tests\Feature\Authorization;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class AccountStatusAndAdminAccessTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ---- Account status (AUTH-09) ----------------------------------------------------------

    public function test_user_suspended_mid_session_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account/two-factor')->assertOk();

        $user->forceFill(['status' => 'suspended', 'status_reason' => 'Spam listings.'])->save();

        $this->get('/account/two-factor')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.inactive', ['reason' => 'Spam listings.'])]);
        $this->assertGuest();
    }

    public function test_freshly_created_user_is_active_before_being_reloaded(): void
    {
        // Regression: without model defaults, status was null until reloaded and isActive() crashed.
        $user = User::create(['name' => 'New', 'email' => 'new@example.com', 'password' => 'irrelevant-pass']);

        $this->assertTrue($user->isActive());
        $this->assertSame('hidden', $user->contact_visibility->value);
    }

    public function test_banned_user_gets_403_on_json_requests(): void
    {
        $user = User::factory()->banned()->create();

        $this->actingAs($user)->getJson('/account/two-factor')->assertForbidden();
        $this->assertGuest();
    }

    public function test_expired_suspension_does_not_sign_out(): void
    {
        $user = User::factory()->suspended(now()->subMinute())->create();

        $this->actingAs($user)->get('/account/two-factor')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    // ---- Admin area access -----------------------------------------------------------------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_users_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_unverified_staff_must_verify_email_first(): void
    {
        $admin = $this->staff(StaffRole::Admin, attributes: ['email_verified_at' => null]);

        $this->actingAs($admin)->get('/admin')->assertRedirect('/email/verify');
    }

    /**
     * @return array<string, array{0: StaffRole, 1: bool, 2: bool}>
     */
    public static function staffAccess(): array
    {
        return [
            'owner with 2FA' => [StaffRole::Owner, true, true],
            'owner without 2FA' => [StaffRole::Owner, false, false],
            'admin with 2FA' => [StaffRole::Admin, true, true],
            'admin without 2FA' => [StaffRole::Admin, false, false],
            'moderator with 2FA' => [StaffRole::Moderator, true, true],
            'moderator without 2FA (optional)' => [StaffRole::Moderator, false, true],
        ];
    }

    #[DataProvider('staffAccess')]
    public function test_owner_and_admin_need_confirmed_two_factor(StaffRole $role, bool $twoFactor, bool $allowed): void
    {
        $response = $this->actingAs($this->staff($role, $twoFactor))->get('/admin');

        $allowed
            ? $response->assertOk()->assertSee(__('admin.dashboard.title'))
            : $response->assertRedirect(route('account.two-factor'))->assertSessionHas('status', __('staff.two_factor_required'));
    }

    public function test_enabled_but_unconfirmed_two_factor_is_not_enough(): void
    {
        $admin = $this->staff(StaffRole::Admin, twoFactor: false);
        $admin->forceFill(['two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'), 'two_factor_confirmed_at' => null])->save();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('account.two-factor'));
    }

    // ---- Two-factor setup and sign-in ------------------------------------------------------

    public function test_turning_on_two_factor_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');
        $this->assertNull($user->fresh()->two_factor_secret);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/two-factor-authentication')->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at, 'Not active until a code is confirmed');
    }

    public function test_password_confirmation_expires_after_15_minutes(): void
    {
        $this->assertSame(900, config('auth.password_timeout'));

        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time() - 901])
            ->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');
    }

    public function test_setup_page_shows_qr_code_then_confirmation_activates_two_factor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication');

        $this->get('/account/two-factor')->assertOk()->assertSee('<svg', false)->assertSee(__('staff.two_factor.status_pending'));

        $secret = decrypt($user->fresh()->two_factor_secret);
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $this->currentOtp($secret)])->assertRedirect();

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactor());
    }

    public function test_wrong_confirmation_code_keeps_two_factor_inactive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication');

        $this->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertSessionHasErrors();

        $this->assertFalse($user->fresh()->hasConfirmedTwoFactor());
    }

    public function test_login_with_two_factor_requires_the_code(): void
    {
        $admin = $this->staff(StaffRole::Admin, attributes: ['email' => 'admin@example.com']);
        $secret = decrypt($admin->two_factor_secret);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->currentOtp($secret)])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_recovery_code_works_once(): void
    {
        $admin = $this->staff(StaffRole::Admin, attributes: ['email' => 'admin@example.com']);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotContains('recovery-one', $admin->fresh()->recoveryCodes());

        $this->post('/logout');
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_two_factor_challenge_is_rate_limited(): void
    {
        $this->staff(StaffRole::Admin, attributes: ['email' => 'admin@example.com']);
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertStatus(429);
    }
}
