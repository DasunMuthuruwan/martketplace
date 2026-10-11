<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends a user's sessions on other devices. Works with the database session driver (production
 * default); "remember me" cookies are invalidated by cycling the remember token.
 */
final class UserSessions
{
    /** Sign the user out everywhere (account deletion, staff 2FA reset). */
    public static function endAll(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        self::cycleRememberToken($user);
    }

    /** Sign the user out of every session except the current one (password change). */
    public static function endOthers(User $user, ?string $currentSessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
        self::cycleRememberToken($user);
    }

    private static function cycleRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
