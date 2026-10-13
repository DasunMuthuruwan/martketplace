<?php

namespace App\Services\Seo;

use App\Models\Country;
use App\Models\Listing;
use App\Models\Region;
use App\Services\Catalogue\CategoryTree;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;

/**
 * Static XML sitemaps (SEO-03, architecture §8, package assessment: no crawler package):
 *   sitemap.xml                      index of every file below
 *   sitemaps/{cc}-pages.xml          country home, all ads, category and region landing pages
 *   sitemaps/{cc}-ads-{n}.xml        public listings, at most `seo.sitemap_chunk` URLs per file (10k)
 * Only active countries and public listings (the same public scope as the site). Every URL is
 * built from APP_URL (P3), so after a domain change one run rewrites them all. Files are written
 * to a temporary name and renamed, and files from earlier runs that are no longer produced are
 * removed.
 */
class SitemapBuilder
{
    public const DIRECTORY = 'sitemaps';

    public function __construct(private readonly CategoryTree $tree) {}

    /** @return list<string> written file names, relative to the public directory */
    public function build(?string $root = null): array
    {
        $root = rtrim($root ?? (string) config('marketplace.seo.sitemap_root'), '/\\');
        File::ensureDirectoryExists($root.'/'.self::DIRECTORY);

        $files = [];
        foreach (Country::active()->orderBy('iso2')->get() as $country) {
            $cc = strtolower($country->iso2);
            $files[self::DIRECTORY."/{$cc}-pages.xml"] = $this->write($root, self::DIRECTORY."/{$cc}-pages.xml", $this->urlset($this->pages($country)));

            $chunk = 1;
            Listing::public()->where('listings.country_id', $country->id)->select(['listings.id', 'listings.slug', 'listings.updated_at'])
                ->orderBy('listings.id')
                ->chunkById(max(1, (int) config('marketplace.seo.sitemap_chunk')), function ($listings) use ($root, $cc, &$chunk, &$files): void {
                    $urls = $listings->map(fn (Listing $listing) => [$listing->publicUrl(), $listing->updated_at])->values()->all();
                    $name = self::DIRECTORY."/{$cc}-ads-{$chunk}.xml";
                    $files[$name] = $this->write($root, $name, $this->urlset($urls));
                    $chunk++;
                }, 'listings.id', 'id');
        }

        $this->write($root, 'sitemap.xml', $this->index($files));
        $this->removeStale($root, array_keys($files));

        return ['sitemap.xml', ...array_keys($files)];
    }

    /** @return list<array{0: string, 1: CarbonInterface|null}> */
    private function pages(Country $country): array
    {
        $cc = strtolower($country->iso2);
        $urls = [[route('country.home', ['cc' => $cc]), null], [route('ads.country', ['cc' => $cc]), null]];

        foreach ($this->tree->forCountry($country->iso2) as $category) {
            $urls[] = [route('ads.category', ['cc' => $cc, 'category' => $category->slug]), null];
            foreach ($category->children as $child) {
                $urls[] = [route('ads.category', ['cc' => $cc, 'category' => $category->slug, 'subcategory' => $child->slug]), null];
            }
        }

        // Location landing pages that have ads (level 1 and 2 regions).
        $regionIds = Listing::public()->where('listings.country_id', $country->id)
            ->select('listings.region_id', 'listings.subregion_id')->distinct()->get()
            ->flatMap(fn ($row) => [$row->region_id, $row->subregion_id])->filter()->unique()->all();
        foreach (Region::whereIn('id', $regionIds)->where('is_active', true)->orderBy('level')->orderBy('slug')->get() as $region) {
            $urls[] = [route('ads.location', ['cc' => $cc, 'region' => $region->slug]), null];
        }

        return $urls;
    }

    /** @param  list<array{0: string, 1: CarbonInterface|null}>  $urls */
    private function urlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as [$loc, $modified]) {
            $xml .= '  <url><loc>'.$this->escape($loc).'</loc>'.($modified ? '<lastmod>'.$modified->toAtomString().'</lastmod>' : '')."</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /** @param  array<string, string>  $files  name => written path */
    private function index(array $files): string
    {
        $now = now()->toAtomString();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (array_keys($files) as $name) {
            $xml .= '  <sitemap><loc>'.$this->escape(url($name)).'</loc><lastmod>'.$now."</lastmod></sitemap>\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    private function write(string $root, string $name, string $contents): string
    {
        $path = $root.'/'.$name;
        $temporary = $path.'.tmp';
        File::put($temporary, $contents);
        File::move($temporary, $path);

        return $path;
    }

    /** @param  list<string>  $keep */
    private function removeStale(string $root, array $keep): void
    {
        foreach (File::glob($root.'/'.self::DIRECTORY.'/*.xml') as $path) {
            if (! in_array(self::DIRECTORY.'/'.basename($path), $keep, true)) {
                File::delete($path);
            }
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
