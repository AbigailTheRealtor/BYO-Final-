<?php

namespace App\Services\Stellar;

use App\Models\TenantAgentAuction;
use Illuminate\Support\Facades\DB;

/**
 * Loads a TenantAgentAuction offer-listing record and maps its EAV meta
 * into the flat array accepted by BuyerCriteriaPayload::__construct().
 *
 * Covers all property types stored in tenant_agent_auctions:
 *   Residential Tenant, Commercial Lease Tenant.
 *
 * Only records with workflow_type meta = 'offer_listing' are eligible.
 *
 * Key rename table (offer listing EAV → matcher payload key):
 *   zipCodes              → preferred_zip_codes  (JSON decode; legacy loader hardcoded [])
 *   counties              → preferred_counties   (JSON decode)
 *   maximum_budget/budget → max_price            (maximum_budget preferred)
 *   bedrooms/other_bedrooms → min_bedrooms       (handles 'custom' value)
 *   bathrooms/other_bathrooms → min_bathrooms    (handles 'custom' value)
 *   minimum_heated_square → min_sqft             (positive int)
 *   pool_needed           → wants_pool           (tristate bool)
 *   garage_needed         → wants_garage         (tristate bool)
 *   view_preference       → wants_water_view + wants_any_view
 *   leasing_55_plus       → is_55_plus_eligible  (bool)
 *   condition_prop_buyer  → property_sub_types   (JSON decode)
 *   property_type (scalar)→ property_types       (single-element array)
 *   location_dna blob     → preferred_cities, radius_searches, polygons
 */
class TenantOfferListingCriteriaLoader
{
    private const WATER_VIEW_KEYWORDS = [
        'water', 'ocean', 'lake', 'bay', 'gulf', 'river',
        'waterfront', 'intracoastal', 'canal', 'pond',
    ];

    /**
     * Load and map a specific TenantAgentAuction offer-listing record by ID.
     *
     * Access rule: record's user_id must be in $allowedUserIds.
     *
     * @param  int   $recordId       The ID of the TenantAgentAuction record.
     * @param  int[] $allowedUserIds User IDs permitted to own this record.
     * @return array|null            Flat array for BuyerCriteriaPayload, or null.
     */
    public function loadById(int $recordId, array $allowedUserIds): ?array
    {
        $record = $this->findRecord($allowedUserIds, $recordId);
        return $record ? $this->mapRecord($record) : null;
    }

