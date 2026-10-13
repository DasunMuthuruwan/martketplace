<?php

namespace App\Console\Commands\Dev;

use App\Enums\ListingStatus;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Country;
use App\Models\MeasurementUnit;
use App\Services\Money\AreaConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bulk data for the performance check (T5.4, architecture §4 validation plan): N listings across
 * four countries with regions, cities, sellers, a status mix and typed attribute values for the
 * main filters (car make/model/year/mileage, land size, phone brand). Never in production.
 * Run on a scratch database after `migrate:fresh --seed`.
 */
class SeedPerformanceData extends Command
{
    protected $signature = 'marketplace:perf-seed {--listings=50000} {--force : skip the confirmation}';

    protected $description = 'Insert bulk listings for the performance check (development only)';

    /** Share of listings per country. */
    private const COUNTRIES = ['LK' => 0.40, 'IN' => 0.25, 'GB' => 0.20, 'US' => 0.15];

    private const WORDS = ['excellent', 'condition', 'owner', 'service', 'history', 'brand', 'new', 'used', 'genuine', 'warranty', 'urgent',
        'sale', 'family', 'spacious', 'modern', 'quiet', 'garden', 'parking', 'clean', 'documents', 'negotiable', 'original', 'imported', 'local'];

    private const BATCH = 1000;

