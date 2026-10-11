<?php

namespace Tests\Feature\Geo;

use App\Enums\LocationRequestStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Locality;
use App\Models\LocationRequest;
use App\Models\Region;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchy_relationships(): void
    {
        $country = Country::factory()->twoLevels()->create();
        $province = Region::factory()->create(['country_id' => $country->id]);
        $district = Region::factory()->childOf($province)->create();
        $town = City::factory()->inRegion($district)->create();
        $area = Locality::factory()->create(['city_id' => $town->id]);

        $this->assertTrue($district->parent->is($province));
        $this->assertTrue($province->children->first()->is($district));
        $this->assertTrue($district->cities->first()->is($town));
        $this->assertTrue($town->region->is($district));
        $this->assertTrue($town->country->is($country));
        $this->assertTrue($area->city->is($town));
        $this->assertSame(2, $district->level);
    }

    public function test_region_slug_is_unique_per_country_but_reusable_across_countries(): void
    {
        $first = Country::factory()->create();
        $second = Country::factory()->create();
        Region::factory()->create(['country_id' => $first->id, 'slug' => 'western']);

        Region::factory()->create(['country_id' => $second->id, 'slug' => 'western']);
        $this->assertSame(2, Region::where('slug', 'western')->count());

        $this->expectException(QueryException::class);
        Region::factory()->create(['country_id' => $first->id, 'slug' => 'western']);
    }

    public function test_level_two_slug_cannot_clash_with_a_level_one_slug_in_the_same_country(): void
    {
        $country = Country::factory()->twoLevels()->create();
        $province = Region::factory()->create(['country_id' => $country->id, 'slug' => 'central']);

        $this->expectException(QueryException::class);
        Region::factory()->childOf($province)->create(['slug' => 'central']);
    }

    public function test_region_with_cities_cannot_be_deleted(): void
    {
        $town = City::factory()->create();

        $this->expectException(QueryException::class);
        $town->region->delete();
    }

    public function test_city_with_localities_cannot_be_deleted(): void
    {
        $area = Locality::factory()->create();

        $this->expectException(QueryException::class);
        $area->city->delete();
    }

    public function test_city_names_are_translatable_with_an_ascii_search_name(): void
    {
        $town = City::factory()->create(['name' => ['en' => 'Nugegoda'], 'ascii_name' => 'Nugegoda']);
        $town->setTranslation('name', 'si', 'නුගේගොඩ')->save();

        $this->assertSame('Nugegoda', $town->fresh()->getTranslation('name', 'en'));
        $this->assertSame('නුගේගොඩ', $town->fresh()->getTranslation('name', 'si'));
        $this->assertSame('Nugegoda', $town->fresh()->ascii_name);
    }

    public function test_location_request_starts_pending(): void
    {
        $region = Region::factory()->create();

        $request = LocationRequest::create([
            'country_id' => $region->country_id,
            'region_id' => $region->id,
            'proposed_city' => 'New Town',
        ]);

        $this->assertSame(LocationRequestStatus::Pending, $request->fresh()->status);
        $this->assertTrue($request->country->is($region->country));
        $this->assertNull($request->handled_at);
    }

    public function test_active_scopes(): void
    {
        $region = Region::factory()->create();
        Region::factory()->inactive()->create(['country_id' => $region->country_id]);
        City::factory()->inRegion($region)->create();
        City::factory()->inRegion($region)->inactive()->create();

        $this->assertSame(1, Region::where('country_id', $region->country_id)->active()->count());
        $this->assertSame(1, $region->cities()->active()->count());
    }
}
