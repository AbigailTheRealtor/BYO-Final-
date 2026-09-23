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
    | Taste DNA — "Your Home Taste" (Phase 4).
    |
    | The customer's own page of patterns learned from their own Save / Maybe /
    | Pass history. Governed by LISTING_PREFERENCE_GOVERNANCE.md §13.
    |
    | Parsed exactly like `enabled`: ON only for `true`, `1`, `on`, `yes`.
    | Requires `enabled` as well — read only through TasteDnaAvailability — so
    | base Save / Maybe / Pass can run with this off, and switching capture on
    | never switches learning on with it.
    |
    | On its own it gates ONE PAGE. Taste DNA is derived on demand for that page;
    | its only other consumer is the Phase 5 rerank, which needs its own flag
    | below as well. Matching, the score, Ask AI and recommendations never read
    | it; TasteDnaArchitectureGuardTest asserts the allowlist of consumers.
    */
    'taste_dna_enabled' => filter_var(env('LISTING_PREFERENCE_TASTE_DNA_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    | Taste reranking — Phase 5 (governance §14).
    |
    | Lets the customer's own Taste DNA reorder NEAR-TIED results on the Stellar
    | Buyer / Tenant results page, under Best Match only, by at most
    | TasteDnaReranker::MAX_INFLUENCE points of ORDERING — never the score,
    | never which listings appear, never an explicit sort.
    |
    | Parsed exactly like `enabled`: ON only for `true`, `1`, `on`, `yes`.
    | Requires `enabled` AND `taste_dna_enabled` as well — read only through
    | TasteDnaAvailability::rerankingEnabled() — so the page that EXPLAINS taste
    | can run with ranking off, and turning Taste DNA on never turns ranking on.
    */
    'taste_reranking_enabled' => filter_var(env('LISTING_PREFERENCE_TASTE_RERANKING_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    | Learning that acts BEYOND the bounded rerank above — recommendations,
    | "find more like this", filtering, Ask AI consumption, any use of Taste DNA
    | outside the Stellar Best Match reorder.
    |
    | HARD OFF, and not merely unbuilt. Each of those needs its own governance
    | revision; turning this on is a reviewed code change, not a configuration
    | edit — the same posture as MLS_REMARKS_PROCESSING_APPROVED. Nothing reads
    | it, and Phase 5's reranking does not either: it has its own flag.
    */
    'learning_enabled' => false,

];
