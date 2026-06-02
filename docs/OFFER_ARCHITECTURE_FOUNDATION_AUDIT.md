# Offer Architecture Foundation Audit

**Date:** 2026-06-02
**Phase:** 0 → 1A pre-work
**Status:** Authoritative — must be read before any Phase 1A migration, model, or service is written

---

## Purpose

This document answers every foundational architecture question that must be resolved before any Phase 1A code is written. It is the single source of truth for Phase 1A table design, state machine semantics, event log shape, snapshot strategy, counter-chain accommodation, and gating. No implementation may deviate from these decisions without an explicit revision to this document.

---

## Critical Conceptual Clarification — Listing Side vs. Submission Side

The most important boundary to hold throughout all phases:

| Layer | What it is | Tables (already exist) |
|-------|-----------|------------------------|
| **Listing side** | Where a seller or landlord posts an offer listing (their property, their preferred terms) | `offer_auctions`, `offer_auction_metas` |
| **Submission side** | Where a buyer or tenant submits a formal purchase or lease offer on a posted listing | **Does not exist yet — Phase 1A creates this** |

`offer_auctions` is a listing-side table. It is **not** an offer submitted by a buyer or tenant. Phase 1A creates the submission-side tables only. The listing-side tables are never modified by the Offer System.

---

## Governance Constraints (Summary)

All recommendations below are governed by:

- `docs/OFFER_SYSTEM_GOVERNANCE.md` — legal/compliance guardrails, fair housing constraints, AI boundaries
- `docs/OFFER_SYSTEM_BUILD_ORDER.md` — strict phase sequencing; no UI before Phase 1A tables exist; no AI before Phase 4
- `docs/OFFER_SYSTEM_DO_NOT_TOUCH.md` — protected tables, EAV keys, hiring flows, referral logic, accepted bid summaries; all migrations must be additive-only

Any recommendation in this document that appears to conflict with those three documents must be resolved in favor of those documents, not this audit.

---

## Schema Survey — Observed Platform Patterns

Before answering the 13 questions, the key patterns observed across the existing schema:

### Native-Column-Heavy Tables (Seller/Buyer Roles)
`seller_agent_auctions` and `buyer_agent_auctions` carry many domain-specific native columns. Their corresponding bid tables (`seller_agent_auction_bids`, `buyer_agent_auction_bids`) also carry many native columns. Meta tables were added later, additively, as the platform needed to extend them.

### EAV-Heavy Tables (Landlord/Tenant Roles)
`landlord_agent_auctions` and `tenant_agent_auctions` carry only a thin shell of native columns (`user_id`, `auction_type`, `is_approved`, `is_draft`, `is_sold`, `sold_date`, `timestamps`). All domain-specific data is stored via `landlord_agent_auction_metas` and `tenant_agent_auction_metas`. Their bid tables mirror this: `landlord_agent_auction_bids` and `tenant_agent_auction_bids` are thin shells; all bid detail goes through `landlord_agent_auction_bid_metas` and `tenant_agent_auction_bid_metas`.

### The Offer Auction (Listing Side) Is Already Unified
`offer_auctions` is a single, unified listing table. It is not split by role. All property-type and role-specific listing data is captured via `offer_auction_metas`. This is the most recent addition to the platform and intentionally chose unified + EAV over role-specific tables.

### Accepted Bid Summaries Are Unified
`accepted_bid_summaries` is a single table that serves all bid types across all roles. It stores a denormalized `summary_html` snapshot, a PDF path, and signature fields. It is not split by role.

### Counter-Chain Pattern
Counter terms follow a self-referencing `parent_counter_id` pattern (`seller_counter_terms`, `tenant_counter_terms`, `landlord_counter_terms`, `buyer_counter_bidding`) — a new row is inserted per counter, pointing to its parent. Fields live in paired `*_metas` tables. This is the established platform pattern for chained negotiations.

---

## Question 1 — One Unified Offers Table or Four Role-Specific Tables?

