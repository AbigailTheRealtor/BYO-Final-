<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;

/**
 * The full ladder a listing flow resolves against: every local rung, then the
 * network rung.
 *
 * WHY THIS EXISTS ALONGSIDE LocalCoordinateLadder
 * -----------------------------------------------
 * {@see LocalCoordinateLadder} is, and stays, exactly the two rungs that cannot
 * leave this machine. Its guarantee — asserted by several tests — is that a
 * resolver built from it is incapable of an outbound request no matter what it
 * is asked. Adding the network rung to it would quietly delete that guarantee.
 *
 * So the ladder that includes the network rung is a separate, explicitly-named
 * thing. A caller now has to choose "local only" or "standard", and the choice
 * is visible at the call site rather than implied.
 *
 * THE CENSUS RUNG GATES ITSELF
 * ----------------------------
 * {@see CensusGeocoderAdapter} is always on this ladder, and is skipped by the
 * resolver whenever `census_geocoder.enabled` is false — which is the shipped
 * default. That is deliberately not expressed as a conditional here.
 *
 * A ladder that omitted the rung when the flag was off would make the flag's
 * effect depend on *when* the ladder was built, so a config change mid-process
 * (a test, a queued job, an octane worker) would be honoured or ignored
 * depending on construction order. Letting the rung answer `isAvailable()` per
 * resolution keeps one source of truth for the flag, and keeps the ladder's
 * shape identical in every environment.
 *
 * PRECEDENCE, AND WHY IT IS THIS ORDER
 * ------------------------------------
 *   1. {@see ExistingCoordinatesAdapter}  a coordinate we already vouched for
 *   2. {@see BridgeMlsCoordinatesAdapter} the MLS feed's own coordinate
 *   3. {@see CensusGeocoderAdapter}       the only rung that costs a request
 *
 * Both local rungs are consulted before the network one, so the cheapest
 * correct answer wins and a provider is asked only when nothing already known
 * can answer. No centroid rung: a ZIP centroid is not a property, and this
 * ladder would rather return nothing than something that will be measured from.
 *
 * A NOTE ON THE BRIDGE RUNG IN CREATE-OFFER FLOWS
 * -----------------------------------------------
 * {@see BridgeMlsCoordinatesAdapter} answers only when the address carries an
 * exact `mlsListingKey`, because matching a feed record by address similarity
 * is guessing. Seller/Landlord Create Offer does not currently persist such a
 * key — its MLS import is a URL/text scrape (MlsListingImportService), not the
 * Bridge OData feed, and supplies no RESO ListingKey.
 *
 * The rung is included anyway. It costs nothing when silent, it is correct the
 * moment a key exists, and leaving it out would mean discovering later that the
 * ladder skipped a free trusted coordinate in favour of a paid lookup.
 */
final class StandardCoordinateLadder
{
    /**
     * Local rungs first, network last.
     *
     * @return list<CoordinateProviderAdapterInterface>
     */
    public static function adapters(): array
    {
        return [
            new ExistingCoordinatesAdapter(),
            new BridgeMlsCoordinatesAdapter(),
            new CensusGeocoderAdapter(),
        ];
    }

    /** A resolver wired with the full ladder. */
    public static function resolver(): PropertyCoordinateResolver
    {
        return new PropertyCoordinateResolver(self::adapters());
    }
}
