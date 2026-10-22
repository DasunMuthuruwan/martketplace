<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\ThrottleAuthEndpoints;
use App\Models\Country;
use App\Models\User;
use App\Support\ListingsDisk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsListings;
use Tests\TestCase;

/**
 * S8.2: every rate limit in the security design (architecture 06 §3) exists with its value and is
 * attached to its routes; the limits added for S-01 (ad submission per IP, photo files per account)
 * stop requests. The login, registration, reset, verification, reveal and report limits are also
 * exercised in their own feature tests.
 */
class RateLimitsTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    /** Route name => limiter, for every throttled route. */
    private const ROUTES = [
        'login.store' => 'login',
        'two-factor.login.store' => 'two-factor',
        'verification.verify' => 'verification',
        'verification.send' => 'verification',
        'register.store' => 'register',
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        'listings.store' => 'listing-submit',
        'listings.submit' => 'listing-submit',
        'account.listings.promote.store' => 'boost-order',
        'listings.contact' => 'contact-reveal',
        'reports.store' => 'report',
        'ads.global' => 'search',
        'ads.country' => 'search',
        'ads.location' => 'search',
        'ads.category' => 'search',
        'ads.category.location' => 'search',
        'ads.subcategory.location' => 'search',
    ];

    /** Limiter => list of [max attempts, decay seconds] for a signed-in request (security doc §3). */
    private const LIMITS = [
        'login' => [[5, 60], [30, 60]],
        'two-factor' => [[5, 60]],
        'verification' => [[6, 60]],
        'register' => [[5, 3600]],
        'password-reset' => [[3, 60], [10, 3600]],
        'listing-submit' => [[30, 3600]],
        'boost-order' => [[10, 3600]],
        'contact-reveal' => [[30, 3600]],
        'report' => [[10, 86400]],
        'search' => [[120, 60]],
    ];

    public function test_every_throttled_route_uses_its_limiter(): void
    {
        $throttled = [];
        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && preg_match('/^throttle:([a-z-]+)$/', $middleware, $m)) {
                    $throttled[(string) $route->getName()] = $m[1];
                }
            }
        }
        // Registration and reset are throttled by a global middleware, by route name.
        foreach (ThrottleAuthEndpoints::LIMITERS as $name => $limiter) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "{$name} no longer exists");
            $throttled[$name] = $limiter;
        }
        ksort($throttled);
        $expected = self::ROUTES;
        ksort($expected);

        $this->assertSame($expected, $throttled);
    }

    public function test_each_limiter_has_the_designed_limits(): void
    {
        $request = Request::create('/', 'POST', ['email' => 'someone@example.com']);
        $request->setUserResolver(fn () => User::factory()->make(['id' => 1]));
        $request->setLaravelSession(app('session.store'));

        foreach (self::LIMITS as $name => $expected) {
            $limiter = RateLimiter::limiter($name);
            $this->assertNotNull($limiter, "limiter {$name} is missing");
            $result = $limiter($request);
            $limits = array_map(fn (Limit $l) => [$l->maxAttempts, $l->decaySeconds], is_array($result) ? $result : [$result]);

            $this->assertSame($expected, $limits, $name);
        }
        $this->assertSame(60, (int) config('marketplace.listings.photos_per_hour_user'));
    }

    public function test_ad_submission_stops_after_the_hourly_limit_per_ip(): void
    {
        $this->seed(DatabaseSeeder::class);
        Country::where('iso2', 'LK')->update(['is_active' => true]);
        Notification::fake();
        config(['marketplace.listings.submits_per_hour_ip' => 2]);
        $seller = User::factory()->create();
        $listing = $this->completeDraft($seller);

        $this->actingAs($seller)->post(route('listings.submit', $listing))->assertStatus(302);
        $this->actingAs($seller)->post(route('listings.store'), [])->assertStatus(302);
        // Shared between new drafts and submissions, from the same address.
        $this->actingAs($seller)->post(route('listings.submit', $listing))->assertStatus(429);
        $this->actingAs($seller)->post(route('listings.store'), [])->assertStatus(429);
        $this->actingAs($seller)->post(route('listings.submit', $listing), [], ['REMOTE_ADDR' => '10.0.0.9'])->assertStatus(302);
    }

    public function test_photo_uploads_stop_after_the_hourly_limit_per_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        Country::where('iso2', 'LK')->update(['is_active' => true]);
        Storage::fake(ListingsDisk::NAME);
        config(['marketplace.listings.photos_per_hour_user' => 2]);
        $seller = $this->sellerWithPhone();
        $listing = $this->completeDraft($seller);

        // Every file counts, including one removed again: three files, two processed.
        $this->actingAs($seller)->post(route('listings.photos.store', $listing), ['photos' => [$this->photo(), $this->photo(), $this->photo()]]);
        $this->assertSame(1 + 2, $listing->images()->count());
        $errors = session('photo_errors');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString(__('posting.photos.too_many', ['minutes' => 60]), $errors[0]);

        $image = $listing->images()->latest('id')->value('id');
        $this->actingAs($seller)->delete(route('listings.photos.destroy', [$listing, $image]));
        $this->actingAs($seller)->postJson(route('listings.photos.store', $listing), ['photos' => [$this->photo()]])
            ->assertStatus(422)->assertJsonPath('added', 0);
        $this->assertSame(2, $listing->images()->count());

        // Another account has its own allowance.
        $other = $this->sellerWithPhone();
        $theirs = $this->completeDraft($other);
        $this->actingAs($other)->postJson(route('listings.photos.store', $theirs), ['photos' => [$this->photo()]])->assertOk();
    }
}
