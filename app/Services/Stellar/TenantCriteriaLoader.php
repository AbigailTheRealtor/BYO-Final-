<?php

namespace App\Services\Stellar;

use App\Models\TenantCriteriaAuction;
use Illuminate\Support\Facades\Schema;

/**
 * Loads a specific TenantCriteriaAuction record and maps its EAV meta into
 * the flat array accepted by BuyerCriteriaPayload::__construct().
 *
 * Access rule (per platform audit): the record's user_id must be in $allowedUserIds.
 * CriteriaListingResolver.resolveAllowedUserIds() builds that list — for a regular user
 * it is [$userId]; for agents it also includes IDs of clients linked via user_agents
 * (agent_id = agent.id), the same relationship used by ShowingPolicy for listing access.
 *
 * Tenant-to-buyer field mapping:
 *  monthly_price              → max_price
 *  cities                     → preferred_cities
 *  counties                   → preferred_counties
 *  location_dna_preferences   → radius_searches + polygons (parsed from JSON)
 *  bedrooms                   → min_bedrooms  (handles 'custom' + custom_bedrooms fallback)
 *  bathrooms                  → min_bathrooms (handles 'custom' + custom_bathrooms fallback)
 *  minimum_sqft_needed        → min_sqft
 *  pool                       → wants_pool
 *  garage                     → wants_garage
 *  has_pets                   → wants_pet_friendly  (tenant has pets → needs pet-friendly)
 *  has_water_view             → wants_water_view
 *  property_type              → property_types (normalised to ['Residential'] or ['Commercial'])
 *  is_55_plus_eligible        → always false (no equivalent field in tenant criteria)
 *  preferred_zip_codes        → [] (no standalone ZIP field in tenant criteria form;
 *                                   ZIP-level geometry stored in location_dna_preferences)
 */
