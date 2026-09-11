<?php

namespace App\Services\Explore\Guards;

use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;

/**
 * How much outbound Stellar/Bridge traffic Explore may cause, at two scopes.
 *
 * THIS IS NOT A BUDGET IMPLEMENTATION. IT COMPOSES THE EXISTING ONE.
 * ------------------------------------------------------------------
 * Every counter, key, window, TTL and lock here belongs to
 * {@see ProviderRequestBudget}. This class holds no storage, does no counting
 * and defines no time window; it decides WHICH budgets apply to an Explore
 * provider call and asks them. Building a second accounting mechanism would
 * mean two things that must agree forever about what "a request" is, and they
 * would not.
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
 * A HARD CEILING, ADMITTED ONE REQUEST AT A TIME
 * ----------------------------------------------
 * The unit is one outbound Bridge HTTP request: one OData page of a discovery
 * pass, or the property panel's single-record lookup. Each is admitted by
 * {@see acquire()} immediately before it is sent, through
 * {@see ProviderRequestBudget::admit()} — the global and actor ceilings checked
 * and charged together, under one lock. A pass that runs out of budget between
 * page 3 and page 4 does not send page 4.
 *
 * The earlier shape — one check before a pass, its pages charged afterwards —
 * let a pass admitted at 59 of 60 finish at 69, and let two racing workers both
 * take the last unit. A configured 60 now admits exactly 60, with any number of
 * PHP processes.
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
 */
class ExploreProviderBudget
{
    /** The provider this budget rations. One id, so scopes cannot collide. */
    public const PROVIDER = 'explore_bridge';

    public const REASON_DISABLED_GUARD = 'explore_provider_budget_misconfigured';
    public const REASON_KILL_SWITCH    = 'explore_provider_kill_switch';

    /**
     * Whether a new provider request would be refused right now — a read-only
     * fast path, NOT the ceiling.
     *
     * It charges nothing and reserves nothing. It lets a request whose ceiling
     * is already spent skip the importer entirely; {@see acquire()} is asked
     * again before every request that is actually sent, and that is where the
     * ceiling is enforced.
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
     * Admit ONE outbound provider request — charging it to the global and the
     * actor ceiling together — or refuse it and charge nothing.
     *
     * Call immediately before the request is sent, once per request: never
     * after, and never for a cache hit, which sends nothing. A non-null return
     * means the request must not be sent.
     *
     * Attempts are charged, not successes. A request that reached the provider
     * and came back a failure consumed exactly as much of its capacity as one
     * that worked, and admission happens before anybody knows which it will be.
     *
     * The kill switch and a disabled guard refuse here too, so no path reaches
     * the provider by skipping the fast-path check.
     */
    public function acquire(?string $actorKey): ?string
    {
        if ($this->killed()) {
            return self::REASON_KILL_SWITCH;
        }

        if (! $this->enabled()) {
            return self::REASON_DISABLED_GUARD;
        }

        $budgets = ['global' => $this->global()];

        if ($actorKey !== null) {
            $budgets['actor'] = $this->actor($actorKey);
        }

        return ProviderRequestBudget::admit($budgets);
    }

    /**
     * Charge requests WITHOUT admission.
     *
     * No request path uses this — they go through {@see acquire()}, which is
     * what makes the ceiling hard. It exists so a known spend can be seeded;
     * the tests pre-spend a ceiling this way.
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

    /**
     * Is the provider kill switch tripped?
     *
     * Anything but an explicit `false` reads as TRIPPED. config/explore.php
     * already parses the environment fail-safe; this re-asserts it for a value
     * set any other way, because a kill switch that a malformed value quietly
     * disarms is not a kill switch.
     */
    public function killed(): bool
    {
        return config('explore.provider_budget.kill_switch', false) !== false;
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

    /**
     * Conservative on purpose — application-side ceilings for a controlled
     * launch, NOT a statement of Stellar's allowance, which is unknown here.
     * Raise from `explore_provider` telemetry, never from optimism.
     */
    private const DEFAULTS = [
        'global_hourly' => 300,
        'global_daily'  => 2_000,
        'actor_hourly'  => 60,
        'actor_daily'   => 300,
    ];
}
