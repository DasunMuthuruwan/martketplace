<?php

namespace Tests\Feature\Security;

use App\Enums\ContactVisibility;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S8.6 (privacy, security doc §8): phone numbers reach the browser only through the rate-limited,
 * logged reveal endpoint, and email addresses are never shown publicly: not in page text, links,
 * data attributes, JSON-LD or Livewire snapshots, on any public page, even with the most open
 * contact setting.
 */
class PrivacyExposureTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['771234567', '77 123 4567', '779876543', '77 987 6543', 'nimal.private@example.com'];

    public function test_public_pages_never_contain_the_sellers_numbers_or_email(): void
    {
        $lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'phone_country_code' => '94', 'is_active' => true]);
        $seller = User::factory()->create([
            'email' => 'nimal.private@example.com', 'phone' => '+94771234567', 'phone_country' => 'LK',
            'whatsapp_phone' => '+94779876543', 'contact_visibility' => ContactVisibility::Public,
        ]);
        $cars = Category::factory()->create(['parent_id' => Category::factory()->create(['slug' => 'vehicles'])->id, 'slug' => 'cars']);
        $listing = Listing::factory()->for($seller)->inCountry($lk)->create(['category_id' => $cars->id, 'show_call' => true, 'show_whatsapp' => true]);
        $visitor = User::factory()->create();

        $pages = ['listing' => $listing->publicUrl(), 'seller' => $seller->sellerUrl(), 'home' => '/lk', 'search' => '/lk/ads', 'category' => '/lk/ads/vehicles/cars', 'global' => '/ads'];
        foreach ([null, $visitor] as $viewer) {
            foreach ($pages as $name => $url) {
                $html = ($viewer ? $this->actingAs($viewer) : $this)->get($url)->assertOk()->getContent();
                foreach (self::SECRETS as $secret) {
                    $this->assertStringNotContainsString($secret, $html, "{$name} page".($viewer ? ' (signed in)' : ' (guest)')." shows {$secret}");
                }
            }
        }

        // The reveal endpoint is the one place a number comes from.
        $this->assertStringContainsString('77', (string) $this->postJson(route('listings.contact', $listing), ['channel' => 'call'])->assertOk()->getContent());
    }

    public function test_user_records_hide_their_secrets_when_serialised(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'secret-value', 'two_factor_recovery_codes' => 'codes-value']);

        $json = $user->toJson();

        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $field) {
            $this->assertArrayNotHasKey($field, $user->toArray(), $field);
        }
        $this->assertStringNotContainsString('secret-value', $json);
    }
}
