<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Rung 1 of the ladder: a coordinate this platform already possesses for this
 * exact listing, reused only when it can be shown to still describe the
 * listing's current address.
 *
 * WHERE THE COORDINATE COMES FROM
 * -------------------------------
 * `property_location_dna`, one row per (listing_type, listing_id) — the table
 * {@see \App\Services\LocationDna\LocationDnaGeocodeService} has been writing
 * since Phase B. It is the only store in this codebase that keeps a coordinate
 * and a record of the address that coordinate was produced from, in the same
 * row:
 *
 *   geocoded_lat / geocoded_lng   the point
 *   geocode_status                'pending' | 'geocoded' | 'failed' | 'skipped'
 *   geocode_source                'google' | 'saved_meta' | null
 *   source_address / source_city / source_county / source_state / source_zip
 *                                 the address snapshot as it stood when the
 *                                 coordinate was obtained
 *
 * WHY NOT THE property_lat / property_lng META (the obvious candidate)
 * -------------------------------------------------------------------
 * Seller and Landlord listings also carry `property_lat` / `property_lng` in
 * EAV meta, and those look like the natural source — they are written on every
 * save by the Offer Listing components. They are deliberately NOT read here.
 *
 * Nothing is stored beside them recording which address produced them. The
 * components write the address meta and the coordinate meta in the same save,
 * but a later save can change the address meta and leave the coordinate meta
 * untouched: `mlsGeocodeSaveTimeFallback()` only geocodes when `property_lat`
 * is *empty*, so once a coordinate exists no address edit ever revisits it.
 * A coordinate found there could be from the current address or from an address
 * the property had two edits ago, and the row cannot tell you which.
 *
 * That is the limitation, stated rather than papered over: for meta-only
 * coordinates, correspondence is unprovable, so this adapter does not claim it.
 * Nothing is lost in practice — the pipeline already forwards those meta values
 * into `property_location_dna` as `pre_lat`/`pre_lng`, where they are written
 * *with* an address snapshot as `geocode_source = 'saved_meta'`. This adapter
 * reads them there, where correspondence is provable, and not where it isn't.
 *
 * WHY THE ADDRESS COMPARISON IS NORMALIZED AND NOT LITERAL
 * -------------------------------------------------------
 * The geocode service compares its snapshot to the incoming address with `!==`
 * on five raw strings. That is correct about the thing it protects and too
 * strict about everything else: "123 N Main St" versus "123 North Main Street"
 * is the same doorway, and re-resolving it buys nothing and costs a provider
 * call. This adapter compares {@see PropertyAddress::coordinateLookupLine()}
 * instead, so casing, punctuation, whitespace and USPS suffix/directional
 * spellings all fold together while a genuine change to the street number,
 * street name, city, state or ZIP still invalidates.
 *
 * Two consequences worth naming:
 *
 *   County is not compared. The legacy check invalidates on a county change;
 *   this one does not. County is not part of a postal address, does not move
 *   the point, and is routinely filled in later by a different code path than
 *   the one that set the street — invalidating on it re-geocodes an address
 *   that never moved.
 *
 *   A unit change does not invalidate. `coordinateLookupLine()` is unit-free by
 *   construction, so Unit 4A and Unit 4B share one building coordinate and one
 *   lookup. Property identity is untouched by this: the two units are separate
 *   listings with separate `property_location_dna` rows, and
 *   `propertyIdentityLine()` still tells them apart. What is shared is where the
 *   building is, which is genuinely the same fact.
 *
 * WHAT THIS RUNG WILL AND WILL NOT VOUCH FOR
 * ------------------------------------------
 * Two independent gates, both of which must pass before a stored coordinate is
 * reused. Together they are what stops this rung — which the resolver consults
 * first — from freezing a listing on the worst coordinate it ever held.
 *
 *   1. The originating rung must be one no later rung can improve upon, read
 *      from the recorded `geocode_provider`. See {@see self::REUSABLE_PROVIDERS}.
 *   2. The precision must be recorded in `geocode_precision`. It is never
 *      inferred from a source name.
 *
 * Anything short of both produces an unresolved result and the ladder moves on
 * to a rung that can vouch for what it returns. Returning an unvouched-for point
 * would be worse than useless, because the resolver stops at the first resolved
 * result — a coordinate we cannot vouch for would outrank an MLS or corpus
 * coordinate we can.
 *
 * Rooftop is claimable only when the corpus recorded it. This rung never invents
 * a tier: the stored `source_address` is a street line with no unit, and Google's
 * own `location_type` (ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER,
 * APPROXIMATE) was never persisted by this codebase — a row written from an
 * APPROXIMATE result is byte-for-byte indistinguishable from a ROOFTOP one.
 *
 * Local by construction: one indexed SELECT, no HTTP client, no network. It
 * consults no other rung and knows nothing about their availability — it only
 * declines, and lets the resolver do what it already does.
 */
