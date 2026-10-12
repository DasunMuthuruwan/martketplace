<?php

use App\Authorization\Permissions;
use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\ContactRevealController;
use App\Http\Controllers\EngagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Account settings. Profile and security stay reachable before email verification (e.g. to fix a
// mistyped address); contact settings need a verified email.
Route::middleware('auth')->prefix('account/settings')->name('account.settings.')->controller(SettingsController::class)->group(function () {
    Route::get('/profile', 'profile')->name('profile');
    Route::get('/security', 'security')->name('security');
    Route::get('/contact', 'contact')->middleware('verified')->name('contact');
    Route::put('/contact', 'updateContact')->middleware('verified')->name('contact.update');
});
Route::delete('/account', [SettingsController::class, 'destroy'])->middleware('auth')->name('account.destroy');

// Seller contact reveal (MSG-04): JSON, CSRF-protected, rate-limited; numbers never in the page HTML.
Route::post('/listings/{listing}/contact', ContactRevealController::class)
    ->whereNumber('listing')
    ->middleware('throttle:contact-reveal')
    ->name('listings.contact');

// Favourites and reports (routes doc §4.3, §4.4).
Route::post('/listings/{listing}/favourite', [EngagementController::class, 'favourite'])
    ->whereNumber('listing')
    ->middleware('auth')
    ->name('listings.favourite');
Route::post('/reports', [EngagementController::class, 'report'])
    ->middleware(['auth', 'verified', 'throttle:report'])
    ->name('reports.store');

Route::middleware(['auth', 'verified'])->group(function () {
    // Two-factor setup (Fortify endpoints do the work; this page shows status, QR and recovery codes).
    Route::view('/account/two-factor', 'account.two-factor')->name('account.two-factor');
});

// Admin area: staff only, Owner/Admin need confirmed 2FA. Screens are built in M6.
Route::middleware(['auth', 'verified', 'can:'.Permissions::ACCESS_ADMIN, 'staff.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('/', 'admin.placeholder')->name('dashboard');
    });
