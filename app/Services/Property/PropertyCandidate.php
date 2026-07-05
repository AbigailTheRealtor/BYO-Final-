<?php

namespace App\Services\Property;

/**
 * PropertyCandidate — the provider-agnostic, normalized representation of a
 * single property, decoupled from wherever it originated (Bridge/Stellar API,
 * the URL/text listing parser, manual entry, or a future MLS provider).
 *
 * Source adapters (e.g. BridgePropertyCandidateAdapter) are the ONLY layer that
 * knows the origin; every downstream consumer — Seller/Landlord prefill and
 * Buyer/Tenant match analysis — receives this object and must never care where
 * it came from. See docs/mls-direct-import-design-and-plan.md.
 *
 * Fields here are FACTUAL property attributes plus provenance. Compliance note:
 * the `$raw` array carries the untouched source record and MAY contain
 * restricted fields (remarks, contact info, media). This DTO does not enforce
 * any allow-list — the "facts only" launch rule is enforced by the prefill
 * CONSUMER (Phase 3), not here. Buyer/Tenant match analysis may read `$raw`
 * internally for scoring but must never republish restricted keys.
 *
 * Immutable by construction (readonly promoted properties).
 */
class PropertyCandidate
{
    public function __construct(
        // ── Provenance ──────────────────────────────────────────────────────
        public readonly string $source,            // e.g. 'bridge', 'url_parser', 'manual'
        public readonly ?string $sourceRecordId,   // local id within the source (e.g. bridge_properties.id)

        // ── Identity / classification ───────────────────────────────────────
        public readonly ?string $mlsNumber,        // ListingId — the human-facing "MLS #"
        public readonly ?string $listingKey,       // ListingKey — globally unique key
        public readonly ?string $standardStatus,
        public readonly ?string $mlsStatus,
        public readonly ?string $propertyType,
        public readonly ?string $propertySubType,

        // ── Price ───────────────────────────────────────────────────────────
        public readonly ?float $listPrice,

        // ── Address ─────────────────────────────────────────────────────────
        public readonly ?string $unparsedAddress,
        public readonly ?string $city,
        public readonly ?string $stateOrProvince,
        public readonly ?string $postalCode,
        public readonly ?string $countyOrParish,

        // ── Structure / size ────────────────────────────────────────────────
        public readonly ?int $bedrooms,
        public readonly ?int $bathrooms,
        public readonly ?int $livingAreaSqft,
        public readonly ?int $lotSizeSqft,
        public readonly ?int $yearBuilt,

        // ── Geo ─────────────────────────────────────────────────────────────
        public readonly ?float $latitude,
        public readonly ?float $longitude,

        // ── Financial ───────────────────────────────────────────────────────
        public readonly ?float $associationFee,
        public readonly ?float $taxAnnualAmount,

        // ── Feature flags ───────────────────────────────────────────────────
        public readonly ?string $petsAllowed,
        public readonly ?bool $pool,
        public readonly ?bool $garage,
        public readonly ?bool $waterfront,
        public readonly ?bool $view,
        public readonly ?bool $waterView,
        public readonly ?bool $seniorCommunity,
        public readonly ?bool $association,
        public readonly ?bool $newConstruction,
        public readonly ?bool $cdd,

        // ── Freshness + full source record ──────────────────────────────────
        public readonly ?string $modificationTimestamp = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Flat, snake_case representation. Excludes `$raw` by default so callers
     * do not accidentally leak restricted source fields; pass true to include it.
     */
    public function toArray(bool $includeRaw = false): array
    {
        $data = [
            'source'                 => $this->source,
            'source_record_id'       => $this->sourceRecordId,
            'mls_number'             => $this->mlsNumber,
            'listing_key'            => $this->listingKey,
            'standard_status'        => $this->standardStatus,
            'mls_status'             => $this->mlsStatus,
            'property_type'          => $this->propertyType,
            'property_sub_type'      => $this->propertySubType,
            'list_price'             => $this->listPrice,
            'unparsed_address'       => $this->unparsedAddress,
            'city'                   => $this->city,
            'state_or_province'      => $this->stateOrProvince,
            'postal_code'            => $this->postalCode,
            'county_or_parish'       => $this->countyOrParish,
            'bedrooms'               => $this->bedrooms,
            'bathrooms'              => $this->bathrooms,
            'living_area_sqft'       => $this->livingAreaSqft,
            'lot_size_sqft'          => $this->lotSizeSqft,
            'year_built'             => $this->yearBuilt,
            'latitude'               => $this->latitude,
            'longitude'              => $this->longitude,
            'association_fee'        => $this->associationFee,
            'tax_annual_amount'      => $this->taxAnnualAmount,
            'pets_allowed'           => $this->petsAllowed,
            'pool'                   => $this->pool,
            'garage'                 => $this->garage,
            'waterfront'             => $this->waterfront,
            'view'                   => $this->view,
            'water_view'             => $this->waterView,
            'senior_community'       => $this->seniorCommunity,
            'association'            => $this->association,
            'new_construction'       => $this->newConstruction,
            'cdd'                    => $this->cdd,
            'modification_timestamp' => $this->modificationTimestamp,
        ];

        if ($includeRaw) {
            $data['raw'] = $this->raw;
        }

        return $data;
    }
}
