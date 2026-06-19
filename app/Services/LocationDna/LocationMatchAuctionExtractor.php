<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\Log;

/**
 * LocationMatchAuctionExtractor — Phase 6D Controller Helper
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * Extracts location match insights for Buyer and Tenant agent auction bid-detail
 * pages. This class is the ONLY place that reads auction meta keys for location
 * match purposes and calls LocationMatchIntegrationService.
 *
 * WHY SELLER AND LANDLORD ARE EXCLUDED
 * ──────────────────────────────────────
 * Location Match requires two sides:
 *   supply side  — a property's physical location (city, ZIP, neighbourhood, lat/lng)
 *   demand side  — a buyer's or tenant's preferred search areas (cities, ZIP codes,
 *                  drawn polygons, radius searches stored as location_dna_preferences)
 *
 * Seller and Landlord agent auction bid-detail pages are supply-side contexts.
 * They hold a property at a known location, but there is no buyer or tenant in
 * scope whose preferences could be compared against it. Calling this extractor
 * on those auctions would always return an empty insights array because
 * LocationMatchIntegrationService::build() short-circuits when $preferences is [].
 *
 * Seller and Landlord controllers therefore intentionally omit $locationMatchInsights
 * from their view data. The Blade partial resolves the missing variable to [] via
 * ($locationMatchInsights ?? []) and renders "No location match data available." —
 * accurately communicating that preferences have not been captured, not that a
 * match was attempted and failed.
 *
 * Buyer and Tenant controllers call extractInsights() because their auctions
 * carry demand-side data (client_areas_of_interest, zipCodes) alongside an
 * optional specific property (property_city, zip_code). When both sides are
 * present the service returns human-readable insight strings.
 *
 * Data sources (Buyer / Tenant agent auctions only):
 *   preferences  — location_dna_preferences (canonical JSON → cities, zip_codes,
 *                                           neighborhoods, polygons, radius_searches)
 *                  client_areas_of_interest (legacy JSON string[] → cities, merged)
 *                  zipCodes                 (legacy JSON string[] → zip_codes, merged)
 *   propertyData — property_city            (string)
 *                  zip_code / property_zip  (string, first non-empty wins)
 *
 * This service MUST NEVER:
 *   - Be called from Seller or Landlord bid-detail controllers.
 *   - Call LocationMatchIntegrationService on Seller or Landlord auctions.
 *   - Introduce Blade logic, routes, or Livewire components.
 *   - Perform matching or insight logic — delegate only to the service.
 * ==================================================================================
 */
class LocationMatchAuctionExtractor
{
    public function __construct(
        private readonly LocationMatchIntegrationService $integrationService,
    ) {}

    /**
     * Compute location match insights for a Buyer or Tenant agent auction.
     *
     * Returns a string[] of human-readable insight lines, or an empty array
     * when preferences or propertyData are absent / insufficient.
     *
     * @param  mixed $auction  A BuyerAgentAuction or TenantAgentAuction model
     *                         instance (must respond to info()).
     * @return string[]
     */
    public function extractInsights(mixed $auction): array
    {
        try {
            $preferences  = $this->buildPreferences($auction);
            $propertyData = $this->buildPropertyData($auction);

            $result = $this->integrationService->build($preferences, $propertyData);

            return $result['insights'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('LocationMatchAuctionExtractor: failed to extract insights', [
                'auction_class' => get_class($auction),
                'auction_id'    => $auction->id ?? null,
                'error'         => $e->getMessage(),
            ]);

            return [];
        }
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    /**
     * Build the preferences array from auction meta keys.
     *
     * @return array  Shape expected by LocationMatchIntegrationService::build().
     */
    private function buildPreferences(mixed $auction): array
    {
        /* ── Canonical source: location_dna_preferences JSON ── */
        $dnaRaw = $this->metaValue($auction, 'location_dna_preferences');
        $dna    = [];
        if (!empty($dnaRaw)) {
            $decoded = is_array($dnaRaw) ? $dnaRaw : json_decode($dnaRaw, true);
            if (is_array($decoded)) {
                $dna = $decoded;
            }
        }

        $dnaCities      = $this->decodeStringArray($dna['cities']          ?? []);
        $dnaZips        = $this->decodeStringArray($dna['zip_codes']        ?? []);
        $dnaNeighborhoods = $this->decodeStringArray($dna['neighborhoods']  ?? []);
        $dnaPolygons    = is_array($dna['polygons']        ?? null) ? ($dna['polygons']        ?? []) : [];
        $dnaRadii       = is_array($dna['radius_searches'] ?? null) ? ($dna['radius_searches'] ?? []) : [];

        /* ── Legacy sources: client_areas_of_interest + zipCodes ── */
        $legacyCities = $this->decodeStringArray($this->metaValue($auction, 'client_areas_of_interest'));
        $legacyZips   = $this->decodeStringArray($this->metaValue($auction, 'zipCodes'));

        /* Merge both sources (canonical wins; legacy fills in when canonical is empty) */
        $cities        = array_values(array_unique(array_merge($dnaCities, $legacyCities)));
        $zips          = array_values(array_unique(array_merge($dnaZips, $legacyZips)));
        $neighborhoods = $dnaNeighborhoods;
        $polygons      = $dnaPolygons;
        $radii         = $dnaRadii;

        if (empty($cities) && empty($zips) && empty($neighborhoods) && empty($polygons) && empty($radii)) {
            return [];
        }

        return [
            'cities'          => $cities,
            'zip_codes'       => $zips,
            'neighborhoods'   => $neighborhoods,
            'polygons'        => $polygons,
            'radius_searches' => $radii,
        ];
    }

    /**
     * Build the propertyData array from auction meta keys.
     *
     * @return array  Shape expected by LocationMatchIntegrationService::build().
     */
    private function buildPropertyData(mixed $auction): array
    {
        $city = (string) ($this->metaValue($auction, 'property_city') ?? '');
        $zip  = (string) ($this->metaValue($auction, 'zip_code')
                       ?: $this->metaValue($auction, 'property_zip')
                       ?? '');

        if ($city === '' && $zip === '') {
            return [];
        }

        return [
            'city'         => $city,
            'zip'          => $zip,
            'neighborhood' => '',
            'lat'          => 0.0,
            'lng'          => 0.0,
        ];
    }

    /**
     * Read a single meta value from the auction model via info().
     */
    private function metaValue(mixed $auction, string $key): mixed
    {
        return method_exists($auction, 'info') ? $auction->info($key) : null;
    }

    /**
     * Decode a raw meta string into a clean string[].
     *
     * Accepts JSON arrays or bare scalar strings. Filters empty values.
     *
     * @return string[]
     */
    private function decodeStringArray(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn ($v) => is_string($v) && $v !== ''));
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($v) => is_string($v) && $v !== ''));
        }

        return (is_string($raw) && $raw !== '') ? [$raw] : [];
    }
}
