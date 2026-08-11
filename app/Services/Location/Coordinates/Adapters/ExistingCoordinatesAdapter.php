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
 * PRECISION: WHAT THIS TABLE CAN AND CANNOT PROVE
 * -----------------------------------------------
 * There is no precision column. Nothing in `property_location_dna` records
 * whether a point is a rooftop, a parcel centroid or a street interpolation, so
 * precision has to be inferred from `geocode_source` — and the inference is
 * capped at {@see CoordinatePrecision::Parcel} for every source. Rooftop is
 * never claimed by this adapter, for two independent reasons, either of which
 * would be sufficient:
 *
 *   1. The stored `source_address` is a street line with no unit — it is the
 *      same unit-free question `forBuilding()` exists to describe. We located a
 *      building.
 *   2. Google's Geocoding API reports its own `location_type` (ROOFTOP,
 *      RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE) and this codebase has
 *      never persisted it. A row written from an APPROXIMATE result is
 *      byte-for-byte indistinguishable from one written from a ROOFTOP result.
 *
 * A `geocode_source` this adapter does not recognise is not guessed at either:
 * it produces an unresolved result with `existing_precision_unprovable`, and
 * the ladder moves on to a rung that can vouch for what it returns. Returning
 * it as a coarse-but-resolved point would be worse than useless, because the
 * resolver stops at the first resolved result — a coordinate we cannot vouch
 * for would outrank an MLS coordinate we can.
 *
 * Local by construction: one indexed SELECT, no HTTP client, no network.
 */
final class ExistingCoordinatesAdapter implements CoordinateProviderAdapterInterface
{
    /**
     * The `geocode_source` values this adapter is willing to vouch for, and the
     * most precise tier each one honestly supports.
     *
     * An allow-list rather than a default, so a `geocode_source` written by some
     * future code path is refused until somebody decides what it means. The
     * failure direction matters: an unknown source that silently inherited
     * `Parcel` would feed an unvouched-for point into flood-zone and school
     * boundary work, which is the exact failure this ladder exists to prevent.
     *
     * @var array<string, CoordinatePrecision>
     */
    private const PRECISION_BY_GEOCODE_SOURCE = [
        // Written when a caller supplied pre-resolved coordinates for this exact
        // record: a user-selected autocomplete address, or an MLS feed's own
        // Latitude/Longitude. Both identify the property; neither proves a roof.
        'saved_meta' => CoordinatePrecision::Parcel,

        // A Google Geocoding API result for a full street address. Capped at
        // Parcel because the API's location_type was never stored — see the
        // class docblock. Reused, not re-requested: this is a database read, and
        // no kill switch governs reading a number we already have.
        'google'     => CoordinatePrecision::Parcel,
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

        $geocodeSource = (string) ($record->geocode_source ?? '');

        // Prefer the precision the row actually records (G4 column) over the one
        // inferred from `geocode_source`.
        //
        // The inference exists because older rows have nothing better. But a row
        // written from the resolver ladder knows its real tier, and the two can
        // disagree in the dangerous direction: such a row carries
        // `geocode_source = 'saved_meta'` — accurate, a caller did supply the
        // coordinate — and the allow-list grades that Parcel, while the point
        // may actually be a street-range Interpolation. Reading it back one tier
        // too precise would launder an estimate into something flood-zone work
        // treats as the property.
        //
        // Stored value first, inference only as a fallback, and an unparseable
        // stored value falls through to the inference rather than to a guess.
        $precision = $this->storedPrecision($record)
            ?? self::PRECISION_BY_GEOCODE_SOURCE[$geocodeSource]
            ?? null;

        if ($precision === null) {
            return PropertyCoordinateResult::unresolved('existing_precision_unprovable', $normalized);
        }

        return PropertyCoordinateResult::resolved(
            latitude:          $latitude,
            longitude:         $longitude,
            precision:         $precision,
            source:            CoordinateSource::Existing,
            // The provider recorded is the one that originally produced the
            // point, not this adapter. "Where did this coordinate come from" has
            // one true answer, and reusing it does not change it.
            provider:          $geocodeSource,
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
