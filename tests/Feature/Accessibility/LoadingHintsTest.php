<?php

namespace Tests\Feature\Accessibility;

use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** F6.1: image loading hints (performance budget, forms/a11y doc §5) and narrow-screen header parts. */
class LoadingHintsTest extends TestCase
{
    use RefreshDatabase;

    /** A photo row (files are not needed to render URLs). */
    private function photo(Listing $listing): ListingImage
    {
        $image = new ListingImage;
        $image->forceFill([
            'listing_id' => $listing->id, 'user_id' => $listing->user_id, 'disk' => 'listings', 'directory' => 'listings/'.$listing->id,
            'basename' => Str::lower(Str::random(32)), 'format' => 'webp', 'width' => 800, 'height' => 600, 'bytes' => 1000, 'sha256' => str_repeat('a', 64),
        ])->save();

        return $image;
    }

    public function test_first_cards_load_at_once_and_the_rest_lazily(): void
    {
        $country = Country::factory()->create();
        $category = Category::factory()->create();
        Listing::factory()->count(6)->inCountry($country)->create(['category_id' => $category->id])
            ->each(fn (Listing $l) => $l->update(['cover_image_id' => $this->photo($l)->id]));

        $html = $this->get(route('country.home', ['cc' => strtolower($country->iso2)]))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertSame(3, substr_count($html, 'fetchpriority="auto"'));
        $this->assertSame(2, substr_count($html, 'width="480" height="360"  loading="lazy"') + substr_count($html, 'width="480" height="360" loading="lazy"'));
    }

    public function test_page_renders_without_a_build_and_skips_the_font_preload(): void
    {
        // Tests run without built assets (withoutVite); the preload must not break the page.
        $this->get(route('countries'))->assertOk()->assertDontSee('rel="preload"', false);
    }

    public function test_gallery_zoom_image_has_no_empty_src(): void
    {
        $listing = Listing::factory()->create();
        $listing->update(['cover_image_id' => $this->photo($listing)->id]);

        $html = $this->get($listing->publicUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('src=""', $html);
        $this->assertStringContainsString('data-gallery-zoom-image', $html);
    }
}
