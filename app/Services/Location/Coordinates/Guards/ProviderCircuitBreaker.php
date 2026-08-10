<?php

namespace App\Services\Location\Coordinates\Guards;

use Illuminate\Support\Facades\Cache;

/**
 * Stops calling a provider that has repeatedly failed, for a fixed cooldown.
 *
 * WHY
 * ---
 * When a provider is down, every further request costs a full timeout before
 * failing in exactly the way the previous one did. Ten listings resolving
 * against a dead geocoder with a 10-second ceiling is a hundred seconds spent
 * learning something the first request already established. Worse, retrying
 * hard against a struggling service is how a partial outage becomes a total
 * one, and how an IP stops being welcome.
 *
 * The breaker converts "fail slowly, repeatedly, forever" into "fail slowly
 * once, then fail instantly for a while". Local rungs are untouched throughout
 * — a Census outage must never stop a coordinate we already possess from being
 * returned, which is precisely why the network rung sits below the local ones.
 *
 * THE POLICY, AND WHY IT IS THIS SIMPLE
 * -------------------------------------
 *   - Consecutive faults are counted within a rolling window.
 *   - At the threshold the circuit opens for a fixed cooldown.
 *   - While open, no request is attempted at all.
 *   - When the cooldown expires the circuit is closed and the count is clear;
 *     the next request is a normal one, and a single fault does not reopen it.
 *
 * No half-open state, no probe request, no exponential backoff. Those matter
 * when a stampede of clients could re-kill a recovering service on the first
 * request through; this is one application calling a government geocoder from a
 * queue, where the realistic failure is a broad outage, not a thundering herd.
 * The extra states would be more code, more ways to be subtly wrong, and no
 * behaviour anyone would notice.
 *
 * ONLY FAULTS COUNT
 * -----------------
 * A no-match is not a failure — it is the provider working correctly and
 * saying no. A rate-limit block is not a failure either; it is our own
 * decision, and counting it would let the breaker trip on our own rationing and
 * then stay open blaming the provider. Only
 * {@see \App\Services\Location\Coordinates\Exceptions\CoordinateProviderUnavailable::isProviderFault()}
 * reaches {@see self::recordFault()}.
 *
 * Provider-neutral: constructed with an id, a threshold and a cooldown, so the
 * commercial adapter later wraps identically.
 */
final class ProviderCircuitBreaker
{
    private const PREFIX = 'coord_provider_breaker_v1_';

    /**
     * @param int $failureThreshold faults within the window before opening.
     * @param int $cooldownSeconds  how long the circuit stays open.
     * @param int $windowSeconds    how long a fault counts toward the threshold.
     *        Bounded so that isolated faults days apart never accumulate into
     *        an outage that never happened.
     */
    public function __construct(
        private readonly string $providerId,
        private readonly int $failureThreshold,
        private readonly int $cooldownSeconds,
        private readonly int $windowSeconds,
    ) {
    }

    /** True when the provider must not be called. */
    public function isOpen(): bool
    {
        return Cache::get($this->openKey()) !== null;
    }

    /**
     * Record a genuine provider fault, opening the circuit at the threshold.
     *
     * The fault counter is cleared when the circuit opens, so the cooldown
     * starts from a clean slate: without that, the count would still be at the
     * threshold when the circuit closed and the very next fault would reopen it
     * immediately, turning a fixed cooldown into a permanent outage.
     */
    public function recordFault(): void
    {
        $key = $this->faultKey();

        Cache::add($key, 0, $this->windowSeconds);

        if ((int) Cache::increment($key) >= $this->failureThreshold) {
            Cache::put($this->openKey(), true, $this->cooldownSeconds);
            Cache::forget($key);
        }
    }

    /**
     * Record a successful call.
     *
     * Clears the fault count, so the threshold means "consecutive-ish faults"
     * rather than "faults ever" — a provider that fails once an hour and
     * succeeds the rest of the time is not broken and must not accumulate its
     * way to an outage.
     */
    public function recordSuccess(): void
    {
        Cache::forget($this->faultKey());
    }

    /** Faults recorded in the current window. Diagnostics only. */
    public function faultCount(): int
    {
        return (int) Cache::get($this->faultKey(), 0);
    }

    /** 'open' or 'closed', for telemetry. */
    public function state(): string
    {
        return $this->isOpen() ? 'open' : 'closed';
    }

    private function faultKey(): string
    {
        return self::PREFIX . $this->providerId . '_faults';
    }

    private function openKey(): string
    {
        return self::PREFIX . $this->providerId . '_open';
    }
}
