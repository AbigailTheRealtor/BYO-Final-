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
     * The provider itself misbehaved — timeout, 5xx, 429, unparseable body.
     * The only kind that should count against a circuit breaker.
     */
    public const KIND_FAULT = 'fault';

    /**
     * We declined to call, because an hourly or daily cap was already spent.
     * Nothing is wrong with the provider; we are rationing it.
     */
    public const KIND_RATE_LIMITED = 'rate_limited';

    /** We declined to call, because the breaker is open after repeated faults. */
    public const KIND_CIRCUIT_OPEN = 'circuit_open';

    /**
     * @param string $providerId the adapter's {@see
     *        \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface::providerId()},
     *        carried so a caught fault can be attributed without parsing the
     *        message.
     * @param string $kind why the provider is unavailable. All three kinds mean
     *        "we could not ask" to a caller, which is why they share a type —
     *        but only KIND_FAULT is evidence about the provider's health, so
     *        collapsing them would make the breaker trip on its own rationing.
     * @param string $reason the structured, machine-readable reason recorded on
     *        telemetry, e.g. `census_hourly_cap_reached`. Distinct from the
     *        human message.
     */
    private function __construct(
        public readonly string $providerId,
        string $message,
        public readonly string $kind = self::KIND_FAULT,
        public readonly string $reason = 'provider_fault',
    ) {
        parent::__construct($message);
    }

    /** The provider misbehaved. Counts against the breaker. */
    public static function fault(string $providerId, string $message, string $reason = 'provider_fault'): self
    {
        return new self($providerId, $message, self::KIND_FAULT, $reason);
    }

    /** A cap was already spent. Does NOT count against the breaker. */
    public static function rateLimited(string $providerId, string $reason, string $message): self
    {
        return new self($providerId, $message, self::KIND_RATE_LIMITED, $reason);
    }

    /** The breaker is open. Does NOT count against the breaker — it is the breaker. */
    public static function circuitOpen(string $providerId, string $message): self
    {
        return new self($providerId, $message, self::KIND_CIRCUIT_OPEN, 'provider_circuit_open');
    }

    /**
     * True when this is evidence the provider is unhealthy, rather than a
     * decision we made about it.
     */
    public function isProviderFault(): bool
    {
        return $this->kind === self::KIND_FAULT;
    }
}
