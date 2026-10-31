<?php

namespace App\Services\Banners;

use App\Enums\BannerPlacement;
use App\Models\Banner;
use App\Models\Country;

/** Which staff-managed banner a page shows (B10): one live banner, at random among those that match. */
class Banners
{
    /** A page without a country (all-countries search) shows only banners for every country. */
    public function pick(BannerPlacement $placement, ?Country $country): ?Banner
    {
        return Banner::query()->live()
            ->where('placement', $placement->value)
            ->where(fn ($q) => $q->whereNull('country_id')->when($country, fn ($q) => $q->orWhere('country_id', $country->id)))
            ->inRandomOrder()
            ->first();
    }
}
