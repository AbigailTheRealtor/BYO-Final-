<?php

namespace App\Services\LocationDna;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\SellerAgentAuction;
use App\Models\UsCity;
use App\Models\UsCounty;
use App\Models\UsState;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * LocationDnaPipelineRunner
 *
 * Orchestrates the four Location DNA services in the correct order:
 *   1. LocationDnaGeocodeService::geocodeForListing()
 *   2. LocationDnaPoiDistanceService::calculateForListing()
 *   3. LocationDnaSummaryService::summarizeForListing()
 *   4. LocationDnaLifestyleScoreService::generateForListing()
 *
 * This class only sequences the four existing services — it adds no new service logic.
 * It extracts address data from the listing model before calling the geocode step.
 *
 * Guard checks between steps: if any step returns status 'skipped' or 'failed',
 * the pipeline stops and returns early without calling subsequent steps.
 *
 * This method never throws. All exceptions are caught internally and returned as
 * status='failed' in the output array.
 */
class LocationDnaPipelineRunner
{
    public function __construct(
        private readonly LocationDnaGeocodeService     $geocodeService,
        private readonly LocationDnaPoiDistanceService $poiService,
        private readonly LocationDnaSummaryService     $summaryService,
        private readonly LocationDnaLifestyleScoreService $lifestyleService,
    ) {}

