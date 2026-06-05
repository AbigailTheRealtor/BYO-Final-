# OFFER_CONTENT_AUDIT.md

**Audit Date:** 2026-06-05
**Scope:** `offers` table, `offer_metas` table, `offers.store`, `offers.show` view
**Status:** Audit only — no implementation changes made

---

## 1. Current Database Structure

### 1a. `offers` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `offer_auction_id` | FK → offer_auctions | The listing this offer is against |
| `user_id` | FK → users | The user who submitted the offer |
| `role` | string(20) | e.g. `buyer`, `tenant`, `agent` |
| `status` | string(30) default `draft` | `draft`, `submitted`, `countered`, `accepted`, `rejected`, `withdrawn`, `expired` |
| `listing_snapshot` | JSON nullable | Intended for a point-in-time copy of the listing at submission |
| `parent_offer_id` | nullable FK → offers | Links counter-offers to their parent |
| `submitted_at` | timestamp nullable | Set when status transitions to `submitted` |
| `expires_at` | timestamp nullable | Optional expiration for the offer itself |
| `created_at` / `updated_at` | timestamps | |

**Key observation:** There are **no offer term columns** on the `offers` table. No price, no financing type, no contingencies, no dates — only workflow metadata (status, timestamps, parent chain).

### 1b. `offer_metas` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `offer_id` | FK → offers | |
| `meta_key` | string(100) | |
| `meta_value` | text nullable | |
| `created_at` / `updated_at` | timestamps | |
| — | unique(`offer_id`, `meta_key`) | |

**Key observation:** The `offer_metas` table is a correctly structured EAV store — it mirrors the `offer_auction_metas` pattern used by the listing side. However, **zero meta keys are ever written to this table** anywhere in the current codebase. No controller, service, or Livewire component calls any equivalent of `saveMeta()` on an `Offer` model instance.

### 1c. `offer_event_logs` table

Tracks state machine transitions only: `actor_id`, `actor_role`, `event_type`, `from_status`, `to_status`, `metadata` (JSON), `ip_address`. Not a content store — not relevant to this audit.

---

## 2. What `offers.store` Currently Captures

`OfferController::store()` validates and persists only:

```
offer_auction_id   (required)
role               (required)
listing_snapshot   (optional array — never populated by any current form)
expires_at         (optional date)
```

The created `Offer` record always has `status = 'draft'`. No offer terms of any kind are captured, saved, or validated.

---

## 3. What the Offer Detail Page Currently Shows

`resources/views/offers/show.blade.php` renders three cards:

| Card | Fields Shown |
|---|---|
| **Offer Information** | Offer ID, Status badge, Parent Offer ID (if set), Created At, Submitted At |
| **Negotiation Timeline** | Chain table: Offer ID, Parent ID, Status, Created At, Submitted At, Event Count, Latest Event Type, Latest Event At |
| **Available Actions** | Submit, Accept, Reject, Withdraw, Counter (all permission-gated), with disabled state and reason text |

**There is no offer terms section.** Nothing from `offer_metas` is read or displayed. `listing_snapshot` is never rendered.

---

## 4. Were Offer Terms Intentionally Deferred?

**Finding: Never implemented — not intentionally deferred.**

Evidence:

1. The `offer_metas` table was created alongside `offers` and `offer_event_logs` in the same migration batch (`2026_06_02`), indicating it was always meant to hold term data. Its structure mirrors `offer_auction_metas` exactly.
2. The `OfferMeta` model, `OfferMetaFactory`, and the `OfferRepository::getOfferMeta()` method all exist — scaffolding written in anticipation of term data being stored there.
3. The `OfferFactory` has no term states defined (only `submitted`, `accepted`, `countered`) and `listing_snapshot` is always `null` — consistent with terms never having been scoped.
4. No architecture document, config file, or comment in the codebase describes which meta keys the `offer_metas` table should hold.
5. The `offers.show` view has a placeholder structure with no `@foreach($offer->metas ...)` block.

---

## 5. Architectural Distinction: Listing vs. Offer

This is the core gap. There are **two separate data layers** that both use the word "offer":

| Layer | Model | Storage | Purpose |
|---|---|---|---|
| **Offer Listing** | `OfferAuction` | `offer_auction_metas` (EAV) | What the seller/landlord publishes — the property being offered |
| **Negotiation Offer** | `Offer` | `offer_metas` (EAV, unused) | What the buyer/tenant submits in response — their terms |

The `OfferAuction` Livewire wizard (`app/Http/Livewire/OfferAuction.php`) fully captures listing terms and saves them to `offer_auction_metas`. The equivalent entry form for the `Offer` (buyer/tenant side) **does not exist**.

---

## 6. Fields Captured by the Listing Side (for Reference)

The `OfferAuction` Livewire component saves these meta keys into `offer_auction_metas`:

**Overview**
- `workflow_type`, `listing_title`, `listing_status`, `offer_type` (sale | rental | lease)
- `property_address`, `city`, `state`, `zip_code`, `property_type`
- `bedrooms`, `bathrooms`, `sqft`

