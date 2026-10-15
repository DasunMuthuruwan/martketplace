<?php

namespace Tests\Feature\Accessibility;

use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use App\Support\ListingsDisk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccessibilityAudit;
use Tests\Support\BuildsListings;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * F6.1: structural accessibility of every main screen (forms/a11y doc §3): one h1, one main,
 * named controls, labelled fields, image alt text, unique ids, valid aria references.
 */
class PageStructureTest extends TestCase
{
    use BuildsListings, CreatesStaff, RefreshDatabase;

    private User $seller;

    private Listing $live;

    private Listing $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seedRoles();
        Country::where('iso2', 'LK')->update(['is_active' => true]);
        Storage::fake(ListingsDisk::NAME);
        Notification::fake();

        $this->seller = $this->sellerWithPhone();
        $this->seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();
        $this->draft = $this->completeDraft($this->seller);
        $this->live = $this->completeDraft($this->seller);
        $this->live->forceFill(['status' => ListingStatus::Active, 'published_at' => now(), 'expires_at' => now()->addDays(30)])->save();
        Page::where('slug', 'terms')->update(['is_published' => true]);
        session()->put('auth.password_confirmed_at', time());
    }

    /** @return array<string, array{0: string, 1: string}> name => [who, path builder] */
    public static function pages(): array
    {
        return [
            'home' => ['guest', 'home'],
            'countries' => ['guest', 'countries'],
            'country home' => ['guest', 'country'],
            'category' => ['guest', 'category'],
            'search' => ['guest', 'search'],
            'listing' => ['guest', 'listing'],
            'seller' => ['guest', 'seller'],
            'help page' => ['guest', 'page'],
            'login' => ['guest', 'login'],
            'register' => ['guest', 'register'],
            'forgot password' => ['guest', 'forgot'],
            'account overview' => ['seller', 'account'],
            'my ads' => ['seller', 'my-ads'],
            'saved' => ['seller', 'saved'],
            'profile settings' => ['seller', 'profile'],
            'security settings' => ['seller', 'security'],
            'contact settings' => ['seller', 'contact'],
            'post: category' => ['seller', 'post'],
            'post: details' => ['seller', 'details'],
            'post: price' => ['seller', 'price'],
            'post: photos' => ['seller', 'photos'],
            'post: review' => ['seller', 'review'],
            'owner preview' => ['seller', 'preview'],
            'admin dashboard' => ['owner', 'admin.dashboard'],
            'admin moderation' => ['owner', 'admin.moderation'],
            'admin changes' => ['owner', 'admin.changes'],
            'admin reports' => ['owner', 'admin.reports'],
            'admin categories' => ['owner', 'admin.categories'],
            'admin attributes' => ['owner', 'admin.attributes'],
            'admin locations' => ['owner', 'admin.locations'],
            'admin countries' => ['owner', 'admin.countries'],
            'admin place requests' => ['owner', 'admin.location-requests'],
            'admin pages' => ['owner', 'admin.pages'],
            'admin blocklist' => ['owner', 'admin.blocklist'],
            'admin audit' => ['owner', 'admin.audit'],
            'admin users' => ['owner', 'admin.users'],
            'admin settings' => ['owner', 'admin.settings'],
            'admin staff' => ['owner', 'admin.staff'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_structure(string $who, string $page): void
    {
        match ($who) {
            'seller' => $this->actingAs($this->seller),
            'owner' => $this->actingAs($this->staff(StaffRole::Owner)),
            default => null,
        };

        if ($page === 'home') {
            // Without a default country (and no cookie) the home page is the country chooser.
            config(['marketplace.default_country' => null]);
        }

        $html = $this->get($this->path($page))->assertOk()->getContent();

        $this->assertSame([], (new AccessibilityAudit($html))->problems());
    }

    private function path(string $page): string
    {
        $cc = 'lk';
        $category = $this->live->category;

        return match ($page) {
            'home' => route('home'),
            'countries' => route('countries'),
            'country' => route('country.home', ['cc' => $cc]),
            'category' => $category->parent
                ? route('ads.category', ['cc' => $cc, 'category' => $category->parent->slug, 'subcategory' => $category->slug])
                : route('ads.category', ['cc' => $cc, 'category' => $category->slug]),
            'search' => route('ads.country', ['cc' => $cc, 'q' => 'iphone']),
            'listing' => $this->live->publicUrl(),
            'seller' => $this->seller->sellerUrl(),
            'page' => route('page.show', 'terms'),
            'login' => route('login'),
            'register' => route('register'),
            'forgot' => route('password.request'),
            'account' => route('account.overview'),
            'my-ads' => route('account.listings'),
            'saved' => route('account.favourites'),
            'profile' => route('account.settings.profile'),
            'security' => route('account.settings.security'),
            'contact' => route('account.settings.contact'),
            'post' => route('listings.create'),
            'details', 'price', 'photos', 'review' => route('listings.edit', [$this->draft, $page]),
            'preview' => $this->draft->publicUrl(),
            default => route($page),
        };
    }
}
