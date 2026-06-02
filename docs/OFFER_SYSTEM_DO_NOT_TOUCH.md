# Offer System — Do Not Touch

This document lists all protected areas of the codebase that must not be modified during any phase of the Offer System build. These protections exist to prevent regressions in production-critical workflows and to maintain a clean separation between the Offer System and the existing platform.

Any task or pull request that touches a protected area must obtain explicit approval and document the reason before proceeding.

---

## 1. Existing Listing Creation Forms

**Do not alter** any of the following listing creation or edit forms:

- Seller Offer Listing
- Buyer Offer Listing
- Landlord Offer Listing
- Tenant Offer Listing

This includes all associated Livewire components, Blade templates, JavaScript (including `initializeLimitedService()` — see `replit.md` Legacy Code section), and validation logic.

The Offer System reads terms from these listings as **read-only source data**. It does not add fields to, restructure, or re-implement these forms.

---

## 2. No Parallel Listing Form System

Do not create a new, parallel listing creation system that duplicates fields already captured in the existing listing forms. The Offer System derives its pre-fill data from existing listing records — it does not introduce a second path to capturing listing terms.

---

## 3. Existing EAV Meta Keys

Do not remove, rename, or change the data type of any existing EAV meta key in any of the following meta tables:

- `seller_offer_listing_metas`
- `buyer_offer_listing_metas`
- `landlord_offer_listing_metas`
- `tenant_offer_listing_metas`
- `seller_agent_auction_metas`
- `buyer_agent_auction_metas`
- `landlord_agent_auction_metas`
- `tenant_agent_auction_metas`
- `seller_agent_auction_bid_metas`
- `buyer_agent_auction_bid_metas`
- `landlord_agent_auction_bid_metas`
- `tenant_agent_auction_bid_metas`
- Any other `*_metas` table in the schema.

New meta keys may be added by later phases when explicitly approved. Existing keys must remain intact.

---

## 4. Accepted Bid Summary Behavior

Do not modify any existing Accepted Bid Summary logic, models, views, or PDF generation until a later phase explicitly approves and scopes the change. This includes:

- `AcceptedBidSummary` model and its relationships.
- The shared `x-bid-detail-layout` Blade component.
- Any existing PDF cache invalidation logic tied to accepted bid summaries.
- Accepted bid summary view pages and download endpoints.

The Offer System will introduce its own Accepted Offer Summary layer (Phase 4) alongside — not in place of — the existing Accepted Bid Summary system.

---

## 5. BidYourAgent Hiring Flows

Do not change the "Hire Me / Hire This Agent" direct entry flows, including:

- The public Hire Me URL layer (`/hire/{agentShortId}/{role}/{propertyType?}`).
- The embeddable widget (`/widget/hire/{agentShortId}/{role}/{propertyType}`).
- The `AgentBidMapperService` auto-bid creation logic.
- The `AgentDefaultProfile` preset system and its UI (`/agent/presets`).
- The Agent Hire Listings Hub (`/agent/hire-listings`).

---

## 6. Referral Tracking

Do not change referral tracking logic, including:

- `referral_visits` table and its population logic.
- The `My Referrals` agent page (`/agent/my-referrals`) and its data queries.
- Any referral percentage field handling in agent bid forms or summaries.

---

## 7. Ask AI / Property DNA / Location DNA Systems

Do not modify any existing AI explanation, Property DNA, or Location DNA features, including:

- Property DNA generation, storage, or display logic.
- Buyer/Tenant DNA compatibility scoring.
- Location DNA phase implementations.
- Any existing OpenAI prompt contracts or response-parsing logic.
- The AI explanation layer for existing bid/listing fields.

Phase 7 of the Offer System introduces new, scoped AI capabilities. It does not alter existing AI systems.

---

## 8. Database Columns — Additive Only

Do not remove or rename any existing database column in any table. All migrations must be **additive only** (new tables, new columns, new indexes). If a later phase determines that a column change is necessary, it must:

1. Be scoped explicitly in the phase plan.
2. Use a backward-compatible migration strategy (e.g., add a new column, migrate data, deprecate the old column in a later phase).
3. Not drop or rename the old column until all dependent code has been updated and deployed.

---

## 9. No Destructive Migrations

No migration introduced by the Offer System may use any of the following operations on existing tables:

- `dropColumn` / `dropColumns`
- `renameColumn`
- `dropTable` / `drop`
- `change` on a column that narrows its type or removes nullability in a way that could reject existing data.

All migrations must be reversible and non-destructive.

---

## 10. General Rule — Add, Don't Replace

Existing platform workflows are presumed protected.

The Offer System adds new files, tables, models, routes, and logic. It does not replace or refactor existing platform features.

Modification of an existing workflow requires:
- Explicit task approval before any code is written.
- Documented justification explaining why the change cannot be achieved additively.
- A defined rollback strategy in case the change causes a regression.

When in doubt, add a new file or class rather than modifying an existing one.

---

## 11. BidYourAgent → BidYourOffer Gated Workflow

The Offer System must remain consistent with the BidYourAgent → BidYourOffer gated workflow architecture. Access to offer submission is unlocked after a client engages through the BidYourAgent hiring flow.

The Offer System must not alter:
- Existing listing creation architecture.
- Agent hiring flows or the conditions under which a listing becomes offer-eligible.
- Listing access controls or visibility rules.

Any change to the gating conditions between BidYourAgent and BidYourOffer requires explicit approval and must be documented before implementation.
