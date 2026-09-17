<?php

namespace App\Services\Stellar\Matching\DTO;

use App\Services\Explore\ExploreTransactionType;
use App\Services\Offers\ImportantPlacesService;
use App\Support\Location\UsStateCode;

class BuyerCriteriaPayload
{
    public readonly array $preferredCities;
    public readonly array $preferredZipCodes;
    public readonly array $preferredCounties;
    public readonly array $radiusSearches;
    public readonly array $polygons;
    public readonly array $preferredSubdivisions;
    public readonly array $preferredMlsAreas;

    /**
     * The single Preferred State, as a two-letter code, or null when none was
     * given or the stored value could not be recognised.
     *
     * A SCALAR, unlike every other geography field here, because the Search
     * Areas widget offers one Preferred State and not a list. Normalised on the
     * way in by {@see \App\Support\Location\UsStateCode} so that consumers
     * compare against `bridge_properties.state_or_province` — which is the RESO
     * two-letter code — rather than against whatever a user typed.
     *
     * Null is the "no state criterion" signal and covers an unrecognised value
     * as well as an absent one; see the normalizer for why an unknown state
     * widens rather than empties a search.
     */
    public readonly ?string $preferredState;

    public readonly ?int $maxPrice;
    public readonly ?int $idealPrice;

    public readonly array $propertyTypes;
    /**
     * Requested RESO PropertySubType values — "Condominium", "Townhouse",
     * "Single Family Residence".
     *
     * A SEPARATE CONCEPT FROM {@see $propertyConditions}, and the separation is
     * the fix. Both offer-listing loaders used to pour the Acceptable Property
     * Conditions answer (`condition_prop_buyer`: "Updated/Renovated",
     * "Partially Updated", "Older but Clean") into this field, and
     * BuyerMatchScorer compares it to `bridge_properties.property_sub_type`. No
     * condition value can ever equal a sub-type, so a seeker who stated a
     * condition preference scored 0 of the 5 sub-type points where a seeker who
     * stated nothing scored the neutral 2 — stating a preference lowered every
     * listing.
     *
     * Nothing populates this today: no Buyer or Tenant form collects a
     * PropertySubType preference. It stays declared, and stays empty, so the
     * scorer's neutral branch is what runs and the slot is ready for the real
     * criterion rather than occupied by the wrong one.
     */
    public readonly array $propertySubTypes;

    /**
     * Acceptable property CONDITION, in the seeker's own vocabulary
     * ("Updated/Renovated", "Partially Updated", "Older but Clean").
     *
     * CAPTURED, CARRIED, AND DELIBERATELY NOT SCORED AGAINST THE FEED.
     * ----------------------------------------------------------------
     * RESO has a PropertyCondition field and Bridge populates it, but not with
     * this concept: across all seven per-type fixtures in
     * tests/fixtures/mls/bridge/ its only non-empty value is "Completed", and the
     * only other values this application has ever established a meaning for are
     * "Under Construction" and "To Be Built". The feed's PropertyCondition
     * describes CONSTRUCTION STATUS. It is not a renovation state, so comparing a
     * renovation preference to it would be the same category error one field to
     * the left.
     *
     * So the honest position is: keep the preference, and keep it out of the
     * score until something can compare it to a like concept. Where that belongs
     * is a product decision, recorded in
     * docs/audits/byo-reso-match-field-audit.md — this class deliberately does
     * not name another subsystem, because an architecture guard keeps that
     * coupling out of files like this one.
     *
     * ONE THING THE NEXT IMPLEMENTER MUST KNOW: the seeker and owner condition
     * vocabularies are NOT the same strings, so whatever consumes them will need
     * a translation rather than an equality test. The seller form stores
     * "No updates needed: Completely updated", "Semi-updated: Needs minor
     * updates", …; the landlord form stores "Updated / Renovated" and "Older but
     * Well Maintained"; the seeker form stores "Updated/Renovated" (no spaces)
     * and "Older but Clean". Only "Partially Updated" is common to all three.
     * Assuming the strings match would silently match nothing.
     */
    public readonly array $propertyConditions;

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

    /**
     * The client's Important Places, as ImportantPlacesService::normalize() rows — INCLUDING each
     * place's private address and coordinate.
     *
     * Internal to matching. ImportantPlaceMatcher reads the coordinate to measure a straight-line
     * distance, and nothing it returns carries the address or the coordinate. Deliberately NOT part
     * of CriteriaHashService's hash: it changes no MLS request, only how fetched listings are scored.
     */
    public readonly array $importantPlaces;

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

        // Normalised HERE rather than in each loader so that every producer of
        // this payload — four Stellar loaders, Match Check, and any test that
        // builds one by hand — gets the same answer for the same stored string.
        $this->preferredState = UsStateCode::normalize(
            isset($data['preferred_state']) && is_string($data['preferred_state'])
                ? $data['preferred_state']
                : null
        );

        $this->maxPrice   = $maxPrice !== null ? (int) $maxPrice : null;
        $this->idealPrice = $idealPrice !== null ? (int) $idealPrice : null;

        $this->propertySubTypes  = $data['property_sub_types'] ?? [];
        $this->propertyConditions = $data['property_conditions'] ?? [];

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

        // Normalised here, like the state, so every producer of this payload hands the matcher the
        // same canonical row shape the wizards save.
        $this->importantPlaces = (new ImportantPlacesService())->normalize($data['important_places'] ?? []);
    }

    /**
     * Is this a search for RENTAL inventory?
     *
     * WHY THE ANSWER MATTERS: on a lease record `ListPrice` is the periodic rent
     * and the period is `LeaseAmountFrequency`, so a price ceiling means something
     * different here than it does on a sale. Everything frequency-aware in the
     * engine branches on this.
     *
     * The classification is {@see ExploreTransactionType::fromPropertyType()} —
     * an exact-match allowlist held in config/explore.php, which already answers
     * "sale or rent" for this exact PropertyType vocabulary and returns null for a
     * value nobody has classified. Reusing it is deliberate: a second lease-type
     * list written here is precisely the duplicate vocabulary that lets two parts
     * of the system disagree about whether a listing is a rental. Note it is NOT
     * PropertyTypeVocabulary, whose substring matching reads 'Residential Lease'
     * as 'Residential' and turns a rental into a sale.
     *
     * EVERY requested type must be a rental for this to be true. A payload with no
     * types, or a mixed or unclassified set, answers false — which leaves the
     * pre-existing sale behaviour in place rather than applying a rent conversion
     * to something that may be a purchase price.
     */
    public function isLeaseSearch(): bool
    {
        if ($this->propertyTypes === []) {
            return false;
        }

        foreach ($this->propertyTypes as $type) {
            if (ExploreTransactionType::fromPropertyType(is_string($type) ? $type : null)
                !== ExploreTransactionType::RENT) {
                return false;
            }
        }

        return true;
    }
}
