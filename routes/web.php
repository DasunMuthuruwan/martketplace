<?php

use App\Authorization\Permissions;
use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\ContactRevealController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SellerController;
use App\Livewire\Admin\AttributesAdmin;
use App\Livewire\Admin\CategoriesAdmin;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\RecentChanges;
use App\Livewire\Admin\ReportsQueue;
use App\Livewire\Admin\StaffAdmin;
use App\Livewire\Admin\UsersAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Account settings. Profile and security stay reachable before email verification (e.g. to fix a
// mistyped address); contact settings need a verified email.
Route::middleware('auth')->prefix('account/settings')->name('account.settings.')->controller(SettingsController::class)->group(function () {
    Route::get('/profile', 'profile')->name('profile');
    Route::get('/security', 'security')->name('security');
    Route::get('/contact', 'contact')->middleware('verified')->name('contact');
    Route::put('/contact', 'updateContact')->middleware('verified')->name('contact.update');
});
Route::delete('/account', [SettingsController::class, 'destroy'])->middleware('auth')->name('account.destroy');

// Visitor country choice (G-2): remembered in a cookie.
Route::post('/country', [CountryController::class, 'choose'])->name('country.choose');

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
        // Each screen also checks its permission inside the component (mount and every action).
        Route::livewire('/moderation', ModerationQueue::class)->middleware('can:'.Permissions::LISTINGS_MODERATE)->name('moderation');
        Route::livewire('/moderation/changes', RecentChanges::class)->middleware('can:'.Permissions::LISTINGS_MODERATE)->name('changes');
        Route::livewire('/reports', ReportsQueue::class)->middleware('can:'.Permissions::REPORTS_VIEW)->name('reports');
        Route::livewire('/categories', CategoriesAdmin::class)->middleware('can:'.Permissions::CATEGORIES_MANAGE)->name('categories');
        Route::livewire('/attributes', AttributesAdmin::class)->middleware('can:'.Permissions::ATTRIBUTES_MANAGE)->name('attributes');
        // Account and staff changes: password re-confirmed within 15 minutes (2FA doc §6).
        Route::livewire('/users', UsersAdmin::class)->middleware(['can:'.Permissions::USERS_VIEW, 'password.confirm'])->name('users');
        Route::livewire('/staff', StaffAdmin::class)->middleware(['can:'.Permissions::STAFF_MANAGE, 'password.confirm'])->name('staff');
    });

// ---- Public pages (routes doc §1, ADR-023). Plain views until Phase 6. ---------------------------
// `{cc}` = two letters (an active country, checked by the `country` middleware; upper case 301s).
// `in` is reserved: it separates the category part from the location part.
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/countries', [CountryController::class, 'index'])->name('countries');
Route::get('/ad/{slugId}', [ListingController::class, 'show'])->where('slugId', '[A-Za-z0-9-]*-[0-9]+')->name('listing.show');
Route::get('/seller/{idSlug}', [SellerController::class, 'show'])->where('idSlug', '[0-9]+(-[A-Za-z0-9-]*)?')->name('seller.show');
Route::get('/ads', [SearchController::class, 'global'])->middleware('throttle:search')->name('ads.global');

Route::prefix('{cc}')->where(['cc' => '[A-Za-z]{2}'])->middleware('country')->group(function () {
    $slug = '(?!in$)[a-z0-9-]+';

    Route::get('/', [HomeController::class, 'country'])->name('country.home');
    Route::middleware('throttle:search')->controller(SearchController::class)->group(function () use ($slug) {
        Route::get('/ads', 'country')->name('ads.country');
        Route::get('/ads/in/{region}/{city?}', 'country')->where(['region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('ads.location');
        Route::get('/ads/{category}/in/{region}/{city?}', 'country')->where(['category' => $slug, 'region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('ads.category.location');
        Route::get('/ads/{category}/{subcategory}/in/{region}/{city?}', 'country')->where(['category' => $slug, 'subcategory' => $slug, 'region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('ads.subcategory.location');
        Route::get('/ads/{category}/{subcategory?}', 'country')->where(['category' => $slug, 'subcategory' => $slug])->name('ads.category');
    });
});
