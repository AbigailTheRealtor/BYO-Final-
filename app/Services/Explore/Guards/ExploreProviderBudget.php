<?php

namespace App\Services\Explore\Guards;

use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;

/**
 * How much outbound Stellar/Bridge traffic Explore may cause, at two scopes.
 *
 * THIS IS NOT A BUDGET IMPLEMENTATION. IT COMPOSES THE EXISTING ONE.
 * ------------------------------------------------------------------
 * Every counter, key, window and TTL here belongs to
 * {@see ProviderRequestBudget}. This class holds no storage, does no counting
 * and defines no time window; it decides WHICH budgets apply to an Explore
 * provider call and asks them in order. Building a second accounting mechanism
 * would mean two things that must agree forever about what "a request" is, and
 * they would not.
 *
 * The shared budget already supports both scopes without modification, because
 * its provider id is an arbitrary string rather than an enum: a global ceiling
 * and a per-actor ceiling are the same class under two ids. That is why no new
 * budget system was written.
 *
 * WHY TWO SCOPES AND NOT ONE
 * --------------------------
 * They fail in opposite directions and neither substitutes for the other.
 *
 *   · The ACTOR ceiling stops one browser from traversing unlimited distinct
 *     cold tiles. Tile snapping and the fetch cache already make REPEAT visits
 *     free; nothing made DISTINCT tiles bounded, and a viewport request can
 *     cost up to five provider pages per transaction type. Inside the existing
 *     `throttle:120,1` that is 1,200 provider requests a minute from one
 *     caller — an HTTP throttle bounds requests, not provider spend.
 *
 *   · The GLOBAL ceiling stops the case the actor ceiling cannot see: many
 *     actors, or one actor arriving from many addresses. It is the emergency
 *     stop for the bill, and it is the reason this exists at all — an earlier
 *     Google integration elsewhere produced roughly 16,000 unexpected requests,
 *     and no per-caller limit would have caught it.
 *
 * ACTOR IDENTITY IS BORROWED, NOT INVENTED
 * ----------------------------------------
 * The actor is `user id, else IP` — byte for byte the identity
 * `RouteServiceProvider::configureRateLimiting()` already uses for every
 * throttled route in this application. Nothing here fingerprints a browser,
 * reads a device signal, or persists anything about a visitor. The value is
 * hashed before it becomes a cache key so a raw IP address never lands in the
 * cache store or in a log line.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 * --------------------------------
 * It does not raise. Census raises `CoordinateProviderUnavailable` because a
 * ladder rung that declines and a rung that fails are the same answer to its
 * caller. Explore's caller needs the difference: "we chose not to ask" must
 * produce a degraded response that still shows last-known inventory, never an
 * empty map, so the decision is returned and {@see \App\Services\Explore\ExploreInventoryService}
 * acts on it.
 *
 * NOT ATOMIC, AND THAT IS INHERITED AND ACCEPTED
 * ----------------------------------------------
 * The shared budget counts through the cache, which is atomic on Redis and
 * read-modify-write on the file driver this environment currently uses. Two
 * racing requests can each read 999 against a cap of 1,000 and both proceed.
 * The overshoot is one request per racing worker — bounded by concurrency, not
 * by the size of the runaway — and the same-tile advisory lock already
 * serialises the case that would race hardest. This is a backstop against a
 * runaway loop, not a billing ledger, and a design that needed locks to be
 * correct here would be a worse design.
 */
class ExploreProviderBudget
{
    /** The provider this budget rations. One id, so scopes cannot collide. */
    public const PROVIDER = 'explore_bridge';

    public const REASON_DISABLED_GUARD = 'explore_provider_budget_misconfigured';
    public const REASON_KILL_SWITCH    = 'explore_provider_kill_switch';

    /**
     * The structured reason Explore may not call the provider right now, or
     * null when it may.
     *
     * @param string|null $actorKey the already-hashed actor identity, or null
     *        to check the global ceiling alone (a scheduled or console caller
     *        with no browser behind it).
     */
    public function blockedReason(?string $actorKey): ?string
    {
        if ($this->killed()) {
            return self::REASON_KILL_SWITCH;
        }

        if (! $this->enabled()) {
            // A guard that has been switched off is the one state where this
            // class must NOT wave traffic through. An unbudgeted public path to
            // a paid provider is the failure being fixed, so "the guard is off"
            // is treated as "do not call the provider" rather than as
            // "call it freely". Turning the guard off is therefore a way to
            // stop Explore spending, never a way to unleash it.
            return self::REASON_DISABLED_GUARD;
        }

        $global = $this->global()->blockedReason();

        if ($global !== null) {
            return 'global_' . $global;
        }

        if ($actorKey === null) {
            return null;
        }

        $actor = $this->actor($actorKey)->blockedReason();

        return $actor !== null ? 'actor_' . $actor : null;
    }

