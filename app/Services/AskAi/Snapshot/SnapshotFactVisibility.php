<?php

namespace App\Services\AskAi\Snapshot;

/**
 * SnapshotFactVisibility — Centralised visibility classification for snapshot facts.
 *
 * ==========================================================================================
 * FAIL-CLOSED CONTRACT (P0 — Property Q&A privacy remediation)
 * ==========================================================================================
 * This class used to be DEFAULT-OPEN: any key absent from the restricted list was classified
 * 'public_allowed'. That made "public_allowed" a statement about what nobody had got round to
 * classifying, rather than a statement about what is safe to publish — and every canonical key
 * added to AskAiContextBuilderService afterwards became public by omission.
 *
 * It is now an INTERSECTION, matching the discipline used by CompatibilityPreferencePolicy,
 * LandlordScreeningPolicy and the MLS display allow-lists: a fact becomes public by being
 * NAMED here, never by escaping a deny-list.
 *
 * Three tiers are returned:
 *
 *   'public_allowed' — explicitly allow-listed below, AND belonging to a role whose listings
 *                      describe a property (seller / landlord). Safe to surface publicly.
 *   'restricted'     — compliance-sensitive (flood zone, deposits, income thresholds, seller
 *                      financing). Carries a disclosure obligation. Never public, and blocked
 *                      at read time by AskAiKnowledgeSearchService for every scope.
 *   'owner_only'     — THE DEFAULT. Everything else, including every unrecognised or newly
 *                      added key. Not public; still readable by the listing owner.
 *
 * WHY 'owner_only' IS NOT 'restricted':
 *   AskAiKnowledgeSearchService blocks any fact with restricted=true for ALL scopes, owner
 *   included. Defaulting unclassified keys to 'restricted' would therefore have silently
 *   removed the owner's own Ask AI answers across the whole platform. 'owner_only' sets
 *   public_allowed=false while leaving restricted=false, so the owner's existing experience
 *   is unchanged and only the PUBLIC claim is withdrawn.
 *
 * ROLE SCOPING (decision D2):
 *   Buyer and tenant listings are search CRITERIA, not properties — they carry negotiating
 *   positions (budget ceilings) and applicant-adjacent data. No buyer or tenant fact is ever
 *   public, so those roles short-circuit to 'owner_only' regardless of key.
 *
 * CALLING CONTRACT:
 *   classify($key, $role) — $role omitted or unrecognised fails closed to 'owner_only'.
 *   Callers that know the role MUST pass it; the four snapshot builders do.
 *
 * SCOPE NOTE (P0):
 *   Keys already classified 'restricted' are NOT promoted to public here, even where decision
 *   D1 approves them in principle (flood zone, security deposit, CDD). Promoting them requires
 *   the disclosure language D1 asks for, which ships with the Property Q&A surface in a later
 *   phase. P0 only removes the default-open hole; it widens nothing.
 *
 * PII (names, phone, email, brokerage) is excluded by the context builder and never reaches
 * a fact row at all.
 * ==========================================================================================
 */
class SnapshotFactVisibility
{
    public const PUBLIC_ALLOWED = 'public_allowed';
    public const RESTRICTED     = 'restricted';
    public const OWNER_ONLY     = 'owner_only';

    /**
     * Roles whose listings describe a property and may therefore carry public facts.
     * Buyer and tenant are deliberately absent (decision D2).
     */
    private const PUBLIC_ELIGIBLE_ROLES = ['seller', 'landlord'];

    /**
     * Listing-type aliases → canonical role, mirroring the snapshot builder's TYPE_ALIASES.
     */
    private const ROLE_ALIASES = [
        'seller'                  => 'seller',
        'seller_agent_auction'    => 'seller',
        'property_auction'        => 'seller',
        'buyer'                   => 'buyer',
        'buyer_agent_auction'     => 'buyer',
        'buyer_criteria_auction'  => 'buyer',
        'landlord'                => 'landlord',
        'landlord_agent_auction'  => 'landlord',
        'landlord_auction'        => 'landlord',
        'tenant'                  => 'tenant',
        'tenant_agent_auction'    => 'tenant',
        'tenant_criteria_auction' => 'tenant',
    ];

