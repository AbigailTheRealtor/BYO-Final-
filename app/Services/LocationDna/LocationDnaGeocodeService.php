<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Services\Schema\ProvenanceSchemaReadiness;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Log;
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
 *   - Make an outbound Google request while GOOGLE_PLACES_ENABLED is false. The kill
 *     switch outranks the credential; a present API key is not permission to geocode.
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
        private readonly ?LocationDnaAuditService $auditService = null,
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
     *   (e) Fail closed unless Google is explicitly enabled. GOOGLE_PLACES_ENABLED=false
     *       returns status='skipped', error='non_google_geocoder_unavailable' and makes
     *       ZERO outbound requests, regardless of whether an API key is present.
     *   (e-bis) Call Google Maps Geocoding API via Guzzle.
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
            // (a) Validate minimum required fields — skipped when full_address override is provided.
            $fullAddressOverride = trim($addressData['full_address'] ?? '');
            if ($fullAddressOverride === '') {
                foreach (self::REQUIRED_ADDRESS_FIELDS as $field) {
                    if (empty($addressData[$field])) {
                        $output = $this->skippedOutput($listingType, $listingId, 'missing_required_address_fields');
                        $this->audit($listingType, $listingId, $output, $addressData);
                        return $output;
                    }
                }
            }

            $address = trim($addressData['address'] ?? '');
            $city    = trim($addressData['city']    ?? '');
            $state   = trim($addressData['state']   ?? '');
            $county  = trim($addressData['county']  ?? '');
            $zip     = trim($addressData['zip']     ?? '');

            // (a-bis) Short-circuit: if pre-geocoded lat/lng from Google Places are present, use them
            //         and skip the Geocoding API call entirely.
            $preLat = isset($addressData['pre_lat']) && $addressData['pre_lat'] !== '' ? (float) $addressData['pre_lat'] : null;
            $preLng = isset($addressData['pre_lng']) && $addressData['pre_lng'] !== '' ? (float) $addressData['pre_lng'] : null;

            if ($preLat !== null && $preLng !== null && $preLat !== 0.0 && $preLng !== 0.0) {
                $record = PropertyLocationDna::firstOrNew([
                    'listing_type' => $listingType,
                    'listing_id'   => $listingId,
                ]);

                $record->source_address = $address;
                $record->source_city    = $city;
                $record->source_state   = $state;
                $record->source_county  = $county ?: null;
                $record->source_zip     = $zip    ?: null;
                $record->geocoded_lat   = $preLat;
                $record->geocoded_lng   = $preLng;
                $record->geocode_source = 'saved_meta';
                $record->geocode_status = 'geocoded';
                $record->geocode_error  = null;
                $record->geocoded_at    = now();

                // Carry the resolver ladder's provenance when the caller supplied
                // it (G5). Without this the pre-coordinate branch flattens every
                // supplied coordinate to 'saved_meta', which loses the two facts
                // a consumer actually needs: which provider answered, and how
                // precisely the point identifies the property. A Census street
                // interpolation and an MLS parcel coordinate are not the same
                // thing, and CoordinatePrecision exists to keep them apart.
                //
                // `geocode_source` deliberately stays 'saved_meta'. It remains
                // literally accurate — a caller did supply this coordinate — and
                // ExistingCoordinatesAdapter reads it through a strict allow-list,
                // so writing anything else here would make that rung refuse the
                // very coordinate it just stored.
                //
                // Absent provenance keeps the legacy behaviour untouched: a
                // coordinate from the autocomplete widget has no provable origin,
                // and inventing one for it would be worse than recording none.
                $this->applyResolverProvenance($record, $addressData['provenance'] ?? null);

                $record->save();

                $output = $this->geocodedOutput($listingType, $listingId, $preLat, $preLng, 'saved_meta');
                $this->audit($listingType, $listingId, $output, $addressData);
                return $output;
            }

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
                $output = $this->geocodedOutput(
                    $listingType,
                    $listingId,
                    (float) $record->geocoded_lat,
                    (float) $record->geocoded_lng,
                );
                $this->audit($listingType, $listingId, $output, $addressData);
                return $output;
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

            // (e) Google is not an approved coordinate source. Fail closed.
            //
            // The kill switch is the OUTERMOST guard on this path and is checked
            // BEFORE the credential: a present GOOGLE_PLACES_API_KEY is not permission
            // to geocode. Until this commit the only gate here was `blank($apiKey)`, so
            // a key sitting in the environment was sufficient to send an outbound
            // Geocoding request even with GOOGLE_PLACES_ENABLED=false. The circuit
            // breaker written after the 2026-07-05 incident covered Nearby Search
            // (GooglePlacesPoiAdapter) and never covered this path.
            //
            // Returns 'skipped', not 'failed' — the same status the service already
            // uses when the address is too incomplete to resolve. Nothing was attempted
            // and nothing went wrong; the coordinate is simply unknown.
            //
            // `non_google_geocoder_unavailable` names the real reason: the platform's
            // approved geocoder is a non-Google resolver that does not exist yet
            // (SPATIAL-INTELLIGENCE-PLATFORM: "Google-free by design"). It is
            // deliberately NOT phrased as "google disabled", which would imply that
            // turning Google back on is the fix. It is not — building the resolver is.
            //
            // Only reachable when the coordinate could not be obtained any other way:
            // pre-supplied property_lat/property_lng (a-bis) and an unchanged cached
            // geocode (c) both return above without consulting this guard.
            if (! config('google_places.enabled', false)) {
                $record->geocode_status = 'skipped';
                $record->geocode_error  = 'non_google_geocoder_unavailable';
                $record->save();

                $output = $this->skippedOutput($listingType, $listingId, 'non_google_geocoder_unavailable');
                $this->audit($listingType, $listingId, $output, $addressData);

                return $output;
            }

            $apiKey = config('services.google.places_key');

            if (blank($apiKey)) {
                $output = $this->failedOutput($listingType, $listingId, 'missing_google_api_key');
                $this->audit($listingType, $listingId, $output, $addressData);
                return $output;
            }

            // Use caller-supplied full_address override when separate city/state are unavailable
            // (e.g. MLS raw address "123 Main St, Tampa, FL 33601" with blank city/state props).
            $fullAddress = $fullAddressOverride !== ''
                ? $fullAddressOverride
                : "{$address}, {$city}, {$state}" . ($zip ? " {$zip}" : '');

            // Phase 0 / S1b: resolve from the container. A bare `new Client()` cannot be
            // intercepted by Http::fake() or by the container binding, and it bypasses
            // GoogleOutboundTelemetryMiddleware entirely.
            $client = $this->httpClient ?? app(ClientInterface::class);

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

                $output = $this->failedOutput($listingType, $listingId, $errorMsg);
                $this->audit($listingType, $listingId, $output, $addressData);
                return $output;
            }

            // (f) Success — persist geocoded data.
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

            $output = $this->geocodedOutput($listingType, $listingId, $lat, $lng, 'google');
            $this->audit($listingType, $listingId, $output, $addressData);
            return $output;

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

            $output = $this->failedOutput($listingType, $listingId, $e->getMessage());
            $this->audit($listingType, $listingId, $output, $addressData);
            return $output;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Write an audit row. Wrapped in its own try/catch so a failure cannot
     * prevent the caller's return value from being delivered.
     */
    private function audit(string $listingType, int $listingId, array $output, array $addressData): void
    {
        try {
            $auditService = $this->auditService ?? new LocationDnaAuditService();
            $auditService->record(
                listingType:    $listingType,
                listingId:      $listingId,
                eventType:      'geocode',
                status:         $output['status'],
                source:         $output['source'] ?? null,
                inputSnapshot:  $addressData,
                outputSnapshot: $output,
                error:          $output['error'] ?? null,
            );
        } catch (Throwable) {
            // Audit failure must never alter the service's return value.
        }
    }

    // =========================================================================
    // Output shape helpers — approved Phase B eight-key contract in all cases
    // =========================================================================

    private function geocodedOutput(
        string  $listingType,
        int     $listingId,
        float   $lat,
        float   $lng,
        string  $source   = 'google',
    ): array {
        return [
            'success'      => true,
            'status'       => 'geocoded',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'lat'          => $lat,
            'lng'          => $lng,
            'source'       => $source,
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

    /**
     * Record where a supplied coordinate came from, when that is provable.
     *
     * @param array{precision: string, provider: string, source: string, normalized_address: string}|null $provenance
     */
    private function applyResolverProvenance(PropertyLocationDna $record, ?array $provenance): void
    {
        if ($provenance === null) {
            return;
        }

        // The columns only exist once the G4 provenance migration has run.
        // Writing them before that raises SQLSTATE[42703] inside whatever
        // request triggered the pipeline, so the shared guard decides — the same
        // guard every other provenance writer consults, rather than a second
        // opinion implemented here.
        if (! ProvenanceSchemaReadiness::isReady()) {
            Log::warning('location_dna_provenance_skipped', [
                'reason'          => ProvenanceSchemaReadiness::REASON_NOT_READY,
                'listing_type'    => $record->listing_type,
                'listing_id'      => $record->listing_id,
                'missing_columns' => ProvenanceSchemaReadiness::missingColumns(),
            ]);

            return;
        }

        $record->geocode_precision  = $provenance['precision'];
        $record->geocode_provider   = $provenance['provider'];
        $record->normalized_address = $provenance['normalized_address'] !== ''
            ? $provenance['normalized_address']
            : null;
    }
}
