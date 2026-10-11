<?php

namespace Tests\Feature\Authorization;

use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\TwoFactorResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class StaffCommandsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_make_owner_promotes_a_verified_active_account(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('marketplace:make-owner', ['email' => 'Owner@Example.com'])
            ->expectsConfirmation('Make owner@example.com an Owner (full control of the marketplace)?', 'yes')
            ->expectsOutputToContain('Two-factor authentication is required')
            ->assertSuccessful();

        $this->assertSame(StaffRole::Owner, $user->fresh()->staffRole());
    }

    public function test_make_owner_can_be_cancelled(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('marketplace:make-owner', ['email' => 'owner@example.com'])
            ->expectsConfirmation('Make owner@example.com an Owner (full control of the marketplace)?', 'no')
            ->assertFailed();

        $this->assertNull($user->fresh()->staffRole());
    }

    public function test_make_owner_refuses_unknown_unverified_or_inactive_accounts(): void
    {
        User::factory()->unverified()->create(['email' => 'new@example.com']);
        User::factory()->banned()->create(['email' => 'banned@example.com']);

        $this->artisan('marketplace:make-owner', ['email' => 'nobody@example.com', '--force' => true])->assertFailed();
        $this->artisan('marketplace:make-owner', ['email' => 'new@example.com', '--force' => true])->assertFailed();
        $this->artisan('marketplace:make-owner', ['email' => 'banned@example.com', '--force' => true])->assertFailed();

        $this->assertSame(0, User::role(StaffRole::Owner->value)->count());
    }

    public function test_reset_two_factor_clears_secrets_ends_sessions_and_emails_the_user(): void
    {
        Notification::fake();
        $owner = $this->staff(StaffRole::Owner, attributes: ['email' => 'owner@example.com']);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

        $this->artisan('marketplace:reset-2fa', ['email' => 'owner@example.com'])
            ->expectsConfirmation('Reset two-factor authentication for owner@example.com?', 'yes')
            ->assertSuccessful();

        $owner->refresh();
        $this->assertNull($owner->two_factor_secret);
        $this->assertNull($owner->two_factor_recovery_codes);
        $this->assertNull($owner->two_factor_confirmed_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $owner->id)->count());
        $this->assertTrue($owner->mustSetUpTwoFactor(), 'Admin area locked until 2FA is set up again');
        Notification::assertSentTo($owner, TwoFactorResetNotification::class);
    }

    public function test_reset_two_factor_cancelled_changes_nothing(): void
    {
        Notification::fake();
        $owner = $this->staff(StaffRole::Owner, attributes: ['email' => 'owner@example.com']);

        $this->artisan('marketplace:reset-2fa', ['email' => 'owner@example.com'])
            ->expectsConfirmation('Reset two-factor authentication for owner@example.com?', 'no')
            ->assertFailed();

        $this->assertTrue($owner->fresh()->hasConfirmedTwoFactor());
        Notification::assertNothingSent();
    }

    public function test_reset_two_factor_for_unknown_or_unprotected_accounts(): void
    {
        User::factory()->create(['email' => 'plain@example.com']);

        $this->artisan('marketplace:reset-2fa', ['email' => 'nobody@example.com', '--force' => true])->assertFailed();
        $this->artisan('marketplace:reset-2fa', ['email' => 'plain@example.com', '--force' => true])
            ->expectsOutputToContain('Nothing to reset')
            ->assertSuccessful();
    }
}