    public function handle(AreaConverter $converter): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }
        $database = (string) DB::connection()->getDatabaseName();
        if (! $this->option('force') && ! $this->confirm("Insert bulk test data into [{$database}]?")) {
            return self::FAILURE;
        }

        mt_srand(20261013); // repeatable data set
        $total = max(1, (int) $this->option('listings'));
        $countries = Country::whereIn('iso2', array_keys(self::COUNTRIES))->get()->keyBy('iso2');
        Country::whereIn('iso2', array_keys(self::COUNTRIES))->update(['is_active' => true]);

        $sellers = $this->sellers(1000);
        $attributes = $this->attributeData();
        $started = microtime(true);
        $inserted = 0;

        foreach (self::COUNTRIES as $iso2 => $share) {
            $country = $countries[$iso2];
            $places = $this->places($country);
            $leaves = Category::whereNotNull('parent_id')->where('is_active', true)->get()
                ->filter(fn (Category $c) => $c->isInScopeFor($iso2))->values();
            $count = $iso2 === array_key_last(self::COUNTRIES) ? $total - $inserted : (int) round($total * $share);

            for ($done = 0; $done < $count; $done += self::BATCH) {
                $rows = [];
                for ($i = 0; $i < min(self::BATCH, $count - $done); $i++) {
                    $rows[] = $this->listingRow($country, $leaves[mt_rand(0, $leaves->count() - 1)], $places[mt_rand(0, count($places) - 1)], $sellers);
                }
                DB::table('listings')->insert($rows);
                $this->attributeValues($rows, $attributes, $converter, $country);
                $inserted += count($rows);
            }
            $this->line("{$iso2}: {$count} listings");
        }

        DB::statement(DB::connection()->getDriverName() === 'sqlite' ? 'ANALYZE' : 'ANALYZE TABLE listings, listing_attribute_values');
        $this->info(sprintf('Inserted %d listings in %.1f s.', $inserted, microtime(true) - $started));

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function sellers(int $count): array
    {
        $password = Hash::make(Str::random(32));
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => "Seller {$i}", 'email' => "perf-seller-{$i}@example.com", 'password' => $password, 'email_verified_at' => now(),
                'status' => 'active', 'contact_visibility' => 'public', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        return DB::table('users')->where('email', 'like', 'perf-seller-%')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * 9 regions per country (LK: each with 3 districts), 20 cities per lowest level.
     *
     * @return list<array{0: int, 1: int|null, 2: int}> [region_id, subregion_id, city_id]
     */
    private function places(Country $country): array
    {
        $places = [];
        for ($r = 1; $r <= 9; $r++) {
            $regionId = DB::table('regions')->insertGetId(['country_id' => $country->id, 'level' => 1, 'parent_id' => null,
                'name' => json_encode(['en' => "{$country->iso2} Region {$r}"]), 'slug' => strtolower($country->iso2)."-region-{$r}", 'is_active' => true]);
            $parents = $country->region_levels >= 2
                ? array_map(fn (int $d) => DB::table('regions')->insertGetId(['country_id' => $country->id, 'level' => 2, 'parent_id' => $regionId,
                    'name' => json_encode(['en' => "District {$r}-{$d}"]), 'slug' => strtolower($country->iso2)."-district-{$r}-{$d}", 'is_active' => true]), [1, 2, 3])
                : [null];

            foreach ($parents as $subregionId) {
                for ($c = 1; $c <= 20; $c++) {
                    $slug = strtolower($country->iso2)."-city-{$r}-".($subregionId ?? 0)."-{$c}";
                    $cityId = DB::table('cities')->insertGetId(['country_id' => $country->id, 'region_id' => $subregionId ?? $regionId,
                        'name' => json_encode(['en' => Str::headline($slug)]), 'ascii_name' => $slug, 'slug' => $slug, 'is_active' => true]);
                    $places[] = [$regionId, $subregionId, $cityId];
                }
            }
        }

        return $places;
    }

    /**
     * @param  array{0: int, 1: int|null, 2: int}  $place
     * @param  list<int>  $sellers
     * @return array<string, mixed>
     */
    private function listingRow(Country $country, Category $category, array $place, array $sellers): array
    {
        $roll = mt_rand(1, 100);
        $status = match (true) {
            $roll <= 85 => ListingStatus::Active,
            $roll <= 90 => ListingStatus::PendingReview,
            $roll <= 95 => ListingStatus::Sold,
            default => ListingStatus::Expired,
        };
        $published = now()->subMinutes(mt_rand(0, 60 * 24 * 58));
        $words = array_map(fn () => self::WORDS[mt_rand(0, count(self::WORDS) - 1)], range(1, 30));
        $title = Str::limit($category->getTranslation('name', 'en').' '.implode(' ', array_slice($words, 0, 5)), 100, '');

        return [
            'user_id' => $sellers[mt_rand(0, count($sellers) - 1)],
            'category_id' => $category->id,
            'type' => $category->allowed_types[0],
            'status' => $status->value,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => ucfirst(implode(' ', $words)).'.',
            'price' => mt_rand(1, 20) === 1 ? null : (string) (mt_rand(10, 5000) * 1000),
            'currency' => $country->default_currency,
            'price_type' => 'negotiable',
            'condition' => $category->show_condition ? 'used' : null,
            'country_id' => $country->id,
            'region_id' => $place[0],
            'subregion_id' => $place[1],
            'city_id' => $place[2],
            'published_at' => $status === ListingStatus::PendingReview ? null : $published,
            'submitted_at' => $published,
            'expires_at' => $status === ListingStatus::Expired ? $published->copy()->subDay() : $published->copy()->addDays(60),
            'created_at' => $published,
            'updated_at' => $published,
        ];
    }

    /** @return array<string, mixed> attribute ids and option ids used for values */
    private function attributeData(): array
    {
        $attribute = fn (string $key) => Attribute::where('key', $key)->value('id');
        $models = AttributeOption::where('attribute_id', $attribute('model'))->get(['id', 'parent_option_id'])
            ->groupBy('parent_option_id')->map(fn ($group) => $group->pluck('id')->all())->all();

        return [
            'make' => $attribute('make'),
            'model' => $attribute('model'),
            'year' => $attribute('year_of_manufacture'),
            'mileage' => $attribute('mileage'),
            'land_size' => $attribute('land_size'),
            'phone_brand' => $attribute('phone_brand'),
            'models_by_make' => $models,
            'phone_brands' => AttributeOption::where('attribute_id', $attribute('phone_brand'))->pluck('id')->all(),
            'categories' => Category::whereIn('slug', ['cars', 'suvs-jeeps', 'vans', 'land', 'mobile-phones'])->pluck('slug', 'id')->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  the listings just inserted
     * @param  array<string, mixed>  $a
     */
    private function attributeValues(array $rows, array $a, AreaConverter $converter, Country $country): void
    {
        $ids = DB::table('listings')->orderByDesc('id')->limit(count($rows))->pluck('category_id', 'id')->all();
        $unit = MeasurementUnit::whereIn('id', $country->areaUnits()->pluck('measurement_units.id'))->first()
            ?? MeasurementUnit::where('code', 'sqm')->first();
        $values = [];

        foreach ($ids as $listingId => $categoryId) {
            $slug = $a['categories'][$categoryId] ?? null;
            if (in_array($slug, ['cars', 'suvs-jeeps', 'vans'], true)) {
                $makeId = array_rand($a['models_by_make']);
                $models = $a['models_by_make'][$makeId];
                $values[] = ['listing_id' => $listingId, 'attribute_id' => $a['make'], 'value_option_id' => $makeId];
                $values[] = ['listing_id' => $listingId, 'attribute_id' => $a['model'], 'value_option_id' => $models[array_rand($models)]];
                $values[] = ['listing_id' => $listingId, 'attribute_id' => $a['year'], 'value_number' => (string) mt_rand(1995, 2025)];
                $values[] = ['listing_id' => $listingId, 'attribute_id' => $a['mileage'], 'value_number' => (string) mt_rand(0, 300000)];
            } elseif ($slug === 'land' && $unit !== null) {
                $entered = (string) mt_rand(5, 400);
                $values[] = ['listing_id' => $listingId, 'attribute_id' => $a['land_size'], 'input_value' => $entered,
                    'input_unit_id' => $unit->id, 'value_number' => $converter->toBase($entered, $unit)];
            } elseif ($slug === 'mobile-phones') {
                $values[] = ['listing_id' => $listingId, 'attribute_id' => $a['phone_brand'], 'value_option_id' => $a['phone_brands'][array_rand($a['phone_brands'])]];
            }
        }

        foreach (array_chunk($values, 2000) as $chunk) {
            DB::table('listing_attribute_values')->insert(array_map(fn (array $row) => $row + [
                'value_number' => null, 'value_option_id' => null, 'value_text' => null, 'value_bool' => null, 'input_value' => null, 'input_unit_id' => null,
            ], $chunk));
        }
    }
}
