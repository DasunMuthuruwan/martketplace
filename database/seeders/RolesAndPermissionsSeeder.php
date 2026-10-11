<?php

namespace Database\Seeders;

use App\Authorization\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission and the Owner/Admin/Moderator roles from App\Authorization\Permissions.
 * Idempotent; re-running re-applies the approved matrix (fixed in the MVP, docs §3).
 * No user receives a role here: staff are appointed with marketplace:make-owner / the staff screen.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Model events may be muted (DatabaseSeeder uses WithoutModelEvents), so spatie's automatic
        // cache refresh doesn't run: clear it before roles look the new permissions up.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::matrix() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
