<?php

namespace App\Console\Commands\Dev;

use App\Enums\ListingStatus;
use App\Models\Country;
use App\Models\Listing;
use App\Services\Money\AreaUnits;
use App\Services\Search\MysqlListingSearch;
use App\Services\Search\SearchCriteriaFactory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Runs the main listing queries (database design §4, global design §9) on the current database:
 * EXPLAIN (key used, rows examined) and timings (median and p95 of N runs, first page of 24 plus
 * the count, as the search page does). Use after `marketplace:perf-seed`. Development only.
 */
class PerformanceReport extends Command
{
    protected $signature = 'marketplace:perf-report {--runs=20}';

    protected $description = 'EXPLAIN and time the main listing queries (development only)';

    public function __construct(private readonly MysqlListingSearch $search)
    {
        parent::__construct();
    }

    public function handle(SearchCriteriaFactory $factory): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }

        $lk = Country::where('iso2', 'LK')->firstOrFail();
        $gb = Country::where('iso2', 'GB')->firstOrFail();
        $sellerId = (int) Listing::query()->value('user_id');
        $areaUnit = (string) app(AreaUnits::class)->defaultFor($lk)?->code; // the country's own unit, from data

        $searches = [
            'Country browse, newest (LK)' => [[], $lk],
            'Country + top category (LK vehicles)' => [['category' => 'vehicles'], $lk],
            'Country + leaf category (LK cars)' => [['category' => 'cars'], $lk],
            'Region (LK region 3)' => [['region' => 'lk-region-3'], $lk],
            'City (LK)' => [['city' => (string) DB::table('cities')->where('country_id', $lk->id)->orderBy('id')->skip(45)->value('slug')], $lk],
            'Price range + sort (GB, GBP)' => [['min_price' => '100000', 'max_price' => '900000', 'sort' => 'price_asc'], $gb],
            'Cars: make + year range (LK)' => [['category' => 'cars', 'a' => ['make' => 'toyota', 'year_of_manufacture' => ['min' => '2015']]], $lk],
            'Cars: make + model + mileage (LK)' => [['category' => 'cars', 'a' => ['make' => 'toyota', 'model' => 'aqua', 'mileage' => ['max' => '100000']]], $lk],
            'Land: area range (LK, default unit)' => [['category' => 'land', 'a' => ['land_size' => ['min' => '20', 'max' => '100', 'unit' => $areaUnit]]], $lk],
            'Keyword (LK "toyota excellent")' => [['q' => 'toyota excellent'], $lk],
            'Keyword + category (LK cars "owner")' => [['q' => 'owner', 'category' => 'cars'], $lk],
            'Global keyword ("garden")' => [['q' => 'garden'], null],
        ];

        $rows = [];
        foreach ($searches as $name => [$input, $country]) {
            $query = $this->search->query($factory->fromInput($input, $country));
            $rows[] = $this->measure($name, $query, true);
        }

        $rows[] = $this->measure('Expiry job (active, expired)', Listing::query()->where('status', ListingStatus::Active->value)->where('expires_at', '<=', now())->orderBy('id'), false);
        $rows[] = $this->measure('My listings (one seller)', Listing::query()->where('user_id', $sellerId)->orderByDesc('updated_at'), false);
        $rows[] = $this->measure('Moderation queue', Listing::query()->where('status', ListingStatus::PendingReview->value)->orderBy('submitted_at'), false);

        $this->table(['Query', 'Rows est.', 'Count ms', 'Page ms', 'Median ms', 'p95 ms', 'Results'], array_map(fn (array $row) => array_merge([$row[0]], array_slice($row, 2)), $rows));
        $this->table(['Query', 'Access (table:key)'], array_map(fn (array $row) => [$row[0], $row[1]], $rows));
        $this->line('Listings: '.Listing::count().', attribute values: '.DB::table('listing_attribute_values')->count()
            .', driver: '.DB::connection()->getDriverName().' '.DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Listing>  $query
     * @return list<string|int>
     */
    private function measure(string $name, Builder $query, bool $paginated): array
    {
        $page = (clone $query)->forPage(1, (int) config('marketplace.listings.per_page'));
        $plan = DB::select('EXPLAIN '.$page->toRawSql());
        $access = collect($plan)->map(fn ($row) => ($row->table ?? '?').':'.($row->key ?? 'ALL'))->implode(' ');
        $estimate = (int) collect($plan)->sum(fn ($row) => (int) ($row->rows ?? 0));

        $count = [];
        $fetch = [];
        $total = [];
        $results = 0;
        for ($i = 0; $i < max(1, (int) $this->option('runs')); $i++) {
            $start = hrtime(true);
            $results = $paginated ? $this->search->total($query) : 0;
            $counted = hrtime(true);
            $items = (clone $page)->get(['listings.id']);
            $end = hrtime(true);
            $count[] = ($counted - $start) / 1e6;
            $fetch[] = ($end - $counted) / 1e6;
            $total[] = ($end - $start) / 1e6;
            $results = $paginated ? $results : $items->count();
        }

        return [
            $name,
            $access,
            $estimate,
            $paginated ? number_format($this->median($count), 1) : '–',
            number_format($this->median($fetch), 1),
            number_format($this->median($total), 1),
            number_format($this->percentile($total, 0.95), 1),
            $results,
        ];
    }

    /** @param  list<float>  $values */
    private function median(array $values): float
    {
        return $this->percentile($values, 0.5);
    }

    /** @param  list<float>  $values */
    private function percentile(array $values, float $p): float
    {
        sort($values);

        return $values[max(0, (int) ceil(count($values) * $p) - 1)];
    }
}
