<?php

namespace App\Services\Bridge\OData;

use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Translates a BuyerCriteriaPayload into a valid OData $filter string for the
 * Bridge Interactive API (rental/lease listings).
 *
 * Tenant criteria uses the same BuyerCriteriaPayload DTO with rental-specific
 * fields populated by TenantOfferListingCriteriaLoader. Key differences from
 * the buyer builder:
 *
 *   - PropertyType values are rental subtypes confirmed via live Bridge API import
 *     on 2026-06-23:
 *       'Residential'     — residential rental listings (list_price = monthly rent)
 *       'Commercial Lease' — commercial lease listings  (list_price = monthly rent)
 *     These exact strings are stored by TenantOfferListingCriteriaLoader in
 *     payload->propertyTypes, so this builder simply passes them through.
 *
 *   - max_price (payload->maxPrice) maps to ListPrice as a monthly rent ceiling.
 *     NOTE: TenantOfferListingCriteriaLoader currently sets max_price=null to
 *     avoid misapplication against sale list_price on bridge_properties. When a
 *     dedicated Bridge rental endpoint is in use, max_price will be populated and
 *     this clause will emit correctly.
 *
 * OData field mapping (Bridge Interactive API → payload field):
 *   StandardStatus   — always 'Active'                          (payload: n/a, always emitted)
 *   ListPrice        — monthly rent in whole dollars            (payload: max_price)
 *   BedroomsTotal    — integer bedroom count                    (payload: min_bedrooms)
 *   PropertyType     — rental type string                       (payload: propertyTypes[])
 *   Latitude         — decimal degrees WGS-84                   (payload: radiusSearches / polygons)
 *   Longitude        — decimal degrees WGS-84                   (payload: radiusSearches / polygons)
 *
 * OData field names confirmed against Bridge Interactive OData metadata:
 *   https://api.bridgedataoutput.com/api/v2/OData/{dataset}/$metadata
 *
 * Geographic filtering — COARSE PRE-FILTER ONLY:
 *   Radius searches are converted to axis-aligned bounding boxes (squares), not circles.
 *   Drawn polygons are reduced to their min/max lat/lng envelope (bounding rectangle).
 *   Both approximations intentionally over-fetch: properties in box corners or outside
 *   the drawn shape will be included in Bridge results. Exact Haversine radius checks
 *   and point-in-polygon (PIP) tests are performed by TenantMatchService AFTER the
 *   Bridge candidate set is returned — these filters are not a geographic gate.
 *   See PolygonBoundingBox for full rationale.
 *
 * OData 4.0 encoding rules:
 *   - String literals are single-quoted: StandardStatus eq 'Active'
 *   - Numeric comparisons use no quotes: ListPrice le 2500
 *   - Clauses are joined with ' and '
 */
class TenantCriteriaODataFilterBuilder implements CriteriaODataFilterBuilderInterface
{
    /**
     * Build an OData $filter string for tenant/rental criteria.
     *
     * Null payload fields are omitted rather than producing a malformed filter.
     * The StandardStatus clause is always present.
     *
     * @param  BuyerCriteriaPayload $payload
     * @return string
     */
    public function build(BuyerCriteriaPayload $payload): string
    {
        $clauses = [];

        $clauses[] = "StandardStatus eq 'Active'";

        $propertyTypeClause = $this->buildPropertyTypeClause($payload->propertyTypes);
        if ($propertyTypeClause !== null) {
            $clauses[] = $propertyTypeClause;
        }

        if ($payload->maxPrice !== null) {
            $clauses[] = "ListPrice le {$payload->maxPrice}";
        }

        if ($payload->minBedrooms !== null) {
            $clauses[] = "BedroomsTotal ge {$payload->minBedrooms}";
        }

        $geoClause = $this->buildGeoClause($payload);
        if ($geoClause !== null) {
            $clauses[] = $geoClause;
        }

        return implode(' and ', $clauses);
    }

    /**
     * Build a PropertyType filter clause for one or more rental property types.
     *
     * Single type:  PropertyType eq 'Residential'
     * Multiple:     (PropertyType eq 'Residential' or PropertyType eq 'Commercial Lease')
     *
     * @param  string[] $propertyTypes
     * @return string|null
     */
    private function buildPropertyTypeClause(array $propertyTypes): ?string
    {
        $propertyTypes = array_values(array_filter($propertyTypes, fn($t) => is_string($t) && $t !== ''));

        if (empty($propertyTypes)) {
            return null;
        }

        if (count($propertyTypes) === 1) {
            $escaped = $this->escapeString($propertyTypes[0]);
            return "PropertyType eq '{$escaped}'";
        }

        $parts = array_map(
            fn($t) => "PropertyType eq '{$this->escapeString($t)}'",
            $propertyTypes
        );

        return '(' . implode(' or ', $parts) . ')';
    }

    /**
     * Build a geographic bounding-box filter clause from radius searches and/or polygons.
     *
     * Each search area produces a bounding-box group:
     *   (Latitude ge {min} and Latitude le {max} and Longitude ge {min} and Longitude le {max})
     *
     * Multiple areas are joined with ' or ':
     *   ((bbox1) or (bbox2))
     *
     * Returns null when no geographic constraints are present in the payload.
     *
     * @param  BuyerCriteriaPayload $payload
     * @return string|null
     */
    private function buildGeoClause(BuyerCriteriaPayload $payload): ?string
    {
        $boxes = PolygonBoundingBox::fromPayload($payload);

        if ($boxes === null || empty($boxes)) {
            return null;
        }

        $boxClauses = [];
        foreach ($boxes as $box) {
            $boxClauses[] = sprintf(
                '(Latitude ge %s and Latitude le %s and Longitude ge %s and Longitude le %s)',
                $this->formatCoord($box['min_lat']),
                $this->formatCoord($box['max_lat']),
                $this->formatCoord($box['min_lng']),
                $this->formatCoord($box['max_lng'])
            );
        }

        if (count($boxClauses) === 1) {
            return $boxClauses[0];
        }

        return '(' . implode(' or ', $boxClauses) . ')';
    }

    /**
     * Format a coordinate float for OData: up to 6 decimal places, no trailing zeros.
     *
     * @param  float $coord
     * @return string
     */
    private function formatCoord(float $coord): string
    {
        return rtrim(rtrim(number_format($coord, 6, '.', ''), '0'), '.');
    }

    /**
     * Escape a string value for use inside an OData single-quoted literal.
     * OData escapes a single quote by doubling it: O'Brien → O''Brien.
     *
     * @param  string $value
     * @return string
     */
    private function escapeString(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
