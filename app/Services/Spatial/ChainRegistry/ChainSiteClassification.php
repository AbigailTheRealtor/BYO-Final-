<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * What one chain's membership at one physical site amounts to. Produced only by
 * {@see ChainSiteClassifier}.
 *
 *   storefront              the chain's own store is present
 *   storefront_unconfirmed  departments only (a Publix pharmacy with no Publix supermarket):
 *                           credible identity, retained internally, never a storefront
 *   fuel_only               fuel only, at a chain permitted a fuel-only format (7-Eleven)
 *   unsupported_format      fuel only at a chain that is not (Wawa, RaceTrac, Speedway)
 *
 * `displayQualifier` is the ONLY user-visible text this layer offers: "Fuel only" for a fuel-only
 * site, a declared visible format label ("Supercenter"), or null. A store that also sells fuel
 * gets no "with fuel" suffix — `store_with_fuel` is internal structured metadata (decision 10).
 */
final class ChainSiteClassification
{
    public const STATUS_STOREFRONT = 'storefront';
    public const STATUS_STOREFRONT_UNCONFIRMED = 'storefront_unconfirmed';
    public const STATUS_FUEL_ONLY = 'fuel_only';
    public const STATUS_UNSUPPORTED_FORMAT = 'unsupported_format';

    public const SITE_FORMAT_STORE_WITH_FUEL = 'store_with_fuel';
    public const SITE_FORMAT_FUEL_ONLY = 'fuel_only';

    public const FUEL_ONLY_QUALIFIER = 'Fuel only';

    public function __construct(
        public readonly string $brandKey,
        public readonly string $status,
        public readonly ?string $siteFormat,
        public readonly ?string $displayQualifier,
        public readonly bool $hasFuel,
    ) {
    }

    /** A query that requires a storefront may return this site. */
    public function qualifiesForStorefrontQuery(): bool
    {
        return $this->status === self::STATUS_STOREFRONT;
    }

    /**
     * A generic brand query may return this site. A fuel-only site may appear, carrying its
     * "Fuel only" qualifier (v2 decision 3d); a storefront_unconfirmed site stays internal
     * (chain-registry decision 2); an unsupported one never appears.
     */
    public function qualifiesForGenericBrandQuery(): bool
    {
        return $this->status === self::STATUS_STOREFRONT || $this->status === self::STATUS_FUEL_ONLY;
    }
}