    /**
     * Count outbound requests that were ACTUALLY SENT against both scopes.
     *
     * Counts attempts, not successes: a request that reached the provider and
     * came back a failure consumed exactly as much of the provider's patience
     * and of our bill as one that worked. Counting only successes is how a
     * failing integration retries its way through a ceiling that appears to be
     * holding.
     *
     * A cache hit sends nothing and must never reach this method — rationing
     * our own memory would defeat the cache the ceiling depends on.
     */
    public function record(?string $actorKey, int $requests = 1): void
    {
        if ($requests < 1) {
            return;
        }

        $global = $this->global();
        $scoped = $actorKey !== null ? $this->actor($actorKey) : null;

        for ($i = 0; $i < $requests; $i++) {
            $global->recordRequest();
            $scoped?->recordRequest();
        }
    }

    /** @return array{global: array{hourly:int,daily:int}, actor: array{hourly:int,daily:int}|null} */
    public function spent(?string $actorKey): array
    {
        return [
            'global' => $this->global()->spent(),
            'actor'  => $actorKey !== null ? $this->actor($actorKey)->spent() : null,
        ];
    }

    /**
     * The actor identity, hashed.
     *
     * `user id, else IP` is the identity every throttled route in this
     * application already uses; this adds no new signal. The hash keeps a raw
     * address out of the cache store and out of every log line the guard
     * emits — the same posture as
     * {@see \App\Services\Location\Coordinates\Guards\CoordinateProviderTelemetry::addressHash()}.
     *
     * Truncated to 16 hex characters: long enough that two visitors sharing a
     * bucket is not a practical concern, short enough for a cache key.
     */
    public static function actorKey(?int $userId, ?string $ipAddress): ?string
    {
        $identity = $userId !== null && $userId > 0
            ? 'u:' . $userId
            : 'i:' . trim((string) $ipAddress);

        if ($identity === 'i:') {
            // No user and no address. A caller we cannot attribute gets no
            // actor bucket — the global ceiling still applies to it.
            return null;
        }

        return substr(hash('sha256', $identity), 0, 16);
    }

    public function enabled(): bool
    {
        return (bool) config('explore.provider_budget.enabled', true);
    }

    public function killed(): bool
    {
        return (bool) config('explore.provider_budget.kill_switch', false);
    }

    private function global(): ProviderRequestBudget
    {
        return new ProviderRequestBudget(
            self::PROVIDER . '_global',
            $this->cap('global_hourly'),
            $this->cap('global_daily'),
        );
    }

    private function actor(string $actorKey): ProviderRequestBudget
    {
        return new ProviderRequestBudget(
            self::PROVIDER . '_actor_' . $actorKey,
            $this->cap('actor_hourly'),
            $this->cap('actor_daily'),
        );
    }

    /**
     * One configured ceiling — always a positive integer, never "unlimited".
     *
     * The shared budget accepts `null` to mean "no ceiling". Explore does not
     * expose that, and the omission is the point: this guard exists because an
     * unbudgeted path to a paid provider is what produced the incident behind
     * this work, and a config value that switches the ceiling off entirely is
     * that path with an extra step.
     *
     * A missing, zero, negative or non-numeric value falls back to the shipped
     * default rather than to no limit. `(int) null` is 0, and a 0 ceiling would
     * block everything while an "unlimited" reading would block nothing — both
     * are wrong answers to a typo, and only one of them is expensive.
     */
    private function cap(string $key): int
    {
        $value = (int) config("explore.provider_budget.{$key}", 0);

        return $value > 0 ? $value : self::DEFAULTS[$key];
    }

    private const DEFAULTS = [
        'global_hourly' => 600,
        'global_daily'  => 5_000,
        'actor_hourly'  => 60,
        'actor_daily'   => 300,
    ];
}
