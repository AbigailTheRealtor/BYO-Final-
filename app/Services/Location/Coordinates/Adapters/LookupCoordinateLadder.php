<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;

/**
 * The ladder for an address somebody typed, as opposed to a property we hold.
 *
 * WHY THIS EXISTS AT ALL, WHEN StandardCoordinateLadder ALREADY DOES THIS
 * ----------------------------------------------------------------------
 * It does not do this. {@see StandardCoordinateLadder} answers the question
 * "where is THIS LISTING?", and its first two rungs answer it from records:
 * {@see ExistingCoordinatesAdapter} reads the coordinate already stored against
 * a listing id, and {@see BridgeMlsCoordinatesAdapter} reads the one on an MLS
 * record key. Both are correct there and both are meaningless — potentially
 * worse than meaningless — here.
 *
 * A radius centre and an Important Place are addresses of somewhere else: a
 * workplace, a school, a relative's house. They carry no listing id and no MLS
 * key, so those two rungs could not match anything today. That is an accident
 * of the current call site, not a safety property, and safety properties should
 * not be accidents. If a future caller ever passed a listing handle alongside
 * typed text — which is an easy thing to do, since {@see \App\Services\Location\Coordinates\PropertyAddress}
 * carries both — the standard ladder would answer with the LISTING'S OWN
 * coordinate and report a clean success. The user would have typed their
 * child's school and been handed the property they are selling, with no error
 * anywhere.
 *
 * Composing the ladder without those rungs makes that unreachable by
 * construction rather than by convention. {@see \Tests\Feature\Location\AddressLookupServiceTest}
 * pins the composition, because a rung added here later would reopen it.
 *
 * WHAT IS ON IT
 * -------------
 *   1. {@see AddressPointCoordinateAdapter}  our own imported corpus — local,
 *      free, exact, and inert today (flag off, corpus empty, no importer). It
 *      is first because a published address point beats interpolating a house
 *      number along a street range, and because when it does answer it costs a
 *      single local query.
 *   2. {@see CensusGeocoderAdapter}          the US Census Bureau geocoder —
 *      the only network rung, free, keyless, public-domain output, and the one
 *      provider whose licence lets us keep what it tells us.
 *
 * Both are shared instances of the same classes the standard ladder uses. There
 * is no second Census implementation, no second cache, no second budget and no
 * second circuit breaker: a lookup from this ladder and a property resolution
 * from the standard one draw on the same 30-day cache and the same hourly and
 * daily ceilings, which is the only way those ceilings can mean anything.
 *
 * @see StandardCoordinateLadder for the listing-resolution ladder
 * @see \App\Services\Location\Lookup\AddressLookupService for the only caller
 */
final class LookupCoordinateLadder
{
    /**
     * The rungs, in precedence order. Local before network, always.
     *
     * @return list<CoordinateProviderAdapterInterface>
     */
    public static function adapters(): array
    {
        return [
            new AddressPointCoordinateAdapter(),
            new CensusGeocoderAdapter(),
        ];
    }

    /** A resolver wired with the free-text lookup ladder. */
    public static function resolver(): PropertyCoordinateResolver
    {
        return new PropertyCoordinateResolver(self::adapters());
    }
}
