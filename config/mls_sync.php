<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MLS live sync — master gate
    |--------------------------------------------------------------------------
    | When false, MlsListingSyncService refuses every sync and reports
    | DISABLED. Nothing is fetched and no listing is touched.
    |
    | SHIPS FALSE, AND FAILS CLOSED. Deploying this code must not, by itself,
    | start unattended traffic to a third-party provider. Merging and activating
    | are two decisions with two different reviews, and a default of true
    | collapses them into one — the deploy becomes the activation, taken by
    | whoever pressed merge, at whatever moment the container happened to
    | restart.
    |
    | That is not a statement that the feature should stay off. Its whole
    | purpose is that an MLS-linked listing stops serving last month's price
    | without a manual re-import, and it is expected to be turned on. The
    | requirement is only that turning it on is a deliberate act with a date and
    | an owner, performed after a deployment-readiness review, by setting
    | MLS_SYNC_ENABLED=true in the environment.
    |
    | ABSENCE IS OFF. `env(..., false)` covers the missing variable; every
    | reader ALSO passes `false` as its config() fallback, which covers the
    | different failure of this file not loading at all. A config that did not
    | load is indistinguishable from one that requires nothing, and for a switch
    | governing outbound provider traffic that ambiguity is not safe to resolve
    | as "on".
    |
    | This is a SAFETY SWITCH, so it must never be named in the required
    | production flags contract — see the "contract may never name a safety
    | switch" rule in CLAUDE.md. A gate whose job is to stop outbound provider
    | traffic must not become something a deploy refuses to start without.
    |
    | (That contract's filename is deliberately not written out here: a test
    | asserts the contract has exactly one reader by searching for its name, and
    | a mention in a comment reads to that search as a second reader.)
    */
    'enabled' => (bool) env('MLS_SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Lazy refresh on access
    |--------------------------------------------------------------------------
    | Whether opening an MLS-linked listing may trigger a stale refresh.
    |
    | SHIPS FALSE, and is subordinate to the master gate above — with that off,
    | this is inert whatever it says.
    |
    | What it enables is narrower than the name suggests. See
    | MlsStaleAccessRefresher: an OWNER viewing their own stale listing gets one
    | synchronous refresh; everybody else — every anonymous visitor — causes no
    | outbound request at all, only a cache hint that moves the listing to the
    | front of the next scheduled sweep. The unbounded case (a public page
    | render becoming a Bridge request) is unreachable on that path by
    | construction rather than by rationing.
    |
    | It ships off anyway, for the same reason as the master gate: a deployment
    | should not silently begin sending requests. It is also the one gate worth
    | enabling SECOND — the sweep alone keeps listings current, so activation
    | can be staged as master-then-schedule-then-lazy, watching request volume
    | at each step, rather than all three at once.
    */
    'lazy_refresh_enabled' => (bool) env('MLS_SYNC_LAZY_REFRESH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Freshness window — the LIVE one
    |--------------------------------------------------------------------------
    | How long a successful sync is trusted before the listing is considered
    | stale. Inside this window no Bridge request is made at all — the check is
    | a stored timestamp comparison, not a conditional request.
    |
    | SIXTY MINUTES, AND THE NUMBER IS BORROWED ON PURPOSE.
    | `config/bridge.php` has shipped `lazy_ttl_minutes => 60` since the criteria
    | importer existed: this repository's already-made judgment about how long
    | Bridge data may be treated as current. Two Bridge-facing caches in one
    | application answering that question differently — one at 60 minutes, one at
    | 360 — would not be two decisions, it would be one decision and one
    | oversight.
    |
    | It replaces a 6-hour window whose stated reason was that the scheduled
    | backstop would close the gap. There was no schedule at the time, so nothing
    | closed it. There is one now (see `schedule` below), and the two are set
    | together because worst-case staleness is the SUM of this window and the
    | sweep interval, not either alone.
    |
    | THIS IS THE DIAL THAT CONTROLS SPEND. The sweep interval controls latency;
    | this controls how often any one listing actually costs a request. Raising
    | the sweep frequency costs nothing extra. Lowering this costs linearly.
    */
    'freshness_minutes' => (int) env('MLS_SYNC_FRESHNESS_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Freshness window — TERMINAL statuses
    |--------------------------------------------------------------------------
    | The window for a listing whose source status means it has left the open
    | market: Closed, Expired, Withdrawn, Canceled, Cancelled, Temporarily Off
    | Market. See MlsSourceStatus::isOffMarket().
    |
    | Twenty-four hours rather than one. A sold property is not about to change
    | its price, and polling it hourly forever spends a request an hour, for
    | years, to re-confirm something that was settled the first time.
    |
    | It is a REDUCTION, never a stop. The record keeps being checked daily, so a
    | status Stellar reverses — a fallen-through contract going back to Active —
    | is picked up within a day, and nothing about the listing or its history is
    | ever deleted.
    |
    | An UNRECOGNISED status does not land here: MlsSourceStatus::isOffMarket()
    | answers false for a word it does not know, so an unfamiliar status keeps
    | the live window. Guessing "off market" from an unfamiliar string would drop
    | a live listing to daily polling for a reason nobody would think to look for.
    */
    'terminal_freshness_minutes' => (int) env('MLS_SYNC_TERMINAL_FRESHNESS_MINUTES', 1440),

    /*
    |--------------------------------------------------------------------------
    | Retry backoff after a failed sync
    |--------------------------------------------------------------------------
    | How long to wait before attempting again after a failure. Deliberately
    | shorter than the freshness window: a transport blip should not freeze a
    | listing's data for six hours, but a persistently unreachable provider
    | must not be hammered either.
    */
    'retry_after_minutes' => (int) env('MLS_SYNC_RETRY_AFTER_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Concurrency lock
    |--------------------------------------------------------------------------
    | Seconds a sync may hold the per-listing-key lock, and how long a second
    | caller waits for it. Two near-simultaneous refreshes of one listing must
    | not both fetch and both rewrite the gallery; the loser waits briefly and
    | then finds the work already done and the listing fresh.
    */
    'lock_seconds'      => (int) env('MLS_SYNC_LOCK_SECONDS', 30),
    'lock_wait_seconds' => (int) env('MLS_SYNC_LOCK_WAIT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Per-run ceilings
    |--------------------------------------------------------------------------
    | Maximum listings one `mls:sync-listings` run will reconcile.
    |
    | THE CEILING, NOT THE CADENCE, IS THE ACTUAL SAFETY PROPERTY. Whatever the
    | schedule or the listing count, no run can exceed this, so the worst case is
    | bounded arithmetic rather than a hope about how many MLS-linked listings
    | exist. If the estate ever outgrows the ceiling, listings are reconciled
    | more SLOWLY (oldest-first) — never faster, and never all at once.
    |
    | 100 every fifteen minutes is a capacity of 400 listings/hour, against a
    | requirement of one request per listing per hour. The reconcile pass raises
    | it because it runs once a day and its job is completeness.
    */
    'batch_limit'           => (int) env('MLS_SYNC_BATCH_LIMIT', 100),
    'reconcile_batch_limit' => (int) env('MLS_SYNC_RECONCILE_BATCH_LIMIT', 500),

    /*
    |--------------------------------------------------------------------------
    | The schedule
    |--------------------------------------------------------------------------
    | Wired in app/Console/Kernel.php and run by the single `schedule:work`
    | process in deploy/scheduler.sh.
    |
    | WHY FIFTEEN MINUTES IS SAFE, AND WHAT THE EVIDENCE ACTUALLY IS
    | --------------------------------------------------------------
    | 1. No provider rate limit is invented here, because none can be cited. The
    |    2026-09-04 licence audit recorded in docs/mls-direct-import-design-and-
    |    plan.md searched this repository and found "no Stellar or Bridge
    |    agreement, licence text, IDX rulebook, or record of approval" anywhere
    |    in it. So there is no contractual number to honour and none to guess.
    |
    | 2. The sweep interval does NOT set request volume. `freshness_minutes`
    |    does. A sweep selects listings whose window has expired and the service
    |    declines the rest locally, so running every fifteen minutes instead of
    |    every hour changes how quickly a newly-stale listing is noticed, not how
    |    often it is fetched. Cost per listing stays at one request per
    |    freshness window.
    |
    | 3. One sync is one Bridge request: BridgeApiService::fetchProperties(1, …)
    |    against a `ListingKey eq` filter. No pagination, no crawl. Related
    |    resources (agent/office/open house) are cached on the member and office
    |    key rather than the listing, and capped by
    |    mls_related_resources.max_requests_per_import.
    |
    | 4. The envelope this fits inside already exists. config/bridge.php ships
    |    lazy_max_pages => 20 with lazy_ttl_minutes => 60, so ONE anonymous
    |    criteria search may already spend up to twenty Bridge requests an hour,
    |    repeatedly, per distinct criteria set. Against that, one request per
    |    MLS-linked listing per hour is the smaller of the two traffic sources by
    |    a wide margin.
    |
    | WORST-CASE ORDINARY STALENESS = freshness_minutes + sweep interval
    |                               = 60 + 15 = 75 minutes.
    |
    | Raise the frequency with telemetry, not optimism; lower `freshness_minutes`
    | only with a reason, since that is the one that costs.
    */
    'schedule' => [
        // The gate on UNATTENDED traffic specifically, and it SHIPS FALSE.
        //
        // Distinct from the master switch above and not redundant with it:
        // turning this off silences the sweep while leaving an owner's own
        // refresh of their own listing working. That is the useful middle
        // posture — sync available to a person who asks for it, nothing on a
        // timer — and it is unreachable if one flag governs both.
        //
        // The scheduler WIRING ships regardless (see app/Console/Kernel.php);
        // only the registration is withheld. Nothing has to be edited to
        // activate — one environment variable, one restart.
        'enabled'           => (bool) env('MLS_SYNC_SCHEDULE_ENABLED', false),

        // Minutes between sweeps. Must divide 60 — Laravel's cron expression is
        // built from it, and a value like 7 would not produce an even cadence.
        'sweep_minutes'     => (int) env('MLS_SYNC_SWEEP_MINUTES', 15),

        // The daily reconciliation floor the owner's contract names as the
        // MINIMUM. It is not the mechanism the platform relies on — the sweep
        // is — but it guarantees complete coverage once a day even if every
        // sweep that day was ceiling-bound. 24-hour clock, server time.
        'reconcile_at'      => (string) env('MLS_SYNC_RECONCILE_AT', '03:20'),

        // Minutes a single sweep may run before the scheduler considers it
        // stuck and lets the next one start. Comfortably longer than a full
        // ceiling-sized batch, short enough that a wedged run does not silence
        // the sweep for an hour.
        'overlap_guard_minutes' => (int) env('MLS_SYNC_OVERLAP_GUARD_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale-on-access demand hints
    |--------------------------------------------------------------------------
    | When a NON-owner opens a stale MLS-linked listing, no request is sent; the
    | listing id is remembered so the next sweep reconciles it first. See
    | MlsSyncDemandQueue.
    |
    | The limit bounds one cache entry; the TTL means a listing nobody looks at
    | any more stops being prioritised without a cleanup pass. Neither is a rate
    | limit — there is no rate to limit, because this path sends nothing.
    */
    'access_hint_limit'      => (int) env('MLS_SYNC_ACCESS_HINT_LIMIT', 200),
    'access_hint_ttl_minutes' => (int) env('MLS_SYNC_ACCESS_HINT_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Roles the sync applies to
    |--------------------------------------------------------------------------
    | The same two roles that can create an MLS-linked listing in the first
    | place. Buyer/Tenant listings describe search criteria across many areas
    | rather than one property, so there is no source record to sync from.
    */
    'roles' => ['seller', 'landlord'],

];