**Recommendation: One unified `offers` table.**

**Reasoning:**

1. The listing side (`offer_auctions`) is already unified. The submission side should mirror it for consistency. Splitting the submission side into four tables while the listing side is unified creates asymmetric coupling.

2. `accepted_bid_summaries` is unified across all bid types and roles — this is the most direct precedent for the submission-side record layer.

3. Purchase offers (buyer → seller) and lease offers (tenant → landlord) share the same structural envelope: who submitted, which listing, what status, timestamps, snapshot, event log reference. Role-specific terms (purchase price, financing type for purchase vs. monthly rent, lease length for lease) belong in EAV meta keys, not separate tables.

4. Four separate tables would require four migrations, four models, four service dispatch branches, and four sets of relationships in Phase 1A — all before any UI is built. This is premature fragmentation.

5. A `role` discriminator column (`buyer` | `tenant`) on the unified `offers` table provides enough branching for Phase 1A and Phase 1B without schema proliferation.

6. Role-specific table splitting remains an additive option in later phases if data divergence demands it. Merging four tables back into one is much harder than splitting one into four.

---

## Question 2 — Shared `offer_metas` Table or Role-Specific Meta Tables?

**Recommendation: One shared `offer_metas` table.**

**Reasoning:**

1. The listing side uses `offer_auction_metas` — a single unified meta table. Submission-side metas should mirror this.

2. Meta key naming conventions (e.g., `purchase_price`, `financing_type`, `lease_term_months`, `monthly_rent`) provide natural namespacing without requiring table-level separation.

3. Splitting into four meta tables in Phase 1A adds migration surface area and forces decisions about which keys belong to which role before Phase 1B has been designed.

4. The composite unique index `(offer_id, meta_key)` on the shared table prevents key collisions per offer record.

5. Role-specific meta tables are additive and remain an option in later phases.

---

## Question 3 — What Native Columns Are Required for the Base Offer Record?

The `offers` table must include the following native columns. Every column listed here is justified. No column should be added to the native schema that can be handled by `offer_metas`.

| Column | Type | Nullable | Purpose |
|--------|------|----------|---------|
| `id` | bigint unsigned, PK | no | Primary key |
| `offer_auction_id` | bigint unsigned, FK → `offer_auctions.id` | no | The listing being offered on |
| `user_id` | bigint unsigned, FK → `users.id` | no | The submitting buyer or tenant |
| `role` | varchar(20) | no | `buyer` or `tenant` — discriminates purchase vs. lease offer |
| `status` | varchar(30) | no | Current state machine state (see Q4) |
| `listing_snapshot` | JSON | yes | Immutable copy of listing terms at submission time (see Q7) |
| `parent_offer_id` | bigint unsigned, FK → `offers.id` | yes | NULL in Phase 1A; reserved for Phase 2 counter chain |
| `submitted_at` | timestamp | yes | When the offer transitioned from `draft` to `submitted`; NULL while in draft |
| `expires_at` | timestamp | yes | Offer expiration date/time if specified by the submitter |
| `created_at` | timestamp | no | Standard Laravel timestamps |
| `updated_at` | timestamp | no | Standard Laravel timestamps |

**Columns intentionally excluded from the native schema:**
- `accepted_at`, `rejected_at`, `withdrawn_at` — derivable from `offer_event_logs`; do not duplicate
- Price, terms, contingencies — go in `offer_metas`
- Role-specific agent info — go in `offer_metas`

---

## Question 4 — What Should the Offer State Machine Be?

**States:**

| State | Description |
|-------|-------------|
| `draft` | The offer is being composed by the submitter but has not been sent. Only the submitter can see it. |
| `submitted` | The offer has been formally submitted and is visible to the listing owner. |
| `accepted` | The listing owner has accepted this offer. Terminal state. |
| `rejected` | The listing owner has explicitly rejected this offer. Terminal state. |
| `withdrawn` | The submitter has withdrawn the offer before a decision was made. Terminal state. |
| `countered` | (Phase 2 stub) A counter-offer has been issued. The original offer is in a pending/counter state. Do not implement transition logic in Phase 1A. |

