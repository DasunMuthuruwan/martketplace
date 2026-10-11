<?php

namespace App\Models;

use App\Enums\LocationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "My town is not listed" request, reviewed by staff in Admin → Locations.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $country_id
 * @property int|null $region_id
 * @property int|null $subregion_id
 * @property int|null $city_id
 * @property string|null $proposed_city
 * @property string|null $proposed_locality
 * @property LocationRequestStatus $status
 * @property int|null $resolved_city_id
 * @property int|null $resolved_locality_id
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property string|null $resolution_note
 */
class LocationRequest extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'country_id', 'region_id', 'subregion_id', 'city_id', 'proposed_city', 'proposed_locality'];

    /** @var array<string, string> */
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => LocationRequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function subregion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'subregion_id');
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
