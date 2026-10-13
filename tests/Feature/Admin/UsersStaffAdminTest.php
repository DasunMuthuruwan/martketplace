<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Enums\UserStatus;
use App\Livewire\Admin\StaffAdmin;
use App\Livewire\Admin\UsersAdmin;
use App\Models\AuditLog;
use App\Models\BannedIdentity;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AccountStatusChanged;
use App\Notifications\TwoFactorResetNotification;
use App\Rules\HumanTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** T6.2: users and staff admin (ADM-04, AUTH-09, retention §1, 2FA doc §5–6). */
class UsersStaffAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        $this->owner = $this->staff(StaffRole::Owner);
        $this->admin = $this->staff(StaffRole::Admin);
        $this->member = User::factory()->create(['name' => 'Nimal Perera', 'email' => 'nimal@example.com', 'phone' => '+94771234567', 'phone_country' => 'LK']);
        session()->put('auth.password_confirmed_at', time());
    }

    private function users(User $actor): Testable
    {
        return Livewire::actingAs($actor)->test(UsersAdmin::class);
    }

    // ---- Access -----------------------------------------------------------------------------

    public function test_pages_need_permission_and_a_recent_password(): void
    {
        session()->forget('auth.password_confirmed_at'); // set in setUp for the component tests
        $this->actingAs($this->admin)->get(route('admin.users'))->assertRedirect(route('password.confirm'));
        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.users'))->assertOk();

        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.staff'))->assertForbidden();
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.staff'))->assertOk();

        Livewire::actingAs(User::factory()->create())->test(UsersAdmin::class)->assertForbidden();
        Livewire::actingAs($this->admin)->test(StaffAdmin::class)->assertForbidden();
    }

    public function test_search_by_name_email_or_phone(): void
    {
        User::factory()->create(['name' => 'Someone Else']);

        $this->users($this->admin)->set('q', 'nimal')->assertSee('Nimal Perera')->assertDontSee('Someone Else');
        $this->users($this->admin)->set('q', '0771234567')->assertSee('Nimal Perera');
        $this->users($this->admin)->set('q', 'NIMAL@EXAMPLE')->assertSee('Nimal Perera');
    }

    public function test_contact_details_only_for_staff_allowed_to_see_them(): void
    {
        $this->users($this->admin)->call('select', $this->member->id)->assertSee('nimal@example.com');
        $this->users($this->staff(StaffRole::Moderator, twoFactor: false))->call('select', $this->member->id)
            ->assertSee('Nimal Perera')->assertDontSee('nimal@example.com')->assertDontSeeHtml('wire:click="ban"');
    }

    // ---- Suspend, ban, reinstate --------------------------------------------------------------

    public function test_suspend_until_a_date_hides_ads_ends_sessions_audits_and_emails(): void
    {
        $listing = Listing::factory()->for($this->member)->create();
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->member->id, 'payload' => '', 'last_activity' => time()]);

        $this->users($this->admin)->call('select', $this->member->id)
            ->set('reason', 'Spam ads.')->set('until', now()->addWeek()->toDateString())->call('suspend')
            ->assertHasNoErrors();

        $member = $this->member->fresh();
        $this->assertSame(UserStatus::Suspended, $member->status);
        $this->assertSame(now()->addWeek()->toDateString(), $member->suspended_until->toDateString());
        $this->assertFalse($listing->isPublic());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $member->id)->count());
        $this->assertSame(['account.suspended', 'Spam ads.'], [AuditLog::sole()->action, AuditLog::sole()->reason]);
        Notification::assertSentTo($member, AccountStatusChanged::class);

        // Without a reason nothing happens.
        $this->users($this->admin)->call('select', $member->id)->call('reinstate')->assertHasNoErrors();
        $this->users($this->admin)->call('select', $member->id)->call('suspend')->assertHasErrors('reason');
    }

    public function test_ban_blocks_the_email_and_phone_from_registering_again(): void
    {
        $this->users($this->admin)->call('select', $this->member->id)->set('reason', 'Scam reports confirmed.')->call('ban')->assertHasNoErrors();

        $this->assertSame(UserStatus::Banned, $this->member->fresh()->status);
        $this->assertSame(2, BannedIdentity::count());
        $this->assertSame(0, BannedIdentity::where('hash', 'nimal@example.com')->count(), 'Only hashes are stored');

        $this->member->forceDelete(); // even after the account is gone, the identifiers stay blocked
        $lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'phone_country_code' => '94']);
        $register = function (string $email, ?string $phone = null) use ($lk) {
            $token = HumanTiming::token();
            $this->travel(5)->seconds();

            return $this->post('/register', [
                'name' => 'New Name', 'email' => $email, 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery',
                'country_id' => $lk->id, 'phone_country' => $phone ? 'LK' : '', 'phone' => $phone, 'terms' => '1', 'website' => '', HumanTiming::FIELD => $token,
            ]);
        };

        auth()->guard('web')->logout(); // /register is for guests
        $register('Nimal@Example.com')->assertSessionHasErrors(['email' => __('auth.cannot_register')]);
        $register('fresh@example.com', '077 123 4567')->assertSessionHasErrors(['email' => __('auth.cannot_register')]);
        $register('fresh@example.com', '077 999 8888')->assertSessionHasNoErrors();
    }

    public function test_reinstating_a_ban_lifts_the_block(): void
    {
        $this->users($this->admin)->call('select', $this->member->id)->set('reason', 'Mistake.')->call('ban');
        $this->users($this->admin)->call('select', $this->member->id)->call('reinstate')->assertHasNoErrors();

        $this->assertSame(UserStatus::Active, $this->member->fresh()->status);
        $this->assertSame(0, BannedIdentity::count());
    }

    public function test_hierarchy_and_permissions_are_enforced(): void
    {
        // Admins cannot act on Owners, Moderators cannot suspend at all, nobody acts on themselves.
        $this->users($this->admin)->call('select', $this->owner->id)->set('reason', 'x')->call('suspend')->assertHasErrors('status');
        $this->users($this->staff(StaffRole::Moderator, twoFactor: false))->call('select', $this->member->id)->set('reason', 'x')->call('suspend')->assertHasErrors('status');
        $this->users($this->admin)->call('select', $this->admin->id)->set('reason', 'x')->call('ban')->assertHasErrors('status');
        $this->users($this->admin)->call('select', $this->member->id)->set('reason', 'x')->call('delete')->assertHasErrors('status'); // users.delete: Owner only

        $this->assertSame(UserStatus::Active, $this->owner->fresh()->status);
        $this->assertSame(UserStatus::Active, $this->member->fresh()->status);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_ban_and_delete_need_a_recent_password(): void
    {
        session()->put('auth.password_confirmed_at', time() - 3600);

        $this->users($this->admin)->call('select', $this->member->id)->set('reason', 'x')->call('ban')->assertRedirect(route('password.confirm'));

        $this->assertSame(UserStatus::Active, $this->member->fresh()->status);
    }

    public function test_owner_deletes_an_account_and_verifies_an_email(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->users($this->admin)->call('select', $unverified->id)->call('verifyEmail');
        $this->assertTrue($unverified->fresh()->hasVerifiedEmail());

        $this->users($this->owner)->call('select', $this->member->id)->set('reason', 'Requested by the person.')->call('delete')->assertHasNoErrors();
        $this->assertSoftDeleted($this->member);
        $this->assertEqualsCanonicalizing(['account.email_verified', 'account.deleted'], AuditLog::pluck('action')->all());
    }

    // ---- Staff ------------------------------------------------------------------------------

    public function test_owner_appoints_changes_and_removes_staff(): void
    {
        Livewire::actingAs($this->owner)->test(StaffAdmin::class)
            ->set('email', 'Nimal@Example.com')->set('role', 'moderator')->call('appoint')->assertHasNoErrors();
        $this->assertSame(StaffRole::Moderator, $this->member->fresh()->staffRole());

        Livewire::actingAs($this->owner)->test(StaffAdmin::class)
            ->set("roleFor.{$this->member->id}", 'admin')->call('changeRole', $this->member->id);
        $this->assertSame(StaffRole::Admin, $this->member->fresh()->staffRole());

        Livewire::actingAs($this->owner)->test(StaffAdmin::class)
            ->set("roleFor.{$this->member->id}", 'none')->call('changeRole', $this->member->id);
        $this->assertNull($this->member->fresh()->staffRole());

        $this->assertSame(3, AuditLog::where('action', 'staff.role_changed')->count());
        $this->assertSame(['moderator', 'admin'], AuditLog::where('action', 'staff.role_changed')->orderBy('id')->skip(1)->first()->changes['role']);
    }

    public function test_staff_rules(): void
    {
        $unverified = User::factory()->unverified()->create(['email' => 'new@example.com']);

        Livewire::actingAs($this->owner)->test(StaffAdmin::class)
            ->set('email', 'new@example.com')->set('role', 'admin')->call('appoint')->assertHasErrors('role');
        Livewire::actingAs($this->owner)->test(StaffAdmin::class)
            ->set('email', 'nobody@example.com')->set('role', 'admin')->call('appoint')->assertHasErrors('email');
        // The only Owner cannot remove their own role.
        Livewire::actingAs($this->owner)->test(StaffAdmin::class)
            ->set("roleFor.{$this->owner->id}", 'admin')->call('changeRole', $this->owner->id)->assertHasErrors('role');

        $this->assertNull($unverified->fresh()->staffRole());
        $this->assertSame(StaffRole::Owner, $this->owner->fresh()->staffRole());
    }

    public function test_owner_resets_another_staff_members_two_factor(): void
    {
        Livewire::actingAs($this->owner)->test(StaffAdmin::class)->call('resetTwoFactor', $this->admin->id)->assertHasNoErrors();

        $this->assertFalse($this->admin->fresh()->hasConfirmedTwoFactor());
        $this->assertTrue($this->admin->fresh()->mustSetUpTwoFactor());
        $this->assertSame('staff.two_factor_reset', AuditLog::sole()->action);
        Notification::assertSentTo($this->admin, TwoFactorResetNotification::class);
    }
}