    /**
     * Canonical fact keys that must be stored as 'restricted'.
     * Compliance-sensitive fields carrying a disclosure obligation. Unchanged in P0 —
     * adding a key here would withdraw the owner's own DB-backed answer for it.
     */
    private const RESTRICTED_KEYS = [
        // Flood zone / environmental compliance
        'flood_zone_code',
        'flood_zone_designation',
        'flood_zone_description',
        'is_in_flood_zone',

        // Financial thresholds with disclosure obligations
        'security_deposit',
        'security_deposit_amount',
        'income_requirement',
        'income_requirement_amount',
        'income_multiplier',

        // HOA / CDD amounts (compliance-disclosure fields in seller context)
        'hoa_monthly_fee',
        'hoa_annual_fee',
        'cdd_annual_amount',
        'cdd_monthly_amount',

        // Rental pricing fields used in landlord/tenant contexts
        'rental_price',
        'min_rent',
        'max_rent',

        // Seller financing terms
        'seller_financing_down_payment',
        'seller_financing_interest_rate',
        'seller_financing_term',
    ];

    /**
     * Ordinary public property facts shared by seller and landlord listings.
     * Approved under decision D1. Every entry is an ordinary, observable property
     * characteristic that a listing page already publishes.
     */
    private const SHARED_PUBLIC_KEYS = [
        // Identity / description
        'description',
        'address',
        'property_zip',

        // Size & configuration
        'bedrooms',
        'bathrooms',
        'square_feet',
        'year_built',
        'unit_size',
        'lot_size',
        'total_acreage',
        'lot_dimensions',
        'zoning',

        // Interior
        'interior_features',
        'appliances',
        'furnished',
        'condition_prop',
        'property_items',

        // Structural / exterior
        'roof_type',
        'exterior_construction',
        'foundation',
        'heating_and_fuel',
        'heating_fuel',
        'air_conditioning',
        'building_features',

        // Views & water
        'water_view',
        'view',
        'waterfront',
        'waterfront_feet',
        'water_access',

        // Parking
        'garage',
        'garage_spaces',
        'carport',
        'parking_terms',

        // Utilities
        'utilities',
        'water',
        'water_source',
        'sewer',

        // Pool
        'pool',
        'pool_type',

        // HOA / community (fee AMOUNTS approved public under D1)
        'has_hoa',
        'hoa_association',
        'hoa_fee',
        'association_fee_amount',
        'hoa_payment_schedule',
        'association_fee_frequency',
        'association_name',
        'hoa_name',
        'association_fee_includes',
        'association_amenities',
        'association_approval_required',

        // Taxes
        'annual_property_taxes',
        'tax_year',
    ];

    /**
     * Public facts specific to seller / for-sale listings (decision D1).
     */
    private const SELLER_PUBLIC_KEYS = [
        'asking_price',
        'parcel_id',
        'legal_description',
        'home_warranty_offered',
        'occupant_status',
        'closing_date',

        // CDD & special assessments (existence + amount approved public under D1)
        'has_cdd',
        'annual_cdd_fee',
        'has_special_assessments',
        'special_assessment_amount',
        'special_assessment_description',
        'additional_parcels',
        'total_parcel_count',

        // Pets & restrictions
        'pets_allowed',
        'number_of_pets_allowed',
        'max_pet_weight',
        'pet_restrictions',
        'rental_restrictions',
    ];

