<?php

namespace App\Services\Canonical;

/**
 * CanonicalListingVocabulary — every key a {@see CanonicalListing} may carry,
 * its type, and which side of the market it describes.
 *
 * WHY A DECLARED VOCABULARY
 * -------------------------
 * CanonicalListing stores its values in a keyed bag so that provenance can sit
 * beside each one. A bag with no declaration is the "arbitrary keys" shape this
 * seam exists to prevent: a consumer would have to learn a source's key names,
 * which is the dependency the canonical layer removes. So every key is declared
 * here ONCE, with one type and one meaning, and an adapter may emit only a
 * declared key (a test asserts it for the BYO adapter).
 *
 * SUPPLY AND DEMAND ARE DIFFERENT KEYS, NOT DIFFERENT READINGS OF ONE KEY
 * -----------------------------------------------------------------------
 * A Seller or Landlord row describes a property that exists. A Buyer or Tenant
 * row describes what somebody is looking for. BidYourOffer stores both under
 * overlapping meta names, several of them demand-shaped even on a supply row —
 * `minimum_heated_square` is a Seller's actual heated area, `pool_needed` /
 * `garage_needed` say whether the property HAS one, and `maximum_budget` is the
 * Seller's Desired Sale Price. The canonical key names the FACT
 * (`property.living_area_sqft`, `property.pool`), never the storage key, and a
 * supply key is populated only from a supply row. A Buyer's "3 bedrooms" is a
 * criterion; it is never `property.bedrooms`.
 *
 *   SIDE_SUPPLY — a fact about a listed property or its listing.
 *   SIDE_DEMAND — a seeker's profile or preference (`demand.*`, `pet.profile.*`).
 *
 * PROPERTY FACTS AND LISTING FACTS
 * --------------------------------
 * `property.*` and `location.*` describe the physical property; `listing.*`
 * describes this offer of it (its status, its transaction, its price). A
 * CanonicalListing carries both because it is a READ MODEL of one listing. That
 * is not a decision to persist a property and a listing as one record — the
 * persisted canonical Property is deliberately deferred to the
 * physical-property identity work.
 *
 * WHAT IS DELIBERATELY ABSENT
 * ---------------------------
 *   · No provider-specific key (`stellar_*`, `bridge_*`, raw feed field names).
 *     A second MLS must populate these same keys without a consumer changing.
 *   · No transaction-workflow term: Your Terms, bidding windows, counters,
 *     compatibility preferences, screening rules, compensation. Those are the
 *     BidYourOffer workflow, not the property or the listing.
 *   · No persisted canonical id. Identity is still (listing_type, listing_id)
 *     on the CanonicalListing itself. An MLS record's canonical listing also
 *     carries its provider-scoped native identity (provider, listing_key) as a
 *     non-persisted reference beside the fields — never as a key (P0-5). An
 *     MLS-linked BYO row does not carry it yet.
 *   · No description or media. Landlord prose passes a Fair Housing gate, MLS
 *     PublicRemarks is licence-restricted and MLS media carries its own licence
 *     policy; none of that is needed for listing convergence yet.
 *
 * RESO is the semantic reference for the core keys (noted per key). This is
 * not a claim of RESO certification, and the names are this application's.
 */
final class CanonicalListingVocabulary
{
    public const SIDE_SUPPLY = 'supply';
    public const SIDE_DEMAND = 'demand';

    public const TYPE_STRING = 'string';
    public const TYPE_INT    = 'int';
    public const TYPE_FLOAT  = 'float';
    public const TYPE_BOOL   = 'bool';
    public const TYPE_ARRAY  = 'array';

    /**
     * RESO StandardStatus values a canonical listing may state. The source-neutral
     * status vocabulary IS RESO's; recognition is delegated to
     * {@see \App\Support\Listing\MlsSourceStatus::recognised()} rather than
     * restated, so this list and that class cannot disagree.
     */
    public const STATUS_ACTIVE  = 'Active';
    public const STATUS_PENDING = 'Pending';

    // ── Core keys added by P0-4 ─────────────────────────────────────────────

    /** 'sale' | 'lease' — {@see \App\Support\Listing\PropertyTypeVocabulary} constants. */
    public const LISTING_TRANSACTION_TYPE = 'listing.transaction_type';

    /** RESO StandardStatus, verbatim spelling. */
    public const LISTING_STANDARD_STATUS = 'listing.standard_status';

    /** 'offer_listing' | 'hire_agent' — {@see \App\Support\Listing\ListingWorkflow}. */
    public const LISTING_WORKFLOW = 'listing.workflow';

    /**
     * RESO ListPrice: the price of record. On a lease listing it is the periodic
     * rent, and {@see LISTING_LEASE_AMOUNT_FREQUENCY} says per what. NEVER a
     * BidYourOffer "Your Terms" figure.
     */
    public const LISTING_LIST_PRICE = 'listing.list_price';

