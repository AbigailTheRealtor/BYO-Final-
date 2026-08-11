<?php

namespace App\Services\Location;

use App\Services\Location\Coordinates\Adapters\StandardCoordinateLadder;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateMeta;
use App\Services\Location\Coordinates\PropertyCoordinateResolverInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolve a listing's coordinate at save time and record it on the listing.
 *
 * The one place a listing flow touches the coordinate ladder. Seller and
 * Landlord both call this; neither knows what a rung is, and nothing here knows
 * what a role is.
 *
 * WHERE IT SITS
 * -------------
 *   Create Offer save
 *        ↓
 *   this service  →  PropertyCoordinateResolver  →  Existing / Bridge / Census
 *        ↓
 *   property_lat / property_lng meta  (+ provenance)
 *        ↓
 *   ComputeLocationDna  (already dispatched today; unchanged by this phase)
 *        ↓
 *   LocationDnaGeocodeService pre-coordinate branch
 *        ↓
 *   property_location_dna
 *
 * It writes meta and stops. It does not write `property_location_dna` and it
 * does not dispatch anything — see the two headings below, which are the whole
 * reason this class is shaped the way it is.
 *
 * WHY IT DOES NOT WRITE property_location_dna
 * -------------------------------------------
 * That table already has an owner, and the owner deletes. {@see \App\Services\LocationDna\LocationDnaGeocodeService}
 * runs asynchronously from a job that Create Offer dispatches *after the same
 * save this service runs on*, and at its step (d) it nulls the stored
 * coordinate whenever the address fields it was handed differ from the ones on
 * the row. A coordinate written directly here could therefore be erased moments
 * later by a job already sitting in the queue — silently, with no error
 * anywhere.
 *
 * Feeding the owner instead of racing it removes the failure mode rather than
 * narrowing it. Meta is the channel the pipeline already reads first.
 *
 * WHY IT DOES NOT DISPATCH
 * ------------------------
 * Create Offer already dispatches {@see \App\Jobs\ComputeLocationDna} — nine
 * sites across the four components, all predating this work. Adding a dispatch
 * here would double the queue traffic for every save; removing those would
 * change behaviour well outside a coordinate change. So this phase adds none
 * and removes none, and the handoff happens through meta that the existing
 * dispatch picks up on its own.
 *
 * WHEN IT DOES NOTHING AT ALL
 * ---------------------------
 * The common case, and deliberately so. Resolution is skipped entirely when the
 * listing already holds a coordinate for the same normalized address — no
 * resolver call, no adapter, no request. Drafts are saved repeatedly and the
 * address rarely changes; a service that re-resolved on every save would spend
 * the provider budget re-learning the same answer.
 *
 * The comparison is the unit-free {@see PropertyAddress::coordinateLookupLine()},
 * which gives the three behaviours the addressing rules require for free:
 * re-typing an address differently ("123 North Main Street" vs "123 N Main St")
 * is not a change; editing the unit is not a change to the *building's*
 * coordinate; moving the property is.
 *
 * FAILURE POSTURE
 * ---------------
 * Every failure degrades to "no coordinate this time". A provider outage, an
 * open circuit, a spent cap, an ambiguous match, a database that has not run
 * its migrations — none of them may interrupt a listing save, and none of them
 * may destroy a coordinate that is already there. An existing value is only
 * ever replaced by a successful resolution, never cleared by a failed one.
 */
class PropertyCoordinatePersistenceService
{
    /** Nothing needed doing — the listing already has this address's coordinate. */
    public const OUTCOME_UNCHANGED = 'unchanged';

    /** A coordinate was resolved and written. */
    public const OUTCOME_RESOLVED = 'resolved';

    /** Resolution ran and produced nothing. Any existing coordinate is untouched. */
    public const OUTCOME_UNRESOLVED = 'unresolved';

    /** There was not enough address to attempt anything. */
    public const OUTCOME_INSUFFICIENT = 'insufficient_address';

    /** Something threw. Swallowed so a save cannot fail over a coordinate. */
    public const OUTCOME_ERROR = 'error';

    private readonly PropertyCoordinateResolverInterface $resolver;

    /**
     * @param PropertyCoordinateResolverInterface|null $resolver injectable for
     *        tests and for a future container binding; defaults to the standard
     *        ladder so callers do not each decide what the ladder is.
     */
    public function __construct(?PropertyCoordinateResolverInterface $resolver = null)
    {
        $this->resolver = $resolver ?? StandardCoordinateLadder::resolver();
    }

    /**
     * Resolve and record this listing's coordinate.
     *
     * @param object $listing a listing model exposing saveMeta()/info() — the
     *        EAV shape Seller and Landlord auctions share. Typed loosely on
     *        purpose: the four role models do not share a base class or an
     *        interface, and inventing one for this is a larger refactor than a
     *        coordinate change should carry.
     * @param string $listingType the `property_location_dna` listing_type this
     *        flow uses ('seller_agent', 'landlord_agent'), so the Existing rung
     *        looks in the right place.
     *
     * @return array{outcome: string, reason: string|null, provider: string|null, precision: string|null}
     */
    public function resolveAndPersist(object $listing, string $listingType): array
    {
        try {
            return $this->attempt($listing, $listingType);
        } catch (Throwable $e) {
            // A coordinate is an enrichment. It may never be the reason a
            // listing fails to save, so every escape route ends here.
            Log::warning('property_coordinate_persistence_failed', [
                'listing_type' => $listingType,
                'listing_id'   => $listing->id ?? null,
                'error'        => $e->getMessage(),
                'exception'    => get_class($e),
            ]);

            return $this->outcome(self::OUTCOME_ERROR, 'exception');
        }
    }

