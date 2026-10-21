<?php

namespace Tests\Feature\Boosts;

use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Services\Boosts\BoostManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * B2: a top ad must match the visitor's keyword. Committed rows (DatabaseMigrations), because
 * MariaDB's full-text index does not see rows inside an open test transaction.
 */
class TopAdsKeywordTest extends TestCase
{
    use DatabaseMigrations;

    public function test_top_ads_must_match_the_keyword(): void
    {
        $lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $cars = Category::factory()->create(['parent_id' => Category::factory()->create(['slug' => 'vehicles'])->id, 'slug' => 'cars']);
        foreach (['Toyota Aqua hybrid', 'Nissan Leaf electric'] as $title) {
            app(BoostManager::class)->top(Listing::factory()->inCountry($lk)->create(['category_id' => $cars->id, 'title' => $title]), 7);
        }

        $block = function (string $url): string {
            $html = $this->get($url)->assertOk()->getContent();

            return preg_match('/<section[^>]*data-top-ads[^>]*>(.*?)<\/section>/s', $html, $m) ? $m[1] : '';
        };

        $toyota = $block('/lk/ads?q=toyota');
        $this->assertStringContainsString('Toyota Aqua hybrid', $toyota);
        $this->assertStringNotContainsString('Nissan Leaf electric', $toyota);
        $this->assertSame('', $block('/lk/ads?q=honda'));
    }
}
