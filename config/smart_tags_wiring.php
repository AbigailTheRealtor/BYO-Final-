<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Smart Tags derivation — master gate
    |--------------------------------------------------------------------------
    |
    | Default OFF, and off is the shipped state.
    |
    | Phase 1 merged the derivation engine with NO flag at all: its inertness was
    | the fact that nothing called it. Phase 2 wires the call sites, so that
    | property is gone and the gate has to become explicit — otherwise merging
    | this branch would BE activating it, which is the posture this repository
    | rejects for `mls_sync.enabled` and `explore.discovery.enabled`, and for the
    | same reason. Merging and activating are two decisions.
    |
    | With the gate closed, every SmartTagLifecycle entry point returns without
    | touching the database: no evidence, no assignments, no derivation state, no
    | purge. Listing saves, Bridge imports and draft deletions behave exactly as
    | they did before this branch.
    |
    | PARSED STRICTLY, FAILING CLOSED: ON only for `true`, `1`, `on`, `yes`.
    | A plain `(bool)` cast reads the strings `off`, `no` and `false` as ON, which
    | is how a flag meant to keep a feature dark switches it on instead.
    |
    | This is a SAFETY SWITCH, and the deploy-time flag contract may never name
    | one — or that gate becomes a deploy-time mechanism for enabling a feature.
    | (The contract file is deliberately not named here: it has exactly one
    | reader by design, and a test enforces that by scanning for its name.)
    |
    */

    'enabled' => filter_var(env('SMART_TAGS_DERIVATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | Bridge-sourced derivation — an ADDITIONAL gate, never a replacement
    |--------------------------------------------------------------------------
    |
    | Both this and the master gate must agree before a `bridge` listing is
    | derived. Native Seller/Landlord Offer Listings need the master gate alone.
    |
    | Separate on purpose, and the separation is the point: native derivation
    | reads a form the listing's own owner filled in on our own site, while this
    | reads a licensed third-party MLS feed. The two carry different data-use
    | questions and different blast radii — `bridge_properties` holds the whole
    | cached inventory, so enabling Bridge derivation makes every subsequent
    | single-record import do tagging work, where native derivation only ever
    | touches a listing somebody just published.
    |
    | Turning the master gate on for the native surfaces must therefore not also
    | start tagging MLS rows. Same shape as GOOGLE_GEOCODING_ENABLED sitting
    | beside GOOGLE_PLACES_ENABLED.
    |
    | Parsed strictly and fail-closed, exactly like the master gate. Also a
    | safety switch, also excluded from the production flag contract.
    |
    */

    'bridge_enabled' => filter_var(env('SMART_TAGS_BRIDGE_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | Buyer/Tenant seeker preferences — a CONSUMER surface, gated on its own
    |--------------------------------------------------------------------------
    |
    | Whether a Buyer or Tenant may choose canonical Smart Tags as criteria on
    | their own criteria record, and whether that picker renders at all.
    |
    | DELIBERATELY INDEPENDENT OF THE TWO GATES ABOVE. Those govern DERIVATION —
    | reading a listing or an MLS feed and writing evidence ABOUT A PROPERTY.
    | This governs a CONSUMER CONTROL on a form, writing what a PERSON asked for.
    | They are different blast radii and different decisions: tagging the
    | listing inventory must not require shipping a new control to customers,
    | and putting a feature picker in front of seekers must not start tagging a
    | licensed MLS feed. Reusing SMART_TAGS_DERIVATION_ENABLED for both would
    | make each of those the price of the other.
    |
    | OFF MEANS THE WRITE PATH IS SKIPPED, NOT CLEARED. A criteria save while
    | this is off leaves any previously stored selection exactly as it was — the
    | feature going dark must never be what deletes a customer's data. Deletion
    | CLEANUP is separate and runs regardless (see SmartTagSeekerPreferenceGate).
    |
    | Parsed strictly and fail-closed, exactly like the gates above. Also a
    | safety switch, and therefore also excluded from the production flag
    | contract.
    |
    */

    'seeker_preferences_enabled' => filter_var(env('SMART_TAGS_SEEKER_PREFERENCES_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | Seeker picks in MATCHING — an ADDITIONAL gate, never a replacement
    |--------------------------------------------------------------------------
    |
    | Whether the Buyer/Tenant picks stored under the gate above take part in the
    | Stellar Match DNA score. Both must be on; this alone does nothing.
    |
    | SEPARATE FROM THE PICKER ON PURPOSE. Picks are scored against the listings'
    | resolved Smart Tags, and Bridge rows have none until Bridge derivation has
    | run and been backfilled. Scoring before that would lower every MLS card's
    | Amenities score alike for want of enrichment we have not produced yet. So
    | the rollout is: store picks (the gate above) → derive and backfill Bridge
    | tags → verify coverage → turn this on. With this OFF, picks keep saving
    | exactly as before and the score is the pre-feature score, with no
    | listing-tag read at all.
    |
    | Parsed strictly and fail-closed, like every gate here. A safety switch, so
    | also excluded from the production flag contract.
    |
    */

    'seeker_matching_enabled' => filter_var(env('SMART_TAGS_SEEKER_MATCHING_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | Which seeker CONTEXTS may be scored — the per-property-type activation list
    |--------------------------------------------------------------------------
    |
    | A third, additional condition on scoring picks: the seeker's own context
    | (from their criteria's property type) must be named here. Comma-separated
    | SmartTagContext values, e.g. "residential.sale,residential.lease".
    |
    | WHY PER CONTEXT. Bridge tag coverage differs sharply by property type — the
    | live corpus predicts ~99–100% for Residential and Residential Lease, ~56%
    | for Business Opportunity. A pick is scored as "the listing has it" or as
    | earning nothing, so switching matching on for a poorly covered context
    | would lower every listing there for want of data we do not hold. Each
    | context is activated when its own coverage is verified.
    |
    | EMPTY BY DEFAULT, AND EMPTY MEANS NONE. Turning SMART_TAGS_SEEKER_MATCHING_ENABLED
    | on without naming a context scores nothing. An unrecognised value is
    | dropped, never guessed at. Read only through SmartTagSeekerPreferenceGate.
    | A safety switch: excluded from the production flag contract.
    |
    */

    'seeker_matching_contexts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SMART_TAGS_SEEKER_MATCHING_CONTEXTS', '')),
    ), static fn (string $value) => $value !== '')),

    /*
    |--------------------------------------------------------------------------
    | Scheduled Bridge catch-up — unattended `smart-tags:derive --only-stale`
    |--------------------------------------------------------------------------
    |
    | The high-volume Bridge import paths defer derivation, so without a
    | catch-up every newly imported row stays untagged — UNKNOWN to seeker
    | matching — and coverage decays after any one-time backfill.
    |
    | An ADDITIONAL gate on top of both derivation gates above, never a
    | replacement: the schedule is registered only when all three are on
    | (SmartTagWiring::bridgeCatchUpScheduled()). It is also the one thing that
    | authorises `smart-tags:derive --scheduled` to write without a person at a
    | terminal — the narrowest possible run: Bridge only, one provider,
    | --only-stale, bounded. Default OFF, parsed fail-closed. A safety switch:
    | excluded from the production flag contract.
    |
    */

    'bridge_catch_up_schedule_enabled' => filter_var(env('SMART_TAGS_BRIDGE_CATCHUP_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

];
