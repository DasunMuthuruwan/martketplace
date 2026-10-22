<?php

namespace Database\Seeders;

use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Models\BankTransferAccount;
use App\Models\BoostPrice;
use App\Models\BoostProduct;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Page;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use App\Services\Boosts\BoostManager;
use App\Services\Geo\RegionChain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data for the browser tests (tests/Browser, Playwright + axe) and the Lighthouse run: the
 * reference data; the default country (Sri Lanka: province → district → town) and the United
 * States (state → city) active with a few places; a seller with live, pending and draft ads; a
 * moderator; published help pages; an open report. Never called by DatabaseSeeder; refuses production.
 *
 * Accounts (password from BROWSER_TEST_PASSWORD): seller@browser.test, moderator@browser.test.
 */
class BrowserTestSeeder extends Seeder
{
    /** Places used by the journeys: [region, subregion (2-level countries) or null, towns]. */
    private const PLACES = [
        'LK' => [['Western', 'Colombo', ['Nugegoda', 'Maharagama']], ['Western', 'Gampaha', ['Negombo']], ['Central', 'Kandy', ['Peradeniya']]],
        'US' => [['California', null, ['Los Angeles', 'San Diego']], ['Texas', null, ['Austin']]],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('BrowserTestSeeder must not run in production.');
        }

        $this->call(DatabaseSeeder::class);

        $iso2 = strtoupper((string) (config('marketplace.default_country') ?: 'LK'));
        $country = Country::where('iso2', $iso2)->firstOrFail();
        $country->update(['is_active' => true]);
        Country::where('iso2', 'US')->update(['is_active' => true]);
        foreach (self::PLACES as $code => $places) {
            $this->places(Country::where('iso2', $code)->firstOrFail(), $places);
        }

        $city = City::where('country_id', $country->id)->where('is_active', true)->orderBy('id')->first();
        $category = Category::whereNotNull('parent_id')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->firstOrFail();

        $password = (string) (config('marketplace.testing.browser_password') ?: 'browser-test-password');
        $seller = User::factory()->create([
            'name' => 'Nimal Seller', 'email' => 'seller@browser.test', 'password' => $password,
            'phone' => '+94771234567', 'phone_country' => $iso2, 'country_id' => $country->id,
        ]);
        $seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();

        $moderator = User::factory()->create(['name' => 'Mala Moderator', 'email' => 'moderator@browser.test', 'password' => $password]);
        $moderator->assignRole('moderator');

        // An Admin with confirmed two-factor sign-in (known test secret), for the promotion journey.
        $admin = User::factory()->create(['name' => 'Ama Admin', 'email' => 'admin@browser.test', 'password' => $password]);
        $admin->assignRole('admin');
        $admin->forceFill([
            'two_factor_secret' => encrypt((string) (config('marketplace.testing.browser_totp_secret') ?: 'MARKETPLACEBROWSERTESTTWOFACTORA')),
            'two_factor_recovery_codes' => encrypt((string) json_encode(['browser-recovery-one'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $place = ['category_id' => $category->id, 'city_id' => $city?->id] + ($city ? RegionChain::forCity($city) : []);
        $titles = ['Toyota Aqua 2015 G grade', 'Honda Vezel 2017 hybrid', 'Suzuki Wagon R 2016', 'Nissan Leaf 2018', 'Toyota Prius 2014', 'Mitsubishi Montero 2012', 'Honda Fit 2015 GP5', 'Toyota Corolla 2019'];
        foreach ($titles as $i => $title) {
            Listing::factory()->inCountry($country)->for($seller)->create($place + [
                'title' => $title, 'published_at' => now()->subHours($i + 1), 'expires_at' => now()->addDays(30),
            ]);
        }
        // Promotions on sale in this country (B5): two prices in its currency and a bank account.
        foreach ([['bump', null, '1000'], ['top', 7, '4500']] as [$type, $days, $amount]) {
            BoostPrice::create(['boost_product_id' => BoostProduct::where('type', $type)->where('duration_days', $days)->value('id'),
                'country_id' => $country->id, 'currency' => $country->default_currency, 'amount' => $amount]);
        }
        BankTransferAccount::create(['country_id' => $country->id, 'bank_name' => 'Test Bank', 'account_name' => 'Marketplace Test', 'account_number' => '0001112223', 'is_active' => true]);

        // One top ad, so the accessibility scans cover the labelled top-ad block (ADR-026).
        app(BoostManager::class)->top(Listing::where('title', 'Mitsubishi Montero 2012')->firstOrFail(), 7);
        Listing::factory()->inCountry($country)->for($seller)->status(ListingStatus::PendingReview)
            ->create($place + ['title' => 'Daihatsu Hijet 2014 van', 'published_at' => null, 'submitted_at' => now()->subMinutes(20)]);
        Listing::factory()->inCountry($country)->for($seller)->draft()->create($place + ['title' => 'Draft: Mazda Axela 2016']);

        Page::query()->update(['is_published' => true]);

        $report = new Report(['reason' => 'scam', 'details' => 'Asked for a deposit before viewing.']);
        $report->forceFill(['reporter_id' => $moderator->id, 'reportable_type' => 'listing', 'reportable_id' => Listing::where('status', ListingStatus::Active)->value('id')])->save();
    }

    /** @param  list<array{0: string, 1: string|null, 2: list<string>}>  $places */
    private function places(Country $country, array $places): void
    {
        foreach ($places as [$regionName, $subregionName, $towns]) {
            $region = Region::where('country_id', $country->id)->where('level', 1)->get()->first(fn (Region $r) => $r->name === $regionName)
                ?? Region::factory()->create(['country_id' => $country->id, 'name' => ['en' => $regionName], 'slug' => Str::slug($regionName), 'is_active' => true]);
            $parent = $region;
            if ($subregionName !== null && $country->region_levels >= 2) {
                $parent = Region::factory()->childOf($region)->create(['name' => ['en' => $subregionName], 'slug' => Str::slug($subregionName), 'is_active' => true]);
            }
            foreach ($towns as $town) {
                City::factory()->inRegion($parent)->create(['name' => ['en' => $town], 'ascii_name' => $town, 'slug' => Str::slug($town), 'is_active' => true]);
            }
        }
    }
}