    /**
     * Load and map the most-recent active offer-listing record for a user.
     *
     * @param  int $userId
     * @return array|null
     */
    public function load(int $userId): ?array
    {
        $record = $this->findRecord([$userId], null);
        return $record ? $this->mapRecord($record) : null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function findRecord(array $allowedUserIds, ?int $recordId): ?TenantAgentAuction
    {
        $offerListingIds = DB::table('tenant_agent_auction_metas')
            ->where('meta_key', 'workflow_type')
            ->where('meta_value', 'offer_listing')
            ->pluck('tenant_agent_auction_id');

        if ($offerListingIds->isEmpty()) {
            return null;
        }

        $query = TenantAgentAuction::whereIn('id', $offerListingIds)
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

    private function mapRecord(TenantAgentAuction $record): ?array
    {
        $get = function (string $key) use ($record) {
            $val = $record->info($key);
            return ($val === false || $val === null) ? null : $val;
        };

        // -----------------------------------------------------------------------
        // property_types — normalised to single-element array
        // -----------------------------------------------------------------------
        $propertyTypeRaw = $get('property_type');
        if ($propertyTypeRaw !== null && str_contains(strtolower($propertyTypeRaw), 'commercial')) {
            $propertyTypes = ['Commercial'];
        } else {
            $propertyTypes = ['Residential'];
        }

        // -----------------------------------------------------------------------
        // Location — LDNA blob for cities/radius/polygons; separate keys for zip/county
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

        $preferredCities = ($ldnaBlobPresent && array_key_exists('cities', $ldna))
            ? $this->decodeJsonMeta($ldna['cities'])
            : $this->decodeJsonMeta($get('cities'));

        $radiusSearches = ($ldnaBlobPresent && array_key_exists('radius_searches', $ldna))
            ? (is_array($ldna['radius_searches']) ? $ldna['radius_searches'] : [])
            : [];

        $polygons = ($ldnaBlobPresent && array_key_exists('polygons', $ldna))
            ? (is_array($ldna['polygons']) ? $ldna['polygons'] : [])
            : [];

        $preferredZipCodes = $this->decodeJsonMeta($get('zipCodes'));
        $preferredCounties = $this->decodeJsonMeta($get('counties'));

        // -----------------------------------------------------------------------
        // Price — tenant criteria use a monthly rental budget, not a sale price.
        // bridge_properties.list_price stores sale prices ($200k+), so applying
        // maximum_budget (~$1k-$5k/mo) as a list_price ceiling would filter out
        // all results. max_price must be null so BuyerMatchQueryBuilder skips the
        // price ceiling filter entirely for tenant offer listing criteria.
        // -----------------------------------------------------------------------
        $maxBudgetRaw = $get('maximum_budget');
        $budgetRaw    = $get('budget');
        // Kept for potential future use in a tenant-specific scorer, but not
        // emitted as max_price to avoid misapplication against sale list_price.
        $this->positiveIntOrNull($this->stripCommas($maxBudgetRaw ?? $budgetRaw)); // parsed but unused as ceiling
        $maxPrice     = null;

        // -----------------------------------------------------------------------
        // Property sub-types
        // -----------------------------------------------------------------------
        $propertySubTypes = $this->decodeJsonMeta($get('condition_prop_buyer'));

        // -----------------------------------------------------------------------
        // Bedrooms / bathrooms — handle 'custom' value
        // -----------------------------------------------------------------------
        $bedroomsRaw    = $get('bedrooms');
        $otherBedrooms  = $get('other_bedrooms');
        $effectiveBeds  = ($bedroomsRaw === 'custom') ? $otherBedrooms : $bedroomsRaw;
        $minBedrooms    = $this->positiveIntOrNull($effectiveBeds);

        $bathroomsRaw    = $get('bathrooms');
        $otherBathrooms  = $get('other_bathrooms');
        $effectiveBaths  = ($bathroomsRaw === 'custom') ? $otherBathrooms : $bathroomsRaw;
        $minBathrooms    = $this->positiveIntOrNull($effectiveBaths);

        $minSqft = $this->positiveIntOrNull($this->stripCommas($get('minimum_heated_square')));

        // -----------------------------------------------------------------------
        // Amenities
        // -----------------------------------------------------------------------
        $wantsPool   = $this->yesNoTristate($get('pool_needed'));
        $wantsGarage = $this->yesNoTristate($get('garage_needed'));

        $viewPreferenceRaw = $get('view_preference');
        $viewArr           = $this->decodeJsonMeta($viewPreferenceRaw);
        $wantsWaterView    = $this->extractWaterView($viewArr);
        $wantsAnyView      = !empty($viewArr) ? true : null;

        // -----------------------------------------------------------------------
        // Community / Eligibility
        // -----------------------------------------------------------------------
        $is55Plus = $this->is55PlusBool($get('leasing_55_plus'));

        $petInfoRaw       = $get('pet_information') ?? $get('pets');
        $wantsPetFriendly = ($petInfoRaw !== null && $petInfoRaw !== '' && $petInfoRaw !== 'none')
            ? true
            : null;

        return [
            'property_types'              => $propertyTypes,
            'is_55_plus_eligible'         => $is55Plus,

            'preferred_cities'            => $preferredCities,
            'preferred_zip_codes'         => $preferredZipCodes,
            'preferred_counties'          => $preferredCounties,
            'radius_searches'             => $radiusSearches,
            'polygons'                    => $polygons,
            'preferred_subdivisions'      => [],
            'preferred_mls_areas'         => [],

            'max_price'                   => $maxPrice,
            'ideal_price'                 => null,

            'property_sub_types'          => $propertySubTypes,

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
            'wants_any_view'              => $wantsAnyView,

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

    // =========================================================================
    // Utility helpers
    // =========================================================================

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

    private function is55PlusBool(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '') {
            return false;
        }
        $result = $this->yesNoTristate($value);
        return $result === true;
    }

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

    private function stripCommas(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(',', '', $value);
        }
        return $value;
    }
}
