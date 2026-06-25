<?php

namespace App\Services\Stellar;

use App\Models\BuyerAgentAuction;
use Illuminate\Support\Facades\DB;

/**
 * Loads a BuyerAgentAuction offer-listing record and maps its EAV meta
 * into the flat array accepted by BuyerCriteriaPayload::__construct().
 *
 * Covers all property types stored in buyer_agent_auctions:
 *   Residential, Income, Commercial (Sale), Business Opportunity, Vacant Land.
 *
 * Only records with workflow_type meta = 'offer_listing' are eligible.
 * Hire-agent records stored in the same table are excluded.
 *
 * Key rename table (offer listing EAV → matcher payload key):
 *   counties              → preferred_counties   (JSON decode)
 *   maximum_budget        → max_price            (positive int)
 *   bedrooms              → min_bedrooms         (positive int)
 *   bathrooms             → min_bathrooms        (positive int)
 *   minimum_heated_square → min_sqft             (positive int)
 *   pool_needed           → wants_pool           (tristate bool)
 *   garage_needed         → wants_garage         (tristate bool)
 *   view_preference       → wants_water_view     (bool; water keywords in array)
 *                        → wants_any_view        (bool; array non-empty)
 *   hoa_acceptance        → hoa_preference       (string passthrough)
 *   hoa_max_monthly_fee   → max_monthly_hoa      (positive int)
 *   leasing_55_plus       → is_55_plus_eligible  (bool)
 *   condition_prop_buyer  → property_sub_types   (JSON decode)
 *   property_type (scalar)→ property_types       (single-element array)
 *   min_acreage (acres)   → min_lot_sqft         (acres × 43560)
 *   total_acreage (acres) → max_lot_sqft         (acres × 43560)
 *   location_dna blob     → preferred_cities, preferred_zip_codes,
 *                           radius_searches, polygons
 */
class BuyerOfferListingCriteriaLoader
{
    private const WATER_VIEW_KEYWORDS = [
        'water', 'ocean', 'lake', 'bay', 'gulf', 'river',
        'waterfront', 'intracoastal', 'canal', 'pond', 'pool view',
    ];

    private const ACRES_TO_SQFT = 43560.0;

    /**
     * Load and map the most-recent active offer-listing record for a user.
     *
     * @param  int $userId
     * @return array|null  Flat array for BuyerCriteriaPayload, or null if not found.
     */
    public function load(int $userId): ?array
    {
        $record = $this->findRecord([$userId], null);
        return $record ? $this->mapRecord($record) : null;
    }

