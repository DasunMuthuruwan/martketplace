<?php

namespace Tests\Feature\Authorization;

use App\Actions\Staff\ChangeStaffRole;
use App\Authorization\StaffHierarchy;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class StaffHierarchyTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_suspend_regular_users_and_moderators_but_not_admins_or_owners(): void
    {
        $admin = $this->staff(StaffRole::Admin);

        $this->assertTrue($admin->can('suspend', User::factory()->create()));
        $this->assertTrue($admin->can('suspend', $this->staff(StaffRole::Moderator)));
        $this->assertFalse($admin->can('suspend', $this->staff(StaffRole::Admin)));
        $this->assertFalse($admin->can('suspend', $this->staff(StaffRole::Owner)));
    }

    public function test_moderator_cannot_suspend_anyone(): void
    {
        $moderator = $this->staff(StaffRole::Moderator);

        $this->assertFalse($moderator->can('suspend', User::factory()->create()));
        $this->assertFalse($moderator->can('ban', User::factory()->create()));
    }

    public function test_nobody_acts_on_their_own_account(): void
    {
        $owner = $this->staff(StaffRole::Owner);
        $this->staff(StaffRole::Owner); // not the last owner

        $this->assertFalse($owner->can('suspend', $owner));
        $this->assertFalse($owner->can('changeRole', $owner));
    }

    public function test_owner_can_reset_another_owners_two_factor(): void
    {
        $owner = $this->staff(StaffRole::Owner);

        $this->assertTrue($owner->can('resetTwoFactor', $this->staff(StaffRole::Owner)));
        $this->assertFalse($this->staff(StaffRole::Admin)->can('resetTwoFactor', $this->staff(StaffRole::Moderator)));
    }

    public function test_owner_can_suspend_another_owner_while_two_owners_exist(): void
    {
        $owner = $this->staff(StaffRole::Owner);
        $other = $this->staff(StaffRole::Owner);

        $this->assertTrue($owner->can('suspend', $other));
        $this->assertFalse($this->staff(StaffRole::Admin)->can('suspend', $other), 'Lower rank');
    }

    public function test_last_owner_detection(): void
    {
        $hierarchy = app(StaffHierarchy::class);
        $owner = $this->staff(StaffRole::Owner);

        $this->assertTrue($hierarchy->isLastOwner($owner));
        $this->assertFalse($hierarchy->isLastOwner($this->staff(StaffRole::Admin)));

        $this->staff(StaffRole::Owner);
        $this->assertFalse($hierarchy->isLastOwner($owner));
    }

    public function test_only_owners_change_roles(): void
    {
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(ChangeStaffRole::class)->handle($this->staff(StaffRole::Admin), $target, StaffRole::Moderator);
    }

    public function test_owner_appoints_changes_and_removes_staff_roles(): void
    {
        $owner = $this->staff(StaffRole::Owner);
        $target = User::factory()->create();
        $action = app(ChangeStaffRole::class);

        $action->handle($owner, $target, StaffRole::Moderator);
        $this->assertSame(StaffRole::Moderator, $target->fresh()->staffRole());

        $action->handle($owner, $target->fresh(), StaffRole::Admin);
        $this->assertSame(StaffRole::Admin, $target->fresh()->staffRole());
        $this->assertCount(1, $target->fresh()->roles, 'One role per staff member');

        $action->handle($owner, $target->fresh(), null);
        $this->assertNull($target->fresh()->staffRole());
    }

    public function test_two_owners_demoting_each_other_cannot_leave_zero_owners(): void
    {
        $a = $this->staff(StaffRole::Owner);
        $b = $this->staff(StaffRole::Owner);
        $b->staffRole(); // B's request loaded its roles before A acted (stale copy)
        $action = app(ChangeStaffRole::class);

        $action->handle($a, User::findOrFail($b->id), null); // A demotes B: allowed, two Owners existed
        $this->assertNull($b->fresh()->staffRole());

        try {
            $action->handle($b, $a, null); // B's concurrent request, still believing it is an Owner
            $this->fail('The last Owner must not be demoted');
        } catch (ValidationException $e) {
            $this->assertSame(__('staff.last_owner'), $e->errors()['role'][0]);
        }

        $this->assertSame(StaffRole::Owner, $a->fresh()->staffRole());
    }

    public function test_unverified_accounts_cannot_become_staff(): void
    {
        $this->expectException(ValidationException::class);

        app(ChangeStaffRole::class)->handle($this->staff(StaffRole::Owner), User::factory()->unverified()->create(), StaffRole::Moderator);
    }
}
