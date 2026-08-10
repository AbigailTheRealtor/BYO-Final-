<?php

namespace App\Services\Location\Coordinates;

/**
 * One rung of the resolution ladder.
 *
 * Adapters are tried in precedence order until one resolves. Each answers for
 * exactly one source of coordinates and knows nothing about the others, so the
 * ladder can be reordered, extended or have a provider swapped out entirely in
 * configuration.
 *
 * Planned implementations (none built in G1):
 *
 *   ExistingCoordinatesAdapter   stored coordinates, address unchanged   local
 *   BridgeMlsCoordinatesAdapter  bridge_properties Latitude/Longitude    local
 *   CensusGeocoderAdapter        US Census Geocoder                      network
 *   CommercialGeocoderAdapter    Geoapify today, swappable               network
 *   LocalCorpusAdapter           our own `addresses` corpus              local
 *
 * `CommercialGeocoderAdapter` is named for its role, not its vendor, precisely
 * so that replacing Geoapify with OpenCage later touches one adapter and no
 * caller.
 */
interface CoordinateProviderAdapterInterface
{
    /**
     * Stable identifier recorded on the result and used for per-provider
     * budgets, caps and telemetry. E.g. 'us_census', 'bridge_mls', 'geoapify'.
     */
    public function providerId(): string;

    /** Which rung of the ladder this adapter answers for. */
    public function source(): CoordinateSource;

    /**
     * True when this adapter reaches the network.
     *
     * Read by the orchestrator before any call, so caps, budgets, circuit
     * breaking and "is this allowed to run right now" can be applied to exactly
     * the adapters that cost money or can fail — and so a test can assert the
     * local-only ladder never touches a socket.
     */
    public function requiresNetwork(): bool;

    /**
     * True when this adapter is currently permitted to run — credentials
     * present, feature flag on, breaker closed.
     *
     * Must be answerable without a network call. An adapter that is not
     * available is skipped, never awaited.
     */
    public function isAvailable(): bool;

    /**
     * Attempt resolution. Return an unresolved result rather than throwing when
     * the address simply cannot be matched; reserve exceptions for genuine
     * faults so the orchestrator can distinguish "no match" from "provider
     * broken" and trip the breaker only for the latter.
     */
    public function resolve(PropertyAddress $address): PropertyCoordinateResult;
}
