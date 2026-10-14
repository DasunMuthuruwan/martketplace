<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retention (docs/architecture/09-data-retention.md §1): when the account status last changed (a
 * ban is anonymised 30 days later) and when the inactive-account warning was sent (deletion
 * follows 30 days later unless the user signs in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('suspended_until');
            $table->timestamp('inactive_warned_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status_changed_at', 'inactive_warned_at']);
        });
    }
};