**Phase 1A must define and store all six state values but only implement transition logic for the first five: `draft`, `submitted`, `accepted`, `rejected`, `withdrawn`. The `countered` state is stubbed for forward compatibility.**

---

## Question 5 — What Transitions Are Allowed and Forbidden?

### Allowed Transitions

| From | To | Who May Trigger | Phase |
|------|----|-----------------|-------|
| `draft` | `submitted` | Submitter (buyer/tenant) | Phase 1A |
| `submitted` | `accepted` | Listing owner (seller/landlord) | Phase 1A |
| `submitted` | `rejected` | Listing owner (seller/landlord) | Phase 1A |
| `submitted` | `withdrawn` | Submitter (buyer/tenant) | Phase 1A |
| `submitted` | `countered` | Listing owner (seller/landlord) | Phase 2 only |
| `countered` | `accepted` | Counter recipient (submitter) | Phase 2 only |
| `countered` | `rejected` | Counter recipient (submitter) | Phase 2 only |
| `countered` | `withdrawn` | Either party | Phase 2 only |

### Forbidden Transitions

| Attempt | Reason |
|---------|--------|
| `accepted` → any | Terminal state; accepted offers are immutable |
| `rejected` → any | Terminal state |
| `withdrawn` → any | Terminal state |
| `draft` → `accepted` | Must pass through `submitted` |
| `draft` → `rejected` | Must pass through `submitted` |
| `draft` → `withdrawn` | Must pass through `submitted`; a draft can simply be deleted |
| `draft` → `countered` | Cannot counter an unsubmitted offer |
| Any skip across states | The state machine is strictly sequential |

**The `OfferStateService` (see Q12) must enforce these rules and throw a named exception on any forbidden transition attempt.**

---

## Question 6 — What Should the Immutable Offer Event Log Store?

### Table: `offer_event_logs`

| Column | Type | Nullable | Purpose |
|--------|------|----------|---------|
| `id` | bigint unsigned, PK | no | Primary key |
| `offer_id` | bigint unsigned, FK → `offers.id` | no | The offer this event belongs to |
| `actor_id` | bigint unsigned, FK → `users.id` | yes | The user who triggered the event (null for system events) |
| `actor_role` | varchar(30) | yes | `submitter`, `listing_owner`, `system` |
| `event_type` | varchar(50) | no | Semantic name: `offer_created`, `offer_submitted`, `offer_accepted`, `offer_rejected`, `offer_withdrawn`, `offer_countered`, `status_changed` |
| `from_status` | varchar(30) | yes | State before the transition; null for creation events |
| `to_status` | varchar(30) | yes | State after the transition; null for non-state events |
| `metadata` | JSON | yes | Additional context: rejection reason, system notes, counter offer ID (Phase 2) |
| `ip_address` | varchar(45) | yes | Submitting IP for audit trail |
| `created_at` | timestamp | no | When the event occurred — set once, never updated |

### Immutability Rules

- **No `updated_at` column.** Event log rows are never modified after insertion.
- **No UPDATE or DELETE operations** may target `offer_event_logs` from application code. The `OfferStateService` must enforce this by only ever calling `insert()` on this table, never `update()` or `delete()`.
- Every call to `OfferStateService` that performs a state transition must write an event log row in the same database transaction as the status update on the `offers` table. If the event log write fails, the transition rolls back.
- The log is the authoritative audit trail. `accepted_at`, `rejected_at`, and `withdrawn_at` timestamps are always derived from this log, never stored as separate columns.

---

## Question 7 — What Listing Snapshot Data Must Be Captured at Offer Submission?

The `listing_snapshot` JSON column on the `offers` table captures an immutable point-in-time copy of the listing's key terms at the moment the offer transitions from `draft` to `submitted`. Its purpose is to preserve what the submitter saw when they made the offer, even if the listing is later edited, expired, or withdrawn.