    /**
     * Public facts specific to landlord / for-rent listings (decision D1).
     *
     * NOTE: screening, qualification, occupancy-limit and income fields are deliberately
     * ABSENT pending the Fair Housing review required by decision D4. Do not add any field
     * that characterises, ranks or qualifies an APPLICANT — only fields describing the
     * property or the offered lease terms belong here.
     */
    private const LANDLORD_PUBLIC_KEYS = [
        'rent_amount',
        'available_date',
        'lease_length',
        'lease_terms',
        'additional_lease_terms',
        'renewal_option',
        'leasing_restrictions',
        'number_of_units',

        // Policies describing the property, not the applicant
        'smoking_policy',
        'subletting_policy',

        // Pets (the policy as written — never an applicant characterisation)
        'pet_policy',
        'pet_species_allowed',
        'pet_fee_type',
        'pet_fee_amount',
        'pet_fee_other',
        'pet_max_weight_lbs',
        'pet_deposit_fee_rent',
    ];

    /**
     * Returns the visibility tier for the given canonical fact key.
     *
     * Fails closed: an unrecognised key, a missing role, or a role that is not
     * public-eligible all resolve to 'owner_only'.
     *
     * @param  string      $key   Canonical fact key from the listing context.
     * @param  string|null $role  Listing type (canonical or aliased). Omitted → fails closed.
     * @return string             One of PUBLIC_ALLOWED | RESTRICTED | OWNER_ONLY.
     */
    public static function classify(string $key, ?string $role = null): string
    {
        // Compliance-sensitive keys are restricted for every role, including the owner's
        // own listing, and regardless of the allow-list.
        if (in_array($key, self::RESTRICTED_KEYS, true)) {
            return self::RESTRICTED;
        }

        $canonicalRole = self::canonicalRole($role);

        // Fail closed: no role, unknown role, or a criteria role (buyer/tenant, D2).
        if ($canonicalRole === null || !in_array($canonicalRole, self::PUBLIC_ELIGIBLE_ROLES, true)) {
            return self::OWNER_ONLY;
        }

        return in_array($key, self::publicKeysForRole($canonicalRole), true)
            ? self::PUBLIC_ALLOWED
            : self::OWNER_ONLY;
    }

    /**
     * True when the key is explicitly approved for public display for the given role.
     */
    public static function isPublic(string $key, ?string $role = null): bool
    {
        return self::classify($key, $role) === self::PUBLIC_ALLOWED;
    }

    /**
     * The complete public allow-list for a canonical role. Empty for any role that is
     * not public-eligible.
     *
     * @return string[]
     */
    public static function publicKeysForRole(string $role): array
    {
        $canonicalRole = self::canonicalRole($role);

        return match ($canonicalRole) {
            'seller'   => array_values(array_unique(array_merge(self::SHARED_PUBLIC_KEYS, self::SELLER_PUBLIC_KEYS))),
            'landlord' => array_values(array_unique(array_merge(self::SHARED_PUBLIC_KEYS, self::LANDLORD_PUBLIC_KEYS))),
            default    => [],
        };
    }

    /**
     * All keys classified 'restricted'. Exposed for tests and audit tooling.
     *
     * @return string[]
     */
    public static function restrictedKeys(): array
    {
        return self::RESTRICTED_KEYS;
    }

    /**
     * Resolve a listing-type alias to its canonical role, or null when unrecognised.
     */
    private static function canonicalRole(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return self::ROLE_ALIASES[strtolower(trim($role))] ?? null;
    }

    /**
     * Derives a human-readable label from a snake_case canonical key.
     * e.g. 'flood_zone_code' → 'Flood Zone Code'
     */
    public static function deriveLabel(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Detects the storage type of a fact value.
     * Returns one of: 'null', 'json', 'numeric', 'boolean', 'string'.
     */
    public static function detectValueType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            return 'json';
        }
        $str = trim((string) $value);
        if ($str === '') {
            return 'null';
        }
        if (str_starts_with($str, '{') || str_starts_with($str, '[')) {
            return 'json';
        }
        if (is_numeric($str)) {
            return 'numeric';
        }
        if (in_array(strtolower($str), ['true', 'false', 'yes', 'no'], true)) {
            return 'boolean';
        }
        return 'string';
    }
}
