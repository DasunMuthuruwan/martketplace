<?php

namespace App\Actions\Locations;

use App\Models\City;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\LocationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Geo\RegionChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merge a duplicate city into the one to keep (ADM-03, GLB-16). In one transaction: listings
 * (including deleted ones) move to the kept city and take its region chain; localities move too,
 * or are folded into the kept city's locality with the same slug; location requests follow. The
 * duplicate is then switched off, never deleted while it has a GeoNames id (a re-import would
 * otherwise create it again); a manually added duplicate with nothing left is deleted.
 */
class MergeCities
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array{listings: int, localities: int} */
    public function handle(User $actor, City $duplicate, City $keep): array
    {
        if ($duplicate->is($keep) || $duplicate->country_id !== $keep->country_id) {
            throw ValidationException::withMessages(['mergeInto' => __('locations.merge_invalid')]);
        }

        return DB::transaction(function () use ($actor, $duplicate, $keep): array {
            $chain = RegionChain::forCity($keep);
            $moved = Listing::withTrashed()->where('city_id', $duplicate->id)->update(['city_id' => $keep->id] + $chain);

            $localities = 0;
            foreach (Locality::where('city_id', $duplicate->id)->get() as $locality) {
                $same = Locality::where('city_id', $keep->id)->where('slug', $locality->slug)->first();
                if ($same === null) {
                    $locality->update(['city_id' => $keep->id]);
                } else {
                    Listing::withTrashed()->where('locality_id', $locality->id)->update(['locality_id' => $same->id]);
                    LocationRequest::where('resolved_locality_id', $locality->id)->update(['resolved_locality_id' => $same->id]);
                    $locality->delete();
                }
                $localities++;
            }

            LocationRequest::where('city_id', $duplicate->id)->update(['city_id' => $keep->id]);
            LocationRequest::where('resolved_city_id', $duplicate->id)->update(['resolved_city_id' => $keep->id]);

            if ($duplicate->geonames_id === null) {
                $duplicate->delete();
            } else {
                $duplicate->update(['is_active' => false, 'is_curated' => true]);
            }

            $this->audit->record($actor, 'city.merged', $keep, null, [
                'duplicate_id' => $duplicate->id,
                'duplicate' => $duplicate->getTranslation('name', 'en', true),
                'listings' => $moved,
                'localities' => $localities,
            ]);

            return ['listings' => $moved, 'localities' => $localities];
        });
    }
}