final class ExistingCoordinatesAdapter implements CoordinateProviderAdapterInterface
{
    /**
     * The `geocode_provider` values whose coordinates may short-circuit the rest
     * of the ladder.
     *
     * WHY THIS REPLACED THE `geocode_source` PRECISION INFERENCE
     * ----------------------------------------------------------
     * This rung used to grade an unprovenanced row by its `geocode_source` name:
     * 'saved_meta' and 'google' both inherited {@see CoordinatePrecision::Parcel}.
     * That inference is gone, and its removal is the point of this change.
     *
     * `saved_meta` means only "a caller supplied this coordinate". The caller is
     * usually the browser — `fillFromResolvedAddress()` accepts a latitude and a
     * longitude from the client as unvalidated strings — so the inference took an
     * unverified number, stamped Parcel on it, and returned it as resolved. The
     * resolver stops at the first resolved rung, so from that moment on the
     * browser's coordinate outranked Bridge and the address-point corpus
     * permanently, for that address. `google` had the same defect with a
     * different name on it.
     *
     * Precision is now read ONLY from the recorded `geocode_precision` column. A
     * coordinate whose provenance was never written is not graded by guesswork;
     * it is declined, and the ladder re-derives an answer it can vouch for.
     *
     * WHY REUSE IS KEYED ON THE ORIGINATING RUNG
     * ------------------------------------------
     * This rung is not a source. It is a cache of some earlier rung's answer, and
     * it can only be as good as whatever produced the number. So the question is
     * not "is this coordinate any good" but "can a rung below me still do better".
     *
     * Only the two rungs at or above the address-point corpus qualify:
     *
     *   bridge_mls     the listing record's own published coordinate. Nothing
     *                  below it outranks it — that precedence is deliberate and
     *                  is preserved exactly.
     *   address_point  our imported NENA/NAD corpus, the most authoritative
     *                  address-derived point this platform has.
     *
     * `us_census` is deliberately absent. A Census result is a house number
     * interpolated along a street segment, and {@see CoordinatePrecision::isExact}
     * grades Interpolated as exact — so a precision comparison alone would let a
     * stored Census point keep blocking an authoritative corpus match forever.
     * Declining here is what lets `AddressPointCoordinateAdapter` be consulted
     * once a corpus exists, which is the whole reason the corpus is being built.
     *
     * Declining costs nothing that matters. {@see \App\Services\Location\PropertyCoordinatePersistenceService}
     * short-circuits before the resolver whenever the listing already holds a
     * coordinate for this exact normalized address, and it explicitly does NOT
     * clear a stored coordinate when the ladder returns unresolved. A declined
     * reuse therefore re-derives or changes nothing; it does not discard.
     *
     * @var list<string>
     */
    private const REUSABLE_PROVIDERS = [
        'bridge_mls',
        'address_point',
    ];

    public function providerId(): string
    {
        return 'existing_coordinates';
    }

    public function source(): CoordinateSource
    {
        return CoordinateSource::Existing;
    }

    public function requiresNetwork(): bool
    {
        return false;
    }

