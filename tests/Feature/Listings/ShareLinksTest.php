<?php

namespace Tests\Feature\Listings;

use App\Models\Country;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** G3 (ADR-027): share an ad straight to WhatsApp and Facebook, with a proper link preview. */
class ShareLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ad_page_links_to_whatsapp_and_facebook_with_its_own_address(): void
    {
        $country = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $listing = Listing::factory()->inCountry($country)->create(['title' => 'Toyota Aqua 2015 & more']);
        $url = $listing->publicUrl();

        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('href="https://wa.me/?text='.e(rawurlencode('Toyota Aqua 2015 & more '.$url)).'"', $html);
        $this->assertStringContainsString('href="https://www.facebook.com/sharer/sharer.php?u='.e(rawurlencode($url)).'"', $html);
        $this->assertSame(2, substr_count($html, 'rel="noopener noreferrer"'));
        $this->assertStringContainsString(__('listings.detail.share_whatsapp_label'), $html);
        // The preview the apps show comes from these tags.
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.$url.'"', $html);
    }
}