### Minimum required snapshot fields:

```json
{
  "snapshot_at": "ISO 8601 timestamp",
  "listing_id": "offer_auctions.id",
  "listing_title": "offer_auctions.title",
  "listing_role": "seller | landlord (from EAV meta)",
  "property_address": "from EAV meta",
  "property_type": "from EAV meta",
  "listing_status": "from EAV meta at time of snapshot",
  "preferred_terms": {
    "note": "All preferred-term EAV keys captured as a flat key-value map"
  }
}
```

### Rules:
- `listing_snapshot` is written once, at submission time, by `OfferSnapshotService` (see Q12).
- It is never updated after the offer is submitted.
- Phase 1B defines exactly which EAV meta keys to capture as `preferred_terms`. Phase 1A must only reserve the JSON column and the snapshot service stub.
- The snapshot must NOT be used as a substitute for the live listing record. The `offer_auction_id` FK always points to the live listing. The snapshot is a read-only archive.

---

## Question 8 — How Should the Offer System Relate to Existing Listing EAV Records Without Modifying Them?

The relationship is **read-only and FK-only** from the Offer System's perspective:

1. **The `offers` table carries `offer_auction_id`** as a foreign key. This is the only structural link between the submission side and the listing side.

2. **The Offer System never writes to `offer_auctions` or `offer_auction_metas`.** It reads from them (Phase 1B pre-fill logic) but never modifies them. This rule has no exceptions.

3. **EAV reads are point-in-time.** Phase 1B pre-fill reads the current `offer_auction_metas` records to populate the offer form. At submission, `OfferSnapshotService` captures the relevant keys into `listing_snapshot`. After submission, the offer has no runtime dependency on live EAV records.

4. **No foreign keys point from `offer_metas` to `offer_auction_metas`.** There is no join between submission-side meta and listing-side meta. The only cross-side relationship is `offers.offer_auction_id → offer_auctions.id`.

5. **No modification of existing EAV meta key definitions.** New offer-specific meta keys live in `offer_metas` only. The protected meta tables listed in `OFFER_SYSTEM_DO_NOT_TOUCH.md` are never touched.

---

## Question 9 — How Should Future Counter Chains (Phase 2) Be Supported Without Building Them Now?

**One schema accommodation: the `parent_offer_id` self-referencing nullable FK on the `offers` table.**

This column is added in Phase 1A but is always `NULL` during Phase 1A operation. Phase 2 will populate it when a counter-offer row is inserted.

The Phase 2 counter engine will:
1. Read the current `submitted` offer as the "parent."
2. Transition the parent offer's status to `countered` (a Phase 2 transition).
3. Insert a new `offers` row with `parent_offer_id` pointing to the parent.
4. The new row starts in `submitted` status representing the counter-offer.

This pattern is consistent with the existing platform counter pattern (`parent_counter_id` on `seller_counter_terms`, `tenant_counter_terms`, etc.).

**Nothing else is added for Phase 2 in Phase 1A.** No counter-specific columns, no counter UI, no counter routing, no counter state transition logic. The `countered` status value is defined in the state machine enum but no transition INTO it is implemented or callable in Phase 1A.

---

## Question 10 — How Should Offer Access Respect the BidYourAgent → BidYourOffer Gated Workflow?

**The gating condition:** A user may only submit a formal offer on an `offer_auctions` listing if they have an active agent-hire relationship attached to that listing. This is the BidYourAgent → BidYourOffer gate.

### Implementation Approach

1. **A named Laravel Gate** — `submit-offer` — is registered in Phase 1A. It checks whether the authenticated user has a qualifying agent-hire relationship for the target `offer_auction_id`.

2. **Use `DB::table()`, not Eloquent**, per the platform's established pattern. Eloquent eager-load (`$with`) can abort PostgreSQL transactions on any query error, poisoning subsequent writes even inside try/catch. Raw `DB::table()` calls are safe in Gate callbacks.

