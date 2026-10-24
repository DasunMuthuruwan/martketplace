<?php

use App\Authorization\Permissions;
use App\Http\Controllers\Account\BoostOrdersController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\FavouritesController;
use App\Http\Controllers\Account\ListingPhotosController;
use App\Http\Controllers\Account\ListingsController;
use App\Http\Controllers\Account\ListingStatsController;
use App\Http\Controllers\Account\PaymentProofsController;
use App\Http\Controllers\Account\PostListingController;
use App\Http\Controllers\Account\PromoteController;
use App\Http\Controllers\Account\ReviewsController;
use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\ContactRevealController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerReviewController;
use App\Http\Controllers\SuggestController;
use App\Livewire\Admin\AttributesAdmin;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\BlocklistAdmin;
use App\Livewire\Admin\BoostOrdersAdmin;
use App\Livewire\Admin\BoostPricesAdmin;
use App\Livewire\Admin\BoostsAdmin;
use App\Livewire\Admin\CategoriesAdmin;
use App\Livewire\Admin\CountriesAdmin;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\DesignSystem;
use App\Livewire\Admin\LocationRequestsAdmin;
use App\Livewire\Admin\LocationsAdmin;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\PagesAdmin;
use App\Livewire\Admin\RecentChanges;
use App\Livewire\Admin\ReportsQueue;
use App\Livewire\Admin\ReviewsAdmin;
use App\Livewire\Admin\SettingsAdmin;
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

// Account area (routes doc §3): overview, my listings with their actions, saved ads.
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', DashboardController::class)->name('overview');
    Route::get('/listings', [ListingsController::class, 'index'])->name('listings');
    Route::get('/listings/{listing}/stats', ListingStatsController::class)->whereNumber('listing')->middleware('verified')->name('listings.stats');
    Route::post('/listings/{listing}/sold', [ListingsController::class, 'sold'])->whereNumber('listing')->name('listings.sold');
    Route::post('/listings/{listing}/renew', [ListingsController::class, 'renew'])->whereNumber('listing')->name('listings.renew');
    Route::delete('/listings/{listing}', [ListingsController::class, 'destroy'])->whereNumber('listing')->name('listings.destroy');
    Route::get('/favourites', FavouritesController::class)->name('favourites');
});

// Posting wizard (routes doc §3.1): a verified, active account (ListingPolicy::create / update).
Route::middleware(['auth', 'verified'])->prefix('account/listings')->name('listings.')->controller(PostListingController::class)->group(function () {
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:listing-submit')->name('store');
    Route::get('/{listing}/edit/{step?}', 'edit')->whereNumber('listing')->name('edit');
    Route::post('/{listing}/edit/{step}', 'update')->whereNumber('listing')->name('update');
    Route::post('/{listing}/contact-options', 'contact')->whereNumber('listing')->name('contact-options');
    Route::post('/{listing}/submit', 'submit')->whereNumber('listing')->middleware('throttle:listing-submit')->name('submit');
    Route::get('/{listing}/submitted', 'submitted')->whereNumber('listing')->name('submitted');
});
// Promotions (B5, ADR-026): order a boost for one's own live ad, pay by bank transfer.
Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('/listings/{listing}/promote', [PromoteController::class, 'show'])->whereNumber('listing')->name('listings.promote');
    Route::post('/listings/{listing}/promote', [PromoteController::class, 'store'])->whereNumber('listing')->middleware('throttle:boost-order')->name('listings.promote.store');
    Route::get('/orders', [BoostOrdersController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [BoostOrdersController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::post('/orders/{order}/cancel', [BoostOrdersController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');
    // Proof of payment (B7): one file per request.
    Route::post('/orders/{order}/proofs', [PaymentProofsController::class, 'store'])->whereNumber('order')->middleware('throttle:payment-proof')->name('orders.proofs.store');
    Route::get('/orders/{order}/proofs/{proof}', [PaymentProofsController::class, 'show'])->whereNumber(['order', 'proof'])->name('orders.proofs.show');
    Route::delete('/orders/{order}/proofs/{proof}', [PaymentProofsController::class, 'destroy'])->whereNumber(['order', 'proof'])->name('orders.proofs.destroy');
});
Route::middleware(['auth', 'verified'])->prefix('account/listings/{listing}/photos')->name('listings.photos.')->controller(ListingPhotosController::class)
    ->whereNumber(['listing', 'image'])->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('/{image}/move', 'move')->name('move');
        Route::post('/{image}/cover', 'cover')->name('cover');
        Route::delete('/{image}', 'destroy')->name('destroy');
    });

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
        Route::livewire('/', Dashboard::class)->name('dashboard');
        // Each screen also checks its permission inside the component (mount and every action).
        Route::livewire('/moderation', ModerationQueue::class)->middleware('can:'.Permissions::LISTINGS_MODERATE)->name('moderation');
        Route::livewire('/moderation/changes', RecentChanges::class)->middleware('can:'.Permissions::LISTINGS_MODERATE)->name('changes');
        Route::livewire('/reports', ReportsQueue::class)->middleware('can:'.Permissions::REPORTS_VIEW)->name('reports');
        Route::livewire('/categories', CategoriesAdmin::class)->middleware('can:'.Permissions::CATEGORIES_MANAGE)->name('categories');
        Route::livewire('/attributes', AttributesAdmin::class)->middleware('can:'.Permissions::ATTRIBUTES_MANAGE)->name('attributes');
        Route::livewire('/locations', LocationsAdmin::class)->middleware('can:'.Permissions::LOCATIONS_MANAGE)->name('locations');
        Route::livewire('/locations/countries', CountriesAdmin::class)->middleware('can:'.Permissions::LOCATIONS_MANAGE)->name('countries');
        Route::livewire('/locations/requests', LocationRequestsAdmin::class)->middleware('can:'.Permissions::LOCATIONS_MANAGE)->name('location-requests');
        Route::livewire('/pages', PagesAdmin::class)->middleware('can:'.Permissions::PAGES_MANAGE)->name('pages');
        Route::livewire('/blocklist', BlocklistAdmin::class)->middleware('can:'.Permissions::BLOCKLIST_MANAGE)->name('blocklist');
        Route::livewire('/reviews', ReviewsAdmin::class)->middleware('can:'.Permissions::REVIEWS_MODERATE)->name('reviews');
        Route::livewire('/orders', BoostOrdersAdmin::class)->middleware('can:'.Permissions::BOOSTS_MANAGE)->name('orders');
        Route::get('/orders/proofs/{proof}', PaymentProofController::class)->whereNumber('proof')->middleware('can:'.Permissions::BOOSTS_MANAGE)->name('orders.proof');
        Route::livewire('/boosts', BoostsAdmin::class)->middleware('can:'.Permissions::BOOSTS_MANAGE)->name('boosts');
        Route::livewire('/boosts/prices', BoostPricesAdmin::class)->middleware('can:'.Permissions::BOOSTS_MANAGE)->name('boost-prices');
        Route::livewire('/audit', AuditLogViewer::class)->middleware('can:'.Permissions::AUDIT_VIEW)->name('audit');
        // Account and staff changes: password re-confirmed within 15 minutes (2FA doc §6).
        Route::livewire('/users', UsersAdmin::class)->middleware(['can:'.Permissions::USERS_VIEW, 'password.confirm'])->name('users');
        Route::livewire('/design-system', DesignSystem::class)->middleware('can:'.Permissions::SETTINGS_MANAGE)->name('design-system');
        Route::livewire('/settings', SettingsAdmin::class)->middleware(['can:'.Permissions::SETTINGS_MANAGE, 'password.confirm'])->name('settings');
        Route::livewire('/staff', StaffAdmin::class)->middleware(['can:'.Permissions::STAFF_MANAGE, 'password.confirm'])->name('staff');
    });

