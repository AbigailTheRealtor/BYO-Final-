# BidYourAgent — Final Launch Architecture & Implementation Plan

**Date:** June 16, 2026  
**Status:** AUTHORITATIVE — supersedes any conflicting conclusions in individual audit documents  
**Source Audits:** #2889 `AGENT_PRESET_DRIVEN_BIDDING_ARCHITECTURE.md`, #2893 `AGENT_HIRING_ARCHITECTURE_AUDIT.md`, #2894 `AUTO_BID_SUBMISSION_READINESS_AUDIT.md`, #2895 `PRESET_TO_BID_INHERITANCE_AUDIT.md`, #2899 `AGENT_MATCHING_ENGINE_READINESS_AUDIT.md`, #2900 `AGENT_PROFILE_PARITY_AUDIT.md`, #2901 `BIDYOURAGENT_LAUNCH_STRATEGY_AUDIT.md`  
**Conflict resolution note:** Where individual audit documents suggest multiple options or leave decisions open, this document records the single final decision and closes the question.

---

> **⚠ Phase 0 Validation Addendum — 2026-06-16**
> Phase 0 live-data validation (`docs/audits/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md`) was executed against 1,000 live Stellar records after this document was written. **Phase 0 validation supersedes the original 20-column Phase 1 list when live data proves a field is under-populated.** After Phase 0, Phase 1 is adjusted to **19 columns**: `furnished` is removed and deferred to Phase 2R (rental feed gate; 35% population rate in the for-sale feed, below the 50% Block threshold). All implementation details are in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` Section 9.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Final Launch Scope](#2-final-launch-scope)
3. [Final Ownership Architecture](#3-final-ownership-architecture)
4. [Final Preset Architecture](#4-final-preset-architecture)
5. [Final Agent Profile Architecture](#5-final-agent-profile-architecture)
6. [Final Matching Architecture](#6-final-matching-architecture)
7. [Auto-Submit Decision](#7-auto-submit-decision)
8. [Bidding Period Decision](#8-bidding-period-decision)
9. [Launch Blockers](#9-launch-blockers)
10. [Implementation Roadmap](#10-implementation-roadmap)
11. [Final Architecture Diagram](#11-final-architecture-diagram)
12. [Final Verdict](#12-final-verdict)

---

## 1. Executive Summary

Seven foundational audits have produced the following cross-cutting findings:

**Finding 1 — Presets are the richest, most reliable agent data source.**  
The `agent_default_profiles.profile_data` JSONB column stores ~157 distinct keys covering credentials, overview, services, compensation (~100 keys), compatibility preferences, and availability. The mapper service (`AgentBidMapperService`) already pipes ~135 of these keys into bid forms automatically. Presets are scoped per `(role, property_type)` with fallback logic — they are the backbone of the agent-hire workflow and the key to making Hire Me work at scale.

**Finding 2 — Agent profiles have meaningful parity gaps.**  
Twelve distinct gaps were identified in `AGENT_PROFILE_PARITY_AUDIT.md`. The most significant: `reviews_links` is whitelisted but never rendered (a code bug), `additional_details` is silently dropped from the public profile, and the entire `compatibility_preferences` section (~20 fields, 6 sub-sections) is invisible on the public profile despite being the platform's core differentiation pitch. Three of the twelve gaps are P1 fixes required before launch.

**Finding 3 — The matching engine uses only a fraction of collected data.**  
The current engine scores Services (50%) and Broker Compensation Terms (50%) across all four roles — roughly 30–35% of all collected fields. Every client field describing property preferences, location, timeline, budget, and personality fit is collected, stored, and ignored at match time. This is not a bug; it is a deliberate current-state baseline that Phase 2 matching expansion will address.

**Finding 4 — Auto-submit is not production-ready for any auction type.**  
The `/test` bot-bidder route is unauthenticated, has no match score gate, no radius gate, no subscription gate, and no schedule. Three referenced Artisan command files do not exist on disk. The Hire Me flow is human-initiated (not autonomous). No auto-bid placement engine exists for Seller Agent, Landlord Agent, or Tenant Agent auction types. The formal verdict is: **launch with manual submission only.**

**Finding 5 — Traditional listings are the correct launch mode for all four roles.**  
Bidding Period is architecturally complete only for the Tenant role. Buyer and Seller lack backend bid-submission guards (UI-only protection), agent anonymization, and competing bid views. Landlord is missing anonymization and competing bid views despite having `auction_ended`. The JIT-only transition mechanism (no cron scheduler) is a correctness gap. The notification burst risk under real traffic is significant. **Launch Traditional-only across all four roles; phase in Bidding Period post-launch.**

**Final Launch Recommendation:** Launch with Traditional listings for all four roles, preset-driven bid creation, the Hire Me flow, Accept/Reject/Counter workflows, match score display, and a fixed agent public profile. Fix three P1 profile parity gaps before go-live. Defer Bidding Period, auto-submit, and expanded matching to the roadmap phases below.

---

## 2. Final Launch Scope

### 2.1 Included At Launch

| Feature | Roles | Notes |
|---------|-------|-------|
| Traditional listings (offer listing forms) | Seller, Buyer, Landlord, Tenant | All four roles in Full Service and Limited Service modes |
| Preset-driven bid creation | All 4 | `AgentBidMapperService` pre-fills bid forms from `agent_default_profiles`; agents can edit before submitting |
| Hire Me / Hire This Agent flow | All 4 | `HireAgentDirectController` creates listing + auto-bid from preset; human-initiated |
| Accept / Reject / Counter workflows | All 4 | Counter models and notifications are complete for all roles |
| Agent presets (create, edit, manage) | All 4 | Per `(role, property_type)` with `__default__` fallback and save-scope propagation |
| Agent public profile (`/agent/{shortId}`) | All 4 | After P1 parity fixes (see §5) |
| Hire Me public URL (`/hire/{agentShortId}/{role}/{propertyType?}`) | All 4 | Shareable, auth-free |
| Agent widget (`/widget/hire/{agentShortId}/{role}/{propertyType}`) | All 4 | Read-only embeddable widget |
| Match score display (Services + Terms) | All 4 | 0–100% score shown to listing owner per bid |
| Compatibility display | All 4 | 7-section compatibility responses shown on bid detail |
| Accepted Bid Summary (PDF) | All 4 | `accepted_bid_summaries` + `barryvdh/laravel-dompdf` |
| My Listings by Role (client dashboard) | All 4 | Consolidated listing view |
| Agent Hire Listings Hub (`/agent/hire-listings`) | All 4 | Filterable view of all listing types |
| My Referrals page (`/agent/my-referrals`) | All 4 | Filterable referral activity with metrics |
| Client-Requested Custom Services | All 4 | Saved as `client_custom_services` JSON on listings and bids |
| Ask AI integration | All 4 | FAQ and listing-field answer pipeline |
| Dashboard Messaging (AJAX chat) | All roles | Blade + jQuery AJAX |

### 2.2 Excluded From Launch

| Feature | Rationale |
|---------|-----------|
| Bidding Period (Auction Timer) listings | Incomplete for 3/4 roles: no backend submission guard, no agent anonymization, no competing bid views for Buyer/Seller/Landlord; JIT-only expiration is a correctness gap; notification burst risk under real traffic |
| Anonymous agent bidding (Bidding Period feature) | Depends on Bidding Period; `BiddingPeriodAgentMapping` wired to Tenant only |
| Competitive bid transparency (agent-facing) | Depends on Bidding Period and full `CompetingBidsController` wiring; absent for Buyer/Seller/Landlord |
| Autonomous / scheduled auto-bid submission | No match score gate, no radius gate, no subscription gate, no schedule, no existing command files; not production safe for any role |
| Auto-bid escalation engine | Stored `autobid_price` / `autobid_days_start_date` / `autobid_lease_length` fields on Landlord bids have no execution engine; UI-only preference with no server-side actor |
| Scheduled listing auto-expiration | `Kernel.php` schedule entries are commented-out; command files are absent |
| Expanded matching (location, budget, property features, compatibility) | Data collected but no scoring path for ~65–70% of fields; normalization and agent-mirror fields required first |

---

## 3. Final Ownership Architecture

This section resolves all cross-layer ownership ambiguities identified in `AGENT_HIRING_ARCHITECTURE_AUDIT.md`. The five layers are: **Profile** (`users` + `user_meta`), **Preset** (`agent_default_profiles.profile_data`), **Listing** (`{role}_agent_auctions` + `_metas`), **Bid** (`{role}_agent_auction_bids` + `_metas`), and **Accepted Summary** (`accepted_bid_summaries`).

### 3.1 Identity

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `first_name`, `last_name` | Profile (`users`) | Preset (pre-fill), Bid (snapshot) | Permanent identity; bid snapshots so the record is self-contained if agent later changes their name |
| `email` | Profile (`users`) | Preset (pre-fill), Bid (snapshot) | Same snapshot rationale |
| `phone` | Profile (`users`) | Preset (pre-fill), Bid (snapshot) | Same; note: `phone_number` on `users` is a legacy duplicate, do not use |
| `short_id` | Profile (`users`) | — | Hire Me URL key; never duplicated |
| `avatar` | Profile (`users`) | — | File path; not duplicated |
| `brokerage` | `user_meta` (string) | Preset, Bid | Native `users.brokerage` does not exist; always read from `user_meta` or Preset |
| `license_no` | `user_meta` | Preset, Bid | Same; native column does not exist |
| `nar_id` | `user_meta` | Preset, Bid | Same |
| `year_licensed` | `user_meta` | Preset, Bid | Same |
| `mls_id` | Profile (`users` native) | — | Not surfaced publicly; not in bid EAV |

### 3.2 Services

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `services` | Preset (template) | Listing (client request), Bid (agent commitment) | Each layer captures state at a different transaction point. Preset = reusable offer. Listing = what client accepted at engagement time. Bid = what agent agreed to provide. Snapshots are required because the preset can change after the listing is created. |
| `other_services` | Preset (template) | Listing, Bid | Same rationale |
| `client_custom_services` | Listing | Bid (copy) | Client-authored services not from preset; bid copy carries them into the engagement record |

**Decision (D2 from #2893):** Both the listing copy and bid copy of `services` are intentional. The listing's copy = client's request. The bid's copy = agent's commitment. Use the bid copy for bid detail displays; use the listing copy for "what this listing requires" displays.

### 3.3 Compensation

| Field Group | Primary Owner | Secondary Copies | Reason |
|-------------|--------------|------------------|--------|
| All ~100 compensation keys | Preset (default template) | Listing (client-requested, Seller/Buyer only), Bid (agreed-to values) | Preset = agent's reusable rate card. Listing = what the client expects (pre-fills bid form). Bid = the agent's actual proposal (may differ from listing's request). |
| Canonical at engagement time | **Bid** | — | The Bid record holds compensation as the agent agreed at submission time. AcceptedBidSummary is derived from the Bid, never from the Listing. |

**Decision (D1 from #2893 — FINAL):** Bid owns canonical compensation values at engagement time. Any code that reads compensation for display or for AcceptedBidSummary generation must read from the bid, not the listing.

**Key name inconsistency (must fix):** Listing stores `referral_percentage`; bid stores `referral_fee_percent`. Same semantic value with different keys. Unify to `referral_fee_percent` in a migration.

### 3.4 Broker Terms

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `brokerage_relationship` | Preset | Bid | Role-contextual; snapshots on bid for self-contained record |
| `additional_details_broker` | Preset | Bid | Same |
| `retained_deposits` | Preset | Bid (Seller only currently; gap for Buyer/Landlord/Tenant) | Should be stamped on all four bid roles; currently only Seller applies it from the mapper |
| `agency_agreement_timeframe`, `agency_agreement_custom` | Preset | Bid | Snapshot at submission |

### 3.5 Compatibility

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `compatibility_preferences` (7 sections) | Preset | Bid (snapshot) | Role-contextual; agent may have different working style per role. Bid snapshots so the commitment is frozen at submission. |

**Decision (D3 from #2893 — FINAL):** Preset owns compatibility preferences. `user_meta` copies of `communication_style` and `preferred_contact_method` are accidental duplicates — stop writing to `user_meta` for these; treat Preset as authority for all new code.

**Known gap (#2893 D5):** The Hire Me auto-bid creation in `commitListingAndBid()` does **not** call `mapCompatibilityFromProfile()`. Hire Me auto-bids lack compatibility data. This must be fixed before launch.

### 3.6 Availability

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `avg_response_time`, `availability_status`, `evenings_available`, `weekends_available` | Preset | Bid (gap — not currently stamped) | These are offer-time signals (agent's commitment for this engagement). They belong on the bid. Currently display-only in Preset. |

**Decision (D4 from #2893 — FINAL):** These four fields must be added to `AgentBidMapperService::mapFromProfile()` so they are stamped onto bid records at form-load time. This is a one-line change per field in the mapper.

### 3.7 Media

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `presentation_link`, `business_card_link` | Preset | Bid (snapshot) | URLs committed at bid time |
| `presentation_upload_path`, `business_card_upload_path` | Preset | Bid (snapshot) | File paths; if only the upload path exists (no link URL), the profile controller must resolve a public URL before the whitelist strip |
| `reviews_links`, `website_link`, `social_media`, `promoMaterials` | Preset | Bid (snapshot) | JSON arrays; snapshot at bid time |
| `intro_video_url`, `video_caption` | Preset | — (Profile display only, not in bid) | Display-only on public profile; not stamped to bid |
| `cover_photo` | Profile (`users` native) | — | Not currently displayed anywhere on agent profile; low-priority gap |

### 3.8 Reviews / Social Proof

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `review_1`, `review_2`, `review_3` | Preset | — | Display-only on public profile; not stamped to bid |
| `awards_recognition` | Preset | — | Same |
| `user_meta` copies | Legacy | — | Treat as deprecated; Preset wins per D6 of #2893 |

### 3.9 Service Areas

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `primary_areas_served`, `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes` | Preset | — | Display-only; not stamped to bid. Used for Phase 2 matching (agent service area vs. client location) |

**Decision (D6 from #2893 — FINAL):** Preset wins for all profile-content fields that appear in the Preset editor UI. Any code reading these fields from `user_meta` directly should be redirected to `AgentDefaultProfile::findForAgentWithFallback()` → `profile_data`.

### 3.10 Marketing Plans

| Field | Primary Owner | Secondary Copies | Reason |
|-------|--------------|------------------|--------|
| `marketing_plan` | Preset | Bid (snapshot) | Role-specific marketing strategy; snapshots at bid submit |

### 3.11 System / Lifecycle

| Field | Primary Owner | Reason |
|-------|--------------|--------|
| `workflow_type` | Listing | Distinguishes `hire_agent` vs `offer_listing` creation path |
| `listing_status` | Listing | Active / Pending / Hired Agent / Expired |
| `expiration_date` | Listing | Platform source of truth for countdown; fallback to `created_at + auction_time` for legacy records |
| `hire_me_flow`, `hire_me_auto_bid` | Listing / Bid respectively | Marker flags; not authoritative for data values |

### 3.12 Summary Ownership Matrix

| Domain | Profile Owns | Preset Owns | Listing Owns | Bid Owns |
|--------|-------------|-------------|--------------|----------|
| Agent identity | ✅ Permanent | Pre-fill copy | — | Snapshot |
| Services | — | Template | Client request | Agent commitment |
| Compensation | — | Default template | Client request (Seller/Buyer) | Agreed-to values ✅ |
| Broker terms | — | Default | Pre-fill (Seller/Buyer) | Snapshot |
| Compatibility | — | ✅ Role-scoped | Client response | Snapshot |
| Availability | — | ✅ Display + bid seed | — | Gap (must fix) |
| Media / Links | — | ✅ | — | Snapshot |
| Areas served | — | ✅ Display-only | — | — |
| Testimonials | — | ✅ Display-only | — | — |
| Client contact | — | — | ✅ `client_*` prefix | `counter_*` copy for counter-flow compat |
| System lifecycle | — | — | ✅ | Derivative markers |

---

## 4. Final Preset Architecture

### 4.1 What Presets Are Responsible For

Presets (`agent_default_profiles`, one row per `(user_id, role_type, property_type)`) are the agent's reusable offer template. They are not a bid. They are not a profile. They are the agent's pre-configured terms for a specific role and property type context.

**Preset responsibilities:**
- Services offered for this role + property type (catalog-filtered)
- Compensation terms (all ~100 compensation keys; role-specific subsets apply)
- Broker agreement terms (agency timeframe, protection period, retainer, early termination)
- Lease-option agreement terms
- Agent overview content (bio, why hire you, what sets you apart, marketing plan)
- Credentials (first name, last name, phone, email, brokerage, license, NAR ID, year licensed)
- Media links (presentation, business card, reviews, website, social media, promo materials)
- Compatibility preferences (7 sections of working style preferences)
- Availability (avg response time, evenings, weekends, availability status)
- Quick highlights (years experience, transactions last 12 months, full-time status, primary areas)
- Areas served (cities, counties, neighborhoods, notes)
- Testimonials and awards (display-only)
- Video introduction (display-only)

**Bid responsibilities (snapshot at submission time):**
- All fields in `mapFromProfile()` output (see §4.2) — the bid captures these at the moment the agent submits, independent of future preset changes
- Client contact data (`counter_*` prefix) — copied from listing for counter-flow compatibility
- Hire Me marker (`hire_me_auto_bid = 1`)

### 4.2 Fields That Inherit Automatically (Preset → Bid)

These are applied by all four bid Livewire components in `mount()` via `AgentBidMapperService::findAndMap()`:

| Category | Fields | All 4 Roles? |
|----------|--------|:------------:|
| Agent overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` | ✅ |
| Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `nar_id`, `year_licensed` | ✅ |
| Media | `presentation_link`, `presentation_upload_path`, `business_card_link`, `business_card_stored_path`, `reviews_links`, `website_link`, `social_media`, `promoMaterials` | ✅ |
| Shared compensation | `commission_structure`, `purchase_fee_*`, `protection_period`, `early_termination_fee_*`, `retainer_fee_*`, `agency_agreement_*`, `interested_lease_option_agreement`, `lease_type/value`, `purchase_type/value`, `brokerage_relationship`, `additional_details_broker` | ✅ |
| Compatibility | All 7 sections via `mapCompatibilityFromProfile()` | ✅ |
| Seller-specific | `nominal`, `commission_structure_type`, all `seller_leasing_*` | Seller only |
| Buyer/Tenant lease fee | `interested_lease_option`, all `lease_fee_*` | Buyer + Tenant |
| Landlord residential | `purchase_fee_rental_period` | Landlord only |
| Landlord commercial | `purchase_fee_net_aggregate`, `purchase_fee_gross_rent`, etc. (10 keys) | Landlord only |
| Landlord tenant broker | All `tenant_broker_*` (7 keys) | Landlord only |
| Landlord/Tenant fee timing | `broker_fee_timing`, `broker_fee_days_*` (5 keys) | Landlord + Tenant |
| Landlord split payment | `split_payment_due`, `split_payment_due_other`, `broker_fee_days_after_due_event` | Landlord only |
| Landlord renewal/extension | All `renewal_fee_*` (10 keys) | Landlord only |
| Landlord expansion | `expansion_commission_percentage` | Landlord only |
| Landlord property mgmt | All `interested_in_property_management*` (6 keys) | Landlord only |
| Landlord selling interest | `interested_in_selling`, `interested_in_selling_type`, all `landlord_broker_*` (7 keys) | Landlord only |