3. **Schema::hasTable() guard**: Before querying any optional tables, confirm the table exists (consistent with how the platform handles `tenant_criteria_auctions` and similar tables that may be absent in some environments).

4. **The gate must not modify any hiring flow data**, referral records, or listing records. It is read-only.

5. **Feature flag stub**: Phase 1A includes a config entry (`offer_system.gating_enabled`) defaulting to `false` during development. When `false`, the gate passes all authenticated users. When `true`, full BidYourAgent relationship check is enforced. This allows Phase 1A tables and models to be tested without requiring a complete hiring flow setup. Before Phase 1B ships to production, `gating_enabled` must be set to `true`.

6. **If the gate fails**, the user is not shown an error that reveals the offer system's existence. They are redirected to the BidYourAgent hiring flow for that listing type. No offer-side route is exposed until Phase 1B is complete.

---

## Question 11 — What Migrations Will Eventually Be Needed? (Names Only)

Listed in creation order. No migration may be created before Phase 1A is formally started.

| Migration name | Purpose |
|----------------|---------|
| `create_offers_table` | Base submission-side offer records |
| `create_offer_metas_table` | EAV key-value store for role-specific offer fields |
| `create_offer_event_logs_table` | Immutable append-only audit log for all offer events |

No other migrations belong to Phase 1A. All Phase 1B, 2, 3, 4, 5, 6, and 7 migrations are listed in their respective phase plans.

---

## Question 12 — What Models and Services Will Eventually Be Needed? (Names Only)

### Eloquent Models (Phase 1A)

| Class name | Responsibility |
|-----------|----------------|
| `Offer` | Represents a submitted or draft offer; relationships to `OfferMeta`, `OfferEventLog`, `OfferAuction`, `User` |
| `OfferMeta` | EAV key-value pair belonging to an `Offer` |
| `OfferEventLog` | Immutable event log entry; belongs to `Offer` |

### Service Classes (Phase 1A)

| Class name | Responsibility |
|-----------|----------------|
| `OfferStateService` | Validates and executes state machine transitions; writes event log rows; rolls back on failure |
| `OfferSnapshotService` | Reads `offer_auction_metas` at submission time and produces the `listing_snapshot` JSON blob |

### Gate / Middleware (Phase 1A)

| Name | Responsibility |
|------|----------------|
| `OfferAccessGate` | Enforces BidYourAgent → BidYourOffer gating; uses `DB::table()`, respects `offer_system.gating_enabled` config |

No Livewire components, Blade views, controllers, or routes belong to Phase 1A.

---

## Question 13 — What Should Explicitly Remain Out of Scope for Phase 1A?

The following are forbidden in Phase 1A. Any pull request that introduces any item from this list must be rejected and re-scoped.

### UI — Phase 1B or later
- Livewire offer submission wizard or form components
- Blade views for offer creation, offer status, or offer detail
- Routes for offer submission, offer viewing, or offer management
- Any buyer-facing or tenant-facing offer UI

### Business Logic — Later Phases
- Counter-offer creation, review, or acceptance (Phase 2)
- Counter-offer state transition logic, even though the `countered` status value is defined (Phase 2)
- Offer comparison view or grid (Phase 3)
- Accepted Offer Summary generation and persistence (Phase 4)
- PDF export of offer summaries (Phase 5)
- E-sign integration (Phase 6)
- AI analysis, explanation, or comparison of offers (Phase 7)

### Platform Systems — Never Modified by Offer System
- `offer_auctions` table or `offer_auction_metas` table (listing side — read-only)
- Any existing listing creation form (seller, buyer, landlord, or tenant offer listing)
- `accepted_bid_summaries`, `AcceptedBidSummary` model, PDF cache invalidation, or `x-bid-detail-layout` component
- BidYourAgent hiring flows: Hire Me URLs, widget, `AgentBidMapperService`, `AgentDefaultProfile`, Agent Hire Listings Hub
- Referral tracking: `referral_visits`, My Referrals page, referral percentage fields
- Property DNA, Buyer/Tenant DNA, Location DNA, or any existing OpenAI prompt contract
- Any existing EAV meta key in any protected meta table

