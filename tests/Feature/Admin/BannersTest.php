<?php

namespace Tests\Feature\Admin;

use App\Enums\BannerPlacement;
use App\Enums\StaffRole;
use App\Livewire\Admin\BannersAdmin;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Country;
use App\Support\ListingsDisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B10: banners managed by staff, shown labelled in their country, placement and dates. */
class BannersTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Country $lk;

    private Country $us;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake(ListingsDisk::NAME);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $this->us = Country::factory()->create(['iso2' => 'US', 'is_active' => true]);
    }

    private function banner(array $attributes = []): Banner
    {
        $banner = new Banner($attributes + ['title' => 'Spring offer', 'placement' => BannerPlacement::HomeTop, 'country_id' => $this->lk->id, 'is_active' => true]);
        $banner->forceFill(['image_path' => 'banners/test.webp', 'width' => 1200, 'height' => 240])->save();

        return $banner;
    }

    private function form(string $title = 'Spring offer')
    {
        return Livewire::actingAs($this->staff(StaffRole::Admin))->test(BannersAdmin::class)
            ->call('create')->set('title', $title)->set('countryId', (string) $this->lk->id);
    }

    public function test_moderators_cannot_open_the_screen(): void
    {
        $this->actingAs($this->staff(StaffRole::Moderator, twoFactor: false))->get(route('admin.banners'))->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Admin))->get(route('admin.banners'))->assertOk();
    }

    public function test_staff_upload_a_banner_which_is_re_encoded_and_audited(): void
    {
        $this->form()->set('image', UploadedFile::fake()->image('offer.png', 2400, 480))
            ->set('linkUrl', 'https://shop.example/offer')->call('save')->assertHasNoErrors();

        $banner = Banner::sole();
        $this->assertSame(config('marketplace.banners.max_width'), $banner->width);
        $this->assertStringStartsWith('banners/', $banner->image_path);
        $this->assertStringEndsNotWith('.png', $banner->image_path);
        ListingsDisk::disk()->assertExists($banner->image_path);
        $this->assertSame(1, AuditLog::where('action', 'banner.created')->count());
    }

    public function test_an_image_is_required_and_must_really_be_an_image(): void
    {
        $this->form()->call('save')->assertHasErrors('image');

        $this->form()->set('image', UploadedFile::fake()->createWithContent('offer.png', '<?php echo 1;'))
            ->call('save')->assertHasErrors('image');

        $this->assertSame(0, Banner::count());
    }

    public function test_only_web_links_are_accepted(): void
    {
        $this->form()->set('image', UploadedFile::fake()->image('offer.jpg', 800, 200))
            ->set('linkUrl', 'javascript:alert(1)')->call('save')->assertHasErrors('linkUrl');
    }

    public function test_the_end_date_cannot_be_before_the_start_date(): void
    {
        $this->form()->set('image', UploadedFile::fake()->image('offer.jpg', 800, 200))
            ->set('startsOn', '2030-02-02')->set('endsOn', '2030-02-01')->call('save')->assertHasErrors('endsOn');
    }

    public function test_replacing_and_deleting_remove_the_old_file(): void
    {
        $this->form()->set('image', UploadedFile::fake()->image('one.jpg', 800, 200))->call('save');
        $banner = Banner::sole();
        $first = $banner->image_path;

        $screen = Livewire::actingAs($this->staff(StaffRole::Admin))->test(BannersAdmin::class);
        $screen->call('edit', $banner->id)->set('image', UploadedFile::fake()->image('two.jpg', 800, 200))->call('save')->assertHasNoErrors();
        $second = $banner->fresh()->image_path;
        $this->assertNotSame($first, $second);
        ListingsDisk::disk()->assertMissing($first);

        $screen->call('delete', $banner->id);
        ListingsDisk::disk()->assertMissing($second);
        $this->assertSame(0, Banner::count());
        $this->assertSame(1, AuditLog::where('action', 'banner.deleted')->count());
    }

    public function test_a_live_banner_is_shown_labelled_with_a_sponsored_link(): void
    {
        $this->banner(['link_url' => 'https://shop.example/offer']);

        $this->get(route('country.home', ['cc' => 'lk']))->assertOk()
            ->assertSee('data-banner', false)->assertSee(__('public.banner.label'))
            ->assertSee('rel="sponsored noopener"', false)->assertSee('alt="Spring offer"', false);
    }

    public function test_a_banner_shows_only_in_its_country_placement_and_dates(): void
    {
        $this->banner();
        $this->get(route('country.home', ['cc' => 'us']))->assertDontSee('data-banner', false);
        $this->get(route('ads.country', ['cc' => 'lk']))->assertDontSee('data-banner', false);
        Banner::query()->delete();

        $this->banner(['starts_at' => now()->addDay()]);
        $this->banner(['ends_at' => now()->subDay()]);
        $this->banner(['is_active' => false]);
        $this->get(route('country.home', ['cc' => 'lk']))->assertDontSee('data-banner', false);
    }

    public function test_a_banner_for_every_country_shows_on_search_too(): void
    {
        $this->banner(['country_id' => null, 'placement' => BannerPlacement::SearchTop]);

        $this->get(route('ads.country', ['cc' => 'us']))->assertSee('data-banner', false);
    }

    public function test_the_title_is_escaped(): void
    {
        $this->banner(['title' => '"><script>alert(1)</script>']);

        $this->get(route('country.home', ['cc' => 'lk']))->assertDontSee('<script>alert(1)</script>', false);
    }
}
