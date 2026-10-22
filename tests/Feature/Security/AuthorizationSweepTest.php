<?php

namespace Tests\Feature\Security;

use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\DesignSystem;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Page;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Q7.4: authorization boundary sweep. Every application route is classified here (a new route
 * fails the completeness test until it is), every page is requested as guest, unverified member,
 * member, moderator, admin and owner, and the outcome is compared with the expected table, which
 * is written from docs/architecture/05-authorization.md, not read from the route middleware.
 */
class AuthorizationSweepTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const ROLES = ['guest', 'unverified', 'member', 'moderator', 'admin', 'owner'];

    /** Admin screens: permission needed (authorization doc §2–§3). */
    private const ADMIN = [
        'admin.dashboard' => Permissions::ACCESS_ADMIN,
        'admin.moderation' => Permissions::LISTINGS_MODERATE,
        'admin.changes' => Permissions::LISTINGS_MODERATE,
        'admin.reports' => Permissions::REPORTS_VIEW,
        'admin.categories' => Permissions::CATEGORIES_MANAGE,
        'admin.attributes' => Permissions::ATTRIBUTES_MANAGE,
        'admin.locations' => Permissions::LOCATIONS_MANAGE,
        'admin.countries' => Permissions::LOCATIONS_MANAGE,
        'admin.location-requests' => Permissions::LOCATIONS_MANAGE,
        'admin.pages' => Permissions::PAGES_MANAGE,
        'admin.blocklist' => Permissions::BLOCKLIST_MANAGE,
        'admin.audit' => Permissions::AUDIT_VIEW,
        'admin.boosts' => Permissions::BOOSTS_MANAGE,
        'admin.users' => Permissions::USERS_VIEW,
        'admin.design-system' => Permissions::SETTINGS_MANAGE,
        'admin.settings' => Permissions::SETTINGS_MANAGE,
        'admin.staff' => Permissions::STAFF_MANAGE,
    ];

    /**
     * Every named application route: public | auth | verified | admin | owned (a member's own ad) |
     * guest-form (login, register…) | fortify-2fa | fortify-public (POST endpoints anyone may call).
     */
    private const ROUTES = [
        'home' => 'public', 'countries' => 'public', 'robots' => 'public', 'page.show' => 'public',
        'listing.show' => 'public', 'seller.show' => 'public', 'ads.global' => 'public', 'country.home' => 'public',
        'ads.country' => 'public', 'ads.location' => 'public', 'ads.category.location' => 'public',
        'ads.subcategory.location' => 'public', 'ads.category' => 'public', 'country.choose' => 'public',
        'listings.contact' => 'public',
        'login' => 'guest-form', 'register' => 'guest-form', 'password.request' => 'guest-form', 'password.reset' => 'guest-form',
        'two-factor.login' => 'guest-form',
        'login.store' => 'fortify-public', 'register.store' => 'fortify-public', 'password.email' => 'fortify-public',
        'password.update' => 'fortify-public', 'two-factor.login.store' => 'fortify-public', 'logout' => 'fortify-public',
        'verification.notice' => 'auth', 'verification.verify' => 'auth', 'verification.send' => 'auth',
        'user-profile-information.update' => 'auth', 'user-password.update' => 'auth',
        'password.confirm' => 'auth', 'password.confirmation' => 'auth', 'password.confirm.store' => 'auth',
        'two-factor.enable' => 'fortify-2fa', 'two-factor.confirm' => 'fortify-2fa', 'two-factor.disable' => 'fortify-2fa',
        'two-factor.qr-code' => 'fortify-2fa', 'two-factor.secret-key' => 'fortify-2fa', 'two-factor.recovery-codes' => 'fortify-2fa',
        'two-factor.regenerate-recovery-codes' => 'fortify-2fa',
        'account.settings.profile' => 'auth', 'account.settings.security' => 'auth', 'account.destroy' => 'auth',
        'account.overview' => 'auth', 'account.listings' => 'auth', 'account.favourites' => 'auth', 'listings.favourite' => 'auth',
        'account.settings.contact' => 'verified', 'account.settings.contact.update' => 'verified', 'listings.create' => 'verified',
        'listings.store' => 'verified', 'reports.store' => 'verified', 'account.two-factor' => 'verified',
        'account.listings.sold' => 'owned', 'account.listings.renew' => 'owned', 'account.listings.destroy' => 'owned',
        'listings.edit' => 'owned', 'listings.update' => 'owned', 'listings.contact-options' => 'owned', 'listings.submit' => 'owned',
        'listings.submitted' => 'owned', 'listings.photos.store' => 'owned', 'listings.photos.move' => 'owned',
        'listings.photos.cover' => 'owned', 'listings.photos.destroy' => 'owned', 'account.listings.stats' => 'owned',
    ] + self::ADMIN_CLASSES;

    private const ADMIN_CLASSES = [
        'admin.dashboard' => 'admin', 'admin.moderation' => 'admin', 'admin.changes' => 'admin', 'admin.reports' => 'admin',
        'admin.categories' => 'admin', 'admin.attributes' => 'admin', 'admin.locations' => 'admin', 'admin.countries' => 'admin',
        'admin.location-requests' => 'admin', 'admin.pages' => 'admin', 'admin.blocklist' => 'admin', 'admin.audit' => 'admin',
        'admin.users' => 'admin', 'admin.design-system' => 'admin', 'admin.settings' => 'admin', 'admin.staff' => 'admin',
        'admin.boosts' => 'admin',
    ];

    /** Framework and package internals: not application endpoints. */
    private const IGNORED_PREFIXES = ['livewire.', 'default-livewire.', 'ignition.', 'storage.'];

    /** @var array<string, User|null> */
    private array $users = [];

    private User $member;

    private Listing $memberDraft;

    private Listing $live;

    private ListingImage $image;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seedRoles();
        $lk = Country::where('iso2', 'LK')->firstOrFail();
        $lk->update(['is_active' => true]);
        Page::where('slug', 'terms')->update(['is_published' => true]);

        $province = Region::factory()->create(['country_id' => $lk->id, 'slug' => 'western']);
        $district = Region::factory()->childOf($province)->create(['slug' => 'colombo']);
        $town = City::factory()->inRegion($district)->create(['slug' => 'nugegoda']);

        $this->member = User::factory()->create();
        $this->users = [
            'guest' => null,
            'unverified' => User::factory()->unverified()->create(),
            'member' => $this->member,
            'moderator' => $this->staff(StaffRole::Moderator, twoFactor: false),
            'admin' => $this->staff(StaffRole::Admin),
            'owner' => $this->staff(StaffRole::Owner),
        ];
        $this->memberDraft = Listing::factory()->inCountry($lk)->for($this->member)->draft()->create();
        $this->live = Listing::factory()->inCountry($lk)->for($this->member)->create(['region_id' => $province->id, 'subregion_id' => $district->id, 'city_id' => $town->id]);
        $this->image = new ListingImage;
        $this->image->forceFill([
            'listing_id' => $this->memberDraft->id, 'user_id' => $this->member->id, 'disk' => 'listings', 'directory' => 'listings/'.$this->memberDraft->id,
            'basename' => Str::lower(Str::random(32)), 'format' => 'webp', 'width' => 800, 'height' => 600, 'bytes' => 1000, 'sha256' => str_repeat('a', 64),
        ])->save();
    }

    // ---- 1. Roles ----------------------------------------------------------------------------

    public function test_role_permissions_match_the_authorization_design(): void
    {
        // docs/architecture/05-authorization.md §3, written out (Owner: everything).
        $moderator = [Permissions::ACCESS_ADMIN, Permissions::LISTINGS_VIEW_ANY, Permissions::LISTINGS_MODERATE, Permissions::LISTINGS_SUSPEND,
            Permissions::REPORTS_VIEW, Permissions::REPORTS_RESOLVE, Permissions::USERS_VIEW, Permissions::BLOCKLIST_MANAGE];
        $adminLacks = [Permissions::USERS_DELETE, Permissions::SETTINGS_MANAGE, Permissions::STAFF_MANAGE];

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($this->users['owner']->can($permission), "owner: {$permission}");
            $this->assertSame(! in_array($permission, $adminLacks, true), $this->users['admin']->can($permission), "admin: {$permission}");
            $this->assertSame(in_array($permission, $moderator, true), $this->users['moderator']->can($permission), "moderator: {$permission}");
            $this->assertFalse($this->users['member']->can($permission), "member: {$permission}");
        }
    }

    // ---- 2. Completeness -----------------------------------------------------------------------

    public function test_every_application_route_is_classified(): void
    {
        $names = collect(Router::getRoutes()->getRoutes())
            ->map(fn (Route $route) => $route->getName())
            ->filter(fn (?string $name) => $name !== null && ! Str::startsWith($name, self::IGNORED_PREFIXES))
            ->unique()->sort()->values()->all();

        $this->assertSame([], array_values(array_diff($names, array_keys(self::ROUTES))), 'Routes missing from the sweep (classify them)');
        $this->assertSame([], array_values(array_diff(array_keys(self::ROUTES), $names)), 'Classified routes that no longer exist');
        $this->assertEqualsCanonicalizing(array_keys(self::ADMIN), array_keys(self::ADMIN_CLASSES));
    }

    // ---- 3. Pages (GET) per role -----------------------------------------------------------------

    public function test_every_page_answers_each_role_as_expected(): void
    {
        $problems = [];
        $checked = 0;
        foreach (self::ROUTES as $name => $class) {
            $route = Router::getRoutes()->getByName($name);
            if (! in_array('GET', $route->methods(), true) || in_array($class, ['guest-form', 'fortify-2fa'], true) || $name === 'verification.verify') {
                continue;
            }
            foreach (self::ROLES as $role) {
                $expected = $this->expectedPage($class, $name, $role);
                $actual = $this->outcome($this->as($role)->get($this->url($name)));
                $checked++;
                if ($expected !== $actual) {
                    $problems[] = "{$name} as {$role}: expected {$expected}, got {$actual}";
                }
            }
        }

        $this->assertSame([], $problems);
        $this->assertGreaterThanOrEqual(40 * count(self::ROLES), $checked, 'Every page was requested as every role');
    }

    // ---- 4. Changing actions refuse guests and unverified accounts --------------------------------

    public function test_state_changing_routes_refuse_guests_and_unverified_accounts(): void
    {
        $problems = [];
        foreach (self::ROUTES as $name => $class) {
            $route = Router::getRoutes()->getByName($name);
            $method = collect($route->methods())->first(fn (string $m) => ! in_array($m, ['GET', 'HEAD'], true));
            if ($method === null || ! in_array($class, ['auth', 'verified', 'owned', 'fortify-2fa'], true)) {
                continue;
            }
            $guest = $this->outcome($this->as('guest')->call($method, $this->url($name)));
            if ($guest !== 'login') {
                $problems[] = "{$name} ({$method}) as guest: expected login, got {$guest}";
            }
            if (in_array($class, ['verified', 'owned'], true)) {
                $unverified = $this->outcome($this->as('unverified')->call($method, $this->url($name)));
                if (! in_array($unverified, ['verify', 'forbidden', 'notfound'], true)) {
                    $problems[] = "{$name} ({$method}) as unverified: expected verify, got {$unverified}";
                }
            }
        }

        $this->assertSame([], $problems);
    }

    // ---- 5. Ownership: nobody else may open or change a member's ad --------------------------------

    public function test_a_members_ad_is_closed_to_other_members_and_to_staff(): void
    {
        $other = User::factory()->create();
        $before = $this->memberDraft->fresh()->toArray();
        $problems = [];
        $checked = 0;

        foreach (array_keys(array_filter(self::ROUTES, fn ($class) => $class === 'owned')) as $name) {
            $method = collect(Router::getRoutes()->getByName($name)->methods())->first(fn (string $m) => $m !== 'HEAD');
            foreach (['another member' => $other, 'moderator' => $this->users['moderator'], 'admin' => $this->users['admin']] as $who => $user) {
                $outcome = $this->outcome($this->actingAs($user)->call($method, $this->url($name), $this->body($name)));
                $checked++;
                if (! in_array($outcome, ['forbidden', 'notfound'], true)) {
                    $problems[] = "{$name} ({$method}) as {$who}: expected forbidden or not found, got {$outcome}";
                }
            }
        }

        $this->assertSame([], $problems);
        $this->assertSame(count(array_filter(self::ROUTES, fn ($class) => $class === 'owned')) * 3, $checked, 'Every owned route as three other users');
        $this->assertEquals($before, $this->memberDraft->fresh()->toArray(), 'The ad was not changed');
        $this->assertSame(ListingStatus::Draft, $this->memberDraft->fresh()->status);
        $this->assertTrue(ListingImage::whereKey($this->image->id)->exists(), 'The photo was not removed');
    }

    // ---- 6. Admin components refuse when mounted without the route ---------------------------------

    public function test_the_design_system_component_checks_its_permission(): void
    {
        // The only admin component without its own "forbidden" test elsewhere.
        Livewire::actingAs($this->users['moderator'])->test(DesignSystem::class)->assertForbidden();
        Livewire::actingAs($this->users['owner'])->test(DesignSystem::class)->assertOk()->call('toast')->assertDispatched('toast');
    }

    // ---- helpers ------------------------------------------------------------------------------

    private function expectedPage(string $class, string $name, string $role): string
    {
        return match ($class) {
            'public' => 'ok',
            'auth' => $role === 'guest' ? 'login' : 'ok',
            'verified' => match ($role) {
                'guest' => 'login', 'unverified' => 'verify', default => 'ok'
            },
            'owned' => match ($role) {
                'guest' => 'login', 'unverified' => 'verify', 'member' => 'ok', default => 'forbidden'
            },
            'admin' => match (true) {
                $role === 'guest' => 'login',
                $role === 'unverified' => 'verify',
                $role === 'member' => 'forbidden',
                default => $this->users[$role]->can(self::ADMIN[$name]) ? 'ok' : 'forbidden',
            },
        };
    }

    /** ok | login | verify | confirm | two-factor | forbidden | notfound | other:NNN */
    private function outcome(TestResponse $response): string
    {
        $status = $response->getStatusCode();
        if ($response->isRedirect()) {
            $target = parse_url((string) $response->headers->get('Location'), PHP_URL_PATH) ?: '/';

            return match (true) {
                $target === '/login' => 'login',
                $target === '/email/verify' => 'verify',
                $target === '/user/confirm-password' => 'confirm',
                $target === '/account/two-factor' => 'two-factor',
                default => 'ok',
            };
        }

        return match (true) {
            $status === 401 => 'login',
            $status === 403 => 'forbidden',
            $status === 404 => 'notfound',
            $status === 422 || ($status >= 200 && $status < 300) => 'ok',
            default => 'other:'.$status,
        };
    }

    private function as(string $role): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withSession(['auth.password_confirmed_at' => time()]);

        return $this->users[$role] === null ? $this : $this->actingAs($this->users[$role]);
    }

    private function url(string $name): string
    {
        $listing = $this->memberDraft;
        $params = match ($name) {
            'page.show' => ['slug' => 'terms'],
            'listing.show' => ['slugId' => Str::afterLast(parse_url($this->live->publicUrl(), PHP_URL_PATH), '/ad/')],
            'seller.show' => ['idSlug' => Str::afterLast(parse_url($this->member->sellerUrl(), PHP_URL_PATH), '/seller/')],
            'country.home', 'ads.country' => ['cc' => 'lk'],
            'ads.location' => ['cc' => 'lk', 'region' => 'western', 'city' => 'nugegoda'],
            'ads.category' => ['cc' => 'lk', 'category' => 'vehicles', 'subcategory' => 'cars'],
            'ads.category.location' => ['cc' => 'lk', 'category' => 'vehicles', 'region' => 'western'],
            'ads.subcategory.location' => ['cc' => 'lk', 'category' => 'vehicles', 'subcategory' => 'cars', 'region' => 'western'],
            'listings.contact' => ['listing' => $this->live->id],
            'password.reset' => ['token' => 'token'],
            'verification.verify' => ['id' => $this->member->id, 'hash' => sha1($this->member->email)],
            'listings.edit' => ['listing' => $listing->id, 'step' => 'details'],
            'listings.update' => ['listing' => $listing->id, 'step' => 'details'],
            'listings.photos.move', 'listings.photos.cover', 'listings.photos.destroy' => ['listing' => $listing->id, 'image' => $this->image->id],
            'listings.contact-options', 'listings.submit', 'listings.submitted', 'listings.photos.store',
            'account.listings.sold', 'account.listings.renew', 'account.listings.destroy', 'account.listings.stats', 'listings.favourite' => ['listing' => $listing->id],
            default => [],
        };

        return route($name, $params);
    }

    /** A plausible body so a refusal is not just a validation error. */
    private function body(string $name): array
    {
        return match ($name) {
            'listings.update' => ['title' => 'Changed by someone else', 'description' => str_repeat('Changed by someone else. ', 4), 'type' => 'sale'],
            'listings.contact-options' => ['show_call' => '1', 'show_whatsapp' => '1'],
            'listings.photos.move' => ['direction' => 'earlier'],
            default => [],
        };
    }
}
