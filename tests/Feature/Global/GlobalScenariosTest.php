<?php

namespace Tests\Feature\Global;

use App\Enums\ContactVisibility;
use App\Enums\StaffRole;
use App\Livewire\Admin\CountriesAdmin;
use App\Livewire\Admin\LocationsAdmin;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use App\Rules\HumanTiming;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Q7.3: global scenarios (architecture doc 13 §11) that had no end-to-end feature test yet.
 * The others are traced to existing tests in docs/qa/global-scenarios.md.
 */
class GlobalScenariosTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seedRoles();
        Country::whereIn('iso2', ['LK', 'IN', 'GB', 'US'])->update(['is_active' => true]);
    }

    private function country(string $iso2): Country
    {
        return Country::where('iso2', $iso2)->firstOrFail();
    }

    /** Scenario 1: each country's currency and number format on card, detail, search, favourites and the seller's ads. */
    public function test_scenario_1_prices_are_formatted_per_country_on_every_surface(): void
    {
        $expected = ['LK' => 'Rs 12,500,000', 'IN' => '₹1,25,00,000', 'GB' => '£12,500,000'];
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        foreach ($expected as $iso2 => $text) {
            $listing = Listing::factory()->inCountry($this->country($iso2))->for($seller)
                ->create(['price' => '12500000', 'price_type' => 'fixed', 'title' => "Price check {$iso2}"]);
            $this->assertSame($this->country($iso2)->default_currency, $listing->currency, "{$iso2} uses its own currency");
            $buyer->favourites()->attach($listing->id);
            $cc = strtolower($iso2);

            $surfaces = [
                'detail' => $this->get($listing->publicUrl())->assertOk()->getContent(),
                'country home card' => $this->get("/{$cc}")->assertOk()->getContent(),
                // No keyword: MariaDB FULLTEXT does not see rows inside the test transaction (see KeywordSearchTest).
                'search' => $this->get("/{$cc}/ads")->assertOk()->getContent(),
                'favourites' => $this->actingAs($buyer)->get(route('account.favourites'))->assertOk()->getContent(),
                'my ads' => $this->actingAs($seller)->get(route('account.listings'))->assertOk()->getContent(),
            ];
            auth()->logout();
            foreach ($surfaces as $surface => $html) {
                // Prices sit in markup (amount and symbol may be separate elements): compare the text.
                $plain = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));
                $this->assertStringContainsString($text, $plain, "{$iso2}: {$surface}");
            }
        }
    }

    /** Scenario 5 (renamed city): ads keep their place by ID, so a renamed town still filters. */
    public function test_scenario_5_a_renamed_town_still_filters_its_ads(): void
    {
        $lk = $this->country('LK');
        $western = Region::factory()->create(['country_id' => $lk->id, 'slug' => 'western']);
        $colombo = Region::factory()->childOf($western)->create(['slug' => 'colombo']);
        $town = City::factory()->inRegion($colombo)->create(['name' => ['en' => 'Kohuwala'], 'slug' => 'kohuwala']);
        $listing = Listing::factory()->inCountry($lk)->create(['title' => 'Rename check', 'region_id' => $western->id, 'subregion_id' => $colombo->id, 'city_id' => $town->id]);

        Livewire::actingAs($this->staff(StaffRole::Admin))->test(LocationsAdmin::class)
            ->set('countryId', (string) $lk->id)
            ->call('editCity', $town->id)->set('cityName', 'Kohuwala Junction')->call('saveCity')->assertHasNoErrors();

        $town->refresh();
        $this->assertSame('Kohuwala Junction', $town->name);
        $this->assertSame($town->id, $listing->fresh()->city_id);
        $this->get("/lk/ads?region=western&subregion=colombo&city={$town->slug}")->assertOk()->assertSee('Rename check');
    }

    /** Scenario 10: the Owner deactivates a country: browse pages 404, its ads are hidden, new ads are blocked. */
    public function test_scenario_10_deactivating_a_country_closes_it(): void
    {
        $gb = $this->country('GB');
        $listing = Listing::factory()->inCountry($gb)->create(['title' => 'British ad']);
        $this->get('/gb')->assertOk();
        $this->get($listing->publicUrl())->assertOk();

        Livewire::actingAs($this->staff(StaffRole::Owner))->test(CountriesAdmin::class)
            ->call('select', $gb->id)->call('toggleActive')->assertHasNoErrors();
        $this->assertFalse($gb->fresh()->is_active);

        $this->get('/gb')->assertNotFound();
        $this->get('/gb/ads')->assertNotFound();
        $this->get($listing->publicUrl())->assertNotFound();
        $this->get('/ads?q=British')->assertOk()->assertDontSee('British ad');

        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('listings.store'), ['country_id' => $gb->id, 'category_id' => $listing->category_id])
            ->assertSessionHasErrors('country_id');
    }

    /** Scenario 12: phone numbers of several countries are stored as E.164; invalid ones are refused. */
    public function test_scenario_12_phones_are_normalised_per_country(): void
    {
        $cases = [
            'LK' => ['077 123 4567', '+94771234567'],
            'US' => ['(201) 555-0123', '+12015550123'],
            'GB' => ['07400 123456', '+447400123456'],
            'IN' => ['098765 43210', '+919876543210'],
        ];
        $lk = $this->country('LK');
        foreach ($cases as $iso2 => [$typed, $e164]) {
            $email = strtolower($iso2).'@example.com';
            $this->post('/register', $this->registration($lk, ['email' => $email, 'phone_country' => $iso2, 'phone' => $typed]))->assertRedirect();
            $this->assertSame($e164, User::where('email', $email)->value('phone'), $iso2);
            auth()->logout();
        }

        $this->post('/register', $this->registration($lk, ['email' => 'bad@example.com', 'phone_country' => 'US', 'phone' => '12345']))
            ->assertSessionHasErrors('phone');
        $this->assertFalse(User::where('email', 'bad@example.com')->exists());
    }

    /** Scenario 12 (WhatsApp): the link uses the E.164 digits of any country. */
    public function test_scenario_12_whatsapp_links_use_the_international_digits(): void
    {
        $us = $this->country('US');
        $seller = User::factory()->create(['phone' => '+12015550123', 'phone_country' => 'US']);
        $seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();
        $listing = Listing::factory()->inCountry($us)->for($seller)->create(['show_whatsapp' => true]);

        $url = $this->postJson(route('listings.contact', $listing), ['channel' => 'whatsapp'])->assertOk()->json('url');

        $this->assertStringStartsWith('https://wa.me/12015550123?text=', $url);
    }

    /** @return array<string, mixed> */
    private function registration(Country $country, array $overrides): array
    {
        $token = HumanTiming::token();
        $this->travel(5)->seconds();

        return array_merge([
            'name' => 'Global Tester', 'email' => 'tester@example.com',
            'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery',
            'country_id' => $country->id, 'terms' => '1', 'website' => '', HumanTiming::FIELD => $token,
        ], $overrides);
    }
}
