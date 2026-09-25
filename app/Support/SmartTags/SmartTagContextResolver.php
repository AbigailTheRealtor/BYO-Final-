<?php

namespace App\Support\SmartTags;

/**
 * Resolves a stored property type to one of the seven canonical contexts.
 *
 * EXACT, AND FAIL-CLOSED. Each source spells property types differently —
 * Bridge `Residential Lease`, Seller `Residential`, Landlord `Residential
 * Property` — and the existing helpers are unsafe for this job:
 *
 *   • PropertyTypeVocabulary::forRole() matches substrings, so a lease read
 *     through it becomes a sale;
 *   • PropertyTypeVocabulary::classifySource('Residential Property') answers
 *     `sale`, because stripping " Property" makes it the feed's Residential.
 *
 * So this class holds its own exact maps, and for native listings the
 * transaction comes from the ROLE (Seller/Buyer sell, Landlord/Tenant lease),
 * never from the property-type string. Anything unrecognised — a typo, a
 * legacy spelling, an empty value — resolves to null, and a listing with no
 * context receives no derivation and no selectable tags.
 */
final class SmartTagContextResolver
{
    /**
     * Bridge `PropertyType`, exact after trimming.
     *
     * `Residential Income` and `Land` are the RESO PropertyType values the live Stellar feed
     * actually sends for income and land listings; `Income` and `Vacant Land` are kept for
     * rows already stored under those spellings. Two spellings, one context each.
     */
    public const BRIDGE = [
        'Residential'          => SmartTagContext::ResidentialSale,
        'Income'               => SmartTagContext::IncomeSale,
        'Residential Income'   => SmartTagContext::IncomeSale,
        'Commercial Sale'      => SmartTagContext::CommercialSale,
        'Business Opportunity' => SmartTagContext::BusinessSale,
        'Vacant Land'          => SmartTagContext::LandSale,
        'Land'                 => SmartTagContext::LandSale,
        'Residential Lease'    => SmartTagContext::ResidentialLease,
        'Commercial Lease'     => SmartTagContext::CommercialLease,
    ];

    /** Seller (and Buyer) Offer Listing `property_type`, exact after trimming. */
    public const SALE_ROLE = [
        'Residential' => SmartTagContext::ResidentialSale,
        'Income'      => SmartTagContext::IncomeSale,
        'Commercial'  => SmartTagContext::CommercialSale,
        'Business'    => SmartTagContext::BusinessSale,
        'Vacant Land' => SmartTagContext::LandSale,
    ];

    /** Landlord (and Tenant) Offer Listing `property_type`, exact after trimming. */
    public const LEASE_ROLE = [
        'Residential Property' => SmartTagContext::ResidentialLease,
        'Commercial Property'  => SmartTagContext::CommercialLease,
    ];

    /**
     * Buyer CRITERIA `property_type` meta — a DIFFERENT vocabulary from the
     * Offer Listing one above, and that is why this map exists.
     *
     * The Buyer Criteria wizard offers 'Residential Property', 'Income Property',
     * 'Commercial Property', 'Business Opportunity', 'Vacant Land'. SALE_ROLE
     * expects 'Residential', 'Income', 'Commercial', 'Business'. Only
     * 'Vacant Land' is spelled the same in both, so reading a criteria row
     * through SALE_ROLE resolves FOUR of the five property types to null — a
     * buyer would silently receive no tags at all rather than an error.
     */
    public const BUYER_CRITERIA = [
        'Residential Property' => SmartTagContext::ResidentialSale,
        'Income Property'      => SmartTagContext::IncomeSale,
        'Commercial Property'  => SmartTagContext::CommercialSale,
        'Business Opportunity' => SmartTagContext::BusinessSale,
        'Vacant Land'          => SmartTagContext::LandSale,
    ];

    /**
     * Tenant CRITERIA `property_type` meta. Its two strings happen to match
     * LEASE_ROLE, but it is written out rather than aliased: the two flows are
     * separate forms whose option lists can drift, and an alias would carry that
     * drift silently. Note 'Residential Property' and 'Commercial Property'
     * appear in BUYER_CRITERIA too and mean SALE there — the ROLE picks the map,
     * which is exactly why one shared map would be wrong.
     */
    public const TENANT_CRITERIA = [
        'Residential Property' => SmartTagContext::ResidentialLease,
        'Commercial Property'  => SmartTagContext::CommercialLease,
    ];

    public static function forBridge(?string $propertyType): ?SmartTagContext
    {
        return self::lookup(self::BRIDGE, $propertyType);
    }

    public static function forListingType(SmartTagListingType $type, ?string $propertyType): ?SmartTagContext
    {
        return match ($type) {
            SmartTagListingType::Bridge        => self::forBridge($propertyType),
            SmartTagListingType::SellerAgent   => self::lookup(self::SALE_ROLE, $propertyType),
            SmartTagListingType::LandlordAgent => self::lookup(self::LEASE_ROLE, $propertyType),
        };
    }

    /**
     * For a Buyer/Tenant OFFER LISTING, whose forms store the same vocabulary as
     * the Seller/Landlord ones (`Residential`, `Income`, `Commercial`, `Business`,
     * `Vacant Land`; `Residential Property`, `Commercial Property`). Role decides
     * the transaction. Seeker preference code reaches this through
     * {@see forSeekerSubject()}, never by role string.
     */
    public static function forSeeker(string $role, ?string $propertyType): ?SmartTagContext
    {
        return match ($role) {
            'buyer'  => self::lookup(self::SALE_ROLE, $propertyType),
            'tenant' => self::lookup(self::LEASE_ROLE, $propertyType),
            default  => null,
        };
    }

    /**
     * For a Buyer/Tenant CRITERIA record — the seeker preference surface.
     *
     * Separate from {@see forSeeker()} because that one reads the Offer Listing
     * vocabulary. Fail-closed in both directions: an unknown role and an
     * unrecognised property type both resolve to null, and a null context means
     * the seeker is offered no tags and can persist none.
     */
    public static function forSeekerCriteria(string $role, ?string $propertyType): ?SmartTagContext
    {
        return match ($role) {
            'buyer'  => self::lookup(self::BUYER_CRITERIA, $propertyType),
            'tenant' => self::lookup(self::TENANT_CRITERIA, $propertyType),
            default  => null,
        };
    }

    /**
     * For ANY seeker preference subject — THE entry point the seeker writer and
     * reader use.
     *
     * The subject type, not the role, chooses the vocabulary
     * ({@see SmartTagSeekerSubjectType::propertyTypeContexts()}): Buyer Criteria
     * and Buyer Offer Listing are both buyers but store different strings, and
     * `Residential Property` is a sale on one form and a lease on another. The
     * result is additionally required to be a context the subject type can ever
     * reach, so a map edit that crossed sale and lease would resolve to null
     * rather than to the wrong side of the market.
     */
    public static function forSeekerSubject(SmartTagSeekerSubjectType $type, ?string $propertyType): ?SmartTagContext
    {
        $context = self::lookup($type->propertyTypeContexts(), $propertyType);

        return $context !== null && in_array($context, $type->possibleContexts(), true)
            ? $context
            : null;
    }

    /**
     * @param array<string, SmartTagContext> $map
     */
    private static function lookup(array $map, ?string $value): ?SmartTagContext
    {
        if ($value === null) {
            return null;
        }

        return $map[trim($value)] ?? null;
    }
}
