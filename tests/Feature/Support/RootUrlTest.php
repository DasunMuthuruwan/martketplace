<?php

namespace Tests\Feature\Support;

use App\Support\RootUrl;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RootUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
        parent::tearDown();
    }

    public function test_generated_urls_use_app_url_even_without_a_request_host(): void
    {
        config(['app.url' => 'https://market.example.com/']);
        RootUrl::configure();

        $this->assertSame('https://market.example.com', url('/'));
        $this->assertSame('https://market.example.com', route('home'));
    }

    public function test_changing_app_url_changes_every_generated_url(): void
    {
        config(['app.url' => 'https://new-domain.example.org']);
        RootUrl::configure();

        $this->assertStringStartsWith('https://new-domain.example.org/', url('/ad/some-listing-1'));
    }

    public function test_production_forces_https(): void
    {
        config(['app.url' => 'http://market.example.com']);
        $this->app['env'] = 'production';

        RootUrl::configure();

        $this->assertStringStartsWith('https://', url('/'));
    }
}
