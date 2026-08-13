<?php

namespace App\Services\Location\Coordinates;

use Illuminate\Support\Facades\Log;
use Throwable;

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
 *
 * FAULTS (added in G3)
 * --------------------
 * {@see PropertyCoordinateResolverInterface} promises callers that a provider
 * outage "degrades to 'no coordinate' rather than an exception in a publish
 * path". Until G3 that promise cost nothing to keep: every rung was a local
 * database read, and the adapter contract's allowance for throwing on genuine
 * faults had no one to exercise it. The US Census rung is the first that can
 * time out, 502, or answer with something that is not JSON.
 *
 * So a throwing rung is caught here, logged, and skipped — the ladder continues
 * to the next rung exactly as it would for a rung that simply found nothing.
 * The distinction is not discarded: when a fault occurred and nothing else
 * resolved, the unresolved reason is `provider_unavailable` rather than
 * `no_adapter_resolved`, so a caller can tell "this address does not exist"
 * from "we could not ask". Acting on that signal — circuit breaking, budgets,
 * retry — is G4; preserving it is this class's job.
 */
final class PropertyCoordinateResolver implements PropertyCoordinateResolverInterface
{
    /**
     * The ladder this resolver is designed around, in order. Documents the G6
     * target; the constructor is what actually decides at runtime.
     */
    public const INTENDED_PRECEDENCE = [
        CoordinateSource::Existing,     // already stored, address unchanged   — local
        CoordinateSource::Mls,          // Bridge/MLS feed coordinates         — local
        CoordinateSource::AddressPoint, // our imported address-point corpus   — local
        CoordinateSource::Geocoder,     // US Census, then commercial fallback — network
        CoordinateSource::Centroid,     // ZIP / city centroid, coarse         — local
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

        $faulted = false;

        foreach ($this->adapters as $adapter) {
            if (! $adapter->isAvailable()) {
                continue;
            }

            try {
                $result = $adapter->resolve($address);
            } catch (Throwable $e) {
                // A rung that is broken is skipped, not fatal. See FAULTS in the
                // class docblock. The try covers resolution only: isAvailable()
                // is required to answer without a network call, so a throw there
                // is a bug to surface rather than an outage to absorb.
                $faulted = true;

                Log::warning('PropertyCoordinateResolver: adapter faulted', [
                    'provider' => $adapter->providerId(),
                    'error'    => $e->getMessage(),
                ]);

                continue;
            }

            if ($result->isResolved()) {
                return $result;
            }
        }

        // No adapter answered. Fail closed with a reason rather than inventing a
        // coordinate — the shape the merged Google kill-switch work established.
        //
        // The reason distinguishes the two ways of answering nothing, because
        // they call for opposite responses: nobody could match this address
        // (final; the address is the problem) versus a rung broke on the way
        // (retryable; the provider is the problem).
        return PropertyCoordinateResult::unresolved(
            $faulted ? 'provider_unavailable' : 'no_adapter_resolved',
            $normalized
        );
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
