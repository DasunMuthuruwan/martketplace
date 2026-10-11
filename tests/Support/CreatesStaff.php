<?php

namespace Tests\Support;

use App\Enums\StaffRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use PragmaRX\Google2FA\Google2FA;

/**
 * Test helpers for staff accounts. Call seedRoles() in setUp() of tests using RefreshDatabase.
 */
trait CreatesStaff
{
    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function staff(StaffRole $role, bool $twoFactor = true, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        if ($twoFactor) {
            $this->enableTwoFactor($user);
        }

        return $user->fresh();
    }

    protected function enableTwoFactor(User $user): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    protected function currentOtp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }
}