    /**
     * Load and map a specific BuyerAgentAuction offer-listing record by ID.
     *
     * Access rule: record's user_id must be in $allowedUserIds.
     *
     * @param  int   $recordId       The ID of the BuyerAgentAuction record.
     * @param  int[] $allowedUserIds User IDs permitted to own this record.
     * @return array|null            Flat array for BuyerCriteriaPayload, or null.
     */
    public function loadById(int $recordId, array $allowedUserIds): ?array
    {
        $record = $this->findRecord($allowedUserIds, $recordId);
        return $record ? $this->mapRecord($record) : null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function findRecord(array $allowedUserIds, ?int $recordId): ?BuyerAgentAuction
    {
        $offerListingIds = DB::table('buyer_agent_auction_metas')
            ->where('meta_key', 'workflow_type')
            ->where('meta_value', 'offer_listing')
            ->pluck('buyer_agent_auction_id');

        if ($offerListingIds->isEmpty()) {
            return null;
        }

        $query = BuyerAgentAuction::whereIn('id', $offerListingIds)
            ->whereIn('user_id', $allowedUserIds)
            ->where('is_approved', true)
            ->where('is_sold', false);

        if ($recordId !== null) {
            $query->where('id', $recordId);
        } else {
            $query->latest();
        }

        return $query->first();
    }

    private function mapRecord(BuyerAgentAuction $record): ?array
    {
        $get = function (string $key) use ($record) {
            $val = $record->info($key);
            return ($val === false || $val === null) ? null : $val;
        };

        // -----------------------------------------------------------------------
        // property_types — required non-empty array
        // Normalize from form EAV casing (e.g. 'residential') to the casing
        // used in bridge_properties.property_type (e.g. 'Residential') so that
        // the SQL whereIn in BuyerMatchQueryBuilder matches correctly.
        // -----------------------------------------------------------------------
        $propertyTypeRaw = $get('property_type');
        if ($propertyTypeRaw !== null && trim($propertyTypeRaw) !== '') {
            $propertyTypes = [$this->normalizeBridgePropertyType(trim($propertyTypeRaw))];
        } else {
            $propertyTypes = ['Residential'];
        }

        // -----------------------------------------------------------------------
        // Location — LDNA blob (primary) with fallback to legacy separate keys
        // -----------------------------------------------------------------------
        $ldnaRaw     = $get('location_dna_preferences');
        $ldna        = [];
        if ($ldnaRaw !== null) {
            if (is_array($ldnaRaw)) {
                $ldna = $ldnaRaw;
            } elseif (is_string($ldnaRaw)) {
                $parsed = json_decode($ldnaRaw, true);
                if (is_array($parsed)) {
                    $ldna = $parsed;
                }
            }
        }
        $ldnaBlobPresent = !empty($ldna);

        $preferredCities = array_values(array_filter(array_map(
            [$this, 'normalizeCityName'],
            ($ldnaBlobPresent && array_key_exists('cities', $ldna))
                ? $this->decodeJsonMeta($ldna['cities'])
                : $this->decodeJsonMeta($get('preferred_cities'))
        )));

        $preferredZipCodes = ($ldnaBlobPresent && array_key_exists('zip_codes', $ldna))
            ? $this->decodeJsonMeta($ldna['zip_codes'])
            : $this->decodeJsonMeta($get('preferred_zip_codes'));

        $radiusSearches = ($ldnaBlobPresent && array_key_exists('radius_searches', $ldna))
            ? (is_array($ldna['radius_searches']) ? $ldna['radius_searches'] : [])
            : [];

        $polygons = ($ldnaBlobPresent && array_key_exists('polygons', $ldna))
            ? (is_array($ldna['polygons']) ? $ldna['polygons'] : [])
            : [];

        $preferredCounties     = array_values(array_filter(array_map(
            [$this, 'normalizeCountyName'],
            $this->decodeJsonMeta($get('counties'))
        )));
        $preferredSubdivisions = $this->decodeJsonMeta($get('preferred_subdivisions'));
        $preferredMlsAreas     = $this->decodeJsonMeta($get('preferred_mls_areas'));

        // -----------------------------------------------------------------------
        // Price
        // -----------------------------------------------------------------------
        $maxPrice = $this->positiveIntOrNull($this->stripCommas($get('maximum_budget')));

        // -----------------------------------------------------------------------
        // Property sub-types
        // -----------------------------------------------------------------------
        $propertySubTypes = $this->decodeJsonMeta($get('condition_prop_buyer'));

        // -----------------------------------------------------------------------
        // Size — bedrooms / bathrooms / sq ft / lot
        // -----------------------------------------------------------------------
        $minBedrooms  = $this->positiveIntOrNull($get('bedrooms'));
        $minBathrooms = $this->positiveIntOrNull($get('bathrooms'));
        $minSqft      = $this->positiveIntOrNull($this->stripCommas($get('minimum_heated_square')));
        $maxSqft      = null;

        $minAcresRaw = $this->positiveFloatOrNull($this->stripCommas($get('min_acreage')));
        $maxAcresRaw = $this->positiveFloatOrNull($this->stripCommas($get('total_acreage')));
        $minLotSqft  = $minAcresRaw !== null ? (int) round($minAcresRaw * self::ACRES_TO_SQFT) : null;
        $maxLotSqft  = $maxAcresRaw !== null ? (int) round($maxAcresRaw * self::ACRES_TO_SQFT) : null;
        if ($minLotSqft !== null && $minLotSqft <= 0) {
            $minLotSqft = null;
        }
        if ($maxLotSqft !== null && $maxLotSqft <= 0) {
            $maxLotSqft = null;
        }

        $yearBuiltMin = null;
        $yearBuiltMax = null;

        // -----------------------------------------------------------------------
        // Amenities
        // -----------------------------------------------------------------------
        $wantsPool  = $this->yesNoTristate($get('pool_needed'));
        $wantsGarage = $this->yesNoTristate($get('garage_needed'));

        $viewPreferenceRaw = $get('view_preference');
        $viewArr = $this->decodeJsonMeta($viewPreferenceRaw);
        $wantsWaterView  = $this->extractWaterView($viewArr);
        $wantsAnyView    = !empty($viewArr) ? true : null;
        $wantsWaterfront = $this->extractWaterfront($viewArr);

        // -----------------------------------------------------------------------
        // HOA / Financial
        // -----------------------------------------------------------------------
        $hoaPreference     = $get('hoa_acceptance');
        $maxMonthlyHoa     = $this->positiveIntOrNull($this->stripCommas($get('hoa_max_monthly_fee')));
        $cddPreference     = null;
        $maxMonthlyBurden  = null;

        // -----------------------------------------------------------------------
        // Community / Eligibility
        // -----------------------------------------------------------------------
        $is55Plus = $this->is55PlusBool($get('leasing_55_plus'));

        $petInfoRaw = $get('pets') ?? $get('pet_information');
        $wantsPetFriendly = ($petInfoRaw !== null && $petInfoRaw !== '' && $petInfoRaw !== 'none')
            ? true
            : null;

        $wantsNewConstruction = null;

        // -----------------------------------------------------------------------
        // Lifestyle
        // -----------------------------------------------------------------------
        $communityFeatureKeywords = [];
        $wantsEnergyEfficient     = null;

        return [
            'property_types'              => $propertyTypes,
            'is_55_plus_eligible'         => $is55Plus,

            'preferred_cities'            => $preferredCities,
            'preferred_zip_codes'         => $preferredZipCodes,
            'preferred_counties'          => $preferredCounties,
            'radius_searches'             => $radiusSearches,
            'polygons'                    => $polygons,
            'preferred_subdivisions'      => $preferredSubdivisions,
            'preferred_mls_areas'         => $preferredMlsAreas,

            'max_price'                   => $maxPrice,
            'ideal_price'                 => null,

            'property_sub_types'          => $propertySubTypes,

            'min_bedrooms'                => $minBedrooms,
            'min_bathrooms'               => $minBathrooms,
            'min_sqft'                    => $minSqft,
            'max_sqft'                    => $maxSqft,
            'min_lot_sqft'                => $minLotSqft,
            'max_lot_sqft'                => $maxLotSqft,
            'year_built_min'              => $yearBuiltMin,
            'year_built_max'              => $yearBuiltMax,

            'wants_pool'                  => $wantsPool,
            'wants_garage'                => $wantsGarage,
            'min_garage_spaces'           => null,
            'wants_waterfront'            => $wantsWaterfront,
            'wants_water_view'            => $wantsWaterView,
            'wants_any_view'              => $wantsAnyView,

            'max_monthly_hoa'             => $maxMonthlyHoa,
            'hoa_preference'              => $hoaPreference,
            'cdd_preference'              => $cddPreference,
            'max_monthly_total_burden'    => $maxMonthlyBurden,

            'wants_pet_friendly'          => $wantsPetFriendly,
            'wants_new_construction'      => $wantsNewConstruction,

            'community_feature_keywords'  => $communityFeatureKeywords,
            'wants_energy_efficient'      => $wantsEnergyEfficient,
        ];
    }

    // =========================================================================
    // Utility helpers
    // =========================================================================

    /**
     * Strip state suffix (", FL") and " County" suffix so values like
     * "Pinellas County, FL" normalise to "Pinellas" to match Bridge API storage.
     */
    private function normalizeCountyName(string $county): string
    {
        $county = preg_replace('/,\s*[A-Z]{2}\s*$/u', '', trim($county));
        $county = preg_replace('/\s+County\s*$/iu', '', trim($county));
        return trim($county);
    }

    /**
     * Strip state suffix (", FL") and expand common abbreviations so values like
     * "St. Petersburg, FL" normalise to "Saint Petersburg" to match Bridge API storage.
     */
    private function normalizeCityName(string $city): string
    {
        $city = preg_replace('/,\s*[A-Z]{2}\s*$/u', '', trim($city));
        $city = preg_replace(['/\bSt\.\s+/u', '/\bFt\.\s+/u', '/\bMt\.\s+/u'], ['Saint ', 'Fort ', 'Mount '], $city);
        return trim($city);
    }

    private function decodeJsonMeta(mixed $value): array
    {
        if ($value === null || $value === false || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter($value, fn($item) =>
                $item !== null && (!is_string($item) || trim($item) !== '')
            ));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn($item) =>
                    $item !== null && (!is_string($item) || trim($item) !== '')
                ));
            }
            $trimmed = trim($value);
            return $trimmed !== '' ? [$trimmed] : [];
        }
        return [];
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function positiveFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        $float = (float) $value;
        return $float > 0.0 ? $float : null;
    }

    /**
     * Map 'Yes'/'No'/'No Preference' / truthy / falsy → bool|null tristate.
     * Null means "no preference" — the matcher will skip this dimension.
     */
    private function yesNoTristate(mixed $value): ?bool
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $lower = strtolower(trim((string) $value));
        if ($lower === 'yes' || $lower === '1' || $lower === 'true') {
            return true;
        }
        if ($lower === 'no' || $lower === '0' || $lower === 'false') {
            return false;
        }
        return null;
    }

    /**
     * Resolve is_55_plus_eligible from the offer listing's leasing_55_plus meta.
     * Defaults to false (not explicit eligibility) when the value is ambiguous.
     */
    private function is55PlusBool(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '') {
            return false;
        }
        $result = $this->yesNoTristate($value);
        return $result === true;
    }

    /**
     * Determine wants_water_view from a JSON-decoded view_preference array.
     * Returns true when any element contains a water-related keyword.
     * Returns null when the array is empty (no preference expressed).
     */
    private function extractWaterView(array $viewArr): ?bool
    {
        if (empty($viewArr)) {
            return null;
        }
        foreach ($viewArr as $item) {
            if (!is_string($item)) {
                continue;
            }
            $lower = strtolower($item);
            foreach (self::WATER_VIEW_KEYWORDS as $kw) {
                if (str_contains($lower, $kw)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine wants_waterfront from view_preference array.
     * Returns true when 'waterfront' or 'intracoastal' is present.
     */
    private function extractWaterfront(array $viewArr): ?bool
    {
        if (empty($viewArr)) {
            return null;
        }
        foreach ($viewArr as $item) {
            if (!is_string($item)) {
                continue;
            }
            $lower = strtolower($item);
            if (str_contains($lower, 'waterfront') || str_contains($lower, 'intracoastal')) {
                return true;
            }
        }
        return null;
    }

    private function stripCommas(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(',', '', $value);
        }
        return $value;
    }

    /**
     * Normalize a property_type EAV value from the buyer offer listing form
     * to the casing used in bridge_properties.property_type.
     *
     * The buyer offer form stores lowercase slugs (e.g. 'residential', 'income')
     * while Stellar's Bridge API — and therefore bridge_properties — uses
     * title-case values (e.g. 'Residential', 'Income', 'Commercial Sale').
     * PostgreSQL whereIn is case-sensitive, so without normalization the SQL
     * filter silently returns zero rows.
     */
    private function normalizeBridgePropertyType(string $type): string
    {
        $map = [
            'residential'      => 'Residential',
            'income'           => 'Income',
            'commercial'       => 'Commercial Sale',
            'commercial sale'  => 'Commercial Sale',
            'commercial_sale'  => 'Commercial Sale',
            'commercial lease' => 'Commercial Lease',
            'commercial_lease' => 'Commercial Lease',
            'business'         => 'Business Opportunity',
            'business opportunity' => 'Business Opportunity',
            'land'             => 'Vacant Land',
            'vacant land'      => 'Vacant Land',
            'vacant_land'      => 'Vacant Land',
        ];

        return $map[strtolower($type)] ?? ucfirst($type);
    }
}