    /**
     * Run the full Location DNA pipeline for a listing.
     *
     * Return shape:
     * [
     *   'status' => 'success|skipped|failed|partial',
     *   'steps'  => [
     *     'geocode'   => $geocodeResult,    // always present
     *     'poi'       => $poiResult,        // present if geocode succeeded
     *     'summary'   => $summaryResult,    // present if poi succeeded
     *     'lifestyle' => $lifestyleResult,  // present if summary succeeded
     *   ],
     * ]
     *
     * Steps that were not reached because an earlier step stopped are omitted.
     * 'status' is 'success' only when all four steps complete without failure;
     * 'partial' when some steps ran but not all; 'skipped' when geocoding skipped
     * due to missing address; 'failed' on any caught exception.
     *
     * @param  string $listingType  'seller' or 'landlord'
     * @param  int    $listingId    Primary key of the listing record
     * @return array
     */
    public function run(string $listingType, int $listingId): array
    {
        try {
            $addressData = $this->resolveAddressData($listingType, $listingId);

            // Step 1: Geocode
            $geocodeResult = $this->geocodeService->geocodeForListing(
                $listingType,
                $listingId,
                $addressData,
            );

            if (! $this->stepSucceeded($geocodeResult)) {
                return [
                    'status' => $geocodeResult['status'] === 'skipped' ? 'skipped' : 'failed',
                    'steps'  => ['geocode' => $geocodeResult],
                ];
            }

            // Step 2: POI distances
            $poiResult = $this->poiService->calculateForListing($listingType, $listingId);

            if (! $this->stepSucceeded($poiResult)) {
                return [
                    'status' => 'partial',
                    'steps'  => [
                        'geocode' => $geocodeResult,
                        'poi'     => $poiResult,
                    ],
                ];
            }

            // Step 3: Summary
            $summaryResult = $this->summaryService->summarizeForListing($listingType, $listingId);

            if (! $this->stepSucceeded($summaryResult)) {
                return [
                    'status' => 'partial',
                    'steps'  => [
                        'geocode' => $geocodeResult,
                        'poi'     => $poiResult,
                        'summary' => $summaryResult,
                    ],
                ];
            }

            // Step 4: Lifestyle scores
            $lifestyleResult = $this->lifestyleService->generateForListing($listingType, $listingId);

            $overallStatus = $this->stepSucceeded($lifestyleResult) ? 'success' : 'partial';

            return [
                'status' => $overallStatus,
                'steps'  => [
                    'geocode'   => $geocodeResult,
                    'poi'       => $poiResult,
                    'summary'   => $summaryResult,
                    'lifestyle' => $lifestyleResult,
                ],
            ];

        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'steps'  => [],
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Determine whether a step result counts as a successful completion.
     * Geocode success: status === 'geocoded'
     * POI success: status === 'completed' or 'cached'
     * Summary success: status === 'completed'
     * Lifestyle success: status === 'completed'
     */
    private function stepSucceeded(array $result): bool
    {
        return $result['success'] ?? false;
    }

    /**
     * Resolve address data from the listing model.
     *
     * For seller (PropertyAuction): reads the direct `address` column and resolves
     * city/state/county names via ID relations. ZIP is stored in EAV meta.
     *
     * For landlord (LandlordAuction): reads address data from EAV meta keys.
     *
     * Returns an array with keys: address, city, state, county (optional), zip (optional).
     * Missing or null values are returned as empty strings so the geocode service can
     * apply its own validation and return the correct skipped/failed status.
     */
    private function resolveAddressData(string $listingType, int $listingId): array
    {
        if ($listingType === 'seller') {
            return $this->resolveSellerAddress($listingId);
        }

        if ($listingType === 'landlord') {
            return $this->resolveLandlordAddress($listingId);
        }

        if ($listingType === 'seller_agent') {
            return $this->resolveSellerAgentAddress($listingId);
        }

        if ($listingType === 'landlord_agent') {
            return $this->resolveLandlordAgentAddress($listingId);
        }

        if ($listingType === 'bridge') {
            return $this->resolveBridgeAddress($listingId);
        }

        return ['address' => '', 'city' => '', 'state' => ''];
    }

    private function resolveSellerAddress(int $listingId): array
    {
        $listing = PropertyAuction::find($listingId);

        if ($listing === null) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        $cityName   = '';
        $stateName  = '';
        $countyName = '';

        if (! empty($listing->city_id)) {
            $city = UsCity::find($listing->city_id);
            $cityName = $city?->name ?? '';
        }

        if (! empty($listing->state_id)) {
            $state = UsState::find($listing->state_id);
            $stateName = $state?->abbreviation ?? '';
        }

        if (! empty($listing->county_id)) {
            $county = UsCounty::find($listing->county_id);
            $countyName = $county?->name ?? '';
        }

        $zip = $listing->get_meta('zip_code') ?? '';

        return [
            'address' => $listing->address ?? '',
            'city'    => $cityName,
            'state'   => $stateName,
            'county'  => $countyName,
            'zip'     => (string) $zip,
        ];
    }

    private function resolveLandlordAddress(int $listingId): array
    {
        if (! Schema::hasTable('landlord_auctions')) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        $listing = LandlordAuction::find($listingId);

        if ($listing === null) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        return [
            'address' => (string) ($listing->info('address') ?: ''),
            'city'    => (string) ($listing->info('property_city') ?: ''),
            'state'   => (string) ($listing->info('property_state') ?: ''),
            'county'  => (string) ($listing->info('property_county') ?: ''),
            'zip'     => (string) ($listing->info('property_zip') ?: ''),
        ];
    }

    /**
     * Resolve address for a SellerAgentAuction (offer listing) record.
     * Address data is stored entirely in EAV meta via info().
     */
    private function resolveSellerAgentAddress(int $listingId): array
    {
        $listing = SellerAgentAuction::find($listingId);

        if ($listing === null) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        return [
            'address' => (string) ($listing->info('address') ?: ''),
            'city'    => (string) ($listing->info('property_city') ?: ''),
            'state'   => (string) ($listing->info('property_state') ?: ''),
            'county'  => (string) ($listing->info('property_county') ?: ''),
            'zip'     => (string) ($listing->info('property_zip') ?: ''),
            'pre_lat' => (string) ($listing->info('property_lat') ?: ''),
            'pre_lng' => (string) ($listing->info('property_lng') ?: ''),
        ];
    }

    /**
     * Resolve address for a LandlordAgentAuction (offer listing) record.
     * Address data is stored entirely in EAV meta via info().
     */
    private function resolveLandlordAgentAddress(int $listingId): array
    {
        $listing = LandlordAgentAuction::find($listingId);

        if ($listing === null) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        return [
            'address' => (string) ($listing->info('address') ?: ''),
            'city'    => (string) ($listing->info('property_city') ?: ''),
            'state'   => (string) ($listing->info('property_state') ?: ''),
            'county'  => (string) ($listing->info('property_county') ?: ''),
            'zip'     => (string) ($listing->info('property_zip') ?: ''),
            'pre_lat' => (string) ($listing->info('property_lat') ?: ''),
            'pre_lng' => (string) ($listing->info('property_lng') ?: ''),
        ];
    }

    /**
     * Resolve address for a BridgeProperty (imported MLS listing) record.
     *
     * Bridge records carry native address columns plus MLS-supplied latitude /
     * longitude. The coordinates are passed through as pre_lat / pre_lng so the
     * geocode service short-circuits and skips the Google Geocoding API call
     * entirely (geocode_source = 'saved_meta'). When coordinates are absent the
     * geocode service falls back to address geocoding using the same fields.
     */
    private function resolveBridgeAddress(int $listingId): array
    {
        if (! Schema::hasTable('bridge_properties')) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        $listing = BridgeProperty::find($listingId);

        if ($listing === null) {
            return ['address' => '', 'city' => '', 'state' => ''];
        }

        return [
            'address' => (string) ($listing->unparsed_address ?: ''),
            'city'    => (string) ($listing->city ?: ''),
            'state'   => (string) ($listing->state_or_province ?: ''),
            'county'  => (string) ($listing->county_or_parish ?: ''),
            'zip'     => (string) ($listing->postal_code ?: ''),
            'pre_lat' => (string) ($listing->latitude ?: ''),
            'pre_lng' => (string) ($listing->longitude ?: ''),
        ];
    }
}
