<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Models\BridgeProperty;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Rung 2 of the ladder: the coordinate the MLS already published for this
 * listing, reached only through the listing's own MLS record key.
 *
 * WHERE THE COORDINATE COMES FROM
 * -------------------------------
 * `bridge_properties`, the local mirror of the Bridge Data Output feed.
 * {@see \App\Services\Bridge\BridgePropertyNormalizer} promotes the RESO
 * `Latitude` / `Longitude` fields into native `decimal(10, 7)` columns and keys
 * every row by `listing_key` — the RESO `ListingKey`, which the normalizer
 * treats as mandatory (a record without one is refused outright) and which the
 * upsert uses as its sole match key.
 *
 * IDENTITY: ONE KEY, NO SIMILARITY
 * --------------------------------
 * A Bridge record is selected by `listing_key` equality and by nothing else.
 * There is no address lookup, no city/ZIP narrowing and no nearest-match — not
 * as a simplification, but because address similarity is not evidence of
 * identity. "123 Main St, Tampa" exists in several forms across a feed, and a
 * lookup that accepted the closest one would attach some other property's
 * coordinate to this listing while reporting complete success. An adapter that
 * cannot prove which record it is looking at returns nothing and lets the next
 * rung try.
 *
 * The consequence is that this rung is silent for any listing that has not had
 * an MLS record key attached to it. That is the correct behaviour and a real
 * limitation: binding a listing to its `ListingKey` is a listing-workflow
 * concern (G5/G6), and nothing in G2 creates that binding.
 *
 * ADDRESS DRIFT: WHAT THIS ADAPTER DOES NOT GUARD
 * -----------------------------------------------
 * Unlike {@see ExistingCoordinatesAdapter}, this rung does not verify that the
 * Bridge record's address still matches the address being asked about. It is
 * not an oversight and it is worth being explicit about.
 *
 * The MLS record is not our copy of the address — it is the MLS's statement
 * about a property, keyed independently of anything we store. Comparing the two
 * would mean normalizing `unparsed_address`, which routinely carries the unit
 * inline ("123 Main St #4A") where our `address` field does not, so every condo
 * in the feed would compare unequal and be rejected for a difference that is
 * purely formatting. A guard whose false-rejection rate is highest exactly where
 * the data is hardest to obtain is not a guard.
 *
 * What is done instead: the Bridge record's own normalized address is recorded
 * on the result, so what the coordinate describes is visible to any consumer or
 * audit rather than implied. The place to detect that a listing has drifted away
 * from its MLS record is where the key is attached, not here.
 *
 * PRECISION: WHY PARCEL, AND WHAT THE EVIDENCE IS
 * -----------------------------------------------
 * The Bridge feed supplies no precision, accuracy or geocode-quality field of
 * any kind. RESO defines none alongside `Latitude`/`Longitude`, the normalizer
 * maps none, and the retained `raw_json` contains none to fall back on. So the
 * tier is a judgement, and it is made in the conservative direction:
 * {@see CoordinatePrecision::Parcel}.
 *
 * Parcel rather than Rooftop: MLS coordinates are ordinarily geocoded by the
 * MLS or the listing agent from the property address, and nothing in the feed
 * distinguishes a surveyed rooftop point from an address geocode. Rooftop is a
 * claim about the structure, and the feed does not support it — which is the
 * same reason G1 already caps unit-free building lookups at Parcel.
 *
 * Parcel rather than Unknown: the coordinate is published per listing, derived
 * from that listing's own address, and identifies the property rather than an
 * area containing it. It is categorically unlike a ZIP or city centroid, and
 * grading it Unknown would mark it coarse, block it from Location DNA, and make
 * this rung incapable of ever contributing — which would misdescribe the data
 * in the opposite direction.
 *
 * Parcel is the tier that says "this is the property, not proven to be the
 * roof", and that is exactly what an MLS coordinate is.
 *
 * Local by construction: one indexed SELECT, no HTTP client, no network. Note
 * in particular that this adapter never reaches the Bridge API — it reads rows
 * the importer has already stored.
 */
final class BridgeMlsCoordinatesAdapter implements CoordinateProviderAdapterInterface
{
    public function providerId(): string
    {
        return 'bridge_mls';
    }

    public function source(): CoordinateSource
    {
        return CoordinateSource::Mls;
    }

    public function requiresNetwork(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable('bridge_properties');
        } catch (Throwable) {
            return false;
        }
    }

    public function resolve(PropertyAddress $address): PropertyCoordinateResult
    {
        $normalized = $address->coordinateLookupLine();

        if (! $address->hasMlsListingKey()) {
            return PropertyCoordinateResult::unresolved('no_mls_listing_key', $normalized);
        }

        $record = BridgeProperty::query()
            ->where('listing_key', trim($address->mlsListingKey))
            ->first();

        if ($record === null) {
            return PropertyCoordinateResult::unresolved('mls_record_not_found', $normalized);
        }

        $latitude  = CoordinateValidator::toFloat($record->latitude);
        $longitude = CoordinateValidator::toFloat($record->longitude);

        // A great many feed records carry no coordinate at all. Ordinary, not an
        // error: the listing exists, its position was simply never published.
        if ($latitude === null || $longitude === null) {
            return PropertyCoordinateResult::unresolved('mls_coordinates_absent', $normalized);
        }

        // Out of range, or Null Island — which in a feed means an unset column
        // that arrived as a zero rather than a property in the Gulf of Guinea.
        if (! CoordinateValidator::isValidPair($latitude, $longitude)) {
            return PropertyCoordinateResult::unresolved('mls_coordinates_invalid', $normalized);
        }

        return PropertyCoordinateResult::resolved(
            latitude:  $latitude,
            longitude: $longitude,
            precision: CoordinatePrecision::Parcel,
            source:    CoordinateSource::Mls,
            provider:  $this->providerId(),
            // The MLS record's address, not the caller's. This coordinate
            // describes what the feed says, and the result should say so.
            normalizedAddress: $this->recordLookupLine($record),
            // The feed reports no confidence score. Null is the honest value;
            // a fabricated 1.0 would read as certainty nobody asserted.
            confidence: null,
        );
    }

    /**
     * The Bridge record's own address, normalized through the same rules as
     * every other address in this namespace so it is comparable to them.
     */
    private function recordLookupLine(BridgeProperty $record): string
    {
        return (new PropertyAddress(
            address: (string) ($record->unparsed_address  ?? ''),
            city:    (string) ($record->city              ?? ''),
            county:  (string) ($record->county_or_parish  ?? ''),
            state:   (string) ($record->state_or_province ?? ''),
            zip:     (string) ($record->postal_code       ?? ''),
        ))->coordinateLookupLine();
    }
}
