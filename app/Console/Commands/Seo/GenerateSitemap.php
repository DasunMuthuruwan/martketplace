<?php

namespace App\Console\Commands\Seo;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Console\Command;

/** Writes the static sitemaps (SEO-03). Scheduled daily; run once after a domain change (runbook). */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write sitemap.xml and the per-country sitemap files';

    public function handle(SitemapBuilder $builder): int
    {
        $files = $builder->build();
        $this->info('Sitemap files written: '.count($files).'.');

        return self::SUCCESS;
    }
}
