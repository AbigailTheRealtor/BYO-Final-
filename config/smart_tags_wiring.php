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

];