class TenantCriteriaLoader
{
    /**
     * Load and map a specific TenantCriteriaAuction record.
     *
     * Access rule: the record's user_id must be in $allowedUserIds.
     * For a regular user pass [$userId]; for an agent pass [$agentId, ...clientIds].
     * CriteriaListingResolver.resolveAllowedUserIds() builds this list correctly.
     *
     * Returns null when:
     *  - The table does not exist in this environment, OR
     *  - No active record with id=$criteriaId whose user_id is in $allowedUserIds exists, OR
     *  - The record cannot be mapped to a valid criteria payload.
     *
     * @param  int   $criteriaId      The ID of the TenantCriteriaAuction record.
     * @param  int[] $allowedUserIds  User IDs that may own this record.
     */
    public function loadById(int $criteriaId, array $allowedUserIds): ?array
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            return null;
        }

        $record = TenantCriteriaAuction::where('id', $criteriaId)
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
     * Map a TenantCriteriaAuction record to the flat array accepted by
     * BuyerCriteriaPayload.
     */
    private function mapRecord(TenantCriteriaAuction $record): ?array
    {
        $infoGet = function (string $key) use ($record) {
            $val = $record->info($key);
            return ($val === false || $val === null) ? null : $val;
        };

        // -----------------------------------------------------------------------
        // property_types — always normalised to ['Residential'] for tenant criteria
        // since the MLS matching engine indexes residential inventory. Commercial
        // is included as a passthrough for future expansion.
        // -----------------------------------------------------------------------
        $propertyTypeRaw = $infoGet('property_type');
        if ($propertyTypeRaw !== null && str_contains(strtolower($propertyTypeRaw), 'commercial')) {
            $propertyTypes = ['Commercial'];
        } else {
            $propertyTypes = ['Residential'];
        }

        // -----------------------------------------------------------------------
        // Location — tenant form stores 'cities' and 'counties' (JSON arrays)
        // -----------------------------------------------------------------------
        $preferredCities   = $this->decodeJsonMeta($infoGet('cities'));
        $preferredCounties = $this->decodeJsonMeta($infoGet('counties'));

        // -----------------------------------------------------------------------
        // Location DNA — radius searches and polygons drawn by the user are stored
        // as a single JSON object under the 'location_dna_preferences' meta key.
        // Structure: { "radius_searches": [...], "polygons": [...] }
        // -----------------------------------------------------------------------
        $ldnaRaw     = $infoGet('location_dna_preferences');
        $ldnaDecoded = [];
        if ($ldnaRaw !== null) {
            if (is_array($ldnaRaw)) {
                $ldnaDecoded = $ldnaRaw;
            } elseif (is_string($ldnaRaw)) {
                $parsed = json_decode($ldnaRaw, true);
                if (is_array($parsed)) {
                    $ldnaDecoded = $parsed;
                }
            }
        }
        $radiusSearches = (isset($ldnaDecoded['radius_searches']) && is_array($ldnaDecoded['radius_searches']))
            ? $ldnaDecoded['radius_searches']
            : [];
        $polygons = (isset($ldnaDecoded['polygons']) && is_array($ldnaDecoded['polygons']))
            ? $ldnaDecoded['polygons']
            : [];

        // -----------------------------------------------------------------------
        // Price — monthly_price maps to max_price for the matching pipeline
        // -----------------------------------------------------------------------
        $maxPrice = $this->positiveIntOrNull($infoGet('monthly_price'));

        // -----------------------------------------------------------------------
        // Bedrooms / bathrooms — may be a numeric string or 'custom' with a
        // companion custom_bedrooms / custom_bathrooms meta key holding the value.
        // -----------------------------------------------------------------------
        $bedroomsRaw     = $infoGet('bedrooms');
        $customBedrooms  = $infoGet('custom_bedrooms');
        $effectiveBeds   = ($bedroomsRaw === 'custom') ? $customBedrooms : $bedroomsRaw;
        $minBedrooms     = $this->positiveIntOrNull($effectiveBeds);

        $bathroomsRaw    = $infoGet('bathrooms');
        $customBathrooms = $infoGet('custom_bathrooms');
        $effectiveBaths  = ($bathroomsRaw === 'custom') ? $customBathrooms : $bathroomsRaw;
        $minBathrooms    = $this->positiveIntOrNull($effectiveBaths);

        $minSqft = $this->positiveIntOrNull($infoGet('minimum_sqft_needed'));

        // -----------------------------------------------------------------------
        // Amenities
        // -----------------------------------------------------------------------
        $wantsPool      = $this->tristateBool($infoGet('pool'));
        $wantsGarage    = $this->tristateBool($infoGet('garage'));
        $wantsWaterView = $this->tristateBool($infoGet('has_water_view'));

        // has_pets: tenant has pets and therefore NEEDS a pet-friendly property
        $wantsPetFriendly = $this->tristateBool($infoGet('has_pets'));

        return [
            'property_types'              => $propertyTypes,
            'is_55_plus_eligible'         => false,

            'preferred_cities'            => $preferredCities,
            'preferred_zip_codes'         => [],
            'preferred_counties'          => $preferredCounties,
            'radius_searches'             => $radiusSearches,
            'polygons'                    => $polygons,
            'preferred_subdivisions'      => [],
            'preferred_mls_areas'         => [],

            'max_price'                   => $maxPrice,
            'ideal_price'                 => null,

            'property_sub_types'          => [],

            'min_bedrooms'                => $minBedrooms,
            'min_bathrooms'               => $minBathrooms,
            'min_sqft'                    => $minSqft,
            'max_sqft'                    => null,
            'min_lot_sqft'                => null,
            'max_lot_sqft'                => null,
            'year_built_min'              => null,
            'year_built_max'              => null,

            'wants_pool'                  => $wantsPool,
            'wants_garage'                => $wantsGarage,
            'min_garage_spaces'           => null,
            'wants_waterfront'            => null,
            'wants_water_view'            => $wantsWaterView,
            'wants_any_view'              => null,

            'max_monthly_hoa'             => null,
            'hoa_preference'              => null,
            'cdd_preference'              => null,
            'max_monthly_total_burden'    => null,

            'wants_pet_friendly'          => $wantsPetFriendly,
            'wants_new_construction'      => null,

            'community_feature_keywords'  => [],
            'wants_energy_efficient'      => null,
        ];
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
