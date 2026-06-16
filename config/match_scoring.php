<?php

/**
 * Match Scoring Configuration — Build 4 / Matching Expansion Phase 1
 *
 * This file is the single source of truth for the Build 4 / Matching Expansion
 * Phase 1 weight table. It defines the relative importance of each scoring
 * dimension, cap values for experience-based scoring, and dimension-level
 * activation flags.
 *
 * IMPORTANT: This file contains config values only. No scoring logic lives here.
 * All scoring logic belongs in the helpers and services that read these values.
 *
 * ALL DIMENSION WEIGHTS MUST SUM TO 100 (verified at the bottom of this file).
 *
 * Activation model:
 *   - 'enabled' => false on a dimension means the helper returns 0 for that
 *     dimension and it is excluded from the overall weighted average.
 *   - When all five Build 4 dimensions are enabled, the overall score formula
 *     becomes: Σ(dimension_weight × dimension_score) / Σ(enabled_dimension_weights)
 *   - The two current dimensions (services, terms) remain at their current
 *     50/50 split until the first Build 4 dimension is enabled.
 *
 * Compatibility dimension is set to weight 0 and enabled => false. The
 * client-side compatibility data exists (flat meta key 'compatibility_preferences'
 * on Seller/Buyer/Tenant listings) but the agent-side data is completely absent
 * (all 7 HasCompatibilityPreferences dot-notation keys are null in all presets
 * and all bid meta tables). Two-sided scoring requires both sides. Additionally,
 * the client-side schema (role-scoped JSON blobs: seller_specific, buyer_specific,
 * tenant_specific) does not map to the HasCompatibilityPreferences 7-section
 * schema — a schema reconciliation step is required before scoring.
 *
 * Build 4 / Matching Expansion Phase 1 audit findings that drove these weights:
 *   - Service area: all four roles have client-side location fields (landlord
 *     uses property_city / property_county meta keys). Normalization is specified.
 *   - Communication: client_preferred_comm_method exists in all four listing
 *     meta tables — this dimension is partially two-sided.
 *   - Experience: year_licensed is the cleanest verifiable credential field.
 *   - Compatibility: client data exists for 3/4 roles; agent data absent entirely.
 *
 * @see docs/audits/MATCHING_ENGINE_PHASE1_AUDIT.md
 */