### 4.3 Profile-Only Fields (Shown on Public Profile, Not Sent to Bids)

These are stored in `profile_data` and displayed on the public profile / Hire Me preview, but are not included in `mapFromProfile()` and are not stamped onto bid records:

| Category | Fields |
|----------|--------|
| Quick highlights | `years_experience`, `transactions_last_12_months`, `avg_response_time`*, `is_full_time`, `primary_areas_served` |
| Areas served | `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes` |
| Testimonials | `review_1`, `review_2`, `review_3`, `awards_recognition` |
| Video | `intro_video_url`, `video_caption` |
| Availability | `availability_status`*, `evenings_available`*, `weekends_available`* |
| Communication | `communication_style`, `preferred_contact_method` |

> *Fields marked with asterisk should be added to `mapFromProfile()` as part of the Launch Cleanup build (see §10). They represent agent availability commitments that belong on the bid.

### 4.4 Fields Never Inherited (Not in Preset at All)

These fields exist on bids but come from sources other than the preset:

| Field | Source |
|-------|--------|
| `referral_fee_percent` | Seeded from listing meta; preset value is available in `$mapped` but not applied when listing value exists |
| `services` (Seller, Buyer) | Seeded from listing's requested services, not from preset directly |
| `client_custom_services` | Client-authored; not from preset |
| All `counter_*` prefix fields | From the Hire Me form submission or counter-bid flow |
| `hire_me_auto_bid` | Set by `HireAgentDirectController`; not from preset |

