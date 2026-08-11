<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\Location\PropertyCoordinatePersistenceService;

/**
 * The Seller/Landlord Create Offer hook into the coordinate ladder.
 *
 * Deliberately one method with one line of behaviour. All of the thinking —
 * precedence, change detection, provenance, failure posture — lives in
 * {@see PropertyCoordinatePersistenceService}, which knows nothing about roles
 * or Livewire. This exists so the four Create Offer components share one
 * insertion point rather than four copies of a call, and so that "resolve
 * before dispatch" is stated once, here, instead of being an ordering somebody
 * has to notice at nine call sites.
 *
 * WHERE IT IS CALLED, AND WHY EXACTLY THERE
 * -----------------------------------------
 * Immediately before the existing {@see \App\Jobs\ComputeLocationDna} dispatch,
 * at each of the nine sites that already dispatch. By that point the listing row
 * is saved and `saveAllMetadata()` has run, so the address meta this reads is
 * the address the user actually submitted — not a half-populated form state.
 *
 * The ordering is the whole point. The pipeline reads `property_lat`/
 * `property_lng` as `pre_lat`/`pre_lng` and prefers them over geocoding, so
 * writing the coordinate first is what lets the already-dispatched job carry it
 * — and its provenance — into `property_location_dna`. Resolving after the
 * dispatch would win the race only by luck.
 *
 * NOT CALLED FROM ANYWHERE ELSE
 * -----------------------------
 * Not from `render()`, `mount()`, `hydrate()`, an `updated*()` hook, address
 * autocomplete, or a validation callback. A geocoder behind a keystroke is how a
 * free public service stops being available to us, and the G4 caps exist to
 * survive that mistake rather than to license it. Save boundaries only.
 *
 * THIS TRAIT ADDS NO DISPATCH
 * ---------------------------
 * It resolves and returns. The nine dispatch sites are exactly the nine that
 * existed before this phase.
 *
 * @see \App\Http\Livewire\OfferListing\Seller\SellerOfferListing
 * @see \App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit
 * @see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing
 * @see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit
 */
trait ResolvesPropertyCoordinates
{
    /**
     * Resolve and record this listing's coordinate, if it needs one.
     *
     * Usually does nothing: the service skips entirely when the listing already
     * holds a coordinate for the same normalized address, which is the case on
     * every repeat draft save.
     *
     * Never throws. The service swallows its own failures precisely so that a
     * provider outage, an open circuit or an unmigrated schema cannot become the
     * reason a listing fails to save.
     *
     * @param object $auction     the saved listing model (EAV meta accessors)
     * @param string $listingType the `property_location_dna` listing_type this
     *                            role uses — 'seller_agent' or 'landlord_agent'
     *
     * @return array{outcome: string, reason: string|null, provider: string|null, precision: string|null}
     */
    protected function resolvePropertyCoordinates(object $auction, string $listingType): array
    {
        return app(PropertyCoordinatePersistenceService::class)
            ->resolveAndPersist($auction, $listingType);
    }
}
