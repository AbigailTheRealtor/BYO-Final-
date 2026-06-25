<?php

namespace App\Services\Stellar;

use App\Models\BridgeProperty;

/**
 * Maps a BridgeProperty model to a Blade-safe array for the property detail page.
 *
 * Shared across all consumer roles (Buyer, Tenant, Landlord preview, Agent, Ask AI).
 * No role-specific data is included here; callers may merge additional context arrays.
 *
 * Compliance rules — identical discipline as BuyerResultViewMapper:
 *  - raw_json is NEVER passed to the view layer.
 *  - No Tier 6 fields: agent email/phone/key, lockbox, showing instructions,
 *    internal remarks (STELLAR_PublicRemarksAgent, STELLAR_SoldRemarks, etc.).
 *  - IDXParticipationYN is read by the controller before calling this mapper.
 *  - listing_key is included for internal routing; MUST NOT render as visible text.
 */
class PropertyDetailViewMapper
{
    public function map(BridgeProperty $listing): array
    {
        $raw = $listing->raw_json ? (json_decode($listing->raw_json, true) ?? []) : [];

        $listPrice = $listing->list_price !== null ? (float) $listing->list_price : null;
        $origPrice = isset($raw['OriginalListPrice']) && $raw['OriginalListPrice'] !== ''
            ? (float) $raw['OriginalListPrice']
            : null;

        return [
            // ----------------------------------------------------------------
            // Identity (internal — never rendered as visible text)
            // ----------------------------------------------------------------
            'listing_key'         => $listing->listing_key,
            'mls_status'          => $listing->mls_status ?? ($raw['MlsStatus'] ?? null),

            // ----------------------------------------------------------------
            // Pricing
            // ----------------------------------------------------------------
            'list_price'          => $listPrice,
            'price_display'       => $listPrice !== null ? '$' . number_format($listPrice, 0) : null,
            'original_list_price' => $origPrice,
            'price_reduced'       => $origPrice !== null && $listPrice !== null && $origPrice > $listPrice,

            // ----------------------------------------------------------------
            // Location
            // ----------------------------------------------------------------
            'address'             => $listing->unparsed_address ?: null,
            'unit_number'         => $this->scalar($raw['UnitNumber'] ?? null),
            'city'                => $listing->city ?: null,
            'state'               => $listing->state_or_province ?: null,
            'postal_code'         => $listing->postal_code ?: null,
            'county'              => $listing->county_or_parish ?: null,
            'subdivision'         => $this->scalar($raw['SubdivisionName'] ?? null),
            'latitude'            => $listing->latitude !== null ? (float) $listing->latitude : null,
            'longitude'           => $listing->longitude !== null ? (float) $listing->longitude : null,

            // ----------------------------------------------------------------
            // Property classification
            // ----------------------------------------------------------------
            'property_type'       => $listing->property_type ?: null,
            'property_sub_type'   => $listing->property_sub_type ?: null,
            'new_construction'    => (bool) $listing->new_construction_yn,

            // ----------------------------------------------------------------
            // Core specs
            // ----------------------------------------------------------------
            'beds'                => $listing->bedrooms_total !== null ? (int) $listing->bedrooms_total : null,
            'baths_full'          => isset($raw['BathroomsFull'])   ? (int) $raw['BathroomsFull']   : null,
            'baths_half'          => isset($raw['BathroomsHalf'])   ? (int) $raw['BathroomsHalf']   : null,
            'baths_total'         => $listing->bathrooms_total_integer !== null ? (int) $listing->bathrooms_total_integer : null,
            'sqft'                => $listing->living_area !== null ? (int) $listing->living_area : null,
            'sqft_display'        => $listing->living_area !== null ? number_format((int) $listing->living_area) : null,
            'lot_size_sqft'       => $listing->lot_size_sqft ?: null,
            'lot_size_acres'      => isset($raw['LotSizeAcres']) && $raw['LotSizeAcres'] !== ''
                ? (float) $raw['LotSizeAcres'] : null,
            'year_built'          => $listing->year_built ?: null,
            'stories'             => isset($raw['Stories']) && $raw['Stories'] !== '' ? (int) $raw['Stories'] : null,
            'levels'              => $this->scalar($raw['Levels'] ?? null),

            // ----------------------------------------------------------------
            // Market facts
            // ----------------------------------------------------------------
            'days_on_market'      => isset($raw['DaysOnMarket']) ? (int) $raw['DaysOnMarket'] : null,
            'on_market_date'      => $this->scalar($raw['OnMarketDate'] ?? null),

            // ----------------------------------------------------------------
            // Description (PublicRemarks only — no internal/agent remarks)
            // ----------------------------------------------------------------
            'public_remarks'      => $this->scalar($raw['PublicRemarks'] ?? null),

            // ----------------------------------------------------------------
            // Feature arrays — all normalised through parseArray()
            // ----------------------------------------------------------------
            'interior_features'   => $this->arr($raw['InteriorFeatures']   ?? null),
            'exterior_features'   => $this->arr($raw['ExteriorFeatures']   ?? null),
            'community_features'  => $this->arr($raw['CommunityFeatures']  ?? null),
            'appliances'          => $this->arr($raw['Appliances']         ?? null),
            'cooling'             => $this->arr($raw['Cooling']            ?? null),
            'heating'             => $this->arr($raw['Heating']            ?? null),
            'parking_features'    => $this->arr($raw['ParkingFeatures']    ?? null),
            'flooring'            => $this->arr($raw['Flooring']           ?? null),
            'construction_materials' => $this->arr($raw['ConstructionMaterials'] ?? null),
            'roof'                => $this->arr($raw['Roof']               ?? null),
            'foundation'          => $this->arr($raw['FoundationDetails']  ?? null),
            'laundry'             => $this->arr($raw['LaundryFeatures']    ?? null),
            'fireplace_features'  => $this->arr($raw['FireplaceFeatures']  ?? null),
            'pool_features'       => $this->arr($raw['PoolFeatures']       ?? null),
            'spa_features'        => $this->arr($raw['SpaFeatures']        ?? null),
            'view'                => $this->arr($raw['View']               ?? null),
            'waterfront_features' => $this->arr($raw['WaterfrontFeatures'] ?? null),
            'accessibility'       => $this->arr($raw['AccessibilityFeatures'] ?? null),
            'other_structures'    => $this->arr($raw['OtherStructures']    ?? null),
            'patio_porch'         => $this->arr($raw['PatioAndPorchFeatures'] ?? null),
            'security'            => $this->arr($raw['SecurityFeatures']   ?? null),
            'window_features'     => $this->arr($raw['WindowFeatures']     ?? null),
            'utilities'           => $this->arr($raw['Utilities']          ?? null),
            'sewer'               => $this->arr($raw['Sewer']              ?? null),
            'water_source'        => $this->arr($raw['WaterSource']        ?? null),

            // ----------------------------------------------------------------
            // Amenity flags (native boolean columns + raw_json scalars)
            // ----------------------------------------------------------------
            'pool'                => (bool) $listing->pool_private_yn,
            'garage'              => (bool) $listing->garage_yn,
            'garage_spaces'       => isset($raw['GarageSpaces']) && $raw['GarageSpaces'] !== ''
                ? (int) $raw['GarageSpaces'] : null,
            'carport_spaces'      => isset($raw['CarportSpaces']) && $raw['CarportSpaces'] !== ''
                ? (int) $raw['CarportSpaces'] : null,
            'spa'                 => isset($raw['SpaYN'])
                ? (filter_var($raw['SpaYN'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true)
                : false,
            'waterfront'          => (bool) $listing->waterfront_yn,
            'view_yn'             => (bool) $listing->view_yn,
            'water_view'          => (bool) $listing->water_view_yn,
            'pets_allowed'        => $listing->pets_allowed ?: null,
            'senior_community'    => (bool) $listing->senior_community_yn,
            'cdd'                 => (bool) $listing->cdd_yn,
            'fireplace'           => isset($raw['FireplaceYN'])
                ? (filter_var($raw['FireplaceYN'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true)
                : !empty($this->arr($raw['FireplaceFeatures'] ?? null)),

            // ----------------------------------------------------------------
            // HOA / financial (native columns + raw_json for name/frequency)
            // ----------------------------------------------------------------
            'hoa'                 => (bool) $listing->association_yn,
            'hoa_fee'             => $listing->association_fee !== null ? (float) $listing->association_fee : null,
            'hoa_fee_display'     => $listing->association_fee !== null
                ? '$' . number_format((float) $listing->association_fee, 0) : null,
            'hoa_frequency'       => $this->scalar($raw['AssociationFeeFrequency'] ?? null),
            'hoa_name'            => $this->scalar($raw['AssociationName']          ?? null),
            'hoa_amenities'       => $this->arr($raw['AssociationAmenities']        ?? null),
            'tax_annual'          => $listing->tax_annual_amount !== null ? (float) $listing->tax_annual_amount : null,
            'tax_annual_display'  => $listing->tax_annual_amount !== null
                ? '$' . number_format((float) $listing->tax_annual_amount, 0) : null,

            // ----------------------------------------------------------------
            // Schools
            // ----------------------------------------------------------------
            'school_elementary'   => $this->scalar($raw['ElementarySchool']       ?? null),
            'school_middle'       => $this->scalar($raw['MiddleOrJuniorSchool']    ?? null),
            'school_high'         => $this->scalar($raw['HighSchool']              ?? null),

            // ----------------------------------------------------------------
            // Listing office (IDX-permitted — name only, no agent PII)
            // ----------------------------------------------------------------
            'list_office_name'    => $this->scalar($raw['ListOfficeName'] ?? null),

            // ----------------------------------------------------------------
            // Photos — sorted by Order, MediaURL only, max 50
            // ----------------------------------------------------------------
            'photos'              => $this->parsePhotos($raw),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Extract and sort MediaURL values from the Media[] array.
     * Returns up to 50 direct CDN URLs; skips items without a MediaURL.
     */
    private function parsePhotos(array $raw): array
    {
        $media = $raw['Media'] ?? [];
        if (!is_array($media) || empty($media)) {
            return [];
        }

        usort($media, fn($a, $b) => (int) ($a['Order'] ?? 0) <=> (int) ($b['Order'] ?? 0));

        $urls = [];
        foreach ($media as $item) {
            if (!empty($item['MediaURL'])) {
                $urls[] = (string) $item['MediaURL'];
            }
            if (count($urls) >= 50) {
                break;
            }
        }
        return $urls;
    }

    /**
     * Normalise a feature field (JSON array, comma-separated string, or null)
     * into a clean, deduplicated array of non-empty strings.
     */
    private function arr($value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            $items   = is_array($decoded) ? $decoded : array_map('trim', explode(',', $value));
        } else {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('trim', array_map('strval', $items)),
            fn($s) => $s !== ''
        )));
    }

    /**
     * Normalise a scalar field to a trimmed non-empty string or null.
     */
    private function scalar($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = is_array($value) ? implode(', ', $value) : trim((string) $value);
        return $str !== '' ? $str : null;
    }
}
