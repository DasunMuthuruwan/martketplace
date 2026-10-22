<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * B3: the new `boosts.manage` permission (Owner and Admin, Permissions::matrix) reaches existing
 * databases on deploy. The seeder is idempotent: it creates missing permissions and re-syncs each
 * role with the matrix.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        // Nothing to undo: the permission is harmless without the screen that checks it.
    }
};
