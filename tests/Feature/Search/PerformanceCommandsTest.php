<?php

namespace Tests\Feature\Search;

use App\Models\Listing;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The T5.4 tooling keeps working (smoke test on a small data set) and never runs in production. */
class PerformanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_and_report_on_a_small_data_set(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('marketplace:perf-seed', ['--listings' => 200, '--force' => true])->assertSuccessful();
        $this->assertSame(200, Listing::count());
        $this->assertGreaterThan(0, Listing::public()->count());

        $this->artisan('marketplace:perf-report', ['--runs' => 1])->expectsOutputToContain('Country browse, newest (LK)')->assertSuccessful();
    }

    public function test_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('marketplace:perf-seed', ['--force' => true])->assertFailed();
        $this->artisan('marketplace:perf-report')->assertFailed();
    }
}
