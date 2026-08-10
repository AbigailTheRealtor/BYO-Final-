<?php

namespace App\Services\Location\Coordinates;

/**
 * Orchestrates the adapter ladder.
 *
 * INERT IN G1 — BY CONSTRUCTION, NOT BY CONVENTION
 * ------------------------------------------------
 * Constructed with an empty adapter list and bound to nothing. With no adapters
 * it resolves nothing, calls nothing and reaches no network. That is not a
 * temporary state guarded by a flag somebody could flip by accident: there is
 * no code path in this class that can produce a coordinate without an adapter
 * being passed to the constructor, and G1 passes none.
 *
 * No listing flow references this class yet. Wiring happens in later phases:
 * local adapters in G2, the US Census adapter in G3, persistence and cost
 * controls in G4 — and Seller/Landlord Location DNA dispatch stays gated behind
 * G4.5 regardless, so a working geocoder does not silently switch the pipeline
 * on.
 *
 * PRECEDENCE
 * ----------
 * Adapters are consulted in the order given. The intended order —
 * {@see self::INTENDED_PRECEDENCE} — puts every local source ahead of every
 * network one, so the cheapest correct answer wins and a provider is asked only
 * when nothing already known can answer.
 *
 * The first adapter to return a resolved result wins. A coarse centroid sits
 * last and is still returned honestly labelled, so a caller can frame a map
 * without ever being handed a centroid that claims to be the property —
 * {@see PropertyCoordinateResult::isUsableForLocationDna()} keeps that promise.
 */
final class PropertyCoordinateResolver implements PropertyCoordinateResolverInterface
{
    /**
     * The ladder this resolver is designed around, in order. Documents the G6
     * target; the constructor is what actually decides at runtime.
     */
    public const INTENDED_PRECEDENCE = [
        CoordinateSource::Existing,   // already stored, address unchanged   — local
        CoordinateSource::Mls,        // Bridge/MLS feed coordinates         — local
        CoordinateSource::Geocoder,   // US Census, then commercial fallback — network
        CoordinateSource::Centroid,   // ZIP / city centroid, coarse         — local
    ];

    /** @var list<CoordinateProviderAdapterInterface> */
    private readonly array $adapters;

    /**
     * @param iterable<CoordinateProviderAdapterInterface> $adapters in precedence
     *        order. Empty in G1 — see the class docblock.
     */
    public function __construct(iterable $adapters = [])
    {
        $ordered = [];

        foreach ($adapters as $adapter) {
            $ordered[] = $adapter;
        }

        $this->adapters = $ordered;
    }

    public function resolve(PropertyAddress $address): PropertyCoordinateResult
    {
        $normalized = $address->coordinateLookupLine();

        if (! $address->hasMinimumForLookup()) {
            return PropertyCoordinateResult::unresolved(
                'insufficient_address',
                $normalized !== '' ? $normalized : null
            );
        }

        foreach ($this->adapters as $adapter) {
            if (! $adapter->isAvailable()) {
                continue;
            }

            $result = $adapter->resolve($address);

            if ($result->isResolved()) {
                return $result;
            }
        }

        // No adapter answered. Fail closed with a reason rather than inventing a
        // coordinate — the shape the merged Google kill-switch work established.
        return PropertyCoordinateResult::unresolved('no_adapter_resolved', $normalized);
    }

    /**
     * True when this resolver cannot reach the network at all, because no
     * configured adapter requires it.
     *
     * Exists so the zero-outbound guarantee is assertable rather than asserted
     * in prose. In G1, with no adapters, this is trivially true.
     */
    public function isLocalOnly(): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->requiresNetwork()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> provider ids in precedence order, for diagnostics. */
    public function providerIds(): array
    {
        return array_map(
            static fn (CoordinateProviderAdapterInterface $a): string => $a->providerId(),
            $this->adapters
        );
    }
}
