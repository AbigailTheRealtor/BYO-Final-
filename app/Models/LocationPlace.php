<?php

namespace App\Models;

use App\Services\LocationDna\Places\PlaceNameKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1d-3 — a selectable real-estate location: Census place, USPS community, or neighbourhood.
 *
 * See the `location_places` migration for why this table exists alongside the Census corpus
 * rather than inside it. In short: the corpus stays verifiable against the Census Bureau, and
 * everything the Bureau does not publish — Clearwater Beach and its 6,910 nationwide siblings —
 * lives here.
 *
 * `name_key` IS DERIVED AND SELF-MAINTAINING. It is set from `name` on every save, so no caller
 * can insert a row whose match surface disagrees with its display name. That is enforced here
 * rather than in the builder because a hand-inserted curated row must obey the same rule.
 */
class LocationPlace extends Model
{
    public const TYPE_CITY         = 'city';
    public const TYPE_TOWN         = 'town';
    public const TYPE_VILLAGE      = 'village';
    public const TYPE_BOROUGH      = 'borough';
    public const TYPE_CDP          = 'cdp';
    public const TYPE_NEIGHBORHOOD = 'neighborhood';
    public const TYPE_COMMUNITY    = 'community';

    /** Types that name a unit of government or a Census-recognised settlement. */
    public const PLACE_TYPES = [
        self::TYPE_CITY,
        self::TYPE_TOWN,
        self::TYPE_VILLAGE,
        self::TYPE_BOROUGH,
        self::TYPE_CDP,
    ];

    /** Types that sit BELOW a place in the hierarchy. */
    public const SUB_PLACE_TYPES = [
        self::TYPE_NEIGHBORHOOD,
        self::TYPE_COMMUNITY,
    ];

    public const SOURCE_CENSUS       = 'census';
    public const SOURCE_SUPPLEMENTAL = 'supplemental';
    public const SOURCE_CURATED      = 'curated';

    protected $table = 'location_places';

    protected $fillable = [
        'name', 'name_key', 'type', 'state_geoid', 'county_geoid',
        'parent_place_id', 'census_place_geoid', 'latitude', 'longitude',
        'source', 'active',
    ];

    protected $casts = [
        'active'    => 'boolean',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        // Derived, always. A row whose key disagreed with its name would be invisible to the
        // resolver while looking perfectly correct in the table.
        static::saving(function (self $place): void {
            $place->name_key = PlaceNameKey::of((string) $place->name);
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_place_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_place_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInState($query, string $stateGeoid)
    {
        return $query->where('state_geoid', $stateGeoid);
    }

    /**
     * Places belonging to ANY of these counties, through the pivot.
     *
     * Deliberately not `whereIn('county_geoid', ...)`. That scalar column holds only the PRIMARY
     * county, so a place straddling a county line was invisible under its other parents — which
     * is how Staten Island ended up with no places at all. `whereExists` rather than a join
     * because a place in two of the selected counties must appear ONCE, and a join would return
     * it twice and quietly inflate every count built on this scope.
     */
    public function scopeInCounties($query, array $countyGeoids)
    {
        return $query->whereExists(function ($sub) use ($countyGeoids): void {
            $sub->from('location_place_counties')
                ->whereColumn('location_place_counties.location_place_id', 'location_places.id')
                ->whereIn('location_place_counties.county_geoid', $countyGeoids);
        });
    }

    /** Every county this place belongs to, primary first. */
    public function countyGeoids(): array
    {
        return DB::table('location_place_counties')
            ->where('location_place_id', $this->id)
            ->orderByDesc('is_primary')
            ->orderBy('county_geoid')
            ->pluck('county_geoid')
            ->map(fn ($g): string => trim((string) $g))
            ->all();
    }

    /** True when the place is published as spanning more than one county. */
    public function spansCounties(): bool
    {
        return DB::table('location_place_counties')->where('location_place_id', $this->id)->count() > 1;
    }

    /** Places proper — the tier the cascade already has. */
    public function scopePlaces($query)
    {
        return $query->whereIn('type', self::PLACE_TYPES);
    }

    /** Neighbourhoods and communities — the tier this layer adds. */
    public function scopeSubPlaces($query)
    {
        return $query->whereIn('type', self::SUB_PLACE_TYPES);
    }

    public function isSubPlace(): bool
    {
        return in_array($this->type, self::SUB_PLACE_TYPES, true);
    }
}