return [

    // ─────────────────────────────────────────────────────────────────────────
    // DIMENSION WEIGHTS (must sum to 100)
    // ─────────────────────────────────────────────────────────────────────────

    'dimensions' => [

        /*
         * Services (35%)
         *
         * The services a client requests vs. those an agent offers.
         * Scored as: matched / baseline_total × 100
         *
         * Rationale: Core of the value proposition — if an agent cannot deliver
         * the services the client expects, the match fails on the most visible
         * dimension. Reduced from 50% (current) to 35% to make room for new
         * dimensions while keeping it the single heaviest weight.
         *
         * Currently active: YES (existing engine handles this dimension).
         * Activation: already live in all four *BidMatchScoreHelper classes.
         */
        'services' => [
            'weight'  => 35,
            'enabled' => true,
        ],

        /*
         * Terms — Broker Compensation & Agency Agreement (35%)
         *
         * Logical field group comparison across all LOGICAL_FIELD_GROUPS entries
         * per role. Scored as: matched_groups / baseline_total_groups × 100
         * Cascade deactivation applies (child groups excluded when parent = No).
         *
         * Rationale: Compensation terms are a legal commitment, not a preference.
         * A mismatch here (e.g., client wants flat fee, agent quotes percentage)
         * requires renegotiation or rejection. Equal weight to services, reflecting
         * that both dimensions are equally disqualifying when mismatched.
         *
         * Currently active: YES (existing engine handles this dimension).
         * Activation: already live in all four *BidMatchScoreHelper classes.
         */
        'terms' => [
            'weight'  => 35,
            'enabled' => true,
        ],

        /*
         * Service Area (15%)
         *
         * Whether the agent's stated service areas overlap with the client's
         * target location. Scored as: overlap_count / client_location_count × 100
         * If the client has no location data, this dimension is excluded from
         * the weighted average.
         *
         * All four roles have confirmed client-side location fields:
         *   Seller:   city_id / county_id native FK columns on seller_agent_auctions
         *   Buyer:    cities / counties JSON arrays in buyer_agent_auction_metas
         *   Tenant:   cities / counties JSON arrays in tenant_agent_auction_metas
         *   Landlord: property_city / property_county meta keys in landlord_agent_auction_metas
         *             (name strings with optional state suffix, same format as Buyer/Tenant)
         *
         * Rationale: An agent who does not serve the client's target area is a
         * fundamental mismatch, even with perfect services and terms alignment.
         * 15% reflects meaningful but not disqualifying weight — agents sometimes
         * expand their service areas.
         *
         * Currently active: NO — requires Build 4 implementation.
         * Normalization prerequisites are fully specified in 'service_area' config
         * block below and in docs/audits/MATCHING_ENGINE_PHASE1_AUDIT.md §6.
         */
        'service_area' => [
            'weight'  => 15,
            'enabled' => false,
        ],

        /*
         * Experience (10%)
         *
         * Agent's years licensed and recent transaction volume compared against
         * an absolute scale (no client minimum field exists yet). Scored against
         * cap values below.
         *
         * Rationale: Experience is a proxy for competence that clients can easily
         * evaluate. 10% reflects moderate influence — a newer licensee with
         * perfect services and terms can still be a good match; experience alone
         * is not disqualifying.
         *
         * Currently active: NO — requires Build 4 implementation.
         * Agent-side source: profile_data['year_licensed'] and
         *   profile_data['transactions_last_12_months'] in agent_default_profiles.
         * Client-side source: none. Score on absolute scale relative to caps.
         */
        'experience' => [
            'weight'  => 10,
            'enabled' => false,
        ],

        /*
         * Availability & Communication (5%)
         *
         * Agent's stated availability and communication style vs. client's
         * communication preference. This dimension is partially two-sided:
         *
         * Two-sided (both sides have data):
         *   Client: client_preferred_comm_method (in all four listing meta tables)
         *           client_preferred_comm_method_other (free-text for "Other" choice)
         *   Agent:  preferred_contact_method (profile_data in agent_default_profiles)
         *           communication_style (profile_data)
         *
         * One-sided (agent only; no client mirror field):
         *   Agent:  evenings_available, weekends_available (Yes/No)
         *           availability_status ("Actively Taking New Clients" | "Limited" | "Not Available")
         *           avg_response_time (free text: "1 Hour", "Same Day", etc.)
         *
         * Rationale: Availability and communication style affect transaction
         * velocity and client satisfaction but are rarely deal-breakers. 5%
         * reflects advisory nature. The low weight also accounts for the partial
         * two-sidedness — client scheduling availability windows are not yet
         * collected in listing forms.
         *
         * Currently active: NO — requires Build 4 implementation.
         * See 'availability' config block below for scoring parameters.
         */
        'availability' => [
            'weight'  => 5,
            'enabled' => false,
        ],

        /*
         * Compatibility — Working Style & Representation Philosophy (0%)
         *
         * Overlap between client-stated compatibility preferences and agent-stated
         * compatibility preferences.
         *
         * Data status (as of Build 4 Phase 1 audit):
         *
         * CLIENT SIDE — data exists for 3 of 4 roles:
         *   Seller: flat meta key 'compatibility_preferences' → JSON blob with
         *     'seller_specific' key containing structured radio/multi-select answers.
         *     Confirmed populated with real data.
         *   Buyer:  same pattern; 'buyer_specific' key; populated (some blanks).
         *   Tenant: same pattern; 'tenant_specific' key; populated (some blanks).
         *   Landlord: meta key exists in schema but ZERO rows in DB. No data.
         *
         * AGENT SIDE — data absent entirely:
         *   All 7 HasCompatibilityPreferences dot-notation sections
         *   (compatibility_preferences.agent_response.*) return zero rows in all
         *   bid meta tables across all four roles.
         *   All 7 profile_data keys (communication_preferences, negotiation_approach,
         *   guidance_style, collaboration_preferences, transaction_strategy,
         *   representation_philosophy, representation_priorities) are null in ALL
         *   agent_default_profiles records currently in the database.
         *
         * SCHEMA MISMATCH:
         *   Client schema: flat 'compatibility_preferences' meta key → role-scoped
         *     JSON blob (seller_specific / buyer_specific / tenant_specific) with
         *     role-specific question sets.
         *   Agent schema: HasCompatibilityPreferences 7-section dot-notation keys
         *     (compatibility_preferences.agent_response.{section}).
         *   These do not map to each other. Reconciliation required before scoring.
         *
         * Weight is 0% until:
         *   a) Agent-side compatibility collection UI is built (preset editor).
         *   b) Landlord client-side compatibility collection is built.
         *   c) Schema reconciliation: define a shared question/answer format that
         *      both client-side (listing creation) and agent-side (preset editor)
         *      can write to and the scoring engine can compare.
         *   d) Scoring function implemented (set-intersection for radio values).
         */
        'compatibility' => [
            'weight'  => 0,
            'enabled' => false,
        ],

    ],

    // ─────────────────────────────────────────────────────────────────────────
    // EXPERIENCE SCORING CAPS
    // ─────────────────────────────────────────────────────────────────────────

    /*
     * Cap values for the experience dimension.
     *
     * Scoring model:
     *   years_score        = min(years_experience, years_cap) / years_cap × years_weight
     *   transactions_score = min(transactions, transactions_cap) / transactions_cap × transactions_weight
     *   experience_score   = (years_score + transactions_score) × 100
     *
     * years_weight + transactions_weight must equal 1.0.
     *
     * Rationale for caps:
     *   - 20 years is the inflection point past which additional years produce
     *     diminishing returns. Agents with 20+ years are treated equivalently.
     *   - 30 transactions in the last 12 months is a strong production indicator.
     *   - 70/30 split favors years because year_licensed is verifiable via state
     *     license databases; transactions_last_12_months is self-reported.
     */
    'experience_caps' => [
        'years_cap'           => 20,
        'transactions_cap'    => 30,
        'years_weight'        => 0.70,
        'transactions_weight' => 0.30,
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // SERVICE AREA SCORING PARAMETERS
    // ─────────────────────────────────────────────────────────────────────────

    /*
     * Normalization and scoring rules for the service area dimension.
     *
     * Agent-side storage (profile_data in agent_default_profiles):
     *   cities_served:        comma-separated string — "St. Pete" or
     *                         "Seminole, St. Pete, Treasure Island"
     *   counties_served:      comma-separated string — "Pinellas"
     *   neighborhoods_served: plain string (supplemental; not used for geo-matching)
     *   primary_areas_served: plain string (supplemental; not used for geo-matching)
     *
     * Client-side storage by role:
     *   Seller:   city_id (bigint FK → us_cities.id) and county_id (bigint FK → us_counties.id)
     *             on seller_agent_auctions native columns. Resolve via DB join.
     *   Buyer:    cities and counties JSON arrays in buyer_agent_auction_metas.
     *             Name strings include state suffix: ["St. Petersburg, FL"]
     *   Tenant:   cities and counties JSON arrays in tenant_agent_auction_metas.
     *             Same format as Buyer.
     *   Landlord: property_city and property_county meta keys in
     *             landlord_agent_auction_metas. Name strings include optional state
     *             suffix: "St. Petersburg, FL" or bare "Seminole". Same normalization
     *             as Buyer/Tenant — strip state suffix and lowercase.
     *
     * Name-form mismatch risk: "St. Pete" (agent) vs. "St. Petersburg" (client).
     * The Build 4 implementation task must resolve this — options:
     *   (a) Require agents to enter canonical city names matching us_cities.city_name
     *   (b) Build a city-alias lookup table
     *   (c) Accept substring matching as a fallback (lower precision)
     *
     * Scoring: overlap_count / client_location_count × 100
     * Fallback: if client has no location data, award no_client_location_default_score.
     */
    'service_area' => [
        'county_suffix_to_strip'            => [' County, FL', ' County'],
        'city_suffix_to_strip'              => [', FL'],
        'no_client_location_default_score'  => 50,

        /*
         * Roles for which service-area scoring is explicitly inactive.
         *
         * Seller is inactive because city_id / county_id on seller_agent_auctions
         * are integer foreign keys pointing to us_cities / us_counties. Resolving
         * them to name strings requires a DB JOIN. Score helpers must remain pure
         * (no DB calls) — the call site would need to enrich $baselineData with
         * 'city_name' / 'county_name' keys before service-area scoring is possible.
         *
         * Until that enrichment path is built, seller always receives the neutral
         * score (50) and is excluded from the weighted average when this dimension
         * is enabled for other roles.
         *
         * To activate seller service-area scoring:
         *   1. Build the enrichment step (DB join at call site — SellerBidMatchScoreHelper
         *      or ScoreBreakdownService — resolving city_id/county_id to name strings).
         *   2. Remove 'seller' from this list.
         */
        'inactive_for_roles' => ['seller'],

        /*
         * Meta key names by role for client-side location resolution.
         * Used by the implementation to know where to read location data per role.
         */
        'client_location_keys' => [
            'seller'   => ['source' => 'native_columns', 'city' => 'city_id',       'county' => 'county_id'],
            'buyer'    => ['source' => 'meta',           'city' => 'cities',         'county' => 'counties'],
            'tenant'   => ['source' => 'meta',           'city' => 'cities',         'county' => 'counties'],
            'landlord' => ['source' => 'meta',           'city' => 'property_city',  'county' => 'property_county'],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // AVAILABILITY & COMMUNICATION SCORING PARAMETERS
    // ─────────────────────────────────────────────────────────────────────────

    /*
     * Scoring rules for the availability & communication dimension.
     *
     * This dimension blends two sub-components:
     *   (1) Communication method match — two-sided; 50% of dimension score
     *   (2) Scheduling availability — one-sided (agent only); 50% of dimension score
     *
     * Sub-component 1: Communication method match
     *   Client meta key: client_preferred_comm_method (all four listing meta tables)
     *     Values observed: "Phone Call", "Text/SMS", "Email", "Video Call",
     *                      "In-Person Meeting", "Other" (+ client_preferred_comm_method_other)
     *   Agent profile key: preferred_contact_method (profile_data)
     *     Values observed: "Any"
     *   Score: exact match = 100; agent says "Any" = 80 (willing but not preferred);
     *          no match = 0.
     *   Note: client_preferred_comm_method_other contains free text when client
     *         selects "Other" — cannot be scored; treat "Other" as "Any" (neutral).
     *
     * Sub-component 2: Scheduling availability (agent-side only)
     *   evenings_available: "Yes" / "No" — 33 points
     *   weekends_available: "Yes" / "No" — 33 points
     *   availability_status: see availability_status_scores — up to 34 points
     *   avg_response_time: supplemental; feeds into response-time sub-score
     *     (see avg_response_time_scores below; currently excluded from interim model)
     *
     * No client-side scheduling availability windows exist (meeting_details_time_zone
     * and service_time_zone are present in listing meta tables but are empty in
     * all current records — treat as not yet collected).
     */
    'availability' => [
        // Sub-component weights (must sum to 1.0)
        'comm_method_weight'   => 0.50,
        'scheduling_weight'    => 0.50,

        // Scheduling sub-component point allocations (must sum to 100)
        'evenings_points'      => 33,
        'weekends_points'      => 33,
        'status_points'        => 34,

        'availability_status_scores' => [
            'Actively Taking New Clients' => 34,
            'Limited Availability'         => 17,
            'Not Available'                => 0,
        ],

        // Communication method scoring (client vs. agent preferred_contact_method)
        'agent_any_score'      => 80,   // agent says "Any" — willing but not a specific match
        'method_match_score'   => 100,  // agent's method == client's method
        'method_no_match_score' => 0,   // no overlap

        // avg_response_time: lookup table for future use when client-side fields added
        'avg_response_time_scores' => [
            '1 Hour'          => 100,
            'Within 1 Hour'   => 100,
            'Same Day'        => 85,
            'Within 24 Hours' => 70,
            '24 Hours'        => 70,
            '1-2 Days'        => 40,
            'Within 48 Hours' => 40,
            '3+ Days'         => 10,
        ],

        'unknown_response_time_score' => 50,
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // WEIGHT INTEGRITY ASSERTION (documentation only — enforced in tests)
    // ─────────────────────────────────────────────────────────────────────────

    /*
     * The six dimension weights above must sum to 100.
     * Verification: 35 (services) + 35 (terms) + 15 (service_area)
     *             + 10 (experience) + 5 (availability) + 0 (compatibility)
     *             = 100 ✓
     *
     * The Build 4 implementation task must add a unit test asserting:
     *   array_sum(array_column(config('match_scoring.dimensions'), 'weight')) === 100
     */

];
