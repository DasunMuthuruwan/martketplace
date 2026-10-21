<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Exceptions\InvalidCategoryTree;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingAttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ---- Public scope (one test per exclusion) ------------------------------------------------

    public function test_an_active_unexpired_listing_is_public(): void
    {
        $listing = Listing::factory()->create();

        $this->assertTrue($listing->isPublic());
        $this->assertSame([$listing->id], Listing::public()->pluck('id')->all());
    }

    /** @return array<string, array{ListingStatus}> */
    public static function nonPublicStatuses(): array
    {
        return array_combine(
            array_map(fn (ListingStatus $s) => $s->value, array_filter(ListingStatus::cases(), fn ($s) => $s !== ListingStatus::Active)),
            array_map(fn (ListingStatus $s) => [$s], array_values(array_filter(ListingStatus::cases(), fn ($s) => $s !== ListingStatus::Active))),
        );
    }

    #[DataProvider('nonPublicStatuses')]
    public function test_only_the_active_status_is_public(ListingStatus $status): void
    {
        $this->assertFalse(Listing::factory()->status($status)->create()->isPublic());
    }

    public function test_expiry_is_enforced_at_query_time(): void
    {
        $listing = Listing::factory()->create(['expires_at' => now()->addHour()]);
        $this->assertTrue($listing->isPublic());

        $this->travel(61)->minutes();
        $this->assertFalse($listing->isPublic(), 'No job needed: expired by time alone');

        $this->assertFalse(Listing::factory()->create(['expires_at' => null])->isPublic(), 'No expiry date = not published');
    }

    public function test_deleted_listings_are_hidden(): void
    {
        $listing = Listing::factory()->create();
        $listing->delete();

        $this->assertSame(0, Listing::public()->count());
        $this->assertSoftDeleted($listing);
    }

    public function test_listings_of_suspended_banned_or_deleted_owners_are_hidden(): void
    {
        $suspended = Listing::factory()->for(User::factory()->suspended())->create();
        $banned = Listing::factory()->for(User::factory()->banned())->create();
        $deletedOwner = Listing::factory()->create();
        $deletedOwner->user->delete();
        $suspensionOver = Listing::factory()->for(User::factory()->suspended(now()->subDay()))->create();
        $suspendedUntilTomorrow = Listing::factory()->for(User::factory()->suspended(now()->addDay()))->create();

        $this->assertSame([$suspensionOver->id], Listing::public()->pluck('id')->all());
        $this->assertFalse($suspended->isPublic());
        $this->assertFalse($banned->isPublic());
        $this->assertFalse($deletedOwner->isPublic());
        $this->assertFalse($suspendedUntilTomorrow->isPublic());
    }

    public function test_listings_in_an_inactive_country_are_hidden(): void
    {
        $listing = Listing::factory()->create();
        $listing->country->update(['is_active' => false]);

        $this->assertFalse($listing->isPublic());
    }

    public function test_listings_in_an_inactive_category_or_under_an_inactive_parent_are_hidden(): void
    {
        $leafOff = Listing::factory()->create();
        $leafOff->category->update(['is_active' => false]);
        $parentOff = Listing::factory()->create();
        $parentOff->category->parent->update(['is_active' => false]);

        $this->assertFalse($leafOff->isPublic());
        $this->assertFalse($parentOff->isPublic());
    }

    // ---- Integrity rules ---------------------------------------------------------------------

    public function test_a_category_with_listings_cannot_be_deleted_or_get_subcategories(): void
    {
        $listing = Listing::factory()->create();
        $leaf = $listing->category;
        $listing->delete(); // even a soft-deleted listing keeps its category

        try {
            $leaf->delete();
            $this->fail('Category with listings deleted');
        } catch (InvalidCategoryTree) {
            $this->assertModelExists($leaf);
        }

        $this->expectException(InvalidCategoryTree::class);
        Category::factory()->create()->update(['parent_id' => $leaf->id]);
    }

    public function test_attributes_and_options_in_use_cannot_be_deleted(): void
    {
        $listing = Listing::factory()->create();
        $fuel = Attribute::factory()->select(['petrol', 'diesel'])->create(['key' => 'fuel']);
        $petrol = $fuel->options()->where('value', 'petrol')->firstOrFail();
        $listing->attributeValues()->create(['attribute_id' => $fuel->id, 'value_option_id' => $petrol->id]);

        foreach (['option' => fn () => $petrol->delete(), 'attribute' => fn () => $fuel->delete()] as $what => $delete) {
            try {
                $delete();
                $this->fail("{$what} in use was deleted");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        // The unused option can go; deleting the listing removes its values (cascade).
        $fuel->options()->where('value', 'diesel')->firstOrFail()->delete();
        $listing->forceDelete();
        $this->assertSame(0, ListingAttributeValue::count());
        $petrol->delete();
        $this->assertSame(0, AttributeOption::count());
    }

    public function test_slug_follows_the_title(): void
    {
        $listing = Listing::factory()->create(['title' => 'Toyota Aqua 2015 – Hybrid!']);
        $this->assertSame('toyota-aqua-2015-hybrid', $listing->slug);

        $listing->update(['title' => 'Toyota Aqua G 2015']);
        $this->assertSame('toyota-aqua-g-2015', $listing->fresh()->slug);

        $this->assertSame('listing', Listing::factory()->create(['title' => '!!!'])->slug);
    }

    public function test_price_is_kept_exactly_in_its_currency(): void
    {
        $listing = Listing::factory()->create(['price' => '12500000.5']);

        $this->assertSame('12500000.500', $listing->fresh()->price);
        $this->assertSame($listing->country->default_currency, $listing->currency);
        $this->assertSame($listing->currency, $listing->currencyModel->code);
    }

    public function test_status_and_moderation_fields_are_not_mass_assignable(): void
    {
        $listing = Listing::factory()->draft()->create();

        $listing->update(['status' => 'active', 'flagged' => true, 'view_count' => 999, 'user_id' => User::factory()->create()->id, 'title' => 'Changed']);

        $listing->refresh();
        $this->assertSame(ListingStatus::Draft, $listing->status);
        $this->assertFalse($listing->flagged);
        $this->assertSame(0, $listing->view_count);
        $this->assertSame('Changed', $listing->title);
    }

    public function test_the_documented_indexes_exist(): void
    {
        $indexes = collect(Schema::getIndexes('listings'))->map(fn (array $index) => implode(',', $index['columns']))->all();

        foreach ([
            // The "newest" order is sorted_at since B1 (a Bump moves it; ADR-026).
            'status,category_id,sorted_at', 'status,country_id,sorted_at', 'status,country_id,category_id,sorted_at',
            'status,region_id,sorted_at', 'status,subregion_id,sorted_at', 'status,city_id,sorted_at',
            'status,country_id,currency,price', 'status,category_id,currency,price', 'status,expires_at',
            'status,submitted_at', 'user_id,status,updated_at',
        ] as $columns) {
            $this->assertContains($columns, $indexes, "Missing index ({$columns})");
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertContains('title,description', $indexes, 'FULLTEXT for keyword search');
        }

        $boostIndexes = collect(Schema::getIndexes('listing_boosts'))->map(fn (array $index) => implode(',', $index['columns']))->all();
        foreach (['type,ends_at', 'listing_id,type', 'order_id'] as $columns) {
            $this->assertContains($columns, $boostIndexes);
        }
        $this->assertContains('listing_id,date', collect(Schema::getIndexes('listing_daily_views'))->map(fn (array $index) => implode(',', $index['columns']))->all());

        $valueIndexes = collect(Schema::getIndexes('listing_attribute_values'))->map(fn (array $index) => implode(',', $index['columns']))->all();
        foreach (['attribute_id,value_option_id,listing_id', 'attribute_id,value_number,listing_id', 'attribute_id,value_bool,listing_id'] as $columns) {
            $this->assertContains($columns, $valueIndexes);
        }
    }
}