    /**
     * RESO LeaseAmountFrequency for a lease list price. Declared so a lease price
     * is never read without its period. The value is the platform's existing
     * normalized token — {@see \App\Services\ListingImport\MlsNormalizer::normalizeLeaseFrequency()}
     * (`monthly`, `weekly`, `daily`, `annually`, `seasonal`, `month_to_month`,
     * …), the one normalization matching (`MonthlyEquivalent`) and Explore
     * already consume — never a second lease-period vocabulary.
     *
     * The MLS adapter populates it from the feed (P0-5). A BidYourOffer Landlord
     * row does store a `lease_amount_frequency` meta, but the BYO adapter does not
     * read it yet, so on a BYO row the period is absent. Absent means unknown and
     * a consumer must treat it so — never assume monthly.
     */
    public const LISTING_LEASE_AMOUNT_FREQUENCY = 'listing.lease_amount_frequency';

    /**
     * The platform's property category — Residential | Income | Commercial |
     * Business | Vacant Land — as {@see \App\Support\Listing\PropertyTypeVocabulary}
     * classifies it. Transaction is NOT folded in: a Residential lease is
     * `property.type = Residential` + `listing.transaction_type = lease`.
     */
    public const PROPERTY_TYPE = 'property.type';

    /** RESO BedroomsTotal. */
    public const PROPERTY_BEDROOMS = 'property.bedrooms';

    /** RESO BathroomsTotalDecimal (1.5 = one full and one half). */
    public const PROPERTY_BATHROOMS = 'property.bathrooms';

    /** RESO LivingArea in square feet — heated living area. */
    public const PROPERTY_LIVING_AREA_SQFT = 'property.living_area_sqft';

    /** RESO YearBuilt. */
    public const PROPERTY_YEAR_BUILT = 'property.year_built';

    /** RESO PoolPrivateYN — the property HAS a pool. */
    public const PROPERTY_POOL = 'property.pool';

    /** RESO GarageYN — the property HAS a garage. */
    public const PROPERTY_GARAGE = 'property.garage';

    /** RESO UnparsedAddress — the street line as the source stores it. */
    public const LOCATION_ADDRESS_LINE = 'location.address_line';
    public const LOCATION_CITY         = 'location.city';
    public const LOCATION_STATE        = 'location.state';
    public const LOCATION_POSTAL_CODE  = 'location.postal_code';
    public const LOCATION_COUNTY       = 'location.county';

    /**
     * A measurable coordinate only: populated solely from a coordinate whose
     * precision is exact and whose address still matches (the coordinate
     * ladder's gate). A ZIP centroid or an unprovenanced meta point is absent,
     * never approximated.
     */
    public const LOCATION_LATITUDE  = 'location.latitude';
    public const LOCATION_LONGITUDE = 'location.longitude';

    /**
     * The P0-4 core vocabulary: key => [type, side].
     *
     * @var array<string, array{0:string, 1:string}>
     */
    public const CORE = [
        self::LISTING_TRANSACTION_TYPE       => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LISTING_STANDARD_STATUS        => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LISTING_WORKFLOW               => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LISTING_LIST_PRICE             => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        self::LISTING_LEASE_AMOUNT_FREQUENCY => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::PROPERTY_TYPE                  => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::PROPERTY_BEDROOMS              => [self::TYPE_INT,    self::SIDE_SUPPLY],
        self::PROPERTY_BATHROOMS             => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        self::PROPERTY_LIVING_AREA_SQFT      => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        self::PROPERTY_YEAR_BUILT            => [self::TYPE_INT,    self::SIDE_SUPPLY],
        self::PROPERTY_POOL                  => [self::TYPE_BOOL,   self::SIDE_SUPPLY],
        self::PROPERTY_GARAGE                => [self::TYPE_BOOL,   self::SIDE_SUPPLY],
        self::LOCATION_ADDRESS_LINE          => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LOCATION_CITY                  => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LOCATION_STATE                 => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LOCATION_POSTAL_CODE           => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LOCATION_COUNTY                => [self::TYPE_STRING, self::SIDE_SUPPLY],
        self::LOCATION_LATITUDE              => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        self::LOCATION_LONGITUDE             => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
    ];