**Financial Terms**
- `offer_price` (sale), `monthly_rent` (rental/lease), `security_deposit` (rental/lease), `lease_term_months` (lease)
- `earnest_deposit`, `financing_type`, `financing_contingency`, `financing_contingency_days`, `down_payment_percent`

**Contingencies & Dates**
- `inspection_contingency`, `inspection_contingency_days`
- `appraisal_contingency`
- `closing_date`, `possession_date`, `listing_expiration`

**Custom**
- `custom_terms`, `notes`

---

## 7. Missing: Offer-Side Term Fields

The buyer/tenant's counter-party negotiation terms are entirely absent. Based on the listing-side fields, real estate offer conventions, and the counter-offer architecture (which clones `listing_snapshot` and applies `$overrides`), the following fields need to be designed and implemented on the offer side:

### Required for all offer types
| Field | Notes |
|---|---|
| `offer_type` | Mirrors listing — must match (sale / rental / lease) |
| `expires_at` | Already on `offers` table natively |
| `custom_terms` | Free-text special conditions |
| `notes` | Internal buyer notes (private) |

### Sale offers
| Field | Notes |
|---|---|
| `offer_price` | Buyer's proposed purchase price |
| `earnest_deposit` | Earnest money amount |
| `financing_type` | cash / conventional / fha / va / other |
| `financing_contingency` | Boolean |
| `financing_contingency_days` | Days to secure financing |
| `down_payment_percent` | Percentage of purchase price |
| `inspection_contingency` | Boolean |
| `inspection_contingency_days` | Days for inspection period |
| `appraisal_contingency` | Boolean |
| `closing_date` | Proposed closing date |
| `possession_date` | Proposed possession date |

### Rental / Lease offers
| Field | Notes |
|---|---|
| `monthly_rent` | Tenant's proposed monthly rent |
| `security_deposit` | Proposed security deposit |
| `lease_term_months` | Proposed lease length (lease type only) |
| `move_in_date` | Proposed start date |

---

## 8. Current UI Behavior Summary

| Workflow Step | Status |
|---|---|
| Create Offer Listing (`OfferAuction` wizard) | **Working** — saves all listing terms to `offer_auction_metas` |
| Create Offer draft (`offers.store`) | **Working but empty** — creates the record, captures no terms |
| Redirect to offer detail page | **Working** — redirects to `/offers/{offer}` |
| Offer Detail page display | **Skeleton only** — shows ID, status, timeline, action buttons |
| Offer terms entry UI | **Does not exist** |
| Offer terms persistence | **Does not exist** — `offer_metas` is never written to |
| Offer terms display | **Does not exist** — no read from `offer_metas` anywhere |
| Counter-offer term propagation | **Structural only** — clones `listing_snapshot` (always null) and applies `$overrides` from request (never sent) |

---

## 9. Recommended Implementation Plan

### Phase A — Offer Terms Entry Form
Add an editable terms section to the Offer Detail page (or a dedicated `/offers/{offer}/edit` route). The form should:
- Render conditionally per `offer_type` (sale / rental / lease) derived from the linked `OfferAuction`
- Post to a new `offers.update` route that validates and saves terms to `offer_metas` using an `updateOrCreate` EAV pattern (same as `OfferAuction::saveMeta()`)
- Only be editable when `offer->status === 'draft'`
- Populate `listing_snapshot` on submission from the current `OfferAuction` metas

### Phase B — Offer Terms Display
Add a read-only "Offer Terms" card to `offers/show.blade.php` that:
- Loads `$offer->metas` keyed by `meta_key`
- Renders sale / rental / lease sections conditionally
- Is visible to both the submitter and the listing owner (once submitted)

### Phase C — Counter-Offer Term Propagation
Update `OfferController::counter()` and `OfferCounterService::counter()` to:
- Copy term metas from the parent offer to the child offer as a starting point
- Accept term overrides in the counter request (beyond just `expires_at` and `listing_snapshot`)

### Phase D — `listing_snapshot` Population
In `OfferSubmissionService::submit()`, before stamping `submitted_at`, populate `listing_snapshot` by reading the linked `OfferAuction`'s current metas and storing the JSON snapshot. This preserves listing terms at the moment of submission for audit/dispute purposes.

---

## 10. Files Relevant to Implementation

```
app/Http/Controllers/OfferController.php
app/Http/Livewire/OfferAuction.php
app/Models/Offer.php
app/Models/OfferMeta.php
app/Models/OfferAuction.php
app/Services/Offers/OfferSubmissionService.php
app/Services/Offers/OfferCounterService.php
app/Services/Offers/OfferWorkflowFacade.php
app/Repositories/OfferRepository.php
resources/views/offers/show.blade.php
resources/views/livewire/offer-auction.blade.php
database/migrations/2026_06_02_000001_create_offers_table.php
database/migrations/2026_06_02_000002_create_offer_metas_table.php
database/factories/OfferFactory.php
database/factories/OfferMetaFactory.php
routes/web.php:1096-1105
```