### 4.5 Save-Scope Propagation Rule

The `PROFILE_FIELDS` constant (41 keys in `AgentPresetController`) is subject to save-scope propagation. When an agent saves with scope `current_role`, these values overwrite the same keys in every preset for that role. Compensation and services keys are **always** preset-scoped (never propagated cross-role).

---

## 5. Final Agent Profile Architecture

### 5.1 Public Profile Specification

The public profile at `/agent/{shortId}` reads exclusively from `agent_default_profiles.profile_data` (the agent's default preset for any role) after `AgentProfileController::PUBLIC_PROFILE_KEYS` whitelist is applied. The `$agent->avatar` is the only field sourced from the `users` table.

**Sections rendered on public profile (current state, before gap fixes):**

| Section | Key Source | Status |
|---------|-----------|--------|
| Hero (name, brokerage, license, avatar) | `profile_data` + `users.avatar` | ✅ |
| Agent Overview (bio, why hire, sets apart, marketing) | `profile_data` | ✅ |
| Quick Highlights (experience, transactions, response time, full-time) | `profile_data` | ✅ |
| Areas Served | `profile_data` | ✅ |
| Services | `profile_data` | ✅ |
| Social Proof (testimonials, awards) | `profile_data` | ✅ |
| Video Intro | `profile_data` | ✅ |
| Availability & Style | `profile_data` | ✅ |
| Presentation & Links (presentation, business card, website, social) | `profile_data` | ✅ |
| Reviews Links | `profile_data` (whitelisted) | ❌ **BUG — whitelisted but never rendered** |
| Additional Details | `profile_data` | ❌ **Missing — not whitelisted** |
| Working Style & Compatibility | `profile_data` | ❌ **Entire section invisible** |
| Broker Compensation (logged-in users) | `profile_data` | ✅ Partial (~15 of 100+ fields shown; intentionally curated) |

### 5.2 Parity Gap Classification

| # | Gap | Severity | Classification | Action |
|---|-----|----------|----------------|--------|
| 1 | `reviews_links` whitelisted but never rendered — field reaches the view but no template block outputs it; also excluded from `$hasLinks` guard | **High** | **Must Fix Before Launch (P1)** | Add rendering block to `show.blade.php`; add to `$hasLinks` guard |
| 2 | `additional_details` not whitelisted — agent fills "Additional Details" in preset, silently dropped from profile | **High** | **Must Fix Before Launch (P1)** | Add to `PUBLIC_PROFILE_KEYS` and render in About section |
| 3 | `compatibility_preferences` — entire section invisible — ~20 fields across 6 sub-sections stored per preset, zero display on profile | **High** | **Must Fix Before Launch (P1)** | Add `compatibility_preferences` to `PUBLIC_PROFILE_KEYS`; add a read-only Working Style section to `show.blade.php` with top-level values |
| 4 | `bio` storage split — Settings saves to `user_meta`; profile reads `profile_data`. Agent who fills bio in Settings sees no change on public profile | **High** | **Recommended Post-Launch** | Option B: on `DashboardController::saveSettings()` save, also sync to agent's primary preset's `profile_data` |
| 5 | `preferred_contact_method` storage split — same pattern as bio split | **Medium** | **Recommended Post-Launch** | Same resolution as #4 |
| 6 | `best_time_to_contact` stored in `user_meta`, never shown on profile or publicly | **Medium** | **Recommended Post-Launch** | Fetch from `$agent->info('best_time_to_contact')` in controller; render in Availability section |
| 7 | Uploaded files (`presentation_upload_path`, `business_card_upload_path`) invisible on profile — upload stored but profile only shows link version | **Medium** | **Recommended Post-Launch** | Resolve public URL from stored path server-side in `AgentProfileController::show()` before whitelist strip; inject as `presentation_link` / `business_card_link` |
| 8 | `phone` (preset) not whitelisted — phone in Credentials section of preset never shown on profile | **Medium** | **Recommended Post-Launch** | Add to `PUBLIC_PROFILE_KEYS`; render as `tel:` link in Credentials section |
| 9 | `phone` (users table / settings) not shown — phone saved via Account Settings never surfaces on profile | **Medium** | **Recommended Post-Launch** | Resolved by P1 gap #8 — they should use the same display slot |
| 10 | `cover_photo` stored but unused on profile — `users.cover_photo` column is populated, no cover photo slot exists | **Low** | **Optional** | Add hero cover photo banner; low priority |
| 11 | `mls_id` stored but not shown on agent public profile | **Low** | **Optional** | Add to profile if agents request it |
| 12 | Compensation section label implies completeness — "Broker Compensation & Agency Agreement Terms" shows ~15 of 100+ fields with no indication it's partial | **Low** | **Recommended Post-Launch** | Add a "(Summary — full terms shared upon bid submission)" notice |

---

## 6. Final Matching Architecture

### 6.1 Current Engine (Phase 1 — At Launch)

**Formula (all four roles):**
```
Overall Match % = 50% × Services Score + 50% × Terms Score
(only non-zero components are averaged)
```

**Services Score:** Client's requested services filtered against role+property-type catalog. Matched services counted against denominator of client-requested count. Extra agent services do not inflate the score.

**Terms Score:** Each `LOGICAL_FIELD_GROUPS` entry = 1 logical decision. Sub-inputs for one concept (e.g., `lease_fee_type` + `lease_fee_flat` + `lease_fee_percentage`) are concatenated into one composite value before comparison. Conditional groups (parent = "No") are cascade-excluded from the denominator.

**Phase 1 Scored Inputs by Role:**

| Category | Seller | Buyer | Landlord | Tenant | Recommended Weight |
|----------|:------:|:-----:|:--------:|:------:|:-----------------:|
| Services | ✅ Scored | ✅ Scored | ✅ Scored | ✅ Scored | 50% |
| Purchase/lease commission | ✅ Scored | ✅ Scored | ✅ Scored | ✅ Scored | 30% of Terms |
| Retainer & protection | ✅ Scored | ✅ Scored | ✅ Scored | ✅ Scored | 10% of Terms |
| Agency agreement & broker terms | ✅ Scored | ✅ Scored | ✅ Scored | ✅ Scored | 10% of Terms |
| Fee timing & renewal (Landlord) | N/A | N/A | ✅ Scored | Partial | Within Terms |
| Property type | 🟡 Scorable | 🟡 Scorable | 🟡 Scorable | 🟡 Scorable | Phase 2 |
| Budget / price range | 🔴 Needs agent mirror | 🔴 Needs agent mirror | 🔴 Needs agent mirror | 🔴 Needs agent mirror | Phase 2 |
| Location / service areas | 🔴 Needs agent mirror | 🔴 Needs agent mirror | 🔴 Needs agent mirror | 🔴 Needs agent mirror | Phase 3 |
| Compatibility preferences | ⬜ Schema undefined | ⬜ Schema undefined | ⬜ Schema undefined | ⬜ Schema undefined | Phase 3 |

### 6.2 Phase 2 — Compatibility Expansion (Post-Launch, ~4–8 weeks)

Add scoring for structured fields that exist on both client and agent sides but are not currently scored. These require normalization work but no new data collection.

| Category | Fields | Exists Today | Scored Today | Effort | Recommended Weight |
|----------|--------|:------------:|:------------:|--------|:-----------------:|
| Property type | `property_type` enum | ✅ Both sides | ❌ No | Low | +5% |
| Service type (Full/Limited) | `service_type` enum | ✅ Both sides | ❌ No | Low | +3% |
| Years licensed (experience threshold) | `year_licensed` integer | ✅ Both sides | ❌ No | Low | +2% |
| Bedrooms / bathrooms | `bedrooms`, `bathrooms` string ("3+") | ✅ Client side | ❌ No | Medium (normalize "3+" → int floor) | +5% |
| Property features array | `property_items` JSON | ✅ Both sides | ❌ No | Medium (set-intersection scoring) | +5% |
| Lease length preferences | `leaseLength`, `desired_lease_length` | ✅ Client side | ❌ No | Medium (normalization) | +3% |
| Pet policy | `petsOpt` vs `has_pets` | ✅ Client side | ❌ No | Medium | +2% |
| Move-in / availability date | `idealDate`, `lease_date` | ✅ Client side | ❌ No | Medium (no agent mirror) | Phase 3 |

### 6.3 Phase 3 — Location DNA Expansion (Post-Launch, ~12–24 weeks)

These require adding agent-side mirror fields and potentially AI-scored comparisons.

| Category | Gap | Fix Required | Effort |
|----------|-----|-------------|--------|
| Location / service areas | Client has `cities`, `counties` JSON arrays; agent has `cities_served` / `counties_served` in preset but these are not checked during scoring | Wire `cities_served` from preset into matching engine; build set-intersection scorer | High |
| Budget ceiling vs. price | Client has `maximum_budget` / `max_rent`; agent has no mirror field | Add `agent_price_range_min/max` to preset; wire into engine | High |
| Financing type | Client has `offered_financing` / `financings` JSON; no agent mirror | Add `accepted_financing_types` to preset | High |
| Compatibility preferences (7 sections) | Both sides store data; schema is unstructured JSON; no scoring | Standardize question/answer schema; implement cosine or overlap scoring | Very High |
| Location DNA | `location_dna_preferences` on tenant side; `location_match_score` in `listing_compatibility_scores` always null | Implement Location DNA Phase 2 in `ComputeCompatibilityScore` job | Very High |

### 6.4 Fields With No Agent Mirror (Phase 2/3 Prerequisites)

These client-side fields cannot be scored until the agent side captures a corresponding value:

| Client Field | Role(s) | Missing Agent Mirror |
|-------------|---------|---------------------|
| `maximum_budget` / `max_rent` | All 4 | Agent price range or willingness threshold |
| `cities` / `counties` (search area) | Buyer, Tenant | Already in preset `cities_served` — needs wiring into scorer |
| `financings` / `offered_financing` | Buyer, Seller | `accepted_financing_types` field on preset |
| `leaseLength` / `lease_for` | Landlord, Tenant | `available_lease_lengths` field on preset |
| `idealDate` / `lease_date` | Landlord, Tenant | `earliest_available_date` field on preset |
| `creditScore` / `tenant_credit_score` | Landlord | Agent minimum credit score requirement |

---

## 7. Auto-Submit Decision

### 7.1 What Exists Today

**Bot-bidder route (`/test`):**
- Unauthenticated GET route in `routes/web.php` (lines 1047–1103)
- Targets `PropertyAuction` listings only; not Seller Agent / Landlord Agent / Tenant Agent
- No schedule, no queue, no retry logic; executes synchronously in the request cycle
- Checks: `auto_bid = 1`, `sold = 0`, `sold_date IS NULL`; random bot user selection
- Stores `autobid_maximum_price = "null"` (string literal, not PHP null) — a known bug
- `auto_bid_record = 0` on bot bids is identical to human bids — no distinguishable sentinel

**Hire Me auto-bid marker:**
- `HireAgentDirectController::acknowledgeSubmit()` stamps `hire_me_auto_bid = 1` on the created bid
- This is a label on a human-initiated, session-protected, multi-step confirmation flow
- It is **not** an autonomous engine

**Commented-out Artisan commands:**
- `AutoBid`, `SellerAutocounter`, `BuyerAutocounter` referenced in `Kernel.php` schedule entries
- None of these command class files exist on disk — uncommenting would cause a fatal boot error

**Autobid preference fields with no engine:**
- Landlord bids store `autobid_price`, `autobid_days_start_date`, `autobid_lease_length` as EAV meta
- No service, job, or command reads these fields to place automatic bids

### 7.2 The Five Required Gates

Before any autonomous bid placement can be enabled for any auction type, all five conditions must exist:

| Gate | Current Status | What Is Needed |
|------|:--------------:|----------------|
| **Match score threshold** | ❌ Missing | Read `overall_score` from `listing_compatibility_scores`; require minimum threshold (e.g., ≥60%) before placing bid |
| **Radius / geography constraint** | ❌ Missing | Compare agent's `cities_served` / `counties_served` against listing location; require overlap before placing bid |
| **Property type filtering** | ⚠️ Partial | Hire Me enforces `VALID_PROPERTY_TYPES` allowlist; bot-bidder has no check; both need role-specific property type matching |
| **Listing status check** | ⚠️ Partial | Bot-bidder checks `sold=0`, `sold_date IS NULL` only; must also check `is_draft`, `is_approved`, listing expiry, auction type |
| **Subscription / payment status** | ❌ Missing | Neither the bot-bidder nor Hire Me checks whether the agent has an active subscription or payment method on file |

Additionally, before production use: rate limiting, deduplication guard (DB unique constraint or advisory lock), queue-based execution, audit log of auto-placed bids.

### 7.3 Formal Verdict

**DECISION — FINAL:** Launch with manual bid submission only. Autonomous bid placement is deferred until all five gates exist and pass a full security review.

Rationale: The current bot-bidder is a prototype accessible via unauthenticated HTTP GET with no safeguards. The Artisan command files do not exist. No auto-submit engine covers Seller Agent, Landlord Agent, or Tenant Agent auctions. The five required gates are absent. Enabling any form of autonomous bid submission under current conditions would expose the platform to bid flooding, bot exploitation, and consent violations.

---

## 8. Bidding Period Decision

### 8.1 Current State by Role

| Feature | Tenant | Buyer | Seller | Landlord |
|---------|:------:|:-----:|:------:|:--------:|
| Countdown timer (UI) | ✅ | ✅ | ✅ | ✅ |
| Action gating (UI only) | ✅ | ✅ | ✅ | ✅ |
| Action gating (backend — server-side enforcement) | ✅ | ❌ | ❌ | ✅ |
| Agent anonymization | ✅ | ❌ | ❌ | ❌ |
| Competing bid view (agents see anonymized peers) | ✅ | ❌ | ❌ | ❌ |
| Auto-transition via JIT (on page load only) | ✅ | ✅ | ✅ | ✅ |
| Auto-transition via cron / scheduled job | ❌ | ❌ | ❌ | ❌ |
| `auction_ended` DB column | ✅ | ❌ | ❌ | ✅ |
| Consistent timer source | ✅ | ✅ | ✅ | ⚠️ `auction_length` int vs `auction_time` string mismatch |

### 8.2 Notification Risk

Bidding Period creates a notification burst on timer expiration. When the listing owner acts quickly after expiry, all rejection notifications fire synchronously in one request cycle. Notifications do not implement `ShouldQueue`. The listing-creation county-blast (agents notified on listing creation) can drive 20–50 agent bids, making the burst scenario realistic at launch scale. This risk is low for Traditional (owner controls the pace) and high for Bidding Period.

### 8.3 Final Rollout Recommendation

**DECISION — FINAL:** Option C (Traditional First, Bidding Period Later).

**Phase 1 — Launch (Traditional Only, all four roles):**
- Add config value `BIDDING_PERIOD_ENABLED=false` disabling `auction_type` selection UI across all four create/edit wizard forms
- Hard-code `auction_type = 'Traditional'` on listing creation when the flag is off
- All Traditional functionality ships as-is: counter-bids, match scores, accepted bid summaries, notifications, Hire Me auto-bid

**Phase 2 — Bidding Period for Tenant (Post-Launch, ~4–8 weeks):**
Pre-conditions before enabling:
1. Scheduled expiration: Uncomment or rewrite `Kernel.php` scheduled task so listings auto-transition without requiring a page load
2. Queued notifications: Convert `BidSubmittedNotification` and all counter/accept/reject notifications to `ShouldQueue`
3. County blast cap: Audit and cap agent-notification-on-listing-create (max N recipients, or opt-in digest)
4. Expiration notification: Add `BiddingPeriodExpiredNotification` that actively tells the listing owner the window has closed

**Phase 3 — Bidding Period for Buyer, Seller, Landlord (Per-Role, ~12+ weeks post-launch):**
Pre-conditions per role before enabling:
1. Backend bid-submission guard: Add server-side enforcement in `BuyerAgentAuctionBidController` and `SellerAgentAuctionController` matching the guard already in `LandlordAgentAuctionBidController`
2. Agent anonymization: Implement `BiddingPeriodAgentMapping` creation and lookup for the role (currently Tenant only)
3. Competing bids view: Add role-aware paths in `CompetingBidsController` and `CompetingBidsService` using the already-complete `BuyerBidMatchScoreHelper`, `SellerBidMatchScoreHelper`, `LandlordBidMatchScoreHelper`
4. DB schema parity: Add `auction_ended` column to Buyer and Seller auction tables; normalize Landlord's `auction_length` integer to the same `auction_time` string format

---

## 9. Launch Blockers

### 9.1 Critical Before Launch (P0 — Must fix; without these, launch is unsafe or broken)

| # | Issue | Source Audit | Rationale |
|---|-------|-------------|-----------|
| C1 | **Hire Me auto-bid missing compatibility data** — `commitListingAndBid()` does not call `mapCompatibilityFromProfile()`; all Hire Me auto-bids are created without compatibility preferences from the preset | #2893 D5 | The compatibility section is a core product differentiator; auto-bids created through the primary hire flow lack the agent's compatibility data entirely |
| C2 | **Unauthenticated `/test` bot-bidder route must be blocked** — any external party can trigger bid insertion via unauthenticated GET; this must be removed or gated behind IP allowlist + secret header before launch | #2894 §G.1 | Active security vulnerability in `routes/web.php`; exploitable from public internet |
| C3 | **`BIDDING_PERIOD_ENABLED` feature flag must be set to `false` at launch** — without disabling Bidding Period mode, listing creators can select `auction_type = 'Auction (Timer)'` which has backend enforcement gaps and no agent anonymization for three of four roles | #2901 | Correctness gap — backend submission guards are UI-only for Buyer and Seller; an agent can bypass the "bidding closed" state via direct HTTP POST |

### 9.2 High Priority (P1 — Fix before or at launch; serious UX/data correctness issues)

| # | Issue | Source Audit | Classification |
|---|-------|-------------|----------------|
| H1 | **`reviews_links` whitelisted but never rendered** on public agent profile — data silently lost on the profile page despite passing the security filter | #2900 Gap #1 | **Must Fix Before Launch** — agent fills this, expects it to show; the template block is simply missing |
| H2 | **`additional_details` not in `PUBLIC_PROFILE_KEYS`** — "Additional Details" in Agent Overview is saved, cross-propagated, and silently dropped from the profile | #2900 Gap #2 | **Must Fix Before Launch** |
| H3 | **`compatibility_preferences` section entirely invisible on public profile** — ~20 fields across 6 sub-sections stored per preset, zero display on profile; this is the platform's primary differentiation pitch | #2900 Gap #3 | **Must Fix Before Launch** |
| H4 | **`retained_deposits` not applied to Buyer / Landlord / Tenant bids** — the mapper outputs it but only the Seller bid component applies it; three roles silently discard the preset value | #2889 §1.3 | **Must Fix Before Launch** |
| H5 | **`referral_percentage` (listing) vs `referral_fee_percent` (bid) key name inconsistency** — same semantic value with different key names across Seller/Buyer listings and bids | #2893 §Cross-Layer | **Must Fix Before Launch** — migration needed to unify key names |
| H6 | **Availability fields not stamped onto bids** — `avg_response_time`, `availability_status`, `evenings_available`, `weekends_available` are in the preset and displayed on the Hire Me preview but not stamped onto bid records via the mapper | #2893 D4 | **Must Fix Before Launch** — one-line-per-field addition to `AgentBidMapperService::mapFromProfile()` |
| H7 | **`autobid_maximum_price` stored as string `"null"` instead of PHP null** — downstream numeric comparisons receive a truthy non-null string | #2894 §G.6 | **Must Fix Before Launch** (for any code path that reads this field) |
| H8 | **Landlord counter FK asymmetry** — `LandlordCounterTerm` FK points to `landlord_agent_auction_id` (the listing) instead of `landlord_agent_auction_bid_id` (the bid); all bids on a listing share counter records | #2889 §4.3 | **Must Fix Before Launch** if Counter is used for Landlord role at launch |

### 9.3 Medium Priority (P2 — Fix soon post-launch; significant gaps but not blockers for day-one correctness)

| # | Issue | Source Audit |
|---|-------|-------------|
| M1 | `bio` storage split (Settings → `user_meta` vs profile reads `profile_data`) — agents who fill bio in Settings see no effect on their public profile | #2900 Gap #4 |
| M2 | `preferred_contact_method` storage split — same pattern as bio | #2900 Gap #5 |
| M3 | `best_time_to_contact` stored in `user_meta`, never displayed anywhere | #2900 Gap #6 |
| M4 | Uploaded files (`presentation_upload_path`, `business_card_upload_path`) invisible on public profile — upload is stored but profile only shows the `_link` URL version | #2900 Gap #7 |
| M5 | `phone` (preset credentials) not whitelisted — phone entered in preset Credentials section is cross-propagated but never shown on the profile | #2900 Gap #8 |
| M6 | `user_meta` copies of profile-content fields are accidental duplicates — any code reading `avg_response_time`, `communication_style`, `preferred_contact_method`, etc. from `user_meta` should be redirected to `profile_data` via `AgentDefaultProfile::findForAgentWithFallback()` | #2893 D6 |
| M7 | Deprecated native bid columns on Seller/Buyer bid tables — modern code writes EAV; legacy native columns (`name`, `brokerage` float, `license_no`, `phone`, `email`, etc.) are never read by Livewire components | #2893 Bid-Owned Fields |
| M8 | `years_experience`, `transactions_last_12_months`, `is_full_time`, `primary_areas_served` — stored in preset, shown on public profile, but not stamped onto bids; Phase 2 bid form additions needed | #2889 §3.2.E |
| M9 | Compatibility scoring schema undefined — both client and agent store compatibility preferences but no question/answer schema is defined; scoring is impossible until the schema is standardized | #2899 §8.4 |
| M10 | JIT-only Bidding Period expiration — no cron scheduler; listings only auto-transition when someone loads the page | #2901 §3 |

### 9.4 Post-Launch (Tracked, Not Blocking)

| # | Issue | Source Audit |
|---|-------|-------------|
| P1 | Expand matching engine Phase 2 (property type, bedrooms/bathrooms, features, lease length, pet policy) | #2899 §10 |
| P2 | Add agent-side mirror fields (price range, financing types, available lease lengths) for Phase 3 matching | #2899 §11 |
| P3 | Location DNA scoring (`location_match_score` in `listing_compatibility_scores` is always null) | #2894 §E.1 |
| P4 | Bidding Period Phase 2 (Tenant role): scheduled expiration, queued notifications, county blast cap, expiration notification | #2901 Phase 2 |
| P5 | Bidding Period Phase 3 (Buyer/Seller/Landlord): backend guards, anonymization, competing views, DB schema parity | #2901 Phase 3 |
| P6 | Auto-submit Phase 4: build all five required gates before enabling any autonomous bid placement | #2894 §F |
| P7 | `cover_photo` not displayed on agent profile — column is stored but no display slot exists | #2900 Gap #10 |
| P8 | `mls_id` not shown on agent public profile | #2900 Gap #11 |
| P9 | Compensation section completeness label on profile — add "Summary" notice | #2900 Gap #12 |
| P10 | `communication_style` and `preferred_contact_method` (standalone scalar fields) not on bid forms — Phase 3 bid form additions | #2889 §3.2.I |
| P11 | `review_1/2/3`, `awards_recognition`, `intro_video_url`, `video_caption`, `years_experience`, `transactions_last_12_months` — Phase 2 bid form additions | #2889 §3.2.G–J |

---

## 10. Implementation Roadmap

### Build 1 — Profile Parity (Launch Prerequisite)

**Depends on:** Nothing  
**Blocks:** Launch  
**Estimated scope:** 3–5 days  

**Steps:**
1. Add `reviews_links` rendering block to `show.blade.php`; include in `$hasLinks` guard
2. Add `additional_details` to `AgentProfileController::PUBLIC_PROFILE_KEYS`; render in the About section of `show.blade.php`
3. Add `compatibility_preferences` to `PUBLIC_PROFILE_KEYS`; add a read-only "Working Style" section to `show.blade.php` rendering top-level values from each of the 7 sub-sections
4. Fix `reviews_links` `$hasLinks` exclusion bug

**Files:** `app/Http/Controllers/AgentProfileController.php`, `resources/views/agent-profile/show.blade.php`

---

### Build 2 — Ownership Refactor (Launch Prerequisite)

**Depends on:** Nothing  
**Blocks:** Launch  
**Estimated scope:** 2–3 days  

**Steps:**
1. Add `mapCompatibilityFromProfile()` call to `HireAgentDirectController::commitListingAndBid()` — compatibility preferences must be stamped on Hire Me auto-bids (Critical C1)
2. Add `avg_response_time`, `availability_status`, `evenings_available`, `weekends_available` to `AgentBidMapperService::mapFromProfile()` (High H6)
3. Add `retained_deposits` application to Buyer, Landlord, Tenant bid `mount()` methods (High H4)
4. Write migration to unify `referral_percentage` (listing) → `referral_fee_percent` (bid) (High H5)
5. Remove or gate `/test` bot-bidder route behind IP allowlist + secret header (Critical C2)
6. Fix `autobid_maximum_price = "null"` to PHP `null` (High H7)

**Files:** `app/Http/Controllers/HireAgentDirectController.php`, `app/Services/AgentBidMapperService.php`, `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`, `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`, `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`, `routes/web.php`, `database/migrations/`

---

### Build 3 — Preset-Driven Bids (Launch Prerequisite)

**Depends on:** Build 2  
**Blocks:** Launch  
**Estimated scope:** 1–2 days  

**Steps:**
1. Add `BIDDING_PERIOD_ENABLED=false` config and feature flag; disable `auction_type` selection UI; hard-code `auction_type = 'Traditional'` on listing creation when flag is off (Critical C3)
2. Verify `filterServicesToCurrentCatalog()` is called on preset-load for all four bid components
3. Smoke-test preset → bid auto-population for all four roles and common property types

**Files:** `config/app.php` or `.env`, four listing Livewire form components, four bid Livewire components

---

### Build 4 — Matching Expansion (Post-Launch Phase 2)

**Depends on:** Builds 1–3 (launch must be stable first)  
**Estimated scope:** 2–3 weeks  

**Steps:**
1. Add property type exact-match scoring to all four `*BidMatchScoreHelper` classes
2. Add service type (Full/Limited) scoring
3. Normalize bedroom/bathroom strings ("3+" → integer floor); add to scoring
4. Add set-intersection scoring for `property_items` JSON arrays
5. Add lease length preference matching (Landlord + Tenant)
6. Add pet policy matching (Landlord)
7. Wire `cities_served`/`counties_served` from preset into scoring engine against listing location
8. Update match formula weights to reflect new dimensions

**Files:** `app/Helpers/*BidMatchScoreHelper.php`, `app/Services/AgentBidMapperService.php`

---

### Build 5 — Launch Cleanup (Post-Launch, P2 Gaps)

**Depends on:** Launch  
**Estimated scope:** 1–2 weeks  

**Steps:**
1. Resolve `bio` storage split: sync `DashboardController::saveSettings()` to also write `profile_data` of agent's primary preset
2. Resolve `preferred_contact_method` storage split (same approach)
3. Add uploaded-file fallback resolution to `AgentProfileController::show()` for `presentation_upload_path` and `business_card_upload_path`
4. Add `phone` to `PUBLIC_PROFILE_KEYS`; render as `tel:` link on profile
5. Surface `best_time_to_contact` from `user_meta` in profile Availability section
6. Audit and redirect all `user_meta` reads of `avg_response_time`, `communication_style`, `preferred_contact_method`, etc. to `profile_data` via `AgentDefaultProfile::findForAgentWithFallback()`
7. Add "Summary" notice to compensation section label on profile
8. Deprecate and schedule removal migration for legacy native bid columns on Seller/Buyer bid tables

**Files:** `app/Http/Controllers/DashboardController.php`, `AgentProfileController.php`, `show.blade.php`, `app/Models/` (Eloquent models for Seller/Buyer bids)

---

### Phase 2 — Tenant Bidding Period (Post-Launch, ~4–8 weeks)

**Pre-conditions (all must be met):**
- Active scheduled task for listing auto-expiration (`Kernel.php` schedule entry, command class exists and is tested)
- All bid-related notifications implement `ShouldQueue`
- County-blast-on-listing-creation is audited and capped (max N recipients)
- `BiddingPeriodExpiredNotification` implemented and tested
- Feature flag `BIDDING_PERIOD_ENABLED=true` tested in staging before production

**Scope:** Tenant role only. All existing `CompetingBidsController`, `CompetingBidsService`, `BiddingPeriodAgentMapping`, `TenantBidMatchScoreHelper` infrastructure ships as-is.

---

### Phase 3 — Remaining Bidding Period Roles (Post-Launch, ~12+ weeks)

**Pre-conditions per role:**
- Backend bid-submission guard server-side (Buyer and Seller)
- `BiddingPeriodAgentMapping` creation and lookup per role
- Role-aware paths in `CompetingBidsController` and `CompetingBidsService`
- `auction_ended` column migration for Buyer and Seller auction tables
- Landlord `auction_length` vs `auction_time` format normalization

**Scope:** Buyer, Seller, Landlord roles. Roll out one role at a time; each requires the pre-conditions above independently.

---

### Phase 4 — Auto-Submit (Post-Launch, ~24+ weeks — after all five gates exist)

**Pre-conditions (all must be met before enabling any autonomous bid placement):**
1. Match score gate (read `overall_score` from `listing_compatibility_scores`; minimum threshold configurable per auction type)
2. Radius/geography constraint (agent `cities_served` intersects listing location)
3. Property type gate (agent preset scoped to listing's property type)
4. Active listing status gate (`is_approved = true`, not expired, not draft)
5. Subscription/payment status gate (agent has active subscription on file)
6. Rate limiting on auto-bid insertion routes
7. Deduplication guard (unique constraint or advisory lock per listing + agent per time window)
8. Queue-based execution (`ShouldQueue` with `$tries` and `$timeout`)
9. Audit log (`auto_bid_log` table or reliable `source` column on bids)

**Scope:** Define one auction type to pilot (recommend Tenant, as it has the most complete infrastructure) before expanding.

---

## 11. Final Architecture Diagram

```
═══════════════════════════════════════════════════════════════════════════════
                     BIDYOURAGENT — END-TO-END DATA FLOW
═══════════════════════════════════════════════════════════════════════════════

  ┌─────────────────────────────────────────────────────────────────────────┐
  │  AGENT SETUP LAYER                                                      │
  │                                                                         │
  │  users + user_meta ──────────── Permanent identity (name, email,        │
  │       [PROFILE]                 license, brokerage, avatar)             │
  │            │                                                            │
  │            ▼                                                            │
  │  agent_default_profiles ──────── Per (role × property_type) defaults    │
  │       [PRESET]                  Services, compensation, bio, compat.    │
  │       profile_data JSONB        ~157 keys; save-scope propagation       │
  │            │                                                            │
  │            ├──── Public profile (/agent/{shortId})                      │
  │            │         [PUBLIC_PROFILE_KEYS whitelist applied]            │
  │            │         P1 gaps: reviews_links, additional_details,        │
  │            │         compatibility_preferences must be added            │
  │            │                                                            │
  │            └──── Hire Me URL (/hire/{shortId}/{role}/{propertyType})    │
  │                      Public, auth-free, shareable                       │
  └─────────────────────────────────────────────────────────────────────────┘
                              │
                              │  Two entry paths
                              ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │  LISTING CREATION LAYER                                                 │
  │                                                                         │
  │  Path A: Client creates listing via Offer Listing form                  │
  │  ─────────────────────────────────────────────────                      │
  │  {role}_agent_auctions + {role}_agent_auction_metas                     │
  │       [LISTING]                                                         │
  │       workflow_type = 'offer_listing'                                   │
  │       service_type = full_service | limited_service                     │
  │       auction_type = Traditional (at launch; Bidding Period deferred)   │
  │       Client contact + property preferences (EAV meta)                  │
  │       Seller/Buyer: also stores requested compensation terms            │
  │                                                                         │
  │  Path B: Client hires via Hire Me flow                                  │
  │  ─────────────────────────────────────────────────                      │
  │  HireAgentDirectController::commitListingAndBid()                       │
  │       Creates listing (workflow_type = 'hire_agent')                    │
  │       AND auto-bid in one atomic operation                              │
  │       Preset → AgentBidMapperService::findAndMap() → bid fields         │
  │       hire_me_auto_bid = 1 stamped on bid                              │
  └─────────────────────────────────────────────────────────────────────────┘
                              │
                              │  Path A only → agents discover listing
                              ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │  MATCHING ENGINE LAYER                                                  │
  │                                                                         │
  │  *BidMatchScoreHelper (4 role-specific helpers)                         │
  │       Services Score (50%) + Terms Score (50%)                          │
  │       LOGICAL_FIELD_GROUPS: ~16–17 logical decisions per role           │
  │       Coverage: ~30–35% of collected fields (Phase 1 baseline)          │
  │                                                                         │
  │  Phase 2 adds: property type, bedroom/bathroom, features, lease length  │
  │  Phase 3 adds: location/geography, budget, financing, compatibility      │
  │                                                                         │
  │  Output: 0–100% match score displayed on bid detail to listing owner    │
  └─────────────────────────────────────────────────────────────────────────┘
                              │
                              │  Agent submits bid
                              ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │  BID LAYER                                                              │
  │                                                                         │
  │  {role}_agent_auction_bids + {role}_agent_auction_bid_metas             │
  │       [BID — CANONICAL AT ENGAGEMENT TIME]                              │
  │                                                                         │
  │  mount():                                                               │
  │       AgentBidMapperService::findAndMap() → pre-fills ~135 fields       │
  │       mapCompatibilityFromProfile() → pre-fills 7 compat sections       │
  │       Listing's requested terms pre-fill compensation (Seller/Buyer)     │
  │  Agent edits any pre-filled field before submitting                      │
  │  submit(): all field values snapshot onto bid EAV meta                  │
  │                                                                         │
  │  Compensation on bid = AUTHORITATIVE (read this, not listing copy)       │
  │  Services on bid = agent's commitment (may differ from listing request)  │
  │  Compatibility on bid = snapshot of preset compatibility at submit time  │
  └─────────────────────────────────────────────────────────────────────────┘
                              │
                              │  Listing owner reviews bids
                              ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │  NEGOTIATION LAYER                                                      │
  │                                                                         │
  │  Accept → creates AcceptedBidSummary (derived from bid, not listing)    │
  │  Reject → bid status = rejected; agent notified                         │
  │  Counter → {role}CounterTerm / {role}CounterBidding record created      │
  │            Agent notified; agent can accept, reject, or counter-back     │
  │                                                                         │
  │  All notifications fire synchronously (pre-launch state)                │
  │  Phase 2: ShouldQueue required before Bidding Period is enabled         │
  └─────────────────────────────────────────────────────────────────────────┘
                              │
                              │  On Accept
                              ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │  AGENT RELATIONSHIP LAYER                                               │
  │                                                                         │
  │  accepted_bid_summaries ──────── Snapshot of accepted bid terms         │
  │       [ACCEPTED SUMMARY]        PDF generated via barryvdh/laravel-dompdf│
  │       Derived from: Bid (compensation), Listing (client identity,       │
  │                     services requested), Agent (credentials at submit)   │
  │                                                                         │
  │  listing_status → 'Hired Agent'                                         │
  │  Referral tracking: referring_agent_id + referral_locked on listing     │
  │  Agent dashboard: /agent/hire-listings + /agent/my-referrals            │
  │  Client dashboard: My Listings by Role + Your Hired Agent section       │
  └─────────────────────────────────────────────────────────────────────────┘

═══════════════════════════════════════════════════════════════════════════════
  OWNERSHIP BOUNDARIES:
  ─────────────────────────────────────────────────────────────────────────
  Profile    → permanent identity; never changes based on role
  Preset     → role-scoped defaults; agent's offer template
  Listing    → client's request; owns requested services & compensation
  Bid        → agent's proposal; CANONICAL compensation & services at time
  Accepted   → immutable snapshot; derived from Bid
═══════════════════════════════════════════════════════════════════════════════
```

---

## 12. Final Verdict

### What launches?

Traditional listings for all four roles (Seller, Buyer, Landlord, Tenant) with Full Service and Limited Service modes. Preset-driven bid creation where the agent's preset auto-populates their bid form for any listing. The Hire Me flow where clients hire agents directly, creating both a listing and a bid in one step. Accept / Reject / Counter negotiation workflows. Match score display (Services + Terms, 50/50). Agent public profiles with compatibility preferences visible. The Hire Me public URL and embeddable widget. The My Referrals hub and Agent Hire Listings Hub.

### What does not launch?

Bidding Period (Auction Timer) listings for any role. Autonomous or scheduled auto-bid submission. The legacy `/test` bot-bidder route (it must be removed before launch). Expanded matching beyond services and compensation terms. Competitive bid transparency (agents seeing anonymized peer bids). Scheduled listing auto-expiration.

### What must be fixed first?

**Five fixes are required before go-live (in priority order):**
1. Add `mapCompatibilityFromProfile()` to the Hire Me auto-bid creation path (Critical C1)
2. Remove or gate the unauthenticated `/test` bot-bidder route (Critical C2)
3. Add `BIDDING_PERIOD_ENABLED=false` flag and disable Bidding Period mode in listing creation forms (Critical C3)
4. Fix three public profile rendering gaps: `reviews_links` never rendered, `additional_details` not whitelisted, `compatibility_preferences` entire section invisible (High H1–H3)
5. Add four availability fields to `AgentBidMapperService::mapFromProfile()` and fix `retained_deposits` application to all four roles (High H4, H6)

### What becomes the long-term moat?

Two compounding advantages:

**Matching fidelity.** The current 30–35% field coverage is a starting baseline, not a ceiling. Every property preference, location signal, timeline field, and personality fit dimension collected today is ready to be scored in Phase 2 and Phase 3. No competitor captures this depth of structured data at intake. As the matching engine expands, early adopters get an increasingly accurate feed of agents who genuinely fit their needs — not just agents who exist in the county.

**Preset-driven transparency.** Agents who fill out complete presets expose their full compensation terms, working style, and service offering before any listing arrives. Clients can evaluate agents on terms, not just on name recognition. The compatibility preferences section — once visible on the public profile — turns the hire decision from a leap of faith into a structured comparison. This is the platform's core differentiation.

### What is the recommended roadmap for the next 6–12 months?

| Timeline | Milestone |
|----------|-----------|
| **Now → Launch** | Complete Builds 1–3 (Profile Parity, Ownership Refactor, Feature Flag); ship Traditional for all four roles |
| **Weeks 2–4 post-launch** | Build 5 Launch Cleanup — resolve bio split, phone visibility, uploaded-file fallback on profile |
| **Weeks 4–8 post-launch** | Build 4 Matching Expansion Phase 2 — add property type, bedrooms, features, lease length, pet policy to match engine |
| **Weeks 6–10 post-launch** | Bidding Period Phase 2 preconditions — scheduled expiration, queued notifications, county blast cap, expiration notification |
| **~Week 10–12 post-launch** | Enable Bidding Period for Tenant role (feature flag) |
| **Months 3–6** | Location DNA matching (Phase 3); agent-mirror fields for budget, financing, geographic scoring |
| **Months 4–8** | Bidding Period Phase 3 — backend guards, anonymization, competing views for Buyer, Seller, Landlord |
| **Months 6–12** | Auto-Submit Phase 4 — build all five gates; pilot on one role with full monitoring before expanding |

The platform has a strong foundation. The preset system, the four-role bid infrastructure, the Hire Me flow, the match score helpers, and the counter-bid workflows are all production-ready. The roadmap above builds the competitive moat on top of that foundation in a staged, risk-managed sequence.