    /**
     * The keys that existed before P0-4 and that the DNA score services read.
     * Declared unchanged — same names, same meanings, same types — so the whole
     * vocabulary is in one place. They are NOT renamed: three score services and
     * their tests depend on them.
     *
     * `property.lot_acreage` is the lot-size fact; no second lot key is added.
     * `property.structure_type` is the (multi-valued) subtype fact; no separate
     * subtype key is added.
     *
     * @var array<string, array{0:string, 1:string}>
     */
    public const EXTENSION = [
        // Supply-side property facts (Seller / Landlord).
        'property.structure_type'      => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],
        'property.hoa_fee_includes'    => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],
        'property.community_amenities' => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],
        'property.lot_acreage'         => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'property.condition'           => [self::TYPE_STRING, self::SIDE_SUPPLY],
        'property.waterfront'          => [self::TYPE_BOOL,   self::SIDE_SUPPLY],
        'property.water_access'        => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],
        'property.water_view'          => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],
        'property.water_frontage_feet' => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'property.view_preference'     => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],

        // Supply-side pet POLICY (what the property permits).
        'pet.policy.pets_allowed'           => [self::TYPE_BOOL,   self::SIDE_SUPPLY],
        'pet.policy.max_weight_lbs'         => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.species_allowed'        => [self::TYPE_ARRAY,  self::SIDE_SUPPLY],
        'pet.policy.has_breed_restrictions' => [self::TYPE_BOOL,   self::SIDE_SUPPLY],
        'pet.policy.deposit_amount'         => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.monthly_fee'            => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.rent'                   => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.fee'                    => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.fee_type'               => [self::TYPE_STRING, self::SIDE_SUPPLY],
        'pet.policy.fee_amount'             => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.fee_other'              => [self::TYPE_STRING, self::SIDE_SUPPLY],
        'pet.policy.has_fee'                => [self::TYPE_BOOL,   self::SIDE_SUPPLY],
        'pet.policy.fee_other_amount'       => [self::TYPE_FLOAT,  self::SIDE_SUPPLY],
        'pet.policy.fee_other_text'         => [self::TYPE_STRING, self::SIDE_SUPPLY],

        // Demand-side seeker profile and preferences (Buyer / Tenant).
        'demand.current_status'   => [self::TYPE_STRING, self::SIDE_DEMAND],
        'demand.purchase_purpose' => [self::TYPE_STRING, self::SIDE_DEMAND],
        'demand.age_targeted'     => [self::TYPE_BOOL,   self::SIDE_DEMAND],
        'demand.view_preference'  => [self::TYPE_ARRAY,  self::SIDE_DEMAND],
        'pet.profile.has_pets'    => [self::TYPE_BOOL,   self::SIDE_DEMAND],
        'pet.profile.count'       => [self::TYPE_FLOAT,  self::SIDE_DEMAND],
        'pet.profile.weight_lbs'  => [self::TYPE_FLOAT,  self::SIDE_DEMAND],
        'pet.profile.species'     => [self::TYPE_ARRAY,  self::SIDE_DEMAND],
        'pet.profile.breed'       => [self::TYPE_STRING, self::SIDE_DEMAND],
    ];

    /**
     * The listing type of an MLS record's canonical listing: a `bridge_properties`
     * row, id = `bridge_properties.id`. The same token the shared listing-type
     * registry produces for that table (a test pins the two equal). It names
     * the local table, not the provider —
     * the provider-scoped native identity travels separately on the
     * CanonicalListing, never in a key name.
     */
    public const MLS_LISTING_TYPE = 'bridge';

    /** Listing types whose rows describe a property on offer. */
    public const SUPPLY_LISTING_TYPES = ['seller_agent', 'landlord_agent', self::MLS_LISTING_TYPE];

    /** Listing types whose rows describe what a seeker wants. */
    public const DEMAND_LISTING_TYPES = ['buyer_agent', 'tenant_agent'];

    /** @return array<string, array{0:string, 1:string}> */
    public static function all(): array
    {
        return self::CORE + self::EXTENSION;
    }

    public static function isDeclared(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function typeOf(string $key): ?string
    {
        return self::all()[$key][0] ?? null;
    }

    public static function sideOf(string $key): ?string
    {
        return self::all()[$key][1] ?? null;
    }

    public static function isSupplyListingType(string $listingType): bool
    {
        return in_array($listingType, self::SUPPLY_LISTING_TYPES, true);
    }

    public static function isDemandListingType(string $listingType): bool
    {
        return in_array($listingType, self::DEMAND_LISTING_TYPES, true);
    }

    /** Does $value have the declared type of $key? Null is never a value here. */
    public static function valueMatchesType(string $key, mixed $value): bool
    {
        return match (self::typeOf($key)) {
            self::TYPE_STRING => is_string($value) && trim($value) !== '',
            self::TYPE_INT    => is_int($value),
            self::TYPE_FLOAT  => is_float($value),
            self::TYPE_BOOL   => is_bool($value),
            self::TYPE_ARRAY  => is_array($value) && $value !== [],
            default           => false,
        };
    }
}
