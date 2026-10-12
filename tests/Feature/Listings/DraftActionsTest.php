<?php

namespace Tests\Feature\Listings;

use App\Actions\Listings\SaveListingDetails;
use App\Actions\Listings\SaveListingLocation;
use App\Actions\Listings\SaveListingPrice;
use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Exceptions\Listings\CountryChangeNeedsConfirmation;
use App\Exceptions\Listings\ListingNotEditable;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use App\Services\Listings\ListingUnitPrice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Draft steps of the posting wizard (T4.2) against the real seeded catalogue.
 * Global scenarios (global design §11): 1 (currency per country), 2 (decimals per currency),
 * 3 (country change clears, never converts), 4 (location chain), 8 (land unit price), 9 (scoped options).
 */
class DraftActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Country::whereIn('iso2', ['LK', 'GB', 'IN', 'JP', 'KW', 'FR'])->update(['is_active' => true]);
        $this->seller = User::factory()->create();
    }

    private function country(string $iso2): Country
    {
        return Country::where('iso2', $iso2)->firstOrFail();
    }

    private function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }

    private function draft(string $slug = 'cars', string $iso2 = 'LK', array $location = []): Listing
    {
        return app(SaveListingLocation::class)->handle($this->seller, null, [
            'category_id' => $this->category($slug)->id,
            'country_id' => $this->country($iso2)->id,
        ] + $location);
    }

    /** @param  array<string, mixed>  $attributes */
    private function details(Listing $listing, array $attributes, array $overrides = []): Listing
    {
        return app(SaveListingDetails::class)->handle($this->seller, $listing, $overrides + [
            'title' => 'A well kept example for sale',
            'description' => 'Carefully used, all documents available. Viewing welcome.',
            'type' => 'sale',
            'condition' => 'used',
            'attributes' => $attributes,
        ]);
    }

    private function price(Listing $listing, array $input): Listing
    {
        return app(SaveListingPrice::class)->handle($this->seller, $listing, $input);
    }

    /** @return array<string, string> */
    private function errors(callable $callback): array
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return array_map(fn (array $messages) => $messages[0], $e->errors());
        }
        $this->fail('Expected a validation error');
    }

    private function landDraft(string $iso2, string $size, string $unit): Listing
    {
        return $this->details($this->draft('land', $iso2), ['land_size' => $size, 'land_size__unit' => $unit], ['condition' => null]);
    }

    // ---- Location step ----------------------------------------------------------------------

    public function test_draft_takes_the_currency_of_its_country(): void
    {
        $this->assertSame('LKR', $this->draft('cars', 'LK')->currency);
        $this->assertSame('INR', $this->draft('cars', 'IN')->currency);
        $listing = $this->draft('cars', 'GB');

        $this->assertSame('GBP', $listing->currency);
        $this->assertSame(ListingStatus::Draft, $listing->status);
        $this->assertSame($this->seller->id, $listing->user_id);
        $this->assertFalse($listing->isPublic());
    }

    public function test_category_must_be_an_available_leaf(): void
    {
        $this->assertArrayHasKey('category_id', $this->errors(fn () => $this->draft('vehicles')), 'Top-level category');
        $this->assertArrayHasKey('category_id', $this->errors(fn () => $this->draft('three-wheelers', 'GB')), 'Scoped to IN/LK');
        $this->assertSame('three-wheelers', $this->draft('three-wheelers', 'LK')->category->slug);

        $this->category('boats')->update(['is_active' => false]);
        $this->assertArrayHasKey('category_id', $this->errors(fn () => $this->draft('boats')));
    }

    public function test_location_chain_is_validated(): void
    {
        $lk = $this->country('LK');
        $western = Region::factory()->create(['country_id' => $lk->id]);
        $southern = Region::factory()->create(['country_id' => $lk->id]);
        $colombo = City::factory()->inRegion($western)->create();

        $listing = $this->draft('cars', 'LK', ['region_id' => $western->id, 'city_id' => $colombo->id, 'locality_text' => 'Near the station']);
        $this->assertSame($colombo->id, $listing->city_id);
        $this->assertSame('Near the station', $listing->locality_text);

        $this->assertArrayHasKey('city_id', $this->errors(fn () => $this->draft('cars', 'LK', ['region_id' => $southern->id, 'city_id' => $colombo->id])));
        $this->assertArrayHasKey('country_id', $this->errors(fn () => $this->draft('cars', 'DE')), 'Inactive country');
        $this->assertArrayHasKey('locality_text', $this->errors(fn () => $this->draft('cars', 'LK', ['locality_text' => '<b>Colombo</b>'])));
    }

    public function test_daily_limit_on_new_drafts(): void
    {
        config(['marketplace.listings.max_new_per_day' => 2]);
        $this->draft();
        $this->draft()->delete(); // deleted drafts still count

        $this->assertStringContainsString('2 new ads per day', $this->errors(fn () => $this->draft())['category_id']);

        $this->travel(25)->hours();
        $this->assertNotNull($this->draft());
    }

    public function test_only_the_owner_edits_and_only_drafts(): void
    {
        $listing = $this->draft();

        try {
            app(SaveListingDetails::class)->handle(User::factory()->create(), $listing, []);
            $this->fail('Another user edited the draft');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $listing->forceFill(['status' => ListingStatus::Sold])->save(); // sold ads are not edited
        $this->expectException(ListingNotEditable::class);
        $this->details($listing, []);
    }

    public function test_unverified_or_suspended_users_cannot_create_drafts(): void
    {
        $this->seller = User::factory()->unverified()->create();
        try {
            $this->draft();
            $this->fail('Unverified user created a draft');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->seller = User::factory()->suspended()->create();
        $this->expectException(AuthorizationException::class);
        $this->draft();
    }

    // ---- Country change (GLB-11, scenario 3) ----------------------------------------------------

    public function test_changing_country_needs_confirmation_and_clears_without_converting(): void
    {
        $listing = $this->landDraft('LK', '50', 'perch');
        $this->details($listing, ['land_size' => '50', 'land_size__unit' => 'perch', 'deed_type' => 'bimsaviya'], ['condition' => null]);
        $listing = $this->price($listing->fresh(), ['price_type' => 'fixed', 'price' => '12500000']);

        $move = fn (bool $confirm) => app(SaveListingLocation::class)->handle($this->seller, $listing->fresh(), [
            'category_id' => $listing->category_id, 'country_id' => $this->country('GB')->id, 'confirm_country_change' => $confirm,
        ]);

        try {
            $move(false);
            $this->fail('Country changed without confirmation');
        } catch (CountryChangeNeedsConfirmation $e) {
            // Price, the LK-only deed type and perches (not a GB unit) would be cleared.
            $this->assertEqualsCanonicalizing(['price', 'deed_type', 'land_size'], array_keys($e->clears));
        }
        $this->assertSame('12500000.000', $listing->fresh()->price, 'Nothing changed yet');

        $moved = $move(true);
        $this->assertSame('GBP', $moved->currency);
        $this->assertNull($moved->price, 'Cleared, never converted');
        $this->assertSame(0, $moved->attributeValues()->count());
        $this->assertNull($moved->attributes_summary);
    }

    public function test_compatible_values_survive_a_country_change(): void
    {
        $listing = $this->details($this->draft('cars', 'LK'), ['make' => 'toyota', 'model' => 'aqua', 'year_of_manufacture' => '2015', 'fuel_type' => 'hybrid', 'transmission' => 'automatic', 'mileage' => '82000']);

        // No price yet, nothing scoped: moves without confirmation and keeps the details.
        $moved = app(SaveListingLocation::class)->handle($this->seller, $listing, ['category_id' => $listing->category_id, 'country_id' => $this->country('IN')->id]);

        $this->assertSame('INR', $moved->currency);
        $this->assertSame('Toyota', $moved->attributes_summary['make']);
        $this->assertSame(6, $moved->attributeValues()->count());
    }

    public function test_changing_category_clears_the_old_details(): void
    {
        $listing = $this->details($this->draft('cars'), ['make' => 'toyota', 'model' => 'aqua', 'year_of_manufacture' => '2015', 'fuel_type' => 'hybrid', 'transmission' => 'automatic', 'mileage' => '82000']);

        $moved = app(SaveListingLocation::class)->handle($this->seller, $listing, ['category_id' => $this->category('vans')->id, 'country_id' => $listing->country_id]);

        $this->assertSame('vans', $moved->category->slug);
        $this->assertSame(0, $moved->attributeValues()->count());
        $this->assertNull($moved->attributes_summary);
    }

    // ---- Details step ---------------------------------------------------------------------------

    public function test_details_are_validated_and_written_as_typed_values(): void
    {
        $listing = $this->details($this->draft('cars'), [
            'make' => 'ford', 'model' => 'ranger', 'year_of_manufacture' => '2019', 'mileage' => '45000',
            'fuel_type' => 'diesel', 'transmission' => 'manual', 'colour' => 'other', 'colour__other' => 'Copper',
        ], ['title' => "  Ford   Ranger\n double cab  ", 'description' => "Line one of the ad.\r\n\r\n\r\n\r\nLine two after gaps."]);

        $this->assertSame('Ford Ranger double cab', $listing->title);
        $this->assertSame("Line one of the ad.\n\nLine two after gaps.", $listing->description);
        $this->assertSame('ford-ranger-double-cab', $listing->slug);
        // "Ranger" exists under Ford and Hino: the Ford one is stored.
        $model = $listing->attributeValues()->whereHas('attribute', fn ($q) => $q->where('key', 'model'))->firstOrFail();
        $this->assertSame('ford', $model->option->parentOption->value);
        $this->assertSame(['make' => 'Ford', 'model' => 'Ranger', 'year_of_manufacture' => '2019', 'mileage' => '45000', 'fuel_type' => 'Diesel', 'transmission' => 'Manual', 'colour' => 'Copper'], $listing->attributes_summary);
    }

    public function test_details_rules(): void
    {
        $listing = $this->draft('cars');
        $valid = ['make' => 'toyota', 'model' => 'aqua', 'year_of_manufacture' => '2015', 'fuel_type' => 'hybrid', 'transmission' => 'automatic', 'mileage' => '82000'];

        $this->assertArrayHasKey('title', $this->errors(fn () => $this->details($listing, $valid, ['title' => 'Too short'])));
        $this->assertArrayHasKey('description', $this->errors(fn () => $this->details($listing, $valid, ['description' => 'Nice car <script>x</script> for sale here'])));
        $this->assertArrayHasKey('type', $this->errors(fn () => $this->details($this->draft('mobile-phones'), [], ['type' => 'rent'])), 'Phones: sale or wanted');
        $this->assertArrayHasKey('condition', $this->errors(fn () => $this->details($listing, $valid, ['condition' => null])));
        $this->assertArrayHasKey('attributes.mileage', $this->errors(fn () => $this->details($listing, array_diff_key($valid, ['mileage' => 1]))), 'Used car needs mileage');
        $this->assertArrayHasKey('attributes.model', $this->errors(fn () => $this->details($listing, ['model' => 'vezel'] + $valid)));
        // Wanted ads may leave the condition empty; property hides it entirely.
        $this->assertNull($this->details($listing, array_diff_key($valid, ['mileage' => 1]), ['type' => 'wanted', 'condition' => null])->condition);
        $this->assertArrayHasKey('condition', $this->errors(fn () => $this->details($this->draft('land'), ['land_size' => '10', 'land_size__unit' => 'perch'], ['condition' => 'new'])));
    }

    public function test_country_scoped_options_are_rejected_elsewhere(): void
    {
        $gbLand = $this->draft('land', 'GB');

        $this->assertArrayHasKey('attributes', $this->errors(fn () => $this->details($gbLand, ['land_size' => '2', 'land_size__unit' => 'acre', 'deed_type' => 'bimsaviya'], ['condition' => null])));
        $this->assertArrayHasKey('attributes.land_size__unit', $this->errors(fn () => $this->details($gbLand, ['land_size' => '50', 'land_size__unit' => 'perch'], ['condition' => null])));
    }

    public function test_area_is_stored_as_entered_with_a_square_metre_copy(): void
    {
        $listing = $this->landDraft('LK', '1.25', 'rood');
        $value = $listing->attributeValues()->firstOrFail();

        $this->assertSame('1.2500', $value->input_value);
        $this->assertSame('rood', $value->inputUnit->code);
        $this->assertSame('1264.6426', $value->value_number);
        $this->assertSame(['value' => '1.25', 'unit' => 'rood', 'm2' => '1264.6426'], $listing->attributes_summary['land_size']);
    }

    // ---- Price step (scenarios 2 and 8) ---------------------------------------------------------

    public function test_decimals_follow_the_currency(): void
    {
        $lk = $this->details($this->draft('mobile-phones', 'LK'), ['phone_brand' => 'apple', 'phone_model' => 'iphone-15']);
        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($lk, ['price_type' => 'fixed', 'price' => '150000.125'])));
        $this->assertSame('150000.120', $this->price($lk, ['price_type' => 'fixed', 'price' => '150000.12'])->price);

        $kw = $this->details($this->draft('mobile-phones', 'KW'), ['phone_brand' => 'apple', 'phone_model' => 'iphone-15']);
        $this->assertSame('250.125', $this->price($kw, ['price_type' => 'fixed', 'price' => '250.125'])->price);

        $jp = $this->details($this->draft('mobile-phones', 'JP'), ['phone_brand' => 'apple', 'phone_model' => 'iphone-15']);
        $this->assertStringContainsString('cannot have decimal places', $this->errors(fn () => $this->price($jp, ['price_type' => 'fixed', 'price' => '98000.5']))['price']);
        $this->assertSame('98000.000', $this->price($jp, ['price_type' => 'fixed', 'price' => '98000'])->price);
    }

    public function test_price_rules(): void
    {
        $phone = $this->details($this->draft('mobile-phones'), ['phone_brand' => 'apple', 'phone_model' => 'iphone-15']);

        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($phone, ['price_type' => 'fixed'])));
        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($phone, ['price_type' => 'fixed', 'price' => '0'])));
        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($phone, ['price_type' => 'fixed', 'price' => '1,500'])));
        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($phone, ['price_type' => 'fixed', 'price' => '9999999999999999'])));
        $this->assertArrayHasKey('price_type', $this->errors(fn () => $this->price($phone, ['price_type' => 'free'])), 'Phones: no free');
        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($phone, ['price_type' => 'on_request', 'price' => '5000'])));
        $this->assertArrayHasKey('rent_period', $this->errors(fn () => $this->price($phone, ['price_type' => 'fixed', 'price' => '5000', 'rent_period' => 'month'])));

        $onRequest = $this->price($phone, ['price_type' => 'on_request']);
        $this->assertSame(PriceType::OnRequest, $onRequest->price_type);
        $this->assertNull($onRequest->price);

        // The currency is never taken from input.
        $this->assertSame('LKR', $this->price($phone, ['price_type' => 'fixed', 'price' => '5000', 'currency' => 'USD'])->currency);

        $sofa = $this->details($this->draft('furniture'), []);
        $this->assertSame(PriceType::Free, $this->price($sofa, ['price_type' => 'free'])->price_type);
    }

    public function test_rent_needs_a_period_and_details_come_first(): void
    {
        $this->assertArrayHasKey('price', $this->errors(fn () => $this->price($this->draft('houses'), ['price_type' => 'fixed', 'price' => '100000'])));

        $house = $this->details($this->draft('houses'), ['bedrooms' => '3'], ['type' => 'rent', 'condition' => null]);
        $this->assertArrayHasKey('rent_period', $this->errors(fn () => $this->price($house, ['price_type' => 'fixed', 'price' => '100000'])));
        $this->assertSame('month', $this->price($house, ['price_type' => 'fixed', 'price' => '100000', 'rent_period' => 'month'])->rent_period->value);
    }

    public function test_wanted_ads_may_leave_the_budget_empty(): void
    {
        $wanted = $this->details($this->draft('mobile-phones'), ['phone_brand' => 'apple', 'phone_model' => 'iphone-15'], ['type' => 'wanted', 'condition' => null]);

        $this->assertNull($this->price($wanted, ['price_type' => 'negotiable'])->price);
    }

    public function test_land_price_per_unit(): void
    {
        $perUnit = app(ListingUnitPrice::class);

        $lk = $this->price($this->landDraft('LK', '50', 'perch'), ['price_type' => 'fixed', 'price' => '12500000']);
        $this->assertSame(['250000.00', 'perch'], [$perUnit->for($lk)['amount'], $perUnit->for($lk)['unit']->code]);

        $sameLand = $this->price($this->landDraft('LK', '1.25', 'rood'), ['price_type' => 'negotiable', 'price' => '12500000']);
        $this->assertSame('250000.00', $perUnit->for($sameLand)['amount']);

        $gb = $this->price($this->landDraft('GB', '2', 'acre'), ['price_type' => 'fixed', 'price' => '500000']);
        $this->assertSame(['250000.00', 'acre'], [$perUnit->for($gb)['amount'], $perUnit->for($gb)['unit']->code]);

        $fr = $this->price($this->landDraft('FR', '5000', 'sqm'), ['price_type' => 'fixed', 'price' => '200000']);
        $this->assertNull($perUnit->for($fr), 'No "price per" unit in FR');

        $house = $this->price($this->details($this->draft('houses'), ['bedrooms' => '3', 'land_size' => '10', 'land_size__unit' => 'perch'], ['condition' => null]), ['price_type' => 'fixed', 'price' => '30000000']);
        $this->assertNull($perUnit->for($house), 'Houses never show a per-unit price');

        // Land: free and on request are not offered (ADR-018).
        $this->assertArrayHasKey('price_type', $this->errors(fn () => $this->price($this->landDraft('LK', '10', 'perch'), ['price_type' => 'on_request'])));
    }
}
