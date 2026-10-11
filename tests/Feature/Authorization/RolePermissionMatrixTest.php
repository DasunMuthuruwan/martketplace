<?php

namespace Tests\Feature\Authorization;

use App\Authorization\Permissions;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * The role matrix must equal docs/architecture/05-authorization.md §3, cell by cell.
 */
class RolePermissionMatrixTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /**
     * Owner, Admin, Moderator — copied from the authorization doc §3.
     *
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: bool}>
     */
    public static function documentedMatrix(): array
    {
        return [
            'access-admin' => [Permissions::ACCESS_ADMIN, true, true, true],
            'listings.view-any' => [Permissions::LISTINGS_VIEW_ANY, true, true, true],
            'listings.moderate' => [Permissions::LISTINGS_MODERATE, true, true, true],
            'listings.suspend' => [Permissions::LISTINGS_SUSPEND, true, true, true],
            'listings.edit-any' => [Permissions::LISTINGS_EDIT_ANY, true, true, false],
            'listings.delete-any' => [Permissions::LISTINGS_DELETE_ANY, true, true, false],
            'reports.view' => [Permissions::REPORTS_VIEW, true, true, true],
            'reports.resolve' => [Permissions::REPORTS_RESOLVE, true, true, true],
            'users.view' => [Permissions::USERS_VIEW, true, true, true],
            'users.suspend' => [Permissions::USERS_SUSPEND, true, true, false],
            'users.ban' => [Permissions::USERS_BAN, true, true, false],
            'users.delete' => [Permissions::USERS_DELETE, true, false, false],
            'categories.manage' => [Permissions::CATEGORIES_MANAGE, true, true, false],
            'attributes.manage' => [Permissions::ATTRIBUTES_MANAGE, true, true, false],
            'locations.manage' => [Permissions::LOCATIONS_MANAGE, true, true, false],
            'pages.manage' => [Permissions::PAGES_MANAGE, true, true, false],
            'blocklist.manage' => [Permissions::BLOCKLIST_MANAGE, true, true, true],
            'settings.manage' => [Permissions::SETTINGS_MANAGE, true, false, false],
            'staff.manage' => [Permissions::STAFF_MANAGE, true, false, false],
            'audit.view' => [Permissions::AUDIT_VIEW, true, true, false],
        ];
    }

    #[DataProvider('documentedMatrix')]
    public function test_each_role_has_exactly_the_documented_permission(string $permission, bool $owner, bool $admin, bool $moderator): void
    {
        $this->assertSame($owner, $this->staff(StaffRole::Owner)->can($permission), "owner / {$permission}");
        $this->assertSame($admin, $this->staff(StaffRole::Admin)->can($permission), "admin / {$permission}");
        $this->assertSame($moderator, $this->staff(StaffRole::Moderator)->can($permission), "moderator / {$permission}");
        $this->assertFalse(User::factory()->create()->can($permission), "regular user / {$permission}");
    }

    public function test_matrix_covers_every_defined_permission(): void
    {
        $this->assertEqualsCanonicalizing(Permissions::all(), array_keys(self::documentedMatrix()));
        $this->assertSame(count(Permissions::all()), Permission::count());
        $this->assertEqualsCanonicalizing(['owner', 'admin', 'moderator'], Role::pluck('name')->all());
    }

    public function test_owner_has_no_blanket_bypass_for_undefined_abilities(): void
    {
        $this->assertFalse($this->staff(StaffRole::Owner)->can('some.undefined-ability'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seedRoles();
        $this->seedRoles();

        $this->assertSame(count(Permissions::all()), Permission::count());
        $this->assertSame(3, Role::count());
    }

    public function test_staff_role_helpers(): void
    {
        $this->assertSame(StaffRole::Owner, $this->staff(StaffRole::Owner)->staffRole());
        $this->assertNull(User::factory()->create()->staffRole());
        $this->assertSame(0, User::factory()->create()->staffRank());
        $this->assertTrue($this->staff(StaffRole::Admin, twoFactor: false)->mustSetUpTwoFactor());
        $this->assertFalse($this->staff(StaffRole::Moderator, twoFactor: false)->mustSetUpTwoFactor());
        $this->assertFalse($this->staff(StaffRole::Admin)->mustSetUpTwoFactor());
    }
}
