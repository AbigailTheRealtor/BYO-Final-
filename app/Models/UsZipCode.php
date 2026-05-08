<?php

namespace App\Models;

use App\Services\CityNameNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsZipCode extends Model
{
    use HasFactory;

    protected $table = 'us_zip_codes';

    protected $fillable = [
        'zip_code',
        'city',
        'state_abbrev',
        'state_name',
        'county',
        'latitude',
        'longitude',
    ];

    public function state()
    {
        return $this->belongsTo(UsState::class, 'state_abbrev', 'abbreviation');
    }

    public function aliases()
    {
        return $this->hasMany(UsZipCodeCity::class, 'zip_code', 'zip_code');
    }

    /**
     * Get ZIP codes for a given city name, checking both the primary table
     * and the alias pivot table.
     *
     * Matching is abbreviation-aware: "St. Pete Beach", "Saint Pete Beach", and
     * "Saint Pete Beach" all resolve to the same results. Whitespace and period
     * differences are handled transparently via CityNameNormalizer::searchVariants().
     *
     * @param  string       $cityName     City name as typed (may contain abbreviations).
     * @param  string|null  $stateAbbrev  Optional 2-letter state filter.
     * @return string[]     Sorted, deduplicated array of ZIP codes.
     */
    public static function getZipCodesForCity(string $cityName, ?string $stateAbbrev = null): array
    {
        $variants = CityNameNormalizer::searchVariants($cityName);

        if (empty($variants)) {
            return [];
        }

        $applyVariants = function ($query) use ($variants) {
            return $query->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('city', 'ILIKE', $variant);
                }
            });
        };

        $applyState = function ($query) use ($stateAbbrev) {
            if ($stateAbbrev) {
                $query->where('state_abbrev', strtoupper($stateAbbrev));
            }
            return $query;
        };

        $primaryQuery = $applyState($applyVariants(self::query()));
        $primaryZips  = $primaryQuery->orderBy('zip_code')->pluck('zip_code')->toArray();

        $aliasQuery = $applyState($applyVariants(DB::table('us_zip_code_cities')));
        $aliasZips  = $aliasQuery->orderBy('zip_code')->pluck('zip_code')->toArray();

        $merged = array_unique(array_merge($primaryZips, $aliasZips));
        sort($merged);

        return array_values($merged);
    }

    /**
     * Search ZIP codes by prefix — returns primary (USPS-preferred) city name
     * from the main table.
     *
     * @param  string  $input  ZIP prefix to search.
     * @param  int     $limit  Maximum rows to return.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function searchZipCodes(string $input, int $limit = 10)
    {
        return self::where('zip_code', 'LIKE', $input . '%')
            ->orderBy('zip_code')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all city names associated with a ZIP — primary city plus aliases.
     *
     * @param  string  $zipCode
     * @return string[]
     */
    public static function getAllCitiesForZip(string $zipCode): array
    {
        $primary = self::where('zip_code', $zipCode)->value('city');

        $aliases = DB::table('us_zip_code_cities')
            ->where('zip_code', $zipCode)
            ->pluck('city')
            ->toArray();

        $cities = array_filter(array_unique(array_merge(
            $primary ? [$primary] : [],
            $aliases
        )));

        return array_values($cities);
    }
}
