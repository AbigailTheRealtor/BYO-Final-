<?php

namespace App\Services\Location\Coordinates\Guards;

use Illuminate\Support\Facades\Cache;

/**
 * Hourly and daily ceilings on how often one provider may be called.
 *
 * WHY A FREE PROVIDER STILL NEEDS A CAP
 * -------------------------------------
 * The US Census Geocoder costs nothing and publishes no rate limit, so the
 * obvious reading is that there is nothing to ration. That reading is what
 * makes the cap necessary. Cost is not the only thing a runaway loop spends: a
 * queue that retries, an observer that fires per save, or a listing page that
 * resolves on every render can each turn one user action into thousands of
 * outbound requests. Against a paid provider that produces a bill, which is
 * unpleasant but visible. Against a free one it produces nothing at all until
 * the Census Bureau starts refusing us — at which point the damage is to a
 * shared public service and to our access to it, and neither is undone by
 * noticing quickly.
 *
 * So the discipline is deliberately independent of price. A provider that
 * cannot bankrupt us can still be abused, and the ceiling exists to make that
 * impossible rather than unlikely.
 *
 * PROVIDER-NEUTRAL BY CONSTRUCTION
 * --------------------------------
 * Nothing here knows what Census is. It is constructed with a provider id and
 * two numbers, so the commercial adapter that arrives later wraps in exactly
 * the same way — which is the point of writing it now, while the only consumer
 * is a provider whose caps do not really matter.
 *
 * WHAT THIS IS NOT
 * ----------------
 * Not a billing system, not a quota ledger, not distributed-consistent. Two
 * concurrent workers can each read a count of 999 against a cap of 1000 and
 * both proceed. That is understood and accepted: this is a backstop against
 * runaway loops, where the overshoot is one request per worker, not the
 * difference between 1,000 and 100,000. A design that needed locks to be
 * correct here would be a worse design.
 */
final class ProviderRequestBudget
{
    private const PREFIX = 'coord_provider_budget_v1_';

    /** Slightly over an hour/day so a bucket cannot expire mid-window. */
    private const HOUR_TTL = 3900;
    private const DAY_TTL  = 90_000;

    /**
     * @param int|null $hourlyCap maximum requests per clock hour; null = no
     *        hourly ceiling.
     * @param int|null $dailyCap  maximum requests per clock day; null = no
     *        daily ceiling.
     */
    public function __construct(
        private readonly string $providerId,
        private readonly ?int $hourlyCap,
        private readonly ?int $dailyCap,
    ) {
    }

    /**
     * The structured reason this provider may not be called right now, or null
     * when it may.
     *
     * Returns a reason rather than a bool so the caller can record *which*
     * ceiling stopped it. "We stopped calling Census today" and "we stop
     * calling Census every hour" are different operational problems and want
     * different responses.
     */
    public function blockedReason(): ?string
    {
        if ($this->hourlyCap !== null && $this->used($this->hourKey()) >= $this->hourlyCap) {
            return 'provider_hourly_cap_reached';
        }

        if ($this->dailyCap !== null && $this->used($this->dayKey()) >= $this->dailyCap) {
            return 'provider_daily_cap_reached';
        }

        return null;
    }

    /**
     * Count one outbound request against both windows.
     *
     * Called when a request is actually about to be sent — never for a cache
     * hit, which is the entire reason the cache is worth having. A budget that
     * counted cache hits would ration our own memory.
     */
    public function recordRequest(): void
    {
        $this->bump($this->hourKey(), self::HOUR_TTL);
        $this->bump($this->dayKey(), self::DAY_TTL);
    }

    /** @return array{hourly: int, daily: int} requests spent in each window. */
    public function spent(): array
    {
        return [
            'hourly' => $this->used($this->hourKey()),
            'daily'  => $this->used($this->dayKey()),
        ];
    }

    private function used(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    private function bump(string $key, int $ttl): void
    {
        // `add` only writes when the key is absent, so it both seeds the
        // counter and refreshes nothing on subsequent calls — the bucket's TTL
        // stays anchored to the start of its window rather than sliding
        // forward on every request, which would make a busy hour never expire.
        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    private function hourKey(): string
    {
        return self::PREFIX . $this->providerId . '_h_' . gmdate('YmdH');
    }

    private function dayKey(): string
    {
        return self::PREFIX . $this->providerId . '_d_' . gmdate('Ymd');
    }
}
