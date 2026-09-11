<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\Offers\ImportantPlacesService;
use App\Support\LocationDna\LocationDnaCriteriaDisplay;
use Illuminate\Database\Eloquent\Model;

/**
 * The inputs `<x-location-dna-map>` and the Location DNA panel take, built from a listing's OWN
 * stored meta — for the listing pages that did not have them.
 *
 * WHY THIS EXISTS. Hire Agent listings share their model, table and meta with the Offer Listing
 * of the same role (only `workflow_type` differs), and they store the same Location DNA: a
 * Buyer/Tenant Hire listing saves `location_dna_preferences` and `important_places_json` through
 * the same concerns, and a Seller/Landlord Hire listing gets `property_lat/lng` from the same
 * coordinate ladder and a `property_location_dna` row from the same pipeline, under the same
 * `listing_type`. Their detail pages read none of it. This assembles the SAME inputs the Offer
 * Listing controllers assemble, so the Hire pages render through the same component, the same
 * renderer and the same criteria summary — not a Hire-specific reading of any of it.
 *
 * It reads; it never writes, geocodes or re-serializes. Rows without coordinates simply produce
 * no pin, exactly as on the Offer Listing pages.
 */
class ListingLocationDnaViewData
{
    public function __construct(
        private BoundaryLookupService $boundaryLookup,
        private FloodZoneLookupService $floodZoneLookup,
        private SchoolDistrictLookupService $schoolDistrictLookup,
        private ImportantPlacesService $importantPlaces,
    ) {
    }

    /**
     * Buyer / Tenant — what the client is searching for.
     *
     * @param  string[]  $legacyZipKeys  meta keys holding a legacy ZIP list for this role (Tenant
     *                                   Hire mirrors `zipCodes`; Buyer never wrote one)
     * @return array{kind: string, hasContent: bool, locationDnaPreferences: ?array,
     *               legacyLocation: array, importantPlaces: array, boundaryData: mixed,
     *               floodZoneData: mixed, schoolDistrictData: mixed}
     */
    public function forSearch(Model $auction, array $legacyZipKeys = []): array
    {
        $raw         = $auction->info('location_dna_preferences');
        $decoded     = is_string($raw) && $raw !== '' ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        $preferences = is_array($decoded) ? $decoded : null;

        $zips = [];
        foreach ($legacyZipKeys as $key) {
            $zips = array_merge($zips, $this->list($auction->info($key)));
        }

        $legacyLocation = [
            'cities'    => $this->list($auction->info('cities')),
            'counties'  => $this->list($auction->info('counties')),
            'states'    => $this->list($auction->info('state')),
            'zip_codes' => array_values(array_unique($zips)),
        ];

        $importantPlaces = $this->importantPlaces->normalize($auction->info('important_places_json') ?: '');

        $boundaryData       = $this->boundaryLookup->resolve($preferences, $legacyLocation);
        $floodZoneData      = $this->floodZoneLookup->resolve($boundaryData, $preferences ?? []);
        $schoolDistrictData = $this->schoolDistrictLookup->resolve($boundaryData, $preferences ?? []);

        return [
            'kind'                   => 'search',
            // Driven by the Location DNA data itself — the blob and the places — not by the legacy
            // city/county rows, which these pages already list under "Acceptable Cities" and friends.
            'hasContent'             => !LocationDnaCriteriaDisplay::from($preferences, $importantPlaces)->isEmpty(),
            'locationDnaPreferences' => $preferences,
            'legacyLocation'         => $legacyLocation,
            'importantPlaces'        => $importantPlaces,
            'boundaryData'           => $boundaryData,
            'floodZoneData'          => $floodZoneData,
            'schoolDistrictData'     => $schoolDistrictData,
        ];
    }

    /**
     * Seller / Landlord — the one property being offered.
     *
     * @param  string  $listingType            the pipeline's key for this role ('seller_agent', 'landlord_agent')
     * @param  bool    $exactLocationVisible   may THIS viewer see the property's exact location? A pin
     *                                         and a list of distances to named places both give away
     *                                         the address, so both follow the page's own answer to
     *                                         whether it publishes the street address to this viewer.
     * @return array{kind: string, hasContent: bool, propertyPin: ?array, locationDna: ?PropertyLocationDna,
     *               locationPois: \Illuminate\Support\Collection, listingType: string, listingId: int}
     */
    public function forProperty(Model $auction, string $listingType, bool $exactLocationVisible): array
    {
        $pin = null;
        $dna = null;
        $pois = collect();

        if ($exactLocationVisible) {
            $lat = $auction->info('property_lat');
            $lng = $auction->info('property_lng');

            if (is_numeric($lat) && is_numeric($lng)) {
                $address = trim((string) ($auction->info('address') ?: ''));
                $unit    = trim((string) ($auction->info('unit_address') ?: ''));

                $pin = [
                    'lat'   => (float) $lat,
                    'lng'   => (float) $lng,
                    'label' => $address !== '' ? $address . ($unit !== '' ? ', ' . $unit : '') : null,
                ];
            }

            $dna = PropertyLocationDna::where('listing_type', $listingType)
                ->where('listing_id', $auction->getKey())
                ->first();

            if ($dna) {
                $pois = PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $auction->getKey())
                    ->orderBy('poi_category')
                    ->orderBy('rank')
                    ->get();
            }
        }

        return [
            'kind'         => 'property',
            'hasContent'   => $pin !== null || $dna !== null,
            'propertyPin'  => $pin,
            'locationDna'  => $dna,
            'locationPois' => $pois,
            'listingType'  => $listingType,
            'listingId'    => (int) $auction->getKey(),
        ];
    }

    /** A meta value that may be a JSON list, an array, or a single string → a clean string list. */
    private function list($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if ((is_string($item) || is_numeric($item)) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            }
        }

        return array_values(array_unique($out));
    }
}
