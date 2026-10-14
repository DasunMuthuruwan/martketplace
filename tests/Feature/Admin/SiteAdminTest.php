<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\BlocklistAdmin;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\PagesAdmin;
use App\Livewire\Admin\SettingsAdmin;
use App\Models\AuditLog;
use App\Models\BlockedTerm;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Moderation\ListingScreener;
use App\Support\Settings;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** T6.5: settings, pages, blocklist, audit log viewer, dashboard (ADM-01/05/06/07, GLB-18). */
class SiteAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->owner = $this->staff(StaffRole::Owner);
        $this->admin = $this->staff(StaffRole::Admin);
        $this->moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
        session()->put('auth.password_confirmed_at', time());
    }

    // ---- Access -----------------------------------------------------------------------------

    public function test_each_screen_needs_its_permission(): void
    {
        $confirmed = ['auth.password_confirmed_at' => time()];

        $this->actingAs($this->owner)->withSession($confirmed)->get(route('admin.settings'))->assertOk();
        $this->actingAs($this->admin)->withSession($confirmed)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => 0])->get(route('admin.settings'))->assertRedirect(route('password.confirm'));

        $this->actingAs($this->admin)->get(route('admin.pages'))->assertOk();
        $this->actingAs($this->moderator)->get(route('admin.pages'))->assertForbidden();
        $this->actingAs($this->moderator)->get(route('admin.blocklist'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.audit'))->assertOk();
        $this->actingAs($this->moderator)->get(route('admin.audit'))->assertForbidden();
        $this->actingAs($this->moderator)->get(route('admin.dashboard'))->assertOk()->assertSee(__('admin.dashboard.title'));

        Livewire::actingAs($this->admin)->test(SettingsAdmin::class)->assertForbidden();
        Livewire::actingAs($this->moderator)->test(PagesAdmin::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(BlocklistAdmin::class)->assertForbidden();
    }

    // ---- Settings ---------------------------------------------------------------------------

    public function test_owner_changes_settings_which_override_config_and_are_audited(): void
    {
        Livewire::actingAs($this->owner)->test(SettingsAdmin::class)
            ->set('values.site_name', 'My Market')->set('values.listing_expiry_days', 30)
            ->set('values.moderation_mode', 'new_users')->set('values.contact_whatsapp', false)
            ->call('save')->assertHasNoErrors();

        $this->assertSame('My Market', config('app.name'));
        $this->assertSame(30, config('marketplace.listings.expiry_days'));
        $this->assertSame('new_users', config('marketplace.moderation_mode'));
        $this->assertFalse(config('marketplace.contact.whatsapp_enabled'));
        $this->assertSame(4, DB::table('settings')->count()); // only changed keys are stored

        $log = AuditLog::where('action', 'settings.updated')->firstOrFail();
        $this->assertSame(['before' => 60, 'after' => 30], $log->changes['listing_expiry_days']);

        // A fresh boot (config back to defaults) applies the stored values again.
        config(['app.name' => 'Default', 'marketplace.listings.expiry_days' => 60]);
        Cache::flush();
        Settings::apply();
        $this->assertSame(['My Market', 30], [config('app.name'), config('marketplace.listings.expiry_days')]);
        $this->get('/countries')->assertSee('My Market');
    }

    public function test_settings_are_validated(): void
    {
        Livewire::actingAs($this->owner)->test(SettingsAdmin::class)
            ->set('values.site_name', '')->set('values.contact_email', 'not-an-email')->set('values.listing_expiry_days', 2)
            ->set('values.moderation_mode', 'sometimes')->set('values.max_images', 99)
            ->call('save')->assertHasErrors(['values.site_name', 'values.contact_email', 'values.listing_expiry_days', 'values.moderation_mode', 'values.max_images']);

        $this->assertSame(0, DB::table('settings')->count());
    }

    public function test_saving_settings_after_the_password_window_changes_nothing(): void
    {
        session()->put('auth.password_confirmed_at', time() - 3600);

        Livewire::actingAs($this->owner)->test(SettingsAdmin::class)->set('values.site_name', 'Late')->call('save')->assertRedirect(route('password.confirm'));

        $this->assertSame(0, DB::table('settings')->count());
    }

    public function test_custom_country_description(): void
    {
        $country = Country::factory()->create();

        Livewire::actingAs($this->owner)->test(SettingsAdmin::class)->set('values.seo_country_description', 'Buy and sell in :country.')->call('save');

        $this->get('/'.strtolower($country->iso2))->assertSee('Buy and sell in '.$country->name.'.', false);
    }

    // ---- Pages ------------------------------------------------------------------------------

    public function test_standard_pages_are_seeded_unpublished(): void
    {
        $this->seed(PageSeeder::class);

        $this->assertSame(array_keys(Page::DEFAULTS), Page::orderBy('id')->pluck('slug')->all());
        $this->get(route('page.show', 'terms'))->assertNotFound();

        Livewire::actingAs($this->admin)->test(PagesAdmin::class)->call('delete', Page::where('slug', 'terms')->value('id'))->assertHasErrors('delete');
        $this->assertTrue(Page::where('slug', 'terms')->exists());
    }

    public function test_write_preview_and_publish_a_page_with_sanitised_markdown(): void
    {
        $markdown = "## Stay safe\n\nMeet in **public**. <script>alert(1)</script> [Terms](/page/terms) [bad](javascript:alert(1))";

        Livewire::actingAs($this->admin)->test(PagesAdmin::class)->call('create')
            ->set('slug', 'safety-guide')->set('title', 'Safety guide')->set('body', $markdown)
            ->call('togglePreview')->assertSeeHtml('<strong>public</strong>')->assertDontSeeHtml('<script>')
            ->set('published', true)->call('save')->assertHasNoErrors();

        $page = Page::where('slug', 'safety-guide')->firstOrFail();
        $this->assertTrue(AuditLog::where('action', 'page.created')->where('subject_id', $page->id)->exists());

        $html = $this->get(route('page.show', 'safety-guide'))->assertOk()
            ->assertSee('Safety guide')->assertSee('<h2>Stay safe</h2>', false)->assertSee('<a href="/page/terms">Terms</a>', false)
            ->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        // Footer links published pages.
        $this->get('/countries')->assertSee(route('page.show', 'safety-guide'), false);

        // Unpublish hides it again.
        Livewire::actingAs($this->admin)->test(PagesAdmin::class)->call('edit', $page->id)->set('published', false)->call('save');
        $this->get(route('page.show', 'safety-guide'))->assertNotFound();
        $this->assertTrue(AuditLog::where('action', 'page.unpublished')->exists());
    }

    public function test_page_validation(): void
    {
        Page::create(['slug' => 'faq', 'title' => ['en' => 'FAQ'], 'body' => ['en' => '']]);

        Livewire::actingAs($this->admin)->test(PagesAdmin::class)->call('create')
            ->set('slug', 'faq')->set('title', '')->call('save')->assertHasErrors(['slug', 'title']);
        Livewire::actingAs($this->admin)->test(PagesAdmin::class)->call('create')
            ->set('slug', 'Bad Slug')->set('title', 'X')->set('published', true)->call('save')->assertHasErrors(['slug', 'body']);
    }

    // ---- Blocklist --------------------------------------------------------------------------

    public function test_global_and_country_terms_drive_the_screener(): void
    {
        $here = Country::factory()->create();
        $there = Country::factory()->create();

        $page = Livewire::actingAs($this->moderator)->test(BlocklistAdmin::class);
        $page->set('term', 'Replica')->set('action', 'block')->call('add')->assertHasNoErrors();
        $page->set('term', 'deed')->set('countryId', (string) $here->id)->set('action', 'flag')->call('add')->assertHasNoErrors();
        $page->set('term', 'replica')->set('countryId', '')->call('add')->assertHasErrors('term'); // duplicate global term

        $this->assertSame('replica', BlockedTerm::whereNull('country_id')->value('term'));
        $this->assertTrue(AuditLog::where('action', 'blocklist.added')->exists());

        $screener = app(ListingScreener::class);
        $blocked = Listing::factory()->inCountry($there)->create(['title' => 'Replica watch', 'description' => 'Good']);
        $flaggedHere = Listing::factory()->inCountry($here)->create(['title' => 'Land with deed', 'description' => 'Clear']);
        $fineThere = Listing::factory()->inCountry($there)->create(['title' => 'Land with deed', 'description' => 'Clear']);
        $this->assertTrue($screener->screen($blocked)->isBlocked());
        $this->assertTrue($screener->screen($flaggedHere)->isFlagged());
        $this->assertFalse($screener->screen($fineThere)->isFlagged());

        $page->call('remove', BlockedTerm::where('term', 'deed')->value('id'));
        $this->assertFalse($screener->screen($flaggedHere->fresh())->isFlagged());
        $this->assertTrue(AuditLog::where('action', 'blocklist.removed')->exists());
    }

    // ---- Audit log --------------------------------------------------------------------------

    public function test_audit_log_filters_and_never_shows_ip_hashes(): void
    {
        $audit = app(AuditLogger::class);
        $audit->record($this->admin, 'listing.approved', null, null, ['note' => 'first']);
        $audit->record($this->owner, 'settings.updated', null, null, ['site_name' => ['before' => 'A', 'after' => 'B']]);
        $hash = AuditLog::latest('id')->value('ip_hash');

        $viewer = Livewire::actingAs($this->admin)->test(AuditLogViewer::class)->assertSee('listing.approved')->assertSee('settings.updated');
        if ($hash !== null) {
            $viewer->assertDontSee($hash);
        }
        $viewer->set('action', 'listing.')->assertSee('listing.approved')->assertDontSee('settings.updated');
        $viewer->set('action', '')->set('actor', $this->owner->email)->assertSee('settings.updated')->assertDontSee('listing.approved');
        $viewer->set('actor', '')->set('from', now()->addDay()->toDateString())->assertSee(__('admin.audit.empty'));
    }

    // ---- Dashboard --------------------------------------------------------------------------

    public function test_dashboard_counts(): void
    {
        $root = Category::factory()->create(['name' => ['en' => 'Vehicles']]);
        $leaf = Category::factory()->create(['parent_id' => $root->id]);
        Listing::factory()->count(2)->create(['category_id' => $leaf->id]);
        Listing::factory()->status(ListingStatus::PendingReview)->create(['category_id' => $leaf->id]);

        Livewire::actingAs($this->moderator)->test(Dashboard::class)
            ->assertViewHas('pending', 1)
            ->assertViewHas('live', 2)
            ->assertViewHas('byCategory', ['Vehicles' => 2])
            ->assertSee('Vehicles');
    }
}
