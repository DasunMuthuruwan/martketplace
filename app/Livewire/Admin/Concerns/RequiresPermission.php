<?php

namespace App\Livewire\Admin\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Livewire actions can be called directly by anyone who can load the page's JavaScript, so every
 * admin component checks its permission on mount and before each action (implementation plan M6:
 * "each action authorised + unauthorised, called directly"). The domain actions check again.
 */
trait RequiresPermission
{
    protected function requirePermission(string $permission): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can($permission), 403);
    }
}
