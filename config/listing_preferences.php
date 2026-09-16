<?php

/*
|--------------------------------------------------------------------------
| Listing Preferences — Save | Maybe | Pass
|--------------------------------------------------------------------------
|
| ABSENCE IS OFF. Every gate here fails closed, and a config that did not load
| reads as off rather than as "nothing was required".
|
| PHASE 1 IS INERT AND DOES NOT READ THIS FILE TO DECIDE ANYTHING. There is no
| write path, no route, no controller and no UI to gate. The contract is
| declared now so Phase 2 adds a caller rather than inventing a flag, and so the
| posture is reviewable before anything customer-facing exists.
|
| NOT IN the deploy-time product flag contract, and it must not be added: that
| contract may never name a safety switch, and a gate that starts customer-facing
| writes is exactly such a switch. (The contract's filename is deliberately not
| written here — a test asserts the gate command is its ONLY reader, and a
| mention in this file would register as a second opinion about what production
| requires.)
|
*/

return [

    /*
    | Master gate for the whole feature — capture, display and (later) learning.
    |
    | Parsed strictly, failing closed: ON only for `true`, `1`, `on`, `yes`.
    | Unset, empty, `false`, `0`, `off`, `no` and any malformed value are OFF.
    | A plain (bool) cast reads `off` and `no` as ON, which is how
    | GOOGLE_PLACES_ENABLED once switched a billable API on.
    */
    'enabled' => filter_var(env('LISTING_PREFERENCES_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    | Guests.
    |
    | PHASE 1–2 PRODUCT DECISION: authenticated only. The Save / Maybe / Pass
    | controls may eventually be VISIBLE to a signed-out visitor, but using one
    | prompts authentication and creates no anonymous preference record.
    |
    | This is a placeholder for a decision already made, not a dial to turn.
    | Guest capture-and-claim needs a retention policy and an identity-merge
    | path, neither of which exists; the authenticated data model is shaped so
    | adding them later is additive (a claim step that rewrites user_id on
    | existing rows), never a redesign.
    */
    'guest_capture_enabled' => false,

    /*
    | Behavioural learning — Taste DNA, ranking influence, "find more like this".
    |
    | HARD OFF, and not merely unbuilt. docs/smart-tags/SMART_TAGS_GOVERNANCE.md
    | requires behavioural learning to carry its own governance revision, and
    | docs/listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md §4 states the
    | Fair Housing prohibitions any learner must satisfy. Turning this on is a
    | reviewed code change after that governance work, not a configuration edit —
    | the same posture as MLS_REMARKS_PROCESSING_APPROVED.
    */
    'learning_enabled' => false,

];
