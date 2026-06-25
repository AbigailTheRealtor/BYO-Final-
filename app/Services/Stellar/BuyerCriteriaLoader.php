<?php

namespace App\Services\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Loads a BuyerCriteriaAuction record and maps its native columns + EAV meta
 * into the flat array accepted by BuyerCriteriaPayload::__construct().
 *
 * Returns null when:
 *  - No matching active record exists, OR
 *  - property_types resolves to an empty array (BuyerCriteriaPayload would throw).
 */
class BuyerCriteriaLoader
{
    /**
     * Load and map the buyer's most-recent active criteria record.
     *
     * @param  int $userId  The authenticated user's ID.
     * @return array|null   Flat array ready for BuyerCriteriaPayload, or null.
     */
    public function load(int $userId): ?array
    {
        $record = BuyerCriteriaAuction::where('user_id', $userId)
            ->where('is_approved', true)
            ->where('is_sold', false)
            ->latest()
            ->first();

        if (!$record) {
            return null;
        }

        return $this->mapRecord($record);
    }

    /**
     * Load and map a specific BuyerCriteriaAuction record by ID.
     *
     * Access rule: the record's user_id must be in $allowedUserIds.
     * For a regular user pass [$userId]; for an agent pass [$agentId, ...clientIds].
     * Only active (is_approved = true, is_sold = false) records are returned.
     *
     * @param  int   $criteriaId      The ID of the BuyerCriteriaAuction record.
     * @param  int[] $allowedUserIds  User IDs that may own this record.
     * @return array|null             Flat array ready for BuyerCriteriaPayload, or null.
     */
    public function loadById(int $criteriaId, array $allowedUserIds): ?array
    {
        $record = BuyerCriteriaAuction::where('id', $criteriaId)
            ->whereIn('user_id', $allowedUserIds)
            ->where('is_approved', true)
            ->where('is_sold', false)
            ->first();

        if (!$record) {
            return null;
        }

        return $this->mapRecord($record);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Map a BuyerCriteriaAuction record to the flat array accepted by
     * BuyerCriteriaPayload::__construct().
     *
     * Returns null when property_types cannot be resolved (BuyerCriteriaPayload
     * requires a non-empty array).
     */
    private function mapRecord(BuyerCriteriaAuction $record): ?array
    {
        $infoGet = function (string $key) use ($record) {
            $val = $record->info($key);
            return ($val === false || $val === null) ? null : $val;
        };

        // -----------------------------------------------------------------------
        // property_types — required non-empty array; check EAV meta first, then
        // fall back to a default 'Residential' derived from the native column.
        // -----------------------------------------------------------------------
        $propertyTypes = $this->decodeJsonMeta($infoGet('property_types'));
        if (empty($propertyTypes) && !empty($record->property_type_id)) {
            $propertyTypes = ['Residential'];
        }
        if (empty($propertyTypes)) {
            return null;
        }

        // -----------------------------------------------------------------------
        // is_55_plus_eligible — must be explicit bool; default false.
        // -----------------------------------------------------------------------
        $is55Raw = $infoGet('is_55_plus_eligible');
        if ($is55Raw !== null) {
            $is55Plus = filter_var($is55Raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        } elseif (!empty($record->old_community)) {
            $is55Plus = (bool) filter_var($record->old_community, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        } else {
            $is55Plus = false;
        }

        // -----------------------------------------------------------------------
        // Location — read from location_dna_preferences blob (primary source)
        // then fall back to individual meta keys for legacy records that stored
        // radius_searches/polygons/cities/zip_codes as separate EAV keys.
        // -----------------------------------------------------------------------
        $ldnaRaw = $infoGet('location_dna_preferences');
        $ldna    = ($ldnaRaw && is_string($ldnaRaw)) ? (json_decode($ldnaRaw, true) ?? []) : [];

        // When the LDNA blob exists and a key is present in it (even as an empty
        // array), we must use the blob value — never fall back to legacy meta.
        // This preserves "Clear All" semantics: a user who cleared the map and
        // saved will have radius_searches:[] in the blob; falling back to a
        // legacy radius_searches meta would resurrect stale geometry.
        // Only fall back to legacy keys when the blob itself is absent.
        $ldnaBlobPresent = !empty($ldna);

        $preferredCities   = array_values(array_filter(array_map(
            [$this, 'normalizeCityName'],
            ($ldnaBlobPresent && array_key_exists('cities', $ldna))
                ? $this->decodeJsonMeta($ldna['cities'])
                : $this->decodeJsonMeta($infoGet('preferred_cities'))
        )));

        $preferredZipCodes = ($ldnaBlobPresent && array_key_exists('zip_codes', $ldna))
            ? $this->decodeJsonMeta($ldna['zip_codes'])
            : $this->decodeJsonMeta($infoGet('preferred_zip_codes'));

        $radiusSearches    = ($ldnaBlobPresent && array_key_exists('radius_searches', $ldna))
            ? $this->decodeJsonMeta($ldna['radius_searches'])
            : $this->decodeJsonMeta($infoGet('radius_searches'));

        $polygons          = ($ldnaBlobPresent && array_key_exists('polygons', $ldna))
            ? $this->decodeJsonMeta($ldna['polygons'])
            : $this->decodeJsonMeta($infoGet('polygons'));

        // Counties are not part of the LDNA blob; always read from separate meta
        $preferredCounties  = array_values(array_filter(array_map(
            [$this, 'normalizeCountyName'],
            $this->decodeJsonMeta($infoGet('preferred_counties'))
        )));
        $preferredSubdivisions = $this->decodeJsonMeta($infoGet('preferred_subdivisions'));
        $preferredMlsAreas     = $this->decodeJsonMeta($infoGet('preferred_mls_areas'));

        // -----------------------------------------------------------------------
        // Price — native columns with EAV override
        // -----------------------------------------------------------------------
        $maxPrice   = $this->positiveIntOrNull($infoGet('max_price') ?? $record->max_price);
        $idealPrice = $this->positiveIntOrNull($infoGet('ideal_price'));

        // -----------------------------------------------------------------------
        // Property sub-types
        // -----------------------------------------------------------------------
        $propertySubTypes = $this->decodeJsonMeta($infoGet('property_sub_types'));

        // -----------------------------------------------------------------------
        // Size — native columns; EAV can override
        // -----------------------------------------------------------------------
        $minBedrooms  = $this->positiveIntOrNull($infoGet('min_bedrooms')  ?? $record->bedrooms);
        $minBathrooms = $this->positiveIntOrNull($infoGet('min_bathrooms') ?? $record->bathrooms);
        $minSqft      = $this->positiveIntOrNull($infoGet('min_sqft')      ?? $record->sqft);
        $maxSqft      = $this->positiveIntOrNull($infoGet('max_sqft'));
        $minLotSqft   = $this->positiveIntOrNull($infoGet('min_lot_sqft'));
        $maxLotSqft   = $this->positiveIntOrNull($infoGet('max_lot_sqft'));
        $yearBuiltMin = $this->positiveIntOrNull($infoGet('year_built_min'));
        $yearBuiltMax = $this->positiveIntOrNull($infoGet('year_built_max'));

        // -----------------------------------------------------------------------
        // Amenities — native columns / EAV
        // -----------------------------------------------------------------------
        $wantsPool       = $this->tristateBool($infoGet('wants_pool')       ?? $record->pool);
        $wantsGarage     = $this->tristateBool($infoGet('wants_garage')     ?? $record->garage);
        $minGarageSpaces = $this->positiveIntOrNull($infoGet('min_garage_spaces') ?? $record->garage_spaces);
        $wantsWaterfront = $this->tristateBool($infoGet('wants_waterfront'));
        $wantsWaterView  = $this->tristateBool($infoGet('wants_water_view') ?? $record->water_view);
        $wantsAnyView    = $this->tristateBool($infoGet('wants_any_view'));

        // -----------------------------------------------------------------------
        // Financial / HOA — native columns / EAV
        // -----------------------------------------------------------------------
        $maxMonthlyHoa         = $this->positiveIntOrNull($infoGet('max_monthly_hoa') ?? $record->max_hoa_fee);
        $hoaPreference         = $infoGet('hoa_preference') ?? ($record->hoa_fee_requirement ?: null);
        $cddPreference         = $infoGet('cdd_preference');
        $maxMonthlyTotalBurden = $this->positiveIntOrNull($infoGet('max_monthly_total_burden'));

        // -----------------------------------------------------------------------
        // Community / Eligibility
        // -----------------------------------------------------------------------
        $wantsPetFriendly    = $this->tristateBool($infoGet('wants_pet_friendly') ?? $record->pets_allowed);
        $wantsNewConstruction = $this->tristateBool($infoGet('wants_new_construction'));

        // -----------------------------------------------------------------------
        // Lifestyle
        // -----------------------------------------------------------------------
        $communityFeatureKeywords = $this->decodeJsonMeta($infoGet('community_feature_keywords'));
        $wantsEnergyEfficient     = $this->tristateBool($infoGet('wants_energy_efficient'));

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
            'ideal_price'                 => $idealPrice,

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
            'min_garage_spaces'           => $minGarageSpaces,
            'wants_waterfront'            => $wantsWaterfront,
            'wants_water_view'            => $wantsWaterView,
            'wants_any_view'              => $wantsAnyView,

            'max_monthly_hoa'             => $maxMonthlyHoa,
            'hoa_preference'              => $hoaPreference,
            'cdd_preference'              => $cddPreference,
            'max_monthly_total_burden'    => $maxMonthlyTotalBurden,

            'wants_pet_friendly'          => $wantsPetFriendly,
            'wants_new_construction'      => $wantsNewConstruction,

            'community_feature_keywords'  => $communityFeatureKeywords,
            'wants_energy_efficient'      => $wantsEnergyEfficient,
        ];
    }

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

    /**
     * Decode a meta value that may be a JSON array string, a PHP array already
     * decoded by the model accessor, or a scalar string.
     */
    private function decodeJsonMeta(mixed $value): array
    {
        if ($value === null || $value === false || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, [$this, 'isNonEmptyMetaElement']));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, [$this, 'isNonEmptyMetaElement']));
            }
            $trimmed = trim($value);
            return $trimmed !== '' ? [$trimmed] : [];
        }

        return [];
    }

    private function isNonEmptyMetaElement(mixed $item): bool
    {
        if ($item === null) {
            return false;
        }
        if (is_array($item)) {
            return true;
        }
        if (is_string($item)) {
            return trim($item) !== '';
        }
        return true;
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function tristateBool(mixed $value): ?bool
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
