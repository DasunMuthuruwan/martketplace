<?php

namespace App\Authorization;

use App\Enums\StaffRole;

/**
 * Permission names and the role matrix (docs/architecture/05-authorization.md §2–§3).
 * Single source for the seeder, gates and tests. Code checks permissions, never role names.
 */
final class Permissions
{
    public const ACCESS_ADMIN = 'access-admin';

    public const LISTINGS_VIEW_ANY = 'listings.view-any';

    public const LISTINGS_MODERATE = 'listings.moderate';

    public const LISTINGS_SUSPEND = 'listings.suspend';

    public const LISTINGS_EDIT_ANY = 'listings.edit-any';

    public const LISTINGS_DELETE_ANY = 'listings.delete-any';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_RESOLVE = 'reports.resolve';

    public const USERS_VIEW = 'users.view';

    public const USERS_SUSPEND = 'users.suspend';

    public const USERS_BAN = 'users.ban';

    public const USERS_DELETE = 'users.delete';

    public const CATEGORIES_MANAGE = 'categories.manage';

    public const ATTRIBUTES_MANAGE = 'attributes.manage';

    public const LOCATIONS_MANAGE = 'locations.manage';

    public const PAGES_MANAGE = 'pages.manage';

    public const BLOCKLIST_MANAGE = 'blocklist.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const STAFF_MANAGE = 'staff.manage';

    public const AUDIT_VIEW = 'audit.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /**
     * Role → permissions. The Owner gets every permission explicitly (no blanket bypass),
     * so a forgotten permission check fails for everyone and shows up in tests.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            StaffRole::Owner->value => self::all(),
            StaffRole::Admin->value => array_values(array_diff(self::all(), [
                self::USERS_DELETE,
                self::SETTINGS_MANAGE,
                self::STAFF_MANAGE,
            ])),
            StaffRole::Moderator->value => [
                self::ACCESS_ADMIN,
                self::LISTINGS_VIEW_ANY,
                self::LISTINGS_MODERATE,
                self::LISTINGS_SUSPEND,
                self::REPORTS_VIEW,
                self::REPORTS_RESOLVE,
                self::USERS_VIEW,
                self::BLOCKLIST_MANAGE,
            ],
        ];
    }
}
