<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\Location\PropertyCoordinatePersistenceService;
use Illuminate\Database\Eloquent\Model;

/**
 * The Seller/Landlord hook into the coordinate ladder — Create Offer and Hire
 * Agent alike.
 *
 * Deliberately one method with one line of behaviour. All of the thinking —
 * precedence, change detection, provenance, failure posture — lives in
 * {@see PropertyCoordinatePersistenceService}, which knows nothing about roles
 * or Livewire. This exists so that eight components share one insertion point
 * rather than eight copies of a call, and so that "resolve before dispatch" is
 * stated once, here, instead of being an ordering somebody has to notice at
 * thirteen call sites.
 *
 * Eight users, all of them Seller or Landlord property listings: four Create
 * Offer components (G5) and four Hire Agent components (G6). Both groups write
 * the same two models under the same `property_location_dna` listing types —
 * 'seller_agent' and 'landlord_agent' — which is why one trait serves both.
 *
 * Buyer and Tenant are not on this list and must not join it. Their geography
 * is multi-area search criteria (`HasSearchAreas`), not one property's point;
 * they carry no `property_lat` and the resolver has nothing to resolve for them.
 *
 * WHERE IT IS CALLED, AND WHY EXACTLY THERE
 * -----------------------------------------
 * At a save boundary, after the listing row is saved and `saveAllMetadata()`
 * has run — so the address meta this reads is the address the user actually
 * submitted, not a half-populated form state — and before any
 * {@see \App\Jobs\ComputeLocationDna} dispatch in the same method.
 *
 * The ordering is the whole point. The pipeline reads `property_lat`/
 * `property_lng` as `pre_lat`/`pre_lng` and prefers them over geocoding, so
 * writing the coordinate first is what lets the dispatched job carry it — and
 * its provenance — into `property_location_dna`. Resolving after the dispatch
 * would win the race only by luck.
 *
 * NOT CALLED FROM ANYWHERE ELSE
 * -----------------------------
 * Not from `render()`, `mount()`, `hydrate()`, an `updated*()` hook, address
 * autocomplete, or a validation callback. A geocoder behind a keystroke is how a
 * free public service stops being available to us, and the G4 caps exist to
 * survive that mistake rather than to license it. Save boundaries only.
 *
 * THIS TRAIT STILL DISPATCHES NOTHING
 * -----------------------------------
 * It resolves and returns. Every dispatch is written explicitly at its call
 * site, and the two groups deliberately differ:
 *
 *   Create Offer  nine dispatch sites, all pre-dating G5 — that phase added
 *                 none and removed none, including on draft saves.
 *   Hire Agent    four dispatch sites, added by G6 at the PUBLISH boundaries
 *                 only (`store()`, `update()`). These flows had no Location DNA
 *                 dispatch at all before G6. Their `saveDraft()` boundaries
 *                 resolve but do NOT dispatch: an unpublished draft has no
 *                 consumer for Location DNA, and drafts are saved repeatedly.
 *
 * That makes the app-wide Location DNA dispatch-site baseline 21 after G6, from
 * 17 before it. (Written without the literal call expression on purpose — the
 * tests below count raw occurrences of it across `app/`, so naming it here
 * would make this comment count itself.) The number is pinned by test in both
 * {@see \Tests\Feature\Location\CreateOfferCoordinateWiringTest} and
 * {@see \Tests\Feature\HireAgent\HireAgentCoordinateWiringTest}, so it fails
 * loudly rather than drifting.
 *
 * @see \App\Http\Livewire\OfferListing\Seller\SellerOfferListing
 * @see \App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit
 * @see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing
 * @see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit
 * @see \App\Http\Livewire\HireSellerAgent\SellerAgentAuction
 * @see \App\Http\Livewire\HireSellerAgent\SellerAgentAuctionEdit
 * @see \App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction
 * @see \App\Http\Livewire\HireLandLordAgent\LandLordAgentAuctionEdit
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
        $this->forgetLoadedMeta($auction);

        return app(PropertyCoordinatePersistenceService::class)
            ->resolveAndPersist($auction, $listingType);
    }

    /**
     * Drop the model's cached `meta` relation so the service reads what was just
     * saved.
     *
     * WHY THIS IS NECESSARY AND NOT DEFENSIVE
     * ---------------------------------------
     * The four role models expose meta through two mechanisms that do not talk
     * to each other. `saveMeta()` writes with `$this->meta()->updateOrCreate()` —
     * the relation QUERY, straight to the database — while `info()` reads
     * `$this->meta`, the loaded relation COLLECTION. Eloquent caches that
     * collection on first access and never invalidates it on a query-builder
     * write.
     *
     * So on a save boundary the sequence is: the component saves a new listing
     * row, `saveAllMetadata()` writes every field through `saveMeta()`, and
     * anything that reads `info()` afterwards on the same instance sees the
     * relation as it stood before all of it — empty, for a listing created in
     * this same request. The service would then find no address, decline with
     * `insufficient_address`, and return without a single provider call. Silently:
     * an insufficient address is an ordinary answer, not an error, so nothing
     * logs and nothing fails.
     *
     * That was survivable only while the components also copied the autocomplete
     * widget's coordinate straight into `property_lat` themselves — the listing
     * ended up with a coordinate either way, just not one the ladder had produced
     * or could vouch for. With that copy removed, the ladder is the only source
     * of a coordinate, and it has to be able to read the address it is being
     * asked about.
     *
     * Unsetting is preferred to `load('meta')`: the service reads several keys
     * and then writes its own, so a fresh lazy load at the first read is both
     * correct and one query rather than two.
     */
    private function forgetLoadedMeta(object $auction): void
    {
        if ($auction instanceof Model && $auction->relationLoaded('meta')) {
            $auction->unsetRelation('meta');
        }
    }
}