    /**
     * @return array{outcome: string, reason: string|null, provider: string|null, precision: string|null}
     */
    private function attempt(object $listing, string $listingType): array
    {
        $address = $this->addressFor($listing, $listingType);

        if (! $address->hasMinimumForLookup()) {
            return $this->outcome(self::OUTCOME_INSUFFICIENT, 'insufficient_address');
        }

        if ($this->alreadyResolvedForThisAddress($listing, $address)) {
            return $this->outcome(self::OUTCOME_UNCHANGED, null);
        }

        $result = $this->resolver->resolve($address);

        if (! $result->isResolved()) {
            // Deliberately does NOT clear an existing coordinate. A Census
            // outage is not evidence that a coordinate we already hold is
            // wrong, and step (d) of the pipeline remains the canonical place
            // where a stale coordinate is invalidated by an address change.
            return $this->outcome(self::OUTCOME_UNRESOLVED, $result->reason);
        }

        $this->persist($listing, $result, $address);

        return [
            'outcome'   => self::OUTCOME_RESOLVED,
            'reason'    => null,
            'provider'  => $result->provider,
            'precision' => $result->precision->value,
        ];
    }

    /**
     * Build the provider-neutral address, including the record handles the local
     * rungs need.
     *
     * The listing handle is what lets {@see \App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter}
     * find a coordinate we already stored. The MLS key is what lets the Bridge
     * rung find the feed's own; Create Offer does not currently persist one, so
     * that rung stays silent here rather than guessing by address.
     */
    private function addressFor(object $listing, string $listingType): PropertyAddress
    {
        return new PropertyAddress(
            address:       $this->meta($listing, 'address'),
            unitAddress:   $this->meta($listing, 'unit_address'),
            city:          $this->meta($listing, 'property_city'),
            county:        $this->meta($listing, 'property_county'),
            state:         $this->meta($listing, 'property_state'),
            zip:           $this->meta($listing, 'property_zip'),
            listingType:   $listingType,
            listingId:     isset($listing->id) ? (int) $listing->id : null,
            mlsListingKey: $this->meta($listing, 'mls_listing_key'),
        );
    }

    /**
     * True when the listing already carries a coordinate for exactly this
     * normalized address.
     *
     * Both halves matter. A coordinate with no recorded address cannot be shown
     * to belong to this one, and a recorded address with no coordinate is not an
     * answer — so anything short of both present and matching re-resolves.
     */
    private function alreadyResolvedForThisAddress(object $listing, PropertyAddress $address): bool
    {
        $lat = trim($this->meta($listing, PropertyCoordinateMeta::LAT));
        $lng = trim($this->meta($listing, PropertyCoordinateMeta::LNG));

        if ($lat === '' || $lng === '') {
            return false;
        }

        $recorded = trim($this->meta($listing, PropertyCoordinateMeta::NORMALIZED_ADDRESS));

        return $recorded !== '' && $recorded === $address->coordinateLookupLine();
    }

    private function persist(
        object $listing,
        PropertyCoordinateResult $result,
        PropertyAddress $address
    ): void {
        $listing->saveMeta(PropertyCoordinateMeta::LAT, (string) $result->latitude);
        $listing->saveMeta(PropertyCoordinateMeta::LNG, (string) $result->longitude);

        foreach (PropertyCoordinateMeta::provenanceFor($result) as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        // The change-detection key is the address we ASKED about, not the one the
        // provider answered with.
        //
        // Those differ, and relying on the answer breaks the cache silently.
        // Census normalizes "315 E Madison St" to "315 MADISON ST" — it drops the
        // directional — so a stored answer would never equal the next request's
        // lookup line, and every save would re-resolve and re-spend the budget
        // while looking like it was working. The rungs also disagree among
        // themselves: the Existing rung echoes the request, Bridge returns the
        // feed record's own address. Only the request is deterministic.
        $listing->saveMeta(
            PropertyCoordinateMeta::NORMALIZED_ADDRESS,
            $address->coordinateLookupLine()
        );
    }

    /** Read one meta value as a string, tolerating either accessor shape. */
    private function meta(object $listing, string $key): string
    {
        try {
            if (method_exists($listing, 'info')) {
                return (string) ($listing->info($key) ?? '');
            }

            if (method_exists($listing, 'getMeta')) {
                return (string) ($listing->getMeta($key) ?? '');
            }
        } catch (Throwable) {
            return '';
        }

        return '';
    }

    /**
     * @return array{outcome: string, reason: string|null, provider: string|null, precision: string|null}
     */
    private function outcome(string $outcome, ?string $reason): array
    {
        return [
            'outcome'   => $outcome,
            'reason'    => $reason,
            'provider'  => null,
            'precision' => null,
        ];
    }
}
