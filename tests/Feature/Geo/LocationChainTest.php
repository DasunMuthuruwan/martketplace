<?php

namespace Tests\Feature\Geo;

use App\Models\City;
use App\Models\Country;
use App\Models\Locality;
use App\Models\Region;
use App\Validation\LocationChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocationChainTest extends TestCase
{
    use RefreshDatabase;

    private Country $twoLevel;     // province → district → town (e.g. Sri Lanka)

    private Region $province;

    private Region $district;

    private City $town;

    private Locality $area;

    private Country $oneLevel;     // state → city (e.g. United States)

    private Region $state;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twoLevel = Country::factory()->twoLevels()
            ->withLabels(['region' => 'Province', 'subregion' => 'District', 'city' => 'City or town', 'locality' => 'Area'])
            ->create(['name' => ['en' => 'Twoland']]);
        $this->province = Region::factory()->create(['country_id' => $this->twoLevel->id]);
        $this->district = Region::factory()->childOf($this->province)->create();
        $this->town = City::factory()->inRegion($this->district)->create();
        $this->area = Locality::factory()->create(['city_id' => $this->town->id]);

        $this->oneLevel = Country::factory()
            ->withLabels(['region' => 'State', 'city' => 'City', 'locality' => 'Neighborhood'])
            ->create(['name' => ['en' => 'Oneland']]);
        $this->state = Region::factory()->create(['country_id' => $this->oneLevel->id]);
        $this->city = City::factory()->inRegion($this->state)->create();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function errorsFor(array $input): array
    {
        $validator = Validator::make($input, [])->after(new LocationChain);
        $validator->passes();

        return collect($validator->errors()->messages())->map(fn (array $m) => $m[0])->all();
    }

    /** @return array<string, int> */
    private function validTwoLevel(): array
    {
        return [
            'country_id' => $this->twoLevel->id,
            'region_id' => $this->province->id,
            'subregion_id' => $this->district->id,
            'city_id' => $this->town->id,
            'locality_id' => $this->area->id,
        ];
    }

    public function test_valid_two_level_chain_passes(): void
    {
        $this->assertSame([], $this->errorsFor($this->validTwoLevel()));
    }

    public function test_valid_one_level_chain_passes(): void
    {
        $this->assertSame([], $this->errorsFor([
            'country_id' => $this->oneLevel->id,
            'region_id' => $this->state->id,
            'city_id' => $this->city->id,
        ]));
    }

    public function test_numeric_strings_from_forms_are_accepted(): void
    {
        $this->assertSame([], $this->errorsFor(array_map('strval', $this->validTwoLevel())));
    }

    public function test_country_is_required(): void
    {
        $this->assertSame(['country_id' => 'Select the country.'], $this->errorsFor([]));
    }

    public function test_inactive_or_unknown_country_is_rejected(): void
    {
        $inactive = Country::factory()->inactive()->create();

        $this->assertArrayHasKey('country_id', $this->errorsFor(['country_id' => $inactive->id]));
        $this->assertArrayHasKey('country_id', $this->errorsFor(['country_id' => 999999]));
    }

    public function test_region_from_another_country_is_rejected(): void
    {
        $errors = $this->errorsFor(['region_id' => $this->state->id] + $this->validTwoLevel());

        $this->assertSame(['region_id' => __('geo.validation.invalid')], $errors);
    }

    public function test_district_that_is_not_in_the_selected_province_is_rejected(): void
    {
        $otherProvince = Region::factory()->create(['country_id' => $this->twoLevel->id]);
        $otherDistrict = Region::factory()->childOf($otherProvince)->create();

        $errors = $this->errorsFor(['subregion_id' => $otherDistrict->id] + $this->validTwoLevel());

        $this->assertSame(['subregion_id' => 'The selected district is not in this province.'], $errors);
    }

    public function test_level_one_region_cannot_be_used_as_subregion(): void
    {
        $errors = $this->errorsFor(['subregion_id' => $this->province->id] + $this->validTwoLevel());

        $this->assertSame(['subregion_id' => __('geo.validation.invalid')], $errors);
    }

    public function test_city_outside_the_selected_district_is_rejected(): void
    {
        $otherDistrict = Region::factory()->childOf($this->province)->create();
        $otherTown = City::factory()->inRegion($otherDistrict)->create();

        $errors = $this->errorsFor(['city_id' => $otherTown->id, 'locality_id' => null] + $this->validTwoLevel());

        $this->assertSame(['city_id' => 'The selected city or town is not in this district.'], $errors);
    }

    public function test_city_from_another_country_is_rejected(): void
    {
        $errors = $this->errorsFor(['city_id' => $this->city->id, 'locality_id' => null] + $this->validTwoLevel());

        $this->assertSame(['city_id' => __('geo.validation.invalid')], $errors);
    }

    public function test_locality_from_another_city_is_rejected(): void
    {
        $otherTown = City::factory()->inRegion($this->district)->create();
        $otherArea = Locality::factory()->create(['city_id' => $otherTown->id]);

        $errors = $this->errorsFor(['locality_id' => $otherArea->id] + $this->validTwoLevel());

        $this->assertSame(['locality_id' => 'The selected area is not in this city or town.'], $errors);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function inactivePlaces(): array
    {
        return [
            'province' => ['province', 'region_id'],
            'district' => ['district', 'subregion_id'],
            'town' => ['town', 'city_id'],
            'area' => ['area', 'locality_id'],
        ];
    }

    #[DataProvider('inactivePlaces')]
    public function test_inactive_places_are_rejected_on_their_own_field(string $property, string $field): void
    {
        $this->{$property}->update(['is_active' => false]);

        $errors = $this->errorsFor($this->validTwoLevel());

        $this->assertArrayHasKey($field, $errors);
        $this->assertStringContainsString('no longer available', $errors[$field]);
    }

    public function test_required_levels_use_the_country_terms(): void
    {
        $this->assertSame(
            ['region_id' => 'Select the province.', 'city_id' => 'Select the city or town.'],
            $this->errorsFor(['country_id' => $this->twoLevel->id]),
        );

        $this->assertSame(
            ['subregion_id' => 'Select the district.', 'city_id' => 'Select the city or town.'],
            $this->errorsFor(['country_id' => $this->twoLevel->id, 'region_id' => $this->province->id]),
        );

        $this->assertSame(
            ['region_id' => 'Select the state.', 'city_id' => 'Select the city.'],
            $this->errorsFor(['country_id' => $this->oneLevel->id]),
        );
    }

    public function test_country_with_incomplete_data_allows_missing_region_and_city(): void
    {
        $country = Country::factory()->optionalLocation()->create();

        $this->assertSame([], $this->errorsFor(['country_id' => $country->id]));
    }

    public function test_child_without_parent_is_rejected_even_when_levels_are_optional(): void
    {
        $country = Country::factory()->optionalLocation()->create();
        $region = Region::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->inRegion($region)->create();

        $errors = $this->errorsFor(['country_id' => $country->id, 'city_id' => $city->id]);

        $this->assertSame(['region_id' => 'Select the region.'], $errors);
    }

    public function test_locality_without_city_is_rejected(): void
    {
        $errors = $this->errorsFor(['city_id' => null] + $this->validTwoLevel());

        $this->assertSame(['city_id' => 'Select the city or town.'], $errors);
    }

    public function test_subregion_is_not_applicable_to_one_level_countries(): void
    {
        $errors = $this->errorsFor([
            'country_id' => $this->oneLevel->id,
            'region_id' => $this->state->id,
            'subregion_id' => $this->district->id,
            'city_id' => $this->city->id,
        ]);

        $this->assertSame(['subregion_id' => 'This field does not apply to Oneland.'], $errors);
    }

    public function test_country_without_regions_accepts_a_city_directly(): void
    {
        $cityState = Country::factory()->withoutRegions()->create(['name' => ['en' => 'Citystate']]);
        $city = City::factory()->inCountryWithoutRegions($cityState)->create();

        $this->assertSame([], $this->errorsFor(['country_id' => $cityState->id, 'city_id' => $city->id]));
        $this->assertSame(
            ['region_id' => 'This field does not apply to Citystate.'],
            $this->errorsFor(['country_id' => $cityState->id, 'region_id' => $this->state->id, 'city_id' => $city->id]),
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function malformedIds(): array
    {
        return ['negative' => [-5], 'zero' => [0], 'decimal string' => ['1.5'], 'text' => ['abc'], 'array' => [[1]], 'sql' => ['1 OR 1=1']];
    }

    #[DataProvider('malformedIds')]
    public function test_malformed_ids_are_rejected_without_querying(mixed $value): void
    {
        $errors = $this->errorsFor(['region_id' => $value] + $this->validTwoLevel());

        $this->assertSame(['region_id' => __('geo.validation.invalid')], $errors);
    }

    public function test_only_the_first_error_per_field_is_kept_when_other_rules_already_failed(): void
    {
        $validator = Validator::make(['country_id' => 'x'], ['country_id' => 'integer'])->after(new LocationChain);

        $this->assertFalse($validator->passes());
        $this->assertCount(1, $validator->errors()->get('country_id'));
    }

    public function test_custom_field_names_are_supported(): void
    {
        $chain = new LocationChain(['country' => 'loc_country', 'region' => 'loc_region', 'subregion' => 'loc_sub', 'city' => 'loc_city', 'locality' => 'loc_area']);

        $errors = $chain->check(['loc_country' => $this->oneLevel->id]);

        $this->assertSame(['loc_region' => 'Select the state.', 'loc_city' => 'Select the city.'], $errors);
    }
}
