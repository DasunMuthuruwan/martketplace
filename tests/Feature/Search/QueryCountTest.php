<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F-08 (Q7.5): result pages must not run one query per ad. The page with 24 ads must need the same
 * number of queries as the page with 3 (category links from the cached tree; no photo lookups for
 * ads without photos).
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create(['iso2' => 'LK']);
        $vehicles = Category::factory()->create(['slug' => 'vehicles']);
        $this->cars = Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'cars']);
        Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'vans']);
        Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'bikes']);
    }

    private function queries(string $url, ?User $user = null): int
    {
        ($user ? $this->actingAs($user) : $this)->get($url)->assertOk(); // warm the catalogue caches
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        ($user ? $this->actingAs($user) : $this)->get($url)->assertOk();

        return $count;
    }

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return ['country browse' => ['/lk/ads'], 'category' => ['/lk/ads/vehicles/cars'], 'country home' => ['/lk']];
    }

    #[DataProvider('pages')]
    public function test_result_pages_do_not_query_per_ad(string $url): void
    {
        $seller = User::factory()->create();
        Listing::factory()->count(3)->inCountry($this->country)->for($seller)->create(['category_id' => $this->cars->id]);
        $few = $this->queries($url);

        Listing::factory()->count(21)->inCountry($this->country)->for($seller)->create(['category_id' => $this->cars->id]);
        $many = $this->queries($url);

        $this->assertSame($few, $many, "{$url}: {$few} queries with 3 ads, {$many} with 24");
    }

    public function test_my_ads_does_not_query_per_ad(): void
    {
        $seller = User::factory()->create();
        Listing::factory()->count(3)->inCountry($this->country)->for($seller)->create(['category_id' => $this->cars->id]);
        $few = $this->queries(route('account.listings'), $seller);

        Listing::factory()->count(21)->inCountry($this->country)->for($seller)->create(['category_id' => $this->cars->id]);
        $many = $this->queries(route('account.listings'), $seller);

        $this->assertSame($few, $many, "My ads: {$few} queries with 3 ads, {$many} with 24");
    }
}
