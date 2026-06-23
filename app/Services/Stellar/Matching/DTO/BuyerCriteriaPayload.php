<?php

namespace App\Services\Stellar\Matching\DTO;

class BuyerCriteriaPayload
{
    public readonly array $preferredCities;
    public readonly array $preferredZipCodes;
    public readonly array $preferredCounties;
    public readonly array $radiusSearches;
    public readonly array $polygons;
    public readonly array $preferredSubdivisions;
    public readonly array $preferredMlsAreas;

    public readonly ?int $maxPrice;
    public readonly ?int $idealPrice;

    public readonly array $propertyTypes;
    public readonly array $propertySubTypes;

    public readonly ?int $minBedrooms;
    public readonly ?int $minBathrooms;
    public readonly ?int $minSqft;
    public readonly ?int $maxSqft;
    public readonly ?int $minLotSqft;
    public readonly ?int $maxLotSqft;
    public readonly ?int $yearBuiltMin;
    public readonly ?int $yearBuiltMax;

    public readonly ?bool $wantsPool;
    public readonly ?bool $wantsGarage;
    public readonly ?int $minGarageSpaces;
    public readonly ?bool $wantsWaterfront;
    public readonly ?bool $wantsWaterView;
    public readonly ?bool $wantsAnyView;

    public readonly ?int $maxMonthlyHoa;
    public readonly ?string $hoaPreference;
    public readonly ?string $cddPreference;
    public readonly ?int $maxMonthlyTotalBurden;

    public readonly bool $is55PlusEligible;
    public readonly ?bool $wantsPetFriendly;
    public readonly ?bool $wantsNewConstruction;

    public readonly array $communityFeatureKeywords;
    public readonly ?bool $wantsEnergyEfficient;

    /**
     * Preferred lease durations for commercial/residential rental matching.
     * Populated by TenantOfferListingCriteriaLoader from the EAV 'desired_lease_length' key.
     * Example values: ['1 Year', '2 Years', 'Month-to-Month'].
     * Empty array = no preference (scorer awards full neutral points).
     * Not used by the Buyer matching flow (BuyerOfferListingCriteriaLoader leaves it empty).
     */
    public readonly array $preferredLeaseTerms;

    public function __construct(array $data)
    {
        $this->propertyTypes = $data['property_types'] ?? [];
        if (empty($this->propertyTypes)) {
            throw new \InvalidArgumentException('property_types must be a non-empty array.');
        }

        if (!isset($data['is_55_plus_eligible']) || !is_bool($data['is_55_plus_eligible'])) {
            throw new \InvalidArgumentException('is_55_plus_eligible must be an explicit boolean value.');
        }
        $this->is55PlusEligible = $data['is_55_plus_eligible'];

        $maxPrice   = $data['max_price']   ?? null;
        $idealPrice = $data['ideal_price'] ?? null;
        if ($maxPrice !== null && $maxPrice < 0) {
            throw new \InvalidArgumentException('max_price must not be negative.');
        }
        if ($idealPrice !== null && $idealPrice < 0) {
            throw new \InvalidArgumentException('ideal_price must not be negative.');
        }

        $maxMonthlyHoa = $data['max_monthly_hoa'] ?? null;
        if ($maxMonthlyHoa !== null && $maxMonthlyHoa < 0) {
            throw new \InvalidArgumentException('max_monthly_hoa must not be negative.');
        }

        $maxMonthlyTotalBurden = $data['max_monthly_total_burden'] ?? null;
        if ($maxMonthlyTotalBurden !== null && $maxMonthlyTotalBurden < 0) {
            throw new \InvalidArgumentException('max_monthly_total_burden must not be negative.');
        }

        $this->preferredCities       = $data['preferred_cities']       ?? [];
        $this->preferredZipCodes     = $data['preferred_zip_codes']     ?? [];
        $this->preferredCounties     = $data['preferred_counties']      ?? [];
        $this->radiusSearches        = $data['radius_searches']         ?? [];
        $this->polygons              = $data['polygons']                ?? [];
        $this->preferredSubdivisions = $data['preferred_subdivisions']  ?? [];
        $this->preferredMlsAreas     = $data['preferred_mls_areas']     ?? [];

        $this->maxPrice   = $maxPrice !== null ? (int) $maxPrice : null;
        $this->idealPrice = $idealPrice !== null ? (int) $idealPrice : null;

        $this->propertySubTypes = $data['property_sub_types'] ?? [];

        $this->minBedrooms  = isset($data['min_bedrooms'])  ? (int) $data['min_bedrooms']  : null;
        $this->minBathrooms = isset($data['min_bathrooms']) ? (int) $data['min_bathrooms'] : null;
        $this->minSqft      = isset($data['min_sqft'])      ? (int) $data['min_sqft']      : null;
        $this->maxSqft      = isset($data['max_sqft'])      ? (int) $data['max_sqft']      : null;
        $this->minLotSqft   = isset($data['min_lot_sqft'])  ? (int) $data['min_lot_sqft']  : null;
        $this->maxLotSqft   = isset($data['max_lot_sqft'])  ? (int) $data['max_lot_sqft']  : null;
        $this->yearBuiltMin = isset($data['year_built_min']) ? (int) $data['year_built_min'] : null;
        $this->yearBuiltMax = isset($data['year_built_max']) ? (int) $data['year_built_max'] : null;

        $this->wantsPool         = $data['wants_pool']          ?? null;
        $this->wantsGarage       = $data['wants_garage']         ?? null;
        $this->minGarageSpaces   = isset($data['min_garage_spaces']) ? (int) $data['min_garage_spaces'] : null;
        $this->wantsWaterfront   = $data['wants_waterfront']     ?? null;
        $this->wantsWaterView    = $data['wants_water_view']     ?? null;
        $this->wantsAnyView      = $data['wants_any_view']       ?? null;

        $this->maxMonthlyHoa          = $maxMonthlyHoa !== null ? (int) $maxMonthlyHoa : null;
        $this->hoaPreference          = $data['hoa_preference']  ?? null;
        $this->cddPreference          = $data['cdd_preference']  ?? null;
        $this->maxMonthlyTotalBurden  = $maxMonthlyTotalBurden !== null ? (int) $maxMonthlyTotalBurden : null;

        $this->wantsPetFriendly     = $data['wants_pet_friendly']     ?? null;
        $this->wantsNewConstruction = $data['wants_new_construction']  ?? null;

        $this->communityFeatureKeywords = $data['community_feature_keywords'] ?? [];
        $this->wantsEnergyEfficient     = $data['wants_energy_efficient']     ?? null;

        $this->preferredLeaseTerms = $data['preferred_lease_terms'] ?? [];
    }
}
