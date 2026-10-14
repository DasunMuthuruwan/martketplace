<?php

namespace App\Actions\Locations;

use App\Enums\LocationRequestStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Locality;
use App\Models\LocationRequest;
use App\Models\Region;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Geo\GeoNames\UniqueSlug;
use App\Services\Geo\RegionChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "My town is not listed" requests (GLB-16, global design §4.3): map to an existing place, approve
 * (create the city and/or locality, curated so imports leave it alone) or reject. Each request is
 * handled once; every decision is audited.
 */
class ResolveLocationRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function map(User $actor, LocationRequest $request, City $city, ?Locality $locality, ?string $note): void
    {
        $this->guardPending($request);
        if ($city->country_id !== $request->country_id || ($locality !== null && $locality->city_id !== $city->id)) {
            throw ValidationException::withMessages(['mapCity' => __('locations.requests.wrong_place')]);
        }

        $this->close($actor, $request, LocationRequestStatus::Mapped, $city->id, $locality?->id, $note);
    }

    /** Create the proposed city (in the request's region) and/or locality, with names the staff member may correct. */
    public function approve(User $actor, LocationRequest $request, ?string $cityName, ?string $localityName, ?string $note): void
    {
        $this->guardPending($request);
        $cityName = $this->clean($cityName);
        $localityName = $this->clean($localityName);

        DB::transaction(function () use ($actor, $request, $cityName, $localityName, $note): void {
            $city = $request->city_id === null ? null : City::find($request->city_id);
            if ($cityName !== null) {
                $city = $this->createCity($request, $cityName);
            }
            if ($city === null) {
                throw ValidationException::withMessages(['approveCity' => __('locations.requests.city_needed')]);
            }

            $locality = $localityName === null ? null : $this->createLocality($city, $localityName);
            if ($cityName === null && $locality === null) {
                throw ValidationException::withMessages(['approveCity' => __('locations.requests.nothing_to_create')]);
            }

            $this->close($actor, $request, LocationRequestStatus::Approved, $city->id, $locality?->id, $note);
        });
    }

    public function reject(User $actor, LocationRequest $request, string $note): void
    {
        $this->guardPending($request);
        $this->close($actor, $request, LocationRequestStatus::Rejected, null, null, $note);
    }

    private function createCity(LocationRequest $request, string $name): City
    {
        $country = Country::findOrFail($request->country_id);
        $level = RegionChain::cityLevel($country);
        $regionId = $level === 2 ? $request->subregion_id : ($level === 1 ? $request->region_id : null);
        $region = $regionId === null ? null : Region::find($regionId);
        if ($level !== null && ($region === null || $region->country_id !== $country->id || $region->level !== $level)) {
            throw ValidationException::withMessages(['approveCity' => __('locations.requests.region_needed', ['label' => $country->label($level === 2 ? 'subregion' : 'region')])]);
        }

        $existing = City::where('country_id', $country->id)->where('region_id', $region?->id)->get();
        if ($existing->contains(fn (City $c) => Str::lower((string) $c->getTranslation('name', 'en', true)) === Str::lower($name))) {
            throw ValidationException::withMessages(['approveCity' => __('locations.requests.city_exists')]);
        }
        $taken = $existing->pluck('slug')->flip();

        return City::create([
            'country_id' => $country->id,
            'region_id' => $region?->id,
            'name' => ['en' => $name],
            'ascii_name' => Str::ascii($name),
            'slug' => UniqueSlug::make($name, fn (string $slug) => $taken->has($slug)),
            'is_active' => true,
            'is_curated' => true,
        ]);
    }

    private function createLocality(City $city, string $name): Locality
    {
        $existing = Locality::where('city_id', $city->id)->get();
        if ($existing->contains(fn (Locality $l) => Str::lower((string) $l->getTranslation('name', 'en', true)) === Str::lower($name))) {
            throw ValidationException::withMessages(['approveLocality' => __('locations.requests.locality_exists')]);
        }
        $taken = $existing->pluck('slug')->flip();

        return Locality::create([
            'city_id' => $city->id,
            'name' => ['en' => $name],
            'slug' => UniqueSlug::make($name, fn (string $slug) => $taken->has($slug)),
            'is_active' => true,
        ]);
    }

    private function close(User $actor, LocationRequest $request, LocationRequestStatus $status, ?int $cityId, ?int $localityId, ?string $note): void
    {
        $request->forceFill([
            'status' => $status,
            'resolved_city_id' => $cityId,
            'resolved_locality_id' => $localityId,
            'handled_by' => $actor->id,
            'handled_at' => now(),
            'resolution_note' => $this->clean($note),
        ])->save();

        $this->audit->record($actor, 'location_request.'.$status->value, $request, $this->clean($note), [
            'city_id' => $cityId, 'locality_id' => $localityId,
        ]);
    }

    private function guardPending(LocationRequest $request): void
    {
        if ($request->status !== LocationRequestStatus::Pending) {
            throw ValidationException::withMessages(['request' => __('locations.requests.already_handled')]);
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 500, '');
    }
}