    /**
     * Available whenever the table it reads exists.
     *
     * Answered from the schema, not from a flag, and never from the network —
     * an environment that has not run the Location DNA migrations skips this
     * rung instead of throwing inside a publish path.
     */
    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable('property_location_dna');
        } catch (Throwable) {
            return false;
        }
    }

    public function resolve(PropertyAddress $address): PropertyCoordinateResult
    {
        $normalized = $address->coordinateLookupLine();

        // No listing handle means there is no row to look up. Not a failure —
        // an address arriving from a flow that has not saved anything yet simply
        // has no stored coordinate, and the next rung should try.
        if (! $address->hasListingHandle()) {
            return PropertyCoordinateResult::unresolved('no_listing_handle', $normalized);
        }

        $record = PropertyLocationDna::query()
            ->where('listing_type', $address->listingType)
            ->where('listing_id', $address->listingId)
            ->first();

        if ($record === null) {
            return PropertyCoordinateResult::unresolved('no_existing_record', $normalized);
        }

        if ($record->geocode_status !== 'geocoded') {
            return PropertyCoordinateResult::unresolved('existing_not_geocoded', $normalized);
        }

        $latitude  = CoordinateValidator::toFloat($record->geocoded_lat);
        $longitude = CoordinateValidator::toFloat($record->geocoded_lng);

        if ($latitude === null || $longitude === null) {
            return PropertyCoordinateResult::unresolved('existing_coordinates_absent', $normalized);
        }

        // A row can be flagged 'geocoded' and still hold a corrupt pair — the
        // status and the numbers are written by different lines and nothing has
        // ever cross-checked them.
        if (! CoordinateValidator::isValidPair($latitude, $longitude)) {
            return PropertyCoordinateResult::unresolved('existing_coordinates_invalid', $normalized);
        }

        // The correspondence check. Everything above proves we have a point;
        // this proves the point belongs to the address being asked about.
        $storedNormalized = $this->storedLookupLine($record);

        if ($storedNormalized === '' || $storedNormalized !== $normalized) {
            return PropertyCoordinateResult::unresolved('existing_address_changed', $normalized);
        }

        // Which ladder rung originally produced this point. Written by the G4
        // provenance work; absent on every row predating it, and on every row
        // whose coordinate never came through the ladder at all.
        $ladderProvider = trim((string) ($record->geocode_provider ?? ''));

        if ($ladderProvider === '') {
            return PropertyCoordinateResult::unresolved('existing_provenance_absent', $normalized);
        }

        // A rung below this one may still do better. See REUSABLE_PROVIDERS.
        if (! in_array($ladderProvider, self::REUSABLE_PROVIDERS, true)) {
            return PropertyCoordinateResult::unresolved('existing_provider_superseded', $normalized);
        }

        // Precision comes from the recorded column and from nowhere else. There
        // is no longer a fallback that infers a tier from a source name — see
        // REUSABLE_PROVIDERS for why that inference was the defect.
        $precision = $this->storedPrecision($record);

        if ($precision === null) {
            return PropertyCoordinateResult::unresolved('existing_precision_unprovable', $normalized);
        }

        return PropertyCoordinateResult::resolved(
            latitude:          $latitude,
            longitude:         $longitude,
            precision:         $precision,
            source:            CoordinateSource::Existing,
            // The provider recorded is the rung that originally produced the
            // point, not this adapter. "Where did this coordinate come from" has
            // one true answer, and reusing it does not change it.
            //
            // Load-bearing for the round trip, not merely descriptive: this value
            // is what the persistence service stores back as `geocode_provider`.
            // Reporting anything else here — the `geocode_source` name this rung
            // used to return, for instance — would overwrite the originating rung
            // on every save, and the coordinate would fail its own reuse check on
            // the very next resolution.
            provider:          $ladderProvider,
            normalizedAddress: $normalized,
            confidence:        null,
            resolvedAt:        $this->storedResolvedAt($record),
        );
    }

    /**
     * The precision this row explicitly records, or null when it records none
     * this release can read.
     *
     * Null is the honest answer for a legacy row, for a partially-applied
     * migration, and for a tier written by some future release — and it sends
     * the caller to the `geocode_source` inference rather than to a default.
     * Silently substituting a tier here would defeat the allow-list above.
     */
    private function storedPrecision(PropertyLocationDna $record): ?CoordinatePrecision
    {
        // The column only exists once the G4 provenance migration has run; the
        // attribute is simply absent before then.
        $stored = $record->geocode_precision ?? null;

        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        return CoordinatePrecision::tryFrom(trim($stored));
    }

    /**
     * The stored address snapshot, run through exactly the same normalizer as
     * the incoming address.
     *
     * Both sides going through {@see PropertyAddress} is the point: a comparison
     * where only one side is normalized is a comparison that fails on casing,
     * and would quietly re-geocode every address a human typed differently the
     * second time.
     *
     * The snapshot carries no unit — the geocode service was never given one —
     * which is consistent rather than lossy, because the line being compared is
     * unit-free on both sides by design.
     */
    private function storedLookupLine(PropertyLocationDna $record): string
    {
        return (new PropertyAddress(
            address: (string) ($record->source_address ?? ''),
            city:    (string) ($record->source_city    ?? ''),
            county:  (string) ($record->source_county  ?? ''),
            state:   (string) ($record->source_state   ?? ''),
            zip:     (string) ($record->source_zip     ?? ''),
        ))->coordinateLookupLine();
    }

    /**
     * When the stored coordinate was obtained, preserved so age is auditable
     * rather than reset to "now" by the act of reading it.
     */
    private function storedResolvedAt(PropertyLocationDna $record): ?DateTimeImmutable
    {
        $geocodedAt = $record->geocoded_at;

        if ($geocodedAt === null) {
            return null;
        }

        try {
            return new DateTimeImmutable((string) $geocodedAt);
        } catch (Throwable) {
            return null;
        }
    }
}
