<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Throwable;

/**
 * LocationDnaGeocodeService — Phase B Address / Geocode Service
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the deterministic geocoding foundation for the Location DNA
 * pipeline. It stores and caches geocoded lat/lng for any listing using the
 * Google Maps Geocoding API (same API key already used in Livewire components).
 *
 * This service MUST NEVER:
 *   - Connect to the AI marketing report or Property DNA persistence pipelines.
 *   - Perform AI or OpenAI calls of any kind.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Populate summary_json or generated_at (reserved for future phases).
 *   - Reference POI (Points of Interest) calculations.
 * ==================================================================================
 */
class LocationDnaGeocodeService
{
    private const GEOCODE_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    private const REQUIRED_ADDRESS_FIELDS = ['address', 'city', 'state'];

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
    ) {}

    /**
     * Geocode a listing address and persist the result to property_location_dna.
     *
     * Returns the approved Phase B output contract in all cases:
     * [
     *     'success'      => bool,          // true only when status === 'geocoded'
     *     'status'       => string,        // 'geocoded' | 'skipped' | 'failed'
     *     'listing_type' => string,        // echoed from $listingType
     *     'listing_id'   => int,           // echoed from $listingId
     *     'lat'          => float|null,    // populated on success, null otherwise
     *     'lng'          => float|null,    // populated on success, null otherwise
     *     'source'       => string|null,   // 'google' on success, null otherwise
     *     'error'        => string|null,   // error/skip reason, null on success
     * ]
     *
     * Logic:
     *   (a) Validate minimum required fields (address, city, state).
     *       Returns status='skipped', error='missing_required_address_fields' if absent.
     *   (b) Find or initialise a PropertyLocationDna record for the listing_type + listing_id.
     *   (c) If record is 'geocoded' and ALL address fields (address, city, state, county, zip)
     *       are unchanged, return the cached result.
     *   (d) If any address field changed, clear prior lat/lng and reset status to 'pending'.
     *   (e) Call Google Maps Geocoding API via Guzzle.
     *   (f) On success: set status 'geocoded', store lat/lng, geocode_source='google', geocoded_at.
     *   (g) On API failure or empty result: persist status 'failed' with error detail.
     *   (h) Entire method is wrapped in try/catch(Throwable). On exception, if a record
     *       was already initialised, persist geocode_status='failed' + geocode_error, then
     *       return failed output without re-throwing.
     *
     * @param  string $listingType  The listing model type (e.g. 'seller_agent_auction').
     * @param  int    $listingId    The primary key of the listing record.
     * @param  array  $addressData  Must contain 'address', 'city', 'state'. May include 'county', 'zip'.
     * @return array                Approved Phase B eight-key output contract.
     */
    public function geocodeForListing(string $listingType, int $listingId, array $addressData): array
    {
        // Declared outside try so the catch block can persist failure on the record.
        $record = null;

        try {
            // (a) Validate minimum required fields
            foreach (self::REQUIRED_ADDRESS_FIELDS as $field) {
                if (empty($addressData[$field])) {
                    return $this->skippedOutput($listingType, $listingId, 'missing_required_address_fields');
                }
            }

            $address = trim($addressData['address']);
            $city    = trim($addressData['city']);
            $state   = trim($addressData['state']);
            $county  = trim($addressData['county'] ?? '');
            $zip     = trim($addressData['zip'] ?? '');

            // (b) Find or initialise the record
            $record = PropertyLocationDna::firstOrNew([
                'listing_type' => $listingType,
                'listing_id'   => $listingId,
            ]);

            // (c) Return cached result when ALL address fields are unchanged and status is geocoded.
            //     ZIP and county are included so a county/ZIP-only change correctly invalidates the cache.
            if (
                $record->exists &&
                $record->geocode_status === 'geocoded' &&
                $record->source_address === $address &&
                $record->source_city    === $city &&
                $record->source_state   === $state &&
                ($record->source_county ?? '') === $county &&
                ($record->source_zip    ?? '') === $zip
            ) {
                return $this->geocodedOutput(
                    $listingType,
                    $listingId,
                    (float) $record->geocoded_lat,
                    (float) $record->geocoded_lng,
                );
            }

            // (d) If any address field changed, clear prior geocode data
            if (
                $record->exists && (
                    $record->source_address  !== $address ||
                    $record->source_city     !== $city    ||
                    $record->source_state    !== $state   ||
                    ($record->source_county ?? '') !== $county ||
                    ($record->source_zip    ?? '') !== $zip
                )
            ) {
                $record->geocoded_lat   = null;
                $record->geocoded_lng   = null;
                $record->geocode_source = null;
                $record->geocode_error  = null;
                $record->geocoded_at    = null;
                $record->geocode_status = 'pending';
            }

            // Persist current address fields
            $record->source_address = $address;
            $record->source_city    = $city;
            $record->source_state   = $state;
            $record->source_county  = $county ?: null;
            $record->source_zip     = $zip    ?: null;

            if (! $record->exists) {
                $record->geocode_status = 'pending';
            }

            $record->save();

            // (e) Call Google Maps Geocoding API
            $apiKey = config('services.google.places_key');

            if (blank($apiKey)) {
                return $this->failedOutput($listingType, $listingId, 'missing_google_api_key');
            }

            $fullAddress = "{$address}, {$city}, {$state}" . ($zip ? " {$zip}" : '');
            $client      = $this->httpClient ?? new Client();

            $response = $client->request('GET', self::GEOCODE_API_URL, [
                'query' => [
                    'address' => $fullAddress,
                    'key'     => $apiKey,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (
                empty($body['results']) ||
                ! isset($body['results'][0]['geometry']['location'])
            ) {
                // (g) Empty / zero result — persist failed status with detail
                $apiStatus = $body['status'] ?? 'UNKNOWN';
                $errorMsg  = "Geocoding API returned no results. Status: {$apiStatus}";

                $record->geocode_status = 'failed';
                $record->geocode_error  = $errorMsg;
                $record->save();

                return $this->failedOutput($listingType, $listingId, $errorMsg);
            }

            // (f) Success — persist geocoded data
            $location = $body['results'][0]['geometry']['location'];
            $lat      = (float) $location['lat'];
            $lng      = (float) $location['lng'];

            $record->geocoded_lat   = $lat;
            $record->geocoded_lng   = $lng;
            $record->geocode_source = 'google';
            $record->geocode_status = 'geocoded';
            $record->geocode_error  = null;
            $record->geocoded_at    = now();
            $record->save();

            return $this->geocodedOutput($listingType, $listingId, $lat, $lng);

        } catch (Throwable $e) {
            // (h) Catch-all — persist failed status when the record was already initialised,
            //     then return failed output without re-throwing.
            if ($record !== null) {
                try {
                    $record->geocode_status = 'failed';
                    $record->geocode_error  = $e->getMessage();
                    $record->save();
                } catch (Throwable) {
                    // Swallow secondary DB failure to ensure output is always returned.
                }
            }

            return $this->failedOutput($listingType, $listingId, $e->getMessage());
        }
    }

    // =========================================================================
    // Output shape helpers — approved Phase B eight-key contract in all cases
    // =========================================================================

    private function geocodedOutput(
        string $listingType,
        int    $listingId,
        float  $lat,
        float  $lng,
    ): array {
        return [
            'success'      => true,
            'status'       => 'geocoded',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'lat'          => $lat,
            'lng'          => $lng,
            'source'       => 'google',
            'error'        => null,
        ];
    }

    private function skippedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'skipped',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'lat'          => null,
            'lng'          => null,
            'source'       => null,
            'error'        => $error,
        ];
    }

    private function failedOutput(string $listingType, int $listingId, ?string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'failed',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'lat'          => null,
            'lng'          => null,
            'source'       => null,
            'error'        => $error,
        ];
    }
}