### Testing Shortcuts
- Mocking the gate in a way that allows offers to be submitted without passing the gating check in production-mode tests
- Using the `countered` status as a test convenience before Phase 2 transition logic exists

---

## Recommended Phase 1A Architecture

### Table Strategy: Unified submission-side tables

Create three new tables only: `offers`, `offer_metas`, `offer_event_logs`. Do not create role-specific variants. The listing side is already unified; the submission side must match.

### Meta Strategy: Shared `offer_metas` with `(offer_id, meta_key)` unique index

All role-specific offer fields (purchase price, financing, lease term, monthly rent, contingencies, closing date, etc.) are stored as EAV key-value pairs in `offer_metas`. Meta keys follow the same naming conventions used by `offer_auction_metas`. No structured columns for these fields on the `offers` table.

### Snapshot Strategy: JSON column on `offers`, written once at submission

`listing_snapshot` is a JSON column on the `offers` table. `OfferSnapshotService` populates it by reading the live `offer_auction_metas` records at the moment of `draft → submitted` transition. The snapshot is immutable from that point. Phase 1B defines the exact EAV keys to capture. Phase 1A reserves the column and the service stub.

### Event Log Strategy: Append-only, transactionally coupled

`offer_event_logs` has no `updated_at` column. Every state transition writes one row atomically with the status change on the `offers` table. The log is the authoritative audit trail for all timestamps (submitted_at, accepted_at, etc.) — these are not stored as separate columns on `offers` except for `submitted_at` (which is a query convenience column, always derived from and consistent with the log).

### State Machine Strategy: Six states, five live transitions in Phase 1A

`draft`, `submitted`, `accepted`, `rejected`, `withdrawn` are fully implemented in Phase 1A. `countered` is defined as a valid enum value but no transition INTO it is implemented. The `OfferStateService` enforces all allowed/forbidden rules from Q5 and throws a typed `OfferTransitionException` on any forbidden attempt.

### Safest Migration Approach

1. Create the three Phase 1A migrations in a single pull request, ordered: `create_offers_table` → `create_offer_metas_table` → `create_offer_event_logs_table`.
2. All three are additive-only. No existing table is altered.
3. `offers.parent_offer_id` self-references the same table; ensure the FK is deferred or created after the table exists (add it as a second `Schema::table('offers', ...)` call within the same migration or a separate migration immediately after, depending on PostgreSQL FK deferral handling in the environment).
4. Run `php artisan migrate --pretend` on the three migrations before executing to confirm no destructive operations are generated.
5. All three `down()` methods use `Schema::dropIfExists()` in reverse order: `offer_event_logs` → `offer_metas` → `offers`.
6. No seeder, factory, or test fixture modifies `offer_auctions` or any existing table.

---

## Consistency Checklist

Before any Phase 1A code is written, verify each item:

- [ ] `docs/OFFER_SYSTEM_GOVERNANCE.md` has been read in full
- [ ] `docs/OFFER_SYSTEM_BUILD_ORDER.md` has been read in full
- [ ] `docs/OFFER_SYSTEM_DO_NOT_TOUCH.md` has been read in full
- [ ] This document has been read in full
- [ ] No migration touches `offer_auctions`, `offer_auction_metas`, or any existing table
- [ ] No Livewire component, Blade view, controller, or route is created
- [ ] The `OfferStateService` uses a typed exception class for forbidden transitions
- [ ] The `OfferAccessGate` uses `DB::table()`, not Eloquent, for all database queries
- [ ] The `OfferSnapshotService` only reads from `offer_auction_metas`; it never writes to it
- [ ] The `parent_offer_id` self-FK is NULL for all Phase 1A records; no counter logic is present
- [ ] `offer_system.gating_enabled` defaults to `false` in development config
- [ ] Unit tests exist for all five Phase 1A state transitions and all forbidden transition attempts