// ---- Public pages (routes doc §1, ADR-023). Plain views until Phase 6. ---------------------------
// `{cc}` = two letters (an active country, checked by the `country` middleware; upper case 301s).
// `in` is reserved: it separates the category part from the location part.
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/countries', [CountryController::class, 'index'])->name('countries');
Route::get('/page/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('page.show');
Route::get('/ad/{slugId}', [ListingController::class, 'show'])->where('slugId', '[A-Za-z0-9-]*-[0-9]+')->name('listing.show');
Route::get('/seller/{idSlug}', [SellerController::class, 'show'])->where('idSlug', '[0-9]+(-[A-Za-z0-9-]*)?')->name('seller.show');
Route::get('/ads', [SearchController::class, 'global'])->middleware('throttle:search')->name('ads.global');
// Seller ratings (G5, ADR-027): buyers who contacted the seller rate them; sellers reply once.
Route::post('/seller/{seller}/reviews', [SellerReviewController::class, 'store'])->whereNumber('seller')
    ->middleware(['auth', 'verified', 'throttle:review'])->name('seller.reviews.store');
Route::post('/reviews/{review}/report', ReviewReportController::class)->whereNumber('review')
    ->middleware(['auth', 'verified', 'throttle:report'])->name('reviews.report');
Route::middleware(['auth', 'verified'])->prefix('account/reviews')->name('account.reviews')->controller(ReviewsController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/{review}/reply', 'reply')->whereNumber('review')->middleware('throttle:review')->name('.reply');
});

// Live search suggestions (G1, ADR-027): JSON for the search boxes.
Route::get('/search/suggest', SuggestController::class)->middleware('throttle:suggest')->name('search.suggest');

Route::prefix('{cc}')->where(['cc' => '[A-Za-z]{2}'])->middleware('country')->group(function () {
    $slug = '(?!in$)[a-z0-9-]+';

    Route::get('/', [HomeController::class, 'country'])->name('country.home');
    Route::middleware('throttle:search')->controller(SearchController::class)->group(function () use ($slug) {
        Route::get('/ads', 'country')->name('ads.country');
        Route::get('/ads/in/{region}/{city?}', 'country')->where(['region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('ads.location');
        Route::get('/ads/{category}/in/{region}/{city?}', 'country')->where(['category' => $slug, 'region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('ads.category.location');
        Route::get('/ads/{category}/{subcategory}/in/{region}/{city?}', 'country')->where(['category' => $slug, 'subcategory' => $slug, 'region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('ads.subcategory.location');
        // Make and model landing pages with a price guide (G4, ADR-027).
        Route::get('/ads/{category}/{subcategory}/make/{make}/{model?}', 'make')->where(['category' => $slug, 'subcategory' => $slug, 'make' => '[a-z0-9-]+', 'model' => '[a-z0-9-]+'])->name('ads.make');
        Route::get('/ads/{category}/{subcategory?}', 'country')->where(['category' => $slug, 'subcategory' => $slug])->name('ads.category');
    });
});
