<?php

namespace App\Services\Location\Coordinates\Exceptions;

use RuntimeException;

/**
 * The provider is broken, not the address.
 *
 * WHY THIS IS AN EXCEPTION AND NOT AN UNRESOLVED RESULT
 * ----------------------------------------------------
 * {@see \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface}
 * asks adapters to return an unresolved result when an address simply cannot be
 * matched, and to reserve exceptions for genuine faults, "so the orchestrator
 * can distinguish 'no match' from 'provider broken' and trip the breaker only
 * for the latter".
 *
 * That distinction is not cosmetic. The two outcomes look the same to a caller
 * holding a result object — no coordinate either way — and want opposite
 * responses. "This address is not in the corpus" is a final answer about one
 * address: cache it, stop asking. "The service returned a 502" is a statement
 * about the service: do not cache it, and if it keeps happening, stop calling
 * the provider entirely. Collapsing them into one unresolved result would mean
 * a provider outage silently teaching the cache that thousands of real
 * addresses do not exist.
 *
 * WHAT COUNTS AS A FAULT
 * ----------------------
 * Transport failure, timeout, 5xx, 429, or a 200 whose body is not the
 * documented shape. Notably NOT a fault: an empty match list (the ordinary "no
 * such address" answer, returned with HTTP 200) or a 400 caused by an address
 * this adapter should not have sent.
 *
 * WHO CATCHES IT
 * --------------
 * {@see \App\Services\Location\Coordinates\PropertyCoordinateResolver} catches
 * it per rung, skips that rung and continues down the ladder — so the
 * resolver's own promise to never throw for an unresolvable address survives
 * the arrival of the first adapter that genuinely can. The breaker and the
 * per-provider budget that will consume this signal properly are G4.
 */
final class CoordinateProviderUnavailable extends RuntimeException
{
    /**
     * @param string $providerId the adapter's {@see
     *        \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface::providerId()},
     *        carried so a caught fault can be attributed without parsing the
     *        message.
     */
    public function __construct(
        public readonly string $providerId,
        string $message,
    ) {
        parent::__construct($message);
    }
}
