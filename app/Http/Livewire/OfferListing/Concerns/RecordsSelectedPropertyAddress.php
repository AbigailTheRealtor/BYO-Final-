<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\Location\Coordinates\PropertyCoordinateMeta;

/**
 * The Create Offer save seam where an address selection stops being a
 * coordinate.
 *
 * WHAT THIS REPLACED, AND WHY
 * ---------------------------
 * Each of the four Seller/Landlord Create Offer components used to end its
 * address block with three identical writes:
 *
 *     $auction->saveMeta('property_lat', $this->property_lat);
 *     $auction->saveMeta('property_lng', $this->property_lng);
 *     $auction->saveMeta('google_place_id', $this->google_place_id);
 *
 * The first two are the problem. `$this->property_lat` is browser state: it is
 * filled by {@see \App\Http\Livewire\Concerns\HandlesResolvedPropertyAddress::fillFromResolvedAddress()}
 * from whatever the autocomplete widget attached to the row somebody clicked,
 * and `property_lat` is the platform's authoritative coordinate meta — the key
 * {@see \App\Services\LocationDna\LocationDnaPipelineRunner} forwards as
 * `pre_lat`, which {@see \App\Services\LocationDna\LocationDnaGeocodeService}
 * honours ahead of any geocoding and stores in `property_location_dna`, and
 * which {@see \App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter}
 * then reads back as rung 1 of the ladder on the next save.
 *
 * So a dropdown pick became a property's location in one assignment, with no
 * record of which provider supplied it or how precisely it located anything —
 * and, one save later, was indistinguishable from a coordinate the ladder had
 * vouched for. The suggestion namespace states the rule this seam now enforces:
 * what a provider proposed while somebody was typing and what the ladder
 * concluded for a property are separate types, and there is deliberately no
 * conversion between them.
 *
 * WHAT HAPPENS INSTEAD
 * --------------------
 * The address parts are saved by the surrounding `saveAllMetadata()`, exactly as
 * before. The coordinate is then produced from those parts, at the same save, by
 * {@see ResolvesPropertyCoordinates::resolvePropertyCoordinates()} →
 * {@see \App\Services\Location\PropertyCoordinatePersistenceService} → the
 * ladder, and written to `property_lat`/`property_lng` with the provenance that
 * says where it came from.
 *
 *     address pick  →  address meta  →  ladder  →  coordinate + provenance
 *
 * The browser may still identify the ADDRESS. It no longer dictates the POINT.
 *
 * WHY THE COMPONENT PROPERTY SURVIVES
 * -----------------------------------
 * `$this->property_lat` / `$this->property_lng` still exist, still carry the
 * widget's value, and still back the hidden inputs the map component reads. They
 * are a display hint from here on — the same status the suggestion namespace
 * gives a proposed address's own point, exposed for map framing and for nothing
 * measurable. Removing the fields is autocomplete-provider work and belongs to
 * the phase that replaces the provider, not to this one.
 *
 * WHY google_place_id IS STILL WRITTEN
 * ------------------------------------
 * It is address-selection metadata, not coordinate provenance, and the existing
 * UI still round-trips it. Keeping it costs nothing here precisely because
 * nothing downstream may now treat it as evidence about a coordinate: the point
 * it used to arrive with no longer reaches `property_lat`, so its presence
 * cannot route anything past the ladder. It leaves when the provider does.
 *
 * @see \App\Http\Livewire\OfferListing\Seller\SellerOfferListing
 * @see \App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit
 * @see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing
 * @see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit
 */
trait RecordsSelectedPropertyAddress
{
    /**
     * Record the address selection's non-coordinate metadata.
     *
     * Called from `saveAllMetadata()` at the point the three raw writes used to
     * sit, so the address block still reads as one thing. Writes no coordinate,
     * by design: the only writer of {@see PropertyCoordinateMeta::LAT} and
     * {@see PropertyCoordinateMeta::LNG} on these flows is now the persistence
     * service, at the resolution boundary later in the same save.
     *
     * Guarded with property_exists() for the same reason the fill trait is — the
     * concern is written against the fields these components happen to declare,
     * not against a base class none of the four role models share.
     *
     * @param object $auction the saved listing model (EAV meta accessors)
     */
    protected function saveSelectedPropertyAddressMeta(object $auction): void
    {
        if (property_exists($this, 'google_place_id')) {
            $auction->saveMeta('google_place_id', (string) ($this->google_place_id ?? ''));
        }
    }
}
