<?php

namespace App\View;

use Illuminate\Support\Facades\Route;

/**
 * Destinations used by the header, account menu and mobile bottom navigation (navigation doc §2,
 * Q-UX-3). A destination whose screen is not built yet resolves to null and is shown as
 * unavailable; it lights up as soon as its route exists (posting wizard F4.1, account area F3.2).
 */
final class Navigation
{
    /** destination => route names to try, first match wins */
    private const ROUTES = [
        'post' => ['listings.create'],
        'saved' => ['account.saved'],
        'account' => ['account.overview', 'account.settings.profile'],
        'my_ads' => ['account.listings'],
        'settings' => ['account.settings.profile'],
    ];

    public static function url(string $destination): ?string
    {
        foreach (self::ROUTES[$destination] ?? [] as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return null;
    }
}
