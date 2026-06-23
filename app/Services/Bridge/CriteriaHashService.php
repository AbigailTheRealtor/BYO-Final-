<?php

namespace App\Services\Bridge;

use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

class CriteriaHashService
{
    /**
     * Produce a stable, deterministic SHA-256 hex string for a given payload + role.
     * Two logically identical payloads (same field values, different object instances)
     * always return the same hash.
     */
    public function hash(BuyerCriteriaPayload $payload, string $role): string
    {
        $canonical = $this->canonicalize($payload);
        return hash('sha256', json_encode($canonical) . '|' . strtolower(trim($role)));
    }

    /**
     * Convert the payload to a sorted, normalised array suitable for stable hashing.
     * - All keys are sorted recursively (ksort).
     * - Null values are dropped.
     * - Numeric string values are cast to int/float.
     * - List arrays (sequential integer keys) are value-sorted for order-independence.
     * - Associative arrays preserve their key structure; only keys are sorted.
     */
    private function canonicalize(BuyerCriteriaPayload $payload): array
    {
        $raw = [
            'preferred_cities'           => $payload->preferredCities,
            'preferred_zip_codes'        => $payload->preferredZipCodes,
            'preferred_counties'         => $payload->preferredCounties,
            'radius_searches'            => $payload->radiusSearches,
            'polygons'                   => $payload->polygons,
            'preferred_subdivisions'     => $payload->preferredSubdivisions,
            'preferred_mls_areas'        => $payload->preferredMlsAreas,
            'max_price'                  => $payload->maxPrice,
            'ideal_price'                => $payload->idealPrice,
            'property_types'             => $payload->propertyTypes,
            'property_sub_types'         => $payload->propertySubTypes,
            'min_bedrooms'               => $payload->minBedrooms,
            'min_bathrooms'              => $payload->minBathrooms,
            'min_sqft'                   => $payload->minSqft,
            'max_sqft'                   => $payload->maxSqft,
            'min_lot_sqft'               => $payload->minLotSqft,
            'max_lot_sqft'               => $payload->maxLotSqft,
            'year_built_min'             => $payload->yearBuiltMin,
            'year_built_max'             => $payload->yearBuiltMax,
            'wants_pool'                 => $payload->wantsPool,
            'wants_garage'               => $payload->wantsGarage,
            'min_garage_spaces'          => $payload->minGarageSpaces,
            'wants_waterfront'           => $payload->wantsWaterfront,
            'wants_water_view'           => $payload->wantsWaterView,
            'wants_any_view'             => $payload->wantsAnyView,
            'max_monthly_hoa'            => $payload->maxMonthlyHoa,
            'hoa_preference'             => $payload->hoaPreference,
            'cdd_preference'             => $payload->cddPreference,
            'max_monthly_total_burden'   => $payload->maxMonthlyTotalBurden,
            'is_55_plus_eligible'        => $payload->is55PlusEligible,
            'wants_pet_friendly'         => $payload->wantsPetFriendly,
            'wants_new_construction'     => $payload->wantsNewConstruction,
            'community_feature_keywords' => $payload->communityFeatureKeywords,
            'wants_energy_efficient'     => $payload->wantsEnergyEfficient,
            'preferred_lease_terms'      => $payload->preferredLeaseTerms,
        ];

        return $this->normaliseArray($raw);
    }

    private function normaliseArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $normalised = $this->normaliseArray($value);
                if (!empty($normalised)) {
                    if (array_is_list($normalised)) {
                        // Value-sort list arrays so insertion order doesn't affect the hash.
                        // usort by json_encode handles both scalar values and nested objects.
                        usort($normalised, fn ($a, $b) => json_encode($a) <=> json_encode($b));
                    }
                    // Associative arrays are already ksorted by the recursive call.
                    $result[$key] = $normalised;
                }
            } elseif (is_string($value) && is_numeric($value)) {
                $result[$key] = str_contains($value, '.') ? (float) $value : (int) $value;
            } else {
                $result[$key] = $value;
            }
        }

        ksort($result);
        return $result;
    }
}
