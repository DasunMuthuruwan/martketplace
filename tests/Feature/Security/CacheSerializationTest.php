<?php

namespace Tests\Feature\Security;

use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Cached values survive a real cache store. Tests normally use the array store, which never
 * serializes, so a value that cannot be unserialized (cache.serializable_classes, Laravel 13
 * hardening) only failed on a file or database cache: the second request broke with
 * __PHP_Incomplete_Class. Each page is requested twice; the second answer comes from the cache.
 */
class CacheSerializationTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Country::where('iso2', 'LK')->update(['is_active' => true]);

        $this->path = storage_path('framework/testing/cache-'.uniqid());
        config(['cache.default' => 'file', 'cache.stores.file.path' => $this->path]);
        Cache::clearResolvedInstances();
        Cache::forgetDriver('file');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        parent::tearDown();
    }

    public function test_public_pages_render_again_from_a_file_cache(): void
    {
        $listing = Listing::factory()->inCountry(Country::where('iso2', 'LK')->first())->create();

        foreach (['/lk', '/countries', '/lk/ads?q=car', $listing->publicUrl()] as $path) {
            $this->get($path)->assertSuccessful();
            $this->get($path)->assertSuccessful();
        }
    }

    public function test_posting_form_renders_again_from_a_file_cache(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->inCountry(Country::where('iso2', 'LK')->first())->for($user)->draft()->create();

        $this->actingAs($user)->get(route('listings.create'))->assertOk();
        $this->actingAs($user)->get(route('listings.create'))->assertOk();
        $this->actingAs($user)->get(route('listings.edit', [$listing, 'details']))->assertOk();
        $this->actingAs($user)->get(route('listings.edit', [$listing, 'details']))->assertOk();
    }
}
