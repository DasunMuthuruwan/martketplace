<?php

namespace Tests\Feature\Design;

use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** F1.3: layouts render their landmarks, navigation and brand rules (navigation doc §2, Q-UX-1/2/3). */
class LayoutsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function oneH1(string $html): void
    {
        $this->assertSame(1, substr_count($html, '<h1'), 'Each page has exactly one h1');
    }

    public function test_public_layout_landmarks_skip_link_and_single_h1(): void
    {
        $country = Country::factory()->create();
        $html = $this->get('/'.strtolower($country->iso2))->assertOk()->getContent();

        $this->assertStringContainsString('href="#main"', $html);          // skip link
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('<main id="main"', $html);
        $this->assertStringContainsString('<footer', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('data-bottom-nav', $html);
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $html);
        $this->assertStringContainsString("localStorage.getItem('theme')", $html); // theme before paint
        $this->oneH1($html);
    }

    public function test_brand_uses_the_configured_name_and_the_parent_line_is_configurable(): void
    {
        config(['app.name' => 'Bazaar Test']);
        $html = $this->get('/countries')->assertOk()->getContent();

        $this->assertStringContainsString('Bazaar Test', $html);
        $this->assertStringContainsString('href="https://dev-talk.com"', $html);
        $this->assertStringContainsString('A Dev Talk service', $html);
        $this->assertStringContainsString(config('marketplace.geo.attribution'), $html);

        config(['marketplace.brand.parent_url' => '']); // configured empty = hidden
        $this->assertStringNotContainsString('dev-talk.com', $this->get('/countries')->getContent());
    }

    public function test_category_bar_lists_the_countrys_categories(): void
    {
        $country = Country::factory()->create();
        $root = Category::factory()->create(['name' => ['en' => 'Vehicles'], 'slug' => 'vehicles']);
        Category::factory()->create(['parent_id' => $root->id]);

        $html = $this->get('/'.strtolower($country->iso2))->getContent();

        $this->assertStringContainsString('href="'.route('ads.category', ['cc' => strtolower($country->iso2), 'category' => 'vehicles']).'"', $html);
    }

    public function test_bottom_nav_items_and_guest_sign_in(): void
    {
        $html = $this->get('/countries')->getContent();
        $nav = substr($html, strpos($html, 'data-bottom-nav'));

        foreach (['Home', 'Search', 'Post', 'Saved', 'Account'] as $label) {
            $this->assertStringContainsString($label, $nav);
        }
        // Account sends guests to log in; screens not built yet show as unavailable.
        $this->assertStringContainsString('href="'.route('login').'"', $nav);
        $this->assertStringContainsString('aria-disabled="true"', $nav);
    }

    public function test_footer_links_published_pages_by_group(): void
    {
        Page::create(['slug' => 'terms', 'title' => ['en' => 'Terms of use'], 'body' => ['en' => 'x'], 'is_published' => true]);
        Page::create(['slug' => 'safety', 'title' => ['en' => 'Safety tips'], 'body' => ['en' => 'x'], 'is_published' => true]);
        Page::create(['slug' => 'privacy', 'title' => ['en' => 'Privacy'], 'body' => ['en' => 'x'], 'is_published' => false]);

        $html = $this->get('/countries')->getContent();

        $this->assertStringContainsString(route('page.show', 'terms'), $html);
        $this->assertStringContainsString(route('page.show', 'safety'), $html);
        $this->assertStringNotContainsString(route('page.show', 'privacy'), $html);
    }

    public function test_account_menu_for_members_and_staff(): void
    {
        $this->assertStringContainsString('href="'.route('login').'"', $this->get('/countries')->getContent());

        $this->seedRoles();
        $staff = $this->staff(StaffRole::Moderator, twoFactor: false);
        $html = $this->actingAs($staff)->get('/countries')->getContent();
        $this->assertStringContainsString(route('admin.dashboard'), $html);
        $this->assertStringContainsString('action="'.route('logout').'"', $html);
    }

    public function test_account_layout_has_side_navigation_and_one_h1(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('account.settings.profile'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="'.__('account.nav.label').'"', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('account.settings.profile'), '/').'"\s+aria-current="page"/', $html);
        $this->oneH1($html);
    }

    public function test_auth_layout_is_a_centred_card_without_site_navigation(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('<main id="main"', $html);
        $this->assertStringNotContainsString('data-bottom-nav', $html);
        $this->assertStringContainsString('noindex', $html);
        $this->oneH1($html);
    }

    public function test_admin_layout_sidebar_follows_permissions_and_shows_queue_badges(): void
    {
        $this->seedRoles();
        $moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
        Listing::factory()->status(ListingStatus::PendingReview)->create();

        $html = $this->actingAs($moderator)->get(route('admin.moderation'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.reports'), $html);
        $this->assertStringNotContainsString(route('admin.settings'), $html);
        $this->assertMatchesRegularExpression('/>1<span class="sr-only"> '.__('admin.waiting').'/', $html);
        $this->assertStringContainsString('data-modal-open="admin-menu"', $html);
    }

    public function test_style_guide_is_owner_only(): void
    {
        $this->seedRoles();
        $this->actingAs($this->staff(StaffRole::Admin))->get(route('admin.design-system'))->assertForbidden();

        $html = $this->actingAs($this->staff(StaffRole::Owner))->get(route('admin.design-system'))->assertOk()->getContent();
        $this->assertStringContainsString('listing-grid-area', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->oneH1($html);
    }

    public function test_error_pages_render_without_built_assets_or_the_database(): void
    {
        $html = $this->get('/ad/missing-999999999')->assertNotFound()->getContent();

        $this->assertStringContainsString('404', $html);
        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }
}
