<?php

namespace Tests\Feature\Geo;

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GeoNamesImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtures;

    private Country $lk;

    private Country $us;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = base_path('tests/Fixtures/geonames');
        $this->lk = Country::factory()->twoLevels()->create(['iso2' => 'LK', 'iso3' => 'LKA']);
        $this->us = Country::factory()->create(['iso2' => 'US', 'iso3' => 'USA']);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    /** Copies the fixtures to a temp folder so a test can edit them. */
    private function editableFixtures(): string
    {
        $dir = storage_path('framework/testing/geonames-'.uniqid());
        File::copyDirectory($this->fixtures, $dir);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function replaceInFile(string $path, string $search, string $replace): void
    {
        file_put_contents($path, str_replace($search, $replace, (string) file_get_contents($path)));
    }

    private function removeLineContaining(string $path, string $needle): void
    {
        $lines = array_filter(file($path), fn (string $line) => ! str_contains($line, $needle));
        file_put_contents($path, implode('', $lines));
    }

    private function importLkRegions(?string $path = null, array $extra = []): void
    {
        $this->artisan('geo:import-regions', ['country' => 'LK', '--path' => $path ?? $this->fixtures] + $extra)->assertSuccessful();
    }

    // ---- Regions ---------------------------------------------------------------------------

    public function test_two_level_country_imports_provinces_and_districts_with_parents(): void
    {
        $this->importLkRegions();

        $this->assertSame(2, Region::where('country_id', $this->lk->id)->where('level', 1)->count());
        $this->assertSame(3, Region::where('country_id', $this->lk->id)->where('level', 2)->count());

        $western = Region::where('code', 'LK.36')->firstOrFail();
        $colombo = Region::where('code', 'LK.36.11')->firstOrFail();

        $this->assertSame('Western Province', $western->name);
        $this->assertSame('western-province', $western->slug);
        $this->assertSame(1223421, $western->geonames_id);
        $this->assertSame($western->id, $colombo->parent_id);
        $this->assertSame('colombo-district', $colombo->slug);
        $this->assertDatabaseMissing('regions', ['code' => 'LK.99.01']); // orphan district without parent is skipped
    }

    public function test_one_level_country_imports_only_level_one(): void
    {
        $this->artisan('geo:import-regions', ['country' => 'US', '--path' => $this->fixtures])->assertSuccessful();

        $this->assertSame(['California', 'Texas'], Region::where('country_id', $this->us->id)->orderBy('code')->get()->pluck('name')->all());
        $this->assertSame(0, Region::where('country_id', $this->us->id)->where('level', 2)->count());
    }

    public function test_region_reimport_is_idempotent(): void
    {
        $this->importLkRegions();
        $before = Region::orderBy('id')->get(['id', 'slug', 'parent_id'])->toArray();

        $this->artisan('geo:import-regions', ['country' => 'LK', '--path' => $this->fixtures])
            ->expectsTable(['created', 'updated', 'unchanged', 'deactivated', 'skipped', 'filtered'], [[0, 0, 5, 0, 1, 0]])
            ->assertSuccessful();

        $this->assertSame($before, Region::orderBy('id')->get(['id', 'slug', 'parent_id'])->toArray());
    }

    public function test_renamed_region_keeps_its_id_and_slug(): void
    {
        $this->importLkRegions();
        $western = Region::where('code', 'LK.36')->firstOrFail();

        $dir = $this->editableFixtures();
        $this->replaceInFile("{$dir}/admin1CodesASCII.txt", "Western Province\tWestern Province", "Western\tWestern");
        $this->importLkRegions($dir);

        $renamed = Region::findOrFail($western->id);
        $this->assertSame('Western', $renamed->name);
        $this->assertSame('western-province', $renamed->slug, 'URLs stay stable after a rename');
    }

    public function test_regions_missing_from_geonames_are_deactivated_only_on_request_and_never_deleted(): void
    {
        $this->importLkRegions();
        $dir = $this->editableFixtures();
        $this->removeLineContaining("{$dir}/admin1CodesASCII.txt", 'LK.29');

        $this->importLkRegions($dir);
        $this->assertTrue(Region::where('code', 'LK.29')->firstOrFail()->is_active, 'Not deactivated without the flag');

        $this->importLkRegions($dir, ['--deactivate-missing' => true]);
        $central = Region::where('code', 'LK.29')->firstOrFail();
        $this->assertFalse($central->is_active);
        $this->assertTrue(Region::where('code', 'LK.36')->firstOrFail()->is_active);
    }

    // ---- Cities ----------------------------------------------------------------------------

    private function importLkCities(?string $path = null, array $extra = []): void
    {
        $this->artisan('geo:import-cities', ['country' => 'LK', '--file' => 'cities.txt', '--path' => $path ?? $this->fixtures] + $extra)
            ->assertSuccessful();
    }

    public function test_cities_are_imported_into_the_deepest_region_with_filters_applied(): void
    {
        $this->importLkRegions();

        $this->artisan('geo:import-cities', ['country' => 'LK', '--file' => 'cities.txt', '--path' => $this->fixtures])
            ->expectsTable(['created', 'updated', 'unchanged', 'deactivated', 'skipped', 'filtered'], [[7, 0, 0, 0, 1, 2]])
            ->assertSuccessful();

        $colombo = City::where('geonames_id', 1248991)->firstOrFail();
        $this->assertSame('Colombo', $colombo->name);
        $this->assertSame(Region::where('code', 'LK.36.11')->value('id'), $colombo->region_id);
        $this->assertSame($this->lk->id, $colombo->country_id);
        $this->assertSame(648034, $colombo->population);
        $this->assertSame('6.931940', $colombo->latitude);
        $this->assertFalse($colombo->is_curated);

        $this->assertNull(City::where('geonames_id', 1232999)->value('population'), 'Population 0 is stored as unknown');
        $this->assertDatabaseMissing('cities', ['geonames_id' => 1100001]); // historical place (PPLH)
        $this->assertDatabaseMissing('cities', ['geonames_id' => 1100002]); // section of a town (PPLX)
        $this->assertDatabaseMissing('cities', ['geonames_id' => 1100003]); // unknown district
        $this->assertDatabaseMissing('cities', ['geonames_id' => 1100004]); // not a populated place
        $this->assertSame(0, City::where('country_id', $this->us->id)->count(), 'Other countries in the file are ignored');
    }

    public function test_ascii_name_falls_back_to_transliteration(): void
    {
        $this->importLkRegions();
        $this->importLkCities();

        $city = City::where('geonames_id', 1100005)->firstOrFail();
        $this->assertSame('Bēruwala', $city->name);
        $this->assertSame('Beruwala', $city->ascii_name);
        $this->assertSame('beruwala', $city->slug);
    }

    public function test_duplicate_names_in_one_region_get_unique_slugs(): void
    {
        $this->importLkRegions();
        $this->importLkCities();

        $this->assertSame(['kottawa', 'kottawa-2'], City::where('ascii_name', 'Kottawa')->orderBy('geonames_id')->pluck('slug')->all());
    }

    public function test_minimum_population_filters_small_places(): void
    {
        $this->importLkRegions();
        $this->importLkCities(null, ['--min-population' => 100000]);

        $this->assertSame(['Colombo', 'Kandy', 'Maharagama'], City::orderBy('ascii_name')->get()->pluck('name')->all());
    }

    public function test_curated_city_keeps_its_name_and_region_on_reimport(): void
    {
        $this->importLkRegions();
        $this->importLkCities();

        $gampaha = Region::where('code', 'LK.36.12')->value('id');
        $colombo = City::where('geonames_id', 1248991)->firstOrFail();
        $colombo->setTranslation('name', 'en', 'Colombo City');
        $colombo->region_id = $gampaha;
        $colombo->is_curated = true;
        $colombo->save();

        $dir = $this->editableFixtures();
        $this->replaceInFile("{$dir}/cities.txt", '648034', '650000');
        $this->importLkCities($dir);

        $colombo->refresh();
        $this->assertSame('Colombo City', $colombo->name, 'Curated name kept');
        $this->assertSame($gampaha, $colombo->region_id, 'Curated region kept');
        $this->assertSame(650000, $colombo->population, 'Population still refreshed');
    }

    public function test_renamed_city_keeps_its_id_and_slug(): void
    {
        $this->importLkRegions();
        $this->importLkCities();
        $city = City::where('geonames_id', 1235846)->firstOrFail();

        $dir = $this->editableFixtures();
        $this->replaceInFile("{$dir}/cities.txt", "Maharagama\tMaharagama", "Maharagama Town\tMaharagama Town");
        $this->importLkCities($dir);

        $city->refresh();
        $this->assertSame('Maharagama Town', $city->name);
        $this->assertSame('maharagama', $city->slug);
    }

    public function test_only_cities_absent_from_the_file_are_deactivated(): void
    {
        $this->importLkRegions();
        $this->importLkCities();

        $dir = $this->editableFixtures();
        $this->removeLineContaining("{$dir}/cities.txt", 'Maharagama');
        $this->importLkCities($dir, ['--deactivate-missing' => true, '--min-population' => 100000]);

        $this->assertFalse(City::where('geonames_id', 1235846)->firstOrFail()->is_active, 'Removed from GeoNames');
        $this->assertTrue(City::where('geonames_id', 1232999)->firstOrFail()->is_active, 'Filtered by population, but still in the file');
        $this->assertSame(7, City::count(), 'Nothing is deleted');
    }

    public function test_cities_imported_before_regions_are_skipped_with_a_warning(): void
    {
        $this->artisan('geo:import-cities', ['country' => 'LK', '--file' => 'cities.txt', '--path' => $this->fixtures])
            ->expectsOutputToContain('run geo:import-regions LK first')
            ->assertSuccessful();

        $this->assertSame(0, City::count());
    }

    // ---- Command errors --------------------------------------------------------------------

    public function test_unknown_country_fails(): void
    {
        $this->artisan('geo:import-regions', ['country' => 'ZZ', '--path' => $this->fixtures])
            ->expectsOutputToContain('Unknown country code [ZZ]')
            ->assertFailed();
    }

    public function test_missing_file_fails_with_download_hint(): void
    {
        $this->artisan('geo:import-cities', ['country' => 'LK', '--file' => 'nope.txt', '--path' => $this->fixtures])
            ->expectsOutputToContain('GeoNames file not found')
            ->assertFailed();
    }

    public function test_invalid_minimum_population_fails(): void
    {
        $this->artisan('geo:import-cities', ['country' => 'LK', '--file' => 'cities.txt', '--path' => $this->fixtures, '--min-population' => 'lots'])
            ->assertFailed();
    }

    // ---- Countries -------------------------------------------------------------------------

    public function test_country_import_sync_updates_reference_fields_only(): void
    {
        Country::query()->delete();
        Currency::query()->delete();

        $this->artisan('geo:import-countries')->assertSuccessful();
        $this->assertSame(250, Country::count());

        $lk = Country::where('iso2', 'LK')->firstOrFail();
        $lk->update(['is_active' => true, 'name' => ['en' => 'Ceylon']]);

        $this->artisan('geo:import-countries')->assertSuccessful();
        $this->assertSame('Ceylon', $lk->fresh()->name, 'Without --sync existing rows are untouched');

        $this->artisan('geo:import-countries', ['--sync' => true])->assertSuccessful();
        $lk->refresh();
        $this->assertSame('Sri Lanka', $lk->name);
        $this->assertTrue($lk->is_active, 'Activation is never changed by an import');
        $this->assertSame(250, Country::count());
    }
}
