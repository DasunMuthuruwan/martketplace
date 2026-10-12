<?php

namespace Tests\Feature\Search;

use App\Models\Country;
use App\Models\Listing;
use App\Services\Search\ListingSearch;
use App\Services\Search\SearchCriteriaFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * Keyword search (SRCH-02). Uses DatabaseMigrations, not RefreshDatabase: InnoDB updates FULLTEXT
 * indexes on commit, so rows inside a test transaction are invisible to MATCH on MySQL/MariaDB.
 * On SQLite every word goes through the LIKE fallback.
 */
class KeywordSearchTest extends TestCase
{
    use DatabaseMigrations;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create();
    }

    private function ad(string $title, string $description = 'Well maintained, all documents available for inspection.'): Listing
    {
        return Listing::factory()->inCountry($this->country)->create(['title' => $title, 'description' => $description]);
    }

    /** @return list<int> */
    private function search(string $keyword): array
    {
        $criteria = app(SearchCriteriaFactory::class)->fromInput(['q' => $keyword], $this->country);

        return app(ListingSearch::class)->search($criteria)->getCollection()->pluck('id')->sort()->values()->all();
    }

    public function test_every_word_must_match_title_or_description(): void
    {
        $aqua = $this->ad('Toyota Aqua 2015 hybrid');
        $vitz = $this->ad('Toyota Vitz for sale', 'Single owner, hybrid battery replaced recently.');
        $this->ad('Honda Fit 2014');

        $this->assertSame([$aqua->id, $vitz->id], $this->search('toyota'));
        $this->assertSame([$aqua->id, $vitz->id], $this->search('Toyota HYBRID'), 'Case-insensitive, description counts');
        $this->assertSame([$aqua->id], $this->search('aqua toyota'));
        $this->assertSame([$aqua->id], $this->search('toyo aqu'), 'Word prefixes');
    }

    public function test_short_words_and_special_characters(): void
    {
        $tv = $this->ad('LG TV 55 inch smart');
        $this->ad('Samsung soundbar');

        $this->assertSame([$tv->id], $this->search('lg tv'), 'Words under 3 characters use LIKE');
        $this->assertSame([$tv->id], $this->search('"lg" +tv* (55)'), 'Operators stripped, never passed to SQL');
        $this->assertSame([], $this->search('100%_off'), 'LIKE wildcards are not wildcards');
        $this->assertCount(2, $this->search('!!!'), 'Nothing searchable: no keyword filter');
    }

    public function test_non_latin_scripts(): void
    {
        $sinhala = $this->ad('කාර් විකිණීමට ඇත Toyota');

        $this->assertSame([$sinhala->id], $this->search('කාර්'));
    }
}
