<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;

/**
 * The G2 ladder: every rung that can answer without leaving this machine, in
 * precedence order, defined in exactly one place.
 *
 * WHY A FACTORY AND NOT A CONTAINER BINDING
 * -----------------------------------------
 * Binding {@see \App\Services\Location\Coordinates\PropertyCoordinateResolverInterface}
 * in a service provider would make the resolver injectable, and injectable is
 * one edit away from injected. G2's guarantee is that nothing in a listing flow
 * can reach this code — a guarantee that holds because there is no binding to
 * resolve, not because no one has written the injection yet. The binding belongs
 * with the phase that has something to bind it for (G4/G5).
 *
 * WHY THE ORDER IS THIS ORDER
 * ---------------------------
 * Existing before MLS. Both are free, so cost does not decide it; correspondence
 * does. {@see ExistingCoordinatesAdapter} only answers when it has proven the
 * stored coordinate belongs to the address in hand, and that coordinate is the
 * one this platform has already been treating as the property's position. An MLS
 * coordinate is a second opinion from outside, keyed to a feed record rather
 * than to our address. Preferring the proven local answer keeps a property from
 * moving on a screen because a feed re-published it slightly differently.
 *
 * Both rungs are strictly local, which is what {@see self::isLocalOnly()}
 * asserts rather than assumes: G2 adds no network rung, and a resolver built
 * here cannot make an outbound request no matter what it is asked to resolve.
 * The US Census rung arrives in G3; Geoapify is later still and behind its own
 * decision.
 */
final class LocalCoordinateLadder
{
    /**
     * The two G2 rungs, in precedence order.
     *
     * @return list<CoordinateProviderAdapterInterface>
     */
    public static function adapters(): array
    {
        return [
            new ExistingCoordinatesAdapter(),
            new BridgeMlsCoordinatesAdapter(),
        ];
    }

    /** A resolver wired with — and limited to — the local ladder. */
    public static function resolver(): PropertyCoordinateResolver
    {
        return new PropertyCoordinateResolver(self::adapters());
    }
}
