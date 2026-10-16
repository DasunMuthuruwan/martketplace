<?php

namespace Database\Seeders;

use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Page;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data for the browser tests (tests/Browser, Playwright + axe) and the Lighthouse run: the
 * reference data, one active country, a seller with live, pending and draft ads, a moderator,
 * published help pages and an open report. Never called by DatabaseSeeder; refuses production.
 *
 * Accounts (password from BROWSER_TEST_PASSWORD): seller@browser.test, moderator@browser.test.
 */
class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('BrowserTestSeeder must not run in production.');
        }

        $this->call(DatabaseSeeder::class);

        $iso2 = strtoupper((string) (config('marketplace.default_country') ?: 'LK'));
        $country = Country::where('iso2', $iso2)->firstOrFail();
        $country->update(['is_active' => true]);
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

        $place = ['category_id' => $category->id, 'city_id' => $city?->id, 'region_id' => $city?->region_id];
        $titles = ['Toyota Aqua 2015 G grade', 'Honda Vezel 2017 hybrid', 'Suzuki Wagon R 2016', 'Nissan Leaf 2018', 'Toyota Prius 2014', 'Mitsubishi Montero 2012', 'Honda Fit 2015 GP5', 'Toyota Corolla 2019'];
        foreach ($titles as $i => $title) {
            Listing::factory()->inCountry($country)->for($seller)->create($place + [
                'title' => $title, 'published_at' => now()->subHours($i + 1), 'expires_at' => now()->addDays(30),
            ]);
        }
        Listing::factory()->inCountry($country)->for($seller)->status(ListingStatus::PendingReview)
            ->create($place + ['title' => 'Daihatsu Hijet 2014 van', 'published_at' => null, 'submitted_at' => now()->subMinutes(20)]);
        Listing::factory()->inCountry($country)->for($seller)->draft()->create($place + ['title' => 'Draft: Mazda Axela 2016']);

        Page::query()->update(['is_published' => true]);

        $report = new Report(['reason' => 'scam', 'details' => 'Asked for a deposit before viewing.']);
        $report->forceFill(['reporter_id' => $moderator->id, 'reportable_type' => 'listing', 'reportable_id' => Listing::where('status', ListingStatus::Active)->value('id')])->save();
    }
}
