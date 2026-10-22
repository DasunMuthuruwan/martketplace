<?php

namespace App\Providers;

use App\Services\Geo\CountryContext;
use App\Services\Search\ListingSearch;
use App\Services\Search\MysqlListingSearch;
use App\Support\MailBudget;
use App\Support\MailGate;
use App\Support\RootUrl;
use App\Support\Settings;
use App\View\Composers\PublicLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passkeys stay disabled: they are bound to the domain, which will change (ADR-015).
        Passkeys::ignoreRoutes();

        // One visitor country per request (reset between requests, also under Octane-style workers).
        $this->app->scoped(CountryContext::class);

        // Listing search engine (MySQL FULLTEXT in the MVP; swappable later).
        $this->app->bind(ListingSearch::class, MysqlListingSearch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RootUrl::configure();

        // Owner-edited site settings over the config defaults (ADM-05).
        Settings::apply();

        // Email: real transports stay off until MAIL_SENDING_ENABLED (CD-09); every message counts
        // against the hourly budget (queued mail waits when it is spent).
        MailGate::apply();
        Event::listen(MessageSending::class, fn () => MailBudget::record());

        // Header, category bar and footer data for public pages (cached).
        View::composer('layouts.public', PublicLayoutComposer::class);

        // 10+ characters; in production also refuse passwords found in known breaches (HIBP k-anonymity).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->max(255)->uncompromised()
            : Password::min(10)->max(255));

        // Contact reveals (security doc §3): per account when logged in, per IP for guests.
        RateLimiter::for('contact-reveal', fn (Request $request) => $request->user()
            ? Limit::perHour((int) config('marketplace.contact.reveals_per_hour_user'))->by('reveal:user:'.$request->user()->id)
            : Limit::perHour((int) config('marketplace.contact.reveals_per_hour_guest'))->by('reveal:ip:'.$request->ip()));

        // Search pages: 120 per minute per IP (security doc §3).
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)->by('search:'.$request->ip()));

        // Reports: 10 per day per account (security doc §3).
        RateLimiter::for('report', fn (Request $request) => Limit::perDay(10)->by('report:'.($request->user()?->id ?: $request->ip())));

        // New drafts and submissions: 30 per hour per IP (security doc §3, S-01). The per-account daily
        // limit (LST-09) is enforced by SaveListingLocation; photo uploads are counted per file in
        // ListingPhotosController.
        // Promotion orders (B5): per account.
        RateLimiter::for('boost-order', fn (Request $request) => Limit::perHour((int) config('marketplace.boosts.orders_per_hour_user'))->by('boost-order:'.$request->user()?->id));

        RateLimiter::for('listing-submit', fn (Request $request) => Limit::perHour((int) config('marketplace.listings.submits_per_hour_ip'))->by('listing-submit:'.$request->ip()));
    }
}
