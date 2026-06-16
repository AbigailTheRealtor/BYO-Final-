# Stellar MLS Alert System Architecture

> Document type: Architecture design record  
> Date: 2026-06-16  
> Source audits: `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` · `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md` · `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`  
> Scope: Design specification only — no migrations, no code changes, no UI changes

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Alert Types](#2-alert-types)
3. [Trigger Field Mapping](#3-trigger-field-mapping)
4. [Recipient Strategy](#4-recipient-strategy)
5. [Deduplication Rules](#5-deduplication-rules)
6. [Alert Storage Strategy](#6-alert-storage-strategy)
7. [Delivery Strategy](#7-delivery-strategy)
8. [Performance Strategy](#8-performance-strategy)
9. [Compliance and Suppression Rules](#9-compliance-and-suppression-rules)
10. [Implementation Roadmap](#10-implementation-roadmap)

---

## 1. Executive Summary

### Purpose

The Stellar MLS Alert System is the notification layer that sits on top of the platform's matching engine. Its purpose is to proactively push relevant listing intelligence — new inventory, price movements, status transitions, and availability changes — to the buyers, tenants, agents, and saved-search users whose criteria or watchlists make a given event meaningful to them. The matching engine determines *who* should care about a listing; the alert system determines *when* they should be told and *how*.

### Relationship to the Matching Engine

Alerts are downstream of match results, not a replacement for them. The matching engine runs queries against `bridge_properties` to score how well a listing satisfies a user's criteria. The alert system monitors for events on listings (import of a new listing, a price change, a status transition) and then asks the matching engine whether any active criteria records are affected. This separation keeps the alert system from re-implementing match logic and ensures that match quality improvements automatically improve alert targeting.

### Current Data Readiness Gap

Several alert types depend on Stellar fields that are currently stored only in `raw_json` within `bridge_properties`. This is acceptable for per-record, import-time detection (O(1) extraction per row), but it is not acceptable for the scheduled comparison jobs that price-change and status-change alerts require. Those jobs must compare column values across potentially thousands of rows — a query pattern that demands indexed native columns.

The specific fields that block Phase 2 alert delivery are:

| Field | Alert Dependency | Current Status |
|---|---|---|
| `PreviousListPrice` | `price_reduction`, `price_increase` | `raw_json` only |
| `PriceChangeTimestamp` | `price_reduction`, `price_increase` | `raw_json` only |
| `StatusChangeTimestamp` | `status_change` | `raw_json` only |
| `MlsStatus` | `status_change`, `back_on_market` | `raw_json` only |

Phase 1 native column promotions (see `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` §8) are also required before `new_match` alerts can run, because the matching query that identifies which criteria records an incoming listing satisfies must use indexed native columns to perform at acceptable speed.

### Phased Delivery Plan

| Phase | Alert Types Delivered | Prerequisite |
|---|---|---|
| 1 | `new_listing`, `new_match` | Phase 1 column promotions from expansion strategy §8 |
| 2 | `price_reduction`, `price_increase`, `status_change`, `back_on_market`, `coming_soon` | `previous_list_price`, `price_change_timestamp`, `status_change_timestamp`, `mls_status` promoted to native columns |
| 3 | `rental_available`, `open_house_scheduled`, `photos_updated` | Stellar For Lease feed active; in-app notification panel and dashboard widget built |

---

## 2. Alert Types

The system defines ten alert types. Each type maps to a distinct triggering event and is scoped to specific recipient classes. All ten types are described below.

### `new_listing`

Fires when a `listing_key` is inserted into `bridge_properties` for the first time and `StandardStatus = Active`. This is the highest-volume alert type and the most time-sensitive. Buyers and tenants with saved criteria expect to know about new inventory within minutes of it entering the MLS. Detection happens at import time: if the upsert creates a new row (no prior record for that `listing_key`), the import pipeline queues a `new_listing` event. No comparison job is needed — this is a pure insertion gate. The alert payload includes listing address, price, beds, baths, square footage, and a link to the listing detail page.

### `new_match`

Fires when an existing listing — newly imported or recently updated — satisfies the criteria of a buyer or tenant record that has opted in to match alerts. Unlike `new_listing`, which alerts on all new inventory, `new_match` is personalized: it runs the match scoring query against the incoming listing and notifies only the users whose criteria the listing meets. This is the highest-value alert type from a conversion standpoint. It requires all Tier 1 match fields to be native indexed columns before it can run at acceptable performance. Detection happens at the end of each import cycle by running the match query for all active buyer/tenant criteria records against the batch of newly imported or modified listings.

### `price_reduction`

Fires when `list_price` drops below `previous_list_price` on a listing with `StandardStatus = Active`. This alert type requires a scheduled comparison job that queries `bridge_properties` rows where `modification_timestamp` has advanced since the last job run, then compares `list_price` against `previous_list_price` — both of which must be native columns for this comparison to be efficient across the full table. The alert payload communicates the old price, the new price, the dollar and percentage change, and the listing address. This is a high-intent signal for buyers who are watching a listing but have not yet made an offer.

### `price_increase`

Fires when `list_price` rises above `previous_list_price` on a listing with `StandardStatus = Active`. Structurally identical to `price_reduction` in detection mechanism and column requirements. Price increases are informational for buyers who are tracking inventory; they are high-value signals for agents monitoring competitive listings on behalf of clients. The alert payload communicates the same delta fields as `price_reduction` and is delivered via the same scheduled comparison job.

### `status_change`

Fires when `standard_status` or `mls_status` transitions from one value to another on a record that was previously in a different state. The canonical trigger is any status transition: `Active → Pending`, `Pending → Closed`, `Active → Withdrawn`, `Pending → Active` (which overlaps with `back_on_market`), etc. Detection requires a scheduled comparison job that reads `status_change_timestamp` and `mls_status` as native columns, compares the current state against a cached prior-status snapshot, and queues alerts for any record where the timestamp has advanced. Buyers and tenants tracking a specific listing care about status changes because they signal whether the listing is still available or has left the market.

### `back_on_market`

Fires when `STELLAR_BOMDate` becomes present on a record for the first time in a sync pass, or when `standard_status` returns to `Active` after having been `Pending` or `Closed`. Back-on-market events represent re-entering inventory that buyers may have dismissed when it went pending. Unlike `status_change`, this alert type is restricted to a specific directional transition and carries a "second chance" framing in the alert copy. Detection is acceptable via import-time JSON extraction of `STELLAR_BOMDate` per record — BOM events are rare, and per-record extraction is O(1) at import time. No new native column is required for Phase 2 delivery of this type.

### `coming_soon`

Fires when `STELLAR_ComingSoonDate` is set on a record and `standard_status` reflects a pre-market state. Coming-soon listings allow buyers and tenants to prepare and schedule showings before a property officially enters active inventory. Detection is acceptable via import-time JSON extraction of `STELLAR_ComingSoonDate` per record — the field is either present or absent, and checking it per row at import time is O(1). `ListPrice` and `StandardStatus` are already native columns that can gate the alert further. No new native column is required.

### `rental_available`

Fires when `availability_date` arrives on a rental listing (i.e., the date is today or in the past and the listing remains active), or when `STELLAR_ForLeaseYN` becomes `true` on a record that was previously not flagged for lease. This alert type is gated behind the Stellar For Lease feed being active — without rental feed data, there are no qualified recipients and no populated trigger fields. When the rental feed is live, `availability_date` should be promoted to a native column so that a scheduled job can efficiently query `WHERE availability_date <= CURRENT_DATE AND standard_status = 'Active'`. Until that promotion occurs, import-time JSON extraction is a viable fallback.

### `photos_updated`

Fires when `PhotosChangeTimestamp` advances on a sync pass and `PhotosCount > 0` on a listing that was previously imported. Updated photos are a meaningful signal for users who viewed a listing and found it lacking visual documentation, or who want to see staging/renovation progress. Detection is acceptable via import-time comparison: on each upsert, the pipeline checks whether the incoming `PhotosChangeTimestamp` (extracted from `raw_json`) is newer than the stored value. This is a per-record O(1) comparison. No native column promotion is required for this alert type.

### `open_house_scheduled`

Fires when `STELLAR_ActiveOpenHouseCount` increments above zero on a sync pass for a listing that had no active open houses in the prior sync. Open house notifications allow buyers and tenants to plan in-person visits without needing to schedule a private showing. Detection is acceptable via import-time JSON extraction of `STELLAR_ActiveOpenHouseCount` per record. The count is compared against the prior stored value to detect the zero-to-nonzero transition. No native column promotion is required.

---

## 3. Trigger Field Mapping

The following tables define, for each alert type, the Stellar source fields that trigger detection, the native column status of each field, whether a native column is required for the alert to fire correctly, whether raw JSON extraction is acceptable as a Phase-gated interim, and the detection timing pattern.

**Detection timing key:**
- **on-import** — Checked per record during the `bridge_properties` upsert pipeline; O(1) per record; no separate scheduled process required
- **scheduled-comparison** — Requires a periodic job that queries rows modified since the last run and compares field values; demands native indexed columns for acceptable performance
- **rental-feed-gated** — Cannot fire until the Stellar For Lease feed is confirmed active and populating data

---

### `new_listing`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `StandardStatus` | `standard_status` ✅ | Yes — gates the alert | N/A (already native) | on-import |
| `ListingKey` | `listing_key` ✅ | Yes — new-record detection | N/A (already native) | on-import |
| `OriginalEntryTimestamp` | none | No — import-time insertion is the trigger, not this field | Yes — per-record lookup if timestamp needed in payload | on-import |
| `OnMarketDate` | none | No — new-row insertion is sufficient trigger | Yes — payload enrichment only | on-import |

**Detection logic:** If the `bridge_properties` upsert creates a new row (INSERT, not UPDATE), and the incoming `StandardStatus = 'Active'`, queue a `new_listing` event. No field comparison required.

---

### `new_match`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| All Phase 1 Tier 1 fields | See expansion strategy §8 | Yes — match query runs WHERE/range scans across table | No — full-table JSON extraction is O(n) and unacceptable | on-import (at end of cycle) |
| `latitude` / `longitude` | none yet | Yes — radius search | No | on-import |
| `county_or_parish` | none yet | Yes — county filter | No | on-import |
| `property_sub_type` | none yet | Yes — subtype filter | No | on-import |
| `year_built` | none yet | Conditional — if criteria records use it | No | on-import |
| `garage_yn`, `pool_private_yn`, `waterfront_yn`, `new_construction_yn` | none yet | Yes — boolean match dimensions | No | on-import |
| `association_fee`, `association_yn` | none yet | Yes — HOA cost filter | No | on-import |
| `mls_status` | none yet | Yes — active listing gate | No | on-import |
| `original_list_price` | none yet | Conditional — price delta matching | No | on-import |

**Detection logic:** After each import cycle, run the match query for all active `buyer_agent_auction` / `buyer_criteria_auction` and `tenant_criteria_auction` records against the set of listings imported or modified in this cycle. Queue a `new_match` event for each criteria record that scores above the match threshold.

---

### `price_reduction`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `ListPrice` | `list_price` ✅ | Yes — left side of comparison | N/A (already native) | scheduled-comparison |
| `PreviousListPrice` | none | **Yes — required** | No — cross-row comparison demands indexed column | scheduled-comparison |
| `PriceChangeTimestamp` | none | **Yes — required** | No — job watermark depends on indexed timestamp | scheduled-comparison |

**Detection logic:** Scheduled job queries `WHERE modification_timestamp > :last_run AND list_price < previous_list_price AND standard_status = 'Active'`. Both `previous_list_price` and `price_change_timestamp` must be promoted to native columns before this job can run efficiently.

---

### `price_increase`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `ListPrice` | `list_price` ✅ | Yes | N/A (already native) | scheduled-comparison |
| `PreviousListPrice` | none | **Yes — required** | No | scheduled-comparison |
| `PriceChangeTimestamp` | none | **Yes — required** | No | scheduled-comparison |

**Detection logic:** Same job as `price_reduction`; condition is `list_price > previous_list_price`. Both alert types can be computed in a single job pass.

---

### `status_change`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `StandardStatus` | `standard_status` ✅ | Yes — current status | N/A (already native) | scheduled-comparison |
| `MlsStatus` | none | **Yes — required** | No — board-specific status diffing requires indexed column | scheduled-comparison |
| `StatusChangeTimestamp` | none | **Yes — required** | No — job watermark | scheduled-comparison |

**Detection logic:** Scheduled job queries `WHERE status_change_timestamp > :last_run`. Compares current `standard_status` and `mls_status` against a cached prior-status snapshot. Queues a `status_change` event with `{from_status, to_status}` for each changed record.

---

### `back_on_market`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `STELLAR_BOMDate` | none | No — import-time detection is sufficient | **Yes** — per-record JSON extraction at import is O(1) | on-import |
| `StandardStatus` | `standard_status` ✅ | Yes — confirm status is Active | N/A (already native) | on-import |
| `DaysOnMarket` | none | No — payload enrichment only | Yes | on-import |

**Detection logic:** On each upsert, if `STELLAR_BOMDate` is newly present (not present in the stored row, now present in incoming JSON), and `standard_status = 'Active'`, queue a `back_on_market` event. Alternatively, if `standard_status` transitions from `Pending` or `Closed` to `Active` (detected via the status-change comparison job), also queue this event type.

---

### `coming_soon`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `STELLAR_ComingSoonDate` | none | No — presence check at import is O(1) | **Yes** | on-import |
| `StandardStatus` | `standard_status` ✅ | Yes — confirm pre-market state | N/A (already native) | on-import |
| `ListPrice` | `list_price` ✅ | Yes — payload field | N/A (already native) | on-import |

**Detection logic:** On each upsert, if `STELLAR_ComingSoonDate` is newly present in the incoming JSON and was not present in the prior stored row, and `standard_status` reflects a pre-market state, queue a `coming_soon` event.

---

### `rental_available`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `AvailabilityDate` | none | **Yes — required for scheduled job** | Phase-gated: Yes at import time; No once rental feed is active and record volume grows | rental-feed-gated |
| `STELLAR_ForLeaseYN` | none | **Yes — rental gate** | Phase-gated: acceptable until column promoted | rental-feed-gated |
| `LeaseConsideredYN` | none | Conditional — alternate rental gate | Phase-gated: acceptable | rental-feed-gated |
| `STELLAR_ExpectedLeaseDate` | none | No — payload enrichment only | Yes | rental-feed-gated |

**Detection logic:** After the Stellar For Lease feed is active, a scheduled job queries `WHERE availability_date <= CURRENT_DATE AND (for_lease_yn = true OR lease_considered_yn = true) AND standard_status = 'Active'`. Until `availability_date` and `for_lease_yn` are promoted to native columns, import-time JSON extraction is the fallback.

---

### `photos_updated`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `PhotosChangeTimestamp` | none | No — per-record comparison at import | **Yes** | on-import |
| `PhotosCount` | none | No — payload gate (count > 0) | **Yes** | on-import |

**Detection logic:** On each upsert, extract `PhotosChangeTimestamp` from the incoming JSON. Compare against the stored value in the prior row. If the timestamp has advanced and `PhotosCount > 0`, queue a `photos_updated` event. Deduplication applies a 7-day suppression window (see §5).

---

### `open_house_scheduled`

| Stellar Field | Native Column | Native Column Needed? | raw_json Acceptable? | Event Timing |
|---|---|---|---|---|
| `STELLAR_ActiveOpenHouseCount` | none | No — per-record extraction at import | **Yes** | on-import |
| `STELLAR_OpenHouseCount` | none | No — secondary enrichment | **Yes** | on-import |

**Detection logic:** On each upsert, extract `STELLAR_ActiveOpenHouseCount` from the incoming JSON. If the value transitions from 0 (or null) to a positive integer, queue an `open_house_scheduled` event. The open house date used in the deduplication key must be extracted from the `STELLAR_OpenHouseDate` field within the JSON payload.

---

## 4. Recipient Strategy

The alert system routes each event to one or more of four recipient classes. Routing is not broadcast — every alert has a specific audience determined by the alert type and the relationship between the user's data and the affected listing.

### Recipient Class 1: Buyer Criteria Owner

**Who:** Users who have an active `buyer_agent_auction` or `buyer_criteria_auction` record with alert opt-in enabled.

**Alert types received:** `new_listing`, `new_match`, `price_reduction`, `price_increase`, `status_change`, `back_on_market`, `coming_soon`, `photos_updated`, `open_house_scheduled`

**Routing join path:**
1. Start from `buyer_agent_auctions` or `buyer_criteria_auctions` — these rows represent a user's active search criteria.
2. The match query (run at import time or on the scheduled comparison job) evaluates whether the affected `bridge_properties.listing_key` satisfies the criteria in those rows.
3. If the match score meets the threshold, the alert is routed to the `user_id` on the criteria record.
4. For `price_reduction`, `status_change`, and similar event-driven alerts, the system first identifies which `listing_key` is affected, then runs the reverse query: which buyer criteria records would match this listing?

**Delivery mode:** Per-alert push (email + in-app notification) for high-priority types (`new_listing`, `new_match`, `price_reduction`, `back_on_market`). Daily digest for lower-priority types (`photos_updated`, `open_house_scheduled`).

---

### Recipient Class 2: Tenant Criteria Owner

**Who:** Users who have an active `tenant_criteria_auction` record with alert opt-in enabled.

**Alert types received:** `new_listing`, `new_match`, `rental_available`, `price_reduction`, `status_change`, `photos_updated`

**Routing join path:**
1. Start from `tenant_criteria_auctions` — these rows represent a user's active rental search criteria (rent range, beds, baths, pets, furnishing, lease term, move-in date).
2. The match query evaluates whether the affected rental listing satisfies the tenant's criteria.
3. `rental_available` alerts are routed only to tenants whose `availability_date` preference (or no preference) aligns with the listing's availability.
4. `status_change` alerts for tenant recipients are restricted to listings that the tenant's criteria would have matched — tenants do not receive status change alerts for listings they were never matched to.

**Delivery mode:** Per-alert push for `new_listing`, `new_match`, `rental_available`, `price_reduction`. Daily digest for `photos_updated`. `status_change` delivered as per-alert push only for listings previously matched.

---

### Recipient Class 3: Agent / Listing Owner

**Who:** The agent whose managed listings are associated with affected `listing_key` values. The platform links agents to their Stellar listings via a future `agent_listing_claims` table or equivalent, where the agent's `listing_key` set is tracked.

**Alert types received:** All ten alert types, but in digest mode for most. Immediate push for `back_on_market` and `status_change` only.

**Routing join path:**
1. The affected `bridge_properties.listing_key` is matched against the agent's claimed listing keys.
2. If the agent has claimed ownership of the listing (or the listing is attributed to their MLS ID), the alert is routed to the agent's `user_id`.
3. `back_on_market` and `status_change` are delivered as immediate push notifications because agents need to be aware of inventory state changes on their managed listings without delay.
4. All other alert types (`photos_updated`, `open_house_scheduled`, `price_reduction`, etc.) are aggregated into a daily agent digest email to avoid notification fatigue.

**Delivery mode:** Digest-mode email for all types except `back_on_market` and `status_change` (immediate push). In-app notification panel shows all types with read-receipt tracking.

---

### Recipient Class 4: Saved-Search Users

**Who:** Future — anonymous or authenticated users who create a named saved search without a full buyer or tenant criteria record. This class does not yet exist in the platform schema.

**Alert types received:** `new_listing`, `price_reduction`, `back_on_market`

**Routing join path (future):**
1. A future `saved_searches` table stores the search parameters (location, price range, property type) and the associated `user_id` (or anonymous session token).
2. On `new_listing` and `back_on_market` events, the system runs a lightweight filter query against saved searches to identify which searches the affected listing satisfies.
3. `price_reduction` alerts fire for any listing that falls within a saved search's price ceiling after the reduction.

**Delivery mode:** Email only (authenticated users). Anonymous users receive no alert — their session is the delivery mechanism. In-app notification if/when the user authenticates and links their session.

---

## 5. Deduplication Rules

Without deduplication, repeated import cycles would re-fire the same alert for the same user and listing on every sync pass. The deduplication layer ensures each meaningful event produces at most one notification per user per event per relevant window.

### Backing Store

All deduplication checks use the `alert_dedup_log` table (see §6) as the backing store. A composite unique index on `dedup_key` makes insertion idempotent: attempting to insert a duplicate key is caught at the database level. The system treats a unique constraint violation as "already sent — suppress this alert." Rows are pruned on a schedule after their `expires_at` timestamp to keep the table manageable.

### Standard Key Format

All deduplication keys follow the base format: `{user_id}:{alert_type}:{listing_key}`

Additional fields are appended for alert types where the same listing can legitimately produce multiple distinct events.

---

### Per-Type Deduplication Keys and TTL Windows

| Alert Type | Deduplication Key | TTL Window | Notes |
|---|---|---|---|
| `new_listing` | `{user_id}:new_listing:{listing_key}` | 30 days | One alert per listing per user lifetime; a listing is new exactly once |
| `new_match` | `{user_id}:new_match:{listing_key}:{criteria_version_hash}` | 14 days | `criteria_version_hash` is a hash of the criteria record's current field values; re-running the match job after the user updates their criteria generates a new hash and may re-alert |
| `price_reduction` | `{user_id}:price_reduction:{listing_key}:{previous_list_price}:{list_price}` | 7 days | Each distinct price move (different old/new price pair) is a separate event; the same move cannot repeat within 7 days |
| `price_increase` | `{user_id}:price_increase:{listing_key}:{previous_list_price}:{list_price}` | 7 days | Same as `price_reduction`; distinct price pair = distinct alert |
| `status_change` | `{user_id}:status_change:{listing_key}:{from_status}:{to_status}` | 30 days | Each directional transition (e.g., `Active→Pending`) is a separate key; if the listing goes Active→Pending→Active, both transitions generate alerts |
| `back_on_market` | `{user_id}:back_on_market:{listing_key}` | 30 days | One back-on-market alert per listing per user; the listing is either back or it is not |
| `coming_soon` | `{user_id}:coming_soon:{listing_key}` | 30 days | One coming-soon alert per listing per user |
| `rental_available` | `{user_id}:rental_available:{listing_key}` | 14 days | One rental-available alert per listing per user per 14-day window |
| `photos_updated` | `{user_id}:photos_updated:{listing_key}` | **7 days** | Hard 7-day TTL window — only one photo update alert per listing per week per user, regardless of how many times photos change |
| `open_house_scheduled` | `{user_id}:open_house_scheduled:{listing_key}:{open_house_date}` | 7 days | `{open_house_date}` is the date of the scheduled open house; one alert per scheduled date per user; a new open house date on the same listing generates a new key and a new alert |

---

### `listing_reactivated` — Separate Deduplication Class

A listing that transitions from `Expired`, `Withdrawn`, or `Cancelled` back to `Active` is semantically distinct from a `new_listing` event: the `listing_key` already exists in `bridge_properties`, so the new-row insertion gate that `new_listing` uses will not fire. Without a dedicated deduplication class, users who had the listing on their watchlist or who previously received a `new_listing` alert would miss genuinely re-entering inventory.

The `listing_reactivated` type is not a standalone alert type surfaced to users — it is an internal trigger class that feeds a `back_on_market` alert. However, its deduplication key is distinct to allow one alert per reactivation event, not just one per listing lifetime.

**Deduplication key:** `{user_id}:listing_reactivated:{listing_key}:{reactivation_timestamp}`

**TTL:** 30 days

**Why this is distinct from `new_listing`:** The `new_listing` gate checks for a new row insertion. A reactivated listing already has a row in `bridge_properties` — only its `standard_status` changes back to `Active`. The `listing_reactivated` class catches this specific transition and routes it to the `back_on_market` alert pipeline. Without this class, the `new_listing` gate would silently skip the reactivated listing because the record is not new, and the `back_on_market` gate would only fire if `STELLAR_BOMDate` was present — which it may not be for an `Expired → Active` transition.

---

## 6. Alert Storage Strategy

Three future tables are recommended to support the alert system's storage needs. No migrations are produced by this document — these are schema design recommendations for the implementation phase.

### Table: `mls_alerts`

One row per pending or sent alert. This is the primary operational table for alert delivery tracking.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `user_id` | `bigint unsigned` | Foreign key → `users.id`; indexed |
| `listing_key` | `varchar(255)` | Foreign key → `bridge_properties.listing_key`; indexed |
| `alert_type` | `varchar(64)` | One of the 10 defined alert types |
| `payload` | `jsonb` | Serialized alert data (price, status, listing details); no Tier 6 fields |
| `status` | `varchar(32)` | `queued` / `sent` / `failed` / `suppressed` |
| `channel` | `varchar(32)` | `email` / `in_app` / `dashboard` |
| `created_at` | `timestamp` | When the alert was generated |
| `sent_at` | `timestamp` (nullable) | When delivery was confirmed |

**Append-only:** No hard deletes. Status transitions (queued → sent, queued → failed, queued → suppressed) are the only mutations. Historical alert records are retained for audit and re-send capability.

**Index recommendations:** Composite index on `(user_id, status)` for the in-app notification query. Index on `(listing_key, alert_type)` for alert history lookups. Index on `(status, created_at)` for the delivery worker queue.

---

### Table: `alert_subscriptions`

One row per user-per-alert-type-per-channel preference. Controls which users receive which alert types through which channels.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `user_id` | `bigint unsigned` | Foreign key → `users.id`; indexed |
| `alert_type` | `varchar(64)` | One of the 10 alert types, or `all` for global opt-out |
| `channel` | `varchar(32)` | `email` / `in_app` / `dashboard` |
| `enabled` | `boolean` | `true` by default for opted-in users |
| `created_at` | `timestamp` | When the subscription row was created |
| `updated_at` | `timestamp` | When the preference was last changed |

**Default state:** For any user who opts in to alerts, all channels are enabled by default for all alert types they are eligible for. A row with `enabled = false` represents an explicit opt-out and must be checked before queuing any alert for that user+type+channel combination.

**Unsubscribe handling:** When a user unsubscribes via an email link (CAN-SPAM), the corresponding `alert_subscriptions` row is updated to `enabled = false` immediately. The 10-business-day CAN-SPAM deadline is met by this immediate suppression — the legal clock begins at opt-out request, not at the next scheduled job run.

**Composite unique index:** `(user_id, alert_type, channel)` — one preference row per user per alert type per channel.

---

### Table: `alert_dedup_log`

One row per deduplication check. The unique index on `dedup_key` makes this table the enforcement point for the suppression rules defined in §5.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `dedup_key` | `varchar(512)` | **Unique indexed** — the composite key defined in §5 |
| `first_sent_at` | `timestamp` | When the first alert for this key was sent |
| `expires_at` | `timestamp` | When this deduplication entry can be pruned; equals `first_sent_at + TTL` |

**Pruning:** A scheduled artisan command (not defined here) deletes rows where `expires_at < NOW()`. Pruning frequency should be at least daily. Rows must not be deleted before their `expires_at` — early deletion would allow the same alert to fire again within its intended suppression window.

**Insertion pattern:** Before queuing any alert to `mls_alerts`, the system attempts to insert a row into `alert_dedup_log` with the appropriate `dedup_key`. If the insert succeeds (unique key not present), the alert is new and is queued. If the insert fails with a unique constraint violation, the alert is suppressed and the `mls_alerts` row is written with `status = 'suppressed'` for audit purposes.

---

## 7. Delivery Strategy

### Channel 1: Email

Transactional email is the primary delivery channel. Each high-priority alert type (`new_listing`, `new_match`, `price_reduction`, `back_on_market`) generates an individual email per qualifying alert. Low-priority alert types (`photos_updated`, `open_house_scheduled`) are aggregated into a daily digest email to avoid inbox fatigue.

**Delivery mechanism:** A queued Laravel job (not defined in this document) reads `mls_alerts` rows with `status = 'queued'` and `channel = 'email'`, renders the appropriate Blade email template for the alert type, and dispatches via the configured mail driver.

**Template requirements:** One email template per alert type. Each template must include the listing address, the event-specific change (price delta, new status, etc.), a call-to-action link to the listing detail page, and an unsubscribe link. Templates must not include any Tier 6 compliance-restricted fields (see §9).

**CAN-SPAM compliance:** Every email must include a physical mailing address for the sending organization, a clear subject line identifying it as a property alert, and a one-click unsubscribe link. The unsubscribe link must update `alert_subscriptions.enabled = false` immediately for the relevant `alert_type` and `channel = 'email'`.

**Agent delivery mode:** Agents receive digest-mode email only — one daily email aggregating all alert types for their managed listings. No per-alert push email for agents.

---

### Channel 2: In-App Notification

A bell icon with an unread count displayed on the buyer/tenant/agent dashboard. The count is derived from `mls_alerts` rows where `user_id = {current user}` and `status = 'sent'` and a `read_at` flag (recommended as a nullable timestamp column on `mls_alerts`) is null.

**Read receipts:** When a user opens the notification panel, the platform marks visible alerts as read by setting `read_at = NOW()`. Alerts are not deleted on read — they remain accessible in the notification history.

**Accessible route:** `/dashboard/notifications` — a full listing of the user's alert history, sorted by `created_at` descending, with filtering by alert type and read/unread status.

**Polling or push:** The implementation detail of whether the unread count is polled (periodic AJAX) or pushed (WebSocket/Livewire) is left to the implementation phase. Either approach is architecturally compatible with the `mls_alerts` table design.

---

### Channel 3: Dashboard Alert Panel

A dedicated widget on the buyer/tenant dashboard surface showing the five most recent unread alerts for the logged-in user. Each item in the panel displays a compact listing card preview: property photo thumbnail (if available), address, alert type label, and event summary (e.g., "Price reduced by $15,000 · Now $345,000"). Each card links to the listing's full detail page.

**Data source:** Query `mls_alerts WHERE user_id = ? AND status = 'sent' AND read_at IS NULL ORDER BY created_at DESC LIMIT 5`.

**Relationship to in-app notification:** The dashboard panel and the bell-icon notification count draw from the same `mls_alerts` table. Marking an item as read in the panel should also decrement the bell-icon count.

---

### User Preference Layer

The `alert_subscriptions` table (see §6) provides the preference layer. Before any alert is queued, the system checks whether a subscription row exists for `(user_id, alert_type, channel)` with `enabled = true`. If no row exists (new user, default state), the alert is permitted. If a row exists with `enabled = false`, the alert is suppressed at queue time and written to `mls_alerts` with `status = 'suppressed'`.

A future user preferences UI (not in scope for this document) allows users to toggle alert types and channels on the `/dashboard/notifications/preferences` route.

---

## 8. Performance Strategy

Alert detection falls into two fundamentally different performance categories. The architecture separates these cleanly to avoid applying expensive scheduled-job patterns to alert types that can be handled far more efficiently at import time.

### Pattern 1: Import-Time Detection (Preferred)

Most alert types can be detected during the `bridge_properties` upsert pipeline with zero additional scheduled processes. On each record upsert, the pipeline checks a small set of conditions that are O(1) per record:

| Check | Alert Type Triggered |
|---|---|
| `listing_key` is new (INSERT, not UPDATE) AND `standard_status = 'Active'` | `new_listing` |
| `PhotosChangeTimestamp` in incoming JSON > stored value AND `PhotosCount > 0` | `photos_updated` |
| `STELLAR_ActiveOpenHouseCount` in incoming JSON > 0 AND prior value was 0 | `open_house_scheduled` |
| `STELLAR_BOMDate` newly present in incoming JSON | `back_on_market` |
| `STELLAR_ComingSoonDate` newly present in incoming JSON | `coming_soon` |

These checks add minimal overhead to the sync job. Each check is a single field comparison per record. The pipeline writes detected events to a staging queue (or directly to `mls_alerts` with `status = 'queued'`), and a downstream worker handles deduplication and delivery asynchronously.

**Why this is preferred:** No additional cron job. No job-scheduling infrastructure for these types. No risk of a comparison job running stale if the scheduler skips a cycle. The detection is tightly coupled to the data change event itself.

---

### Pattern 2: Scheduled Comparison Job (Required for Price and Status Alerts)

Price-change and status-change alerts cannot use import-time detection because the import pipeline does not have efficient access to the prior state of every field. Comparing `list_price` against `previous_list_price` across thousands of rows requires both columns to be native indexed columns so the job can use a WHERE clause efficiently.

**Job design:**

1. Read a `last_alert_run` watermark from a singleton record (recommended: a `system_settings` key-value row keyed `alert_comparison_last_run`) or from a Laravel cache key.
2. Query `bridge_properties WHERE modification_timestamp > :last_run`.
3. For each returned row, evaluate:
   - `list_price < previous_list_price` → queue `price_reduction`
   - `list_price > previous_list_price` → queue `price_increase`
   - `status_change_timestamp > :last_run` → queue `status_change` with `{from_status, to_status}`
4. Update the watermark to `NOW()` after the job completes.

**Column requirements:** `previous_list_price`, `price_change_timestamp`, `status_change_timestamp`, and `mls_status` must be native columns before this job can be built. Running this job against `raw_json` extraction on a large dataset would produce sequential scans on every run — unacceptable at production record counts.

**Recommended watermark storage:** A `system_settings` table row or a durable cache key (not an in-memory cache that resets on deployment). If the watermark is lost, the job should default to `NOW() - 24 hours` rather than processing all historical records.

---

### Pattern 3: Match Alert Job (Required for `new_match`)

`new_match` alerts require the most compute of the three patterns. After each import cycle, the system must run the match query for all active buyer and tenant criteria records against the set of newly imported or modified listings.

**Job design:**

1. Collect the set of `listing_key` values imported or modified in the current cycle.
2. For each active `buyer_agent_auction` / `buyer_criteria_auction` record, run the match query against only the current-cycle listing set (not the full `bridge_properties` table).
3. For each match score that meets the threshold, check the `alert_dedup_log` for the `new_match` key. If not present, queue a `new_match` alert.
4. Repeat for active `tenant_criteria_auction` records.

**Index requirements:** All Tier 1 match fields used in the match query WHERE clauses must be native indexed columns (see expansion strategy §8). Running the match query against `raw_json` extraction is explicitly prohibited for this job — the entire motivation for Phase 1 column promotions is to make this query viable.

**Scoping to the current-cycle listing set** is the critical performance optimization: instead of running every criteria record against every listing on every job run, the job only evaluates the delta (listings that changed since the last cycle). This keeps the job O(criteria_count × cycle_listing_count) rather than O(criteria_count × total_listing_count).

---

## 9. Compliance and Suppression Rules

The alert system operates on Stellar MLS data that contains agent personal information, compliance-restricted identifiers, and fair housing-sensitive attributes. The following rules are non-negotiable and must be enforced at the alert generation layer — not just at the display layer.

### Tier 6 Field Suppression

The following field categories are classified Tier 6 (Compliance/Restricted) in the field audit and must never appear in alert payloads, email body text, in-app notification copy, or dashboard widget content:

**Agent PII — must never appear in alerts:**
- `ListAgentEmail`
- `ListAgentPreferredPhone`
- `ListOfficePhone`
- `ListAgentStateLicense`
- `CoListAgentStateLicense`
- `BuyerAgentStateLicense`
- `CoBuyerAgentStateLicense`
- `License1`, `License2`, `License3`
- `STELLAR_BuilderLicenseNumber`
- `STELLAR_CallCenterPhoneNumber`
- `ListAgentFirstName`, `ListAgentLastName`, `ListAgentFullName`
- `CoListAgentFirstName`, `CoListAgentLastName`, `CoListAgentFullName`
- `BuyerAgentFirstName`, `BuyerAgentLastName`, `BuyerAgentFullName`

**Lockbox and access information — must never appear in alerts:**
- `LockBoxLocation`
- `LockBoxSerialNumber`
- `LockBoxType`

**Internal MLS admin fields — must not be surfaced to users:**
- `OriginatingSystemKey`, `OriginatingSystemName`, `ListingKeyNumeric`
- `ListAgentKey`, `ListAgentMlsId`, `ListAOR`, `ListAgentAOR`
- `BuyerAgentKeyNumeric`, `BuyerAgentMlsId`, `BuyerOfficeKeyNumeric`, `BuyerOfficeMlsId`

**Alert copy rule:** Alert copy must reference only property attributes — price, beds, baths, address, square footage, status, and type. Agent contact information must never appear in any alert channel.

---

### Inactive / Closed / Expired Listing Gates

Listings whose `StandardStatus` is in `['Cancelled', 'Withdrawn', 'Expired', 'Deleted']` must be suppressed from all buyer-facing and tenant-facing alert types. The one exception:

- `status_change` alerts for these terminal status values may be delivered **to the listing's agent/owner only** (Recipient Class 3), not to buyer or tenant recipients. Buyers and tenants should not receive alerts telling them a listing they were watching has been cancelled or withdrawn — this is noise that erodes trust in the alert system.

---

### IDX Participation Gate

Alerts must not fire for any listing where `IDXParticipationYN = false`. This flag indicates that the listing agent has not consented to IDX display. Surfacing that listing in alerts — even in a non-display context like a price change notification — would violate the IDX consent boundary. Before queuing any alert, the system must check that the affected listing's `IDXParticipationYN` value is `true`.

---

### Fair Housing: Senior Community Gate

Listings where `SeniorCommunityYN = true` are age-restricted communities. Under Fair Housing Act requirements, these listings may only be shown to users who have affirmatively indicated eligibility (typically age 55+). The alert system must enforce this gate at recipient-routing time, not just at display time:

- Before routing any alert for a `SeniorCommunityYN = true` listing to a buyer or tenant recipient, the system must verify that the recipient's profile includes an affirmative eligibility flag.
- If no eligibility flag is present, the alert must be suppressed for that recipient.
- This check applies to all buyer-facing and tenant-facing alert types: `new_listing`, `new_match`, `price_reduction`, `price_increase`, `status_change`, `back_on_market`, `coming_soon`.

---

### Rental Feed Gate

`rental_available` alerts must only fire for listings where `STELLAR_ForLeaseYN = true` OR `LeaseConsideredYN = true`. These alerts must never fire for for-sale listings, even if a for-sale listing has `AvailabilityDate` set or an unusual status. The rental/sale separation is enforced at the alert generation query level, not just at the display level.

---

### Pet Policy Suppression in Tenant Match Alert Copy

When generating copy for `new_match` alerts delivered to tenant recipients, the following Stellar fields must not appear in the alert body or subject line:

- `STELLAR_MaxPetWeight`
- `STELLAR_PetSize`
- `STELLAR_NumberOfPets`
- `STELLAR_PetDepositFee`
- `STELLAR_PetMonthlyFee`

These fields are contextual display fields appropriate for the full listing detail page, but they are not notification fields. Including pet policy specifics in alert copy would make alerts verbose and potentially misleading (e.g., a tenant with a large dog receiving an alert for a listing that allows only small pets). Pet-related matching is a gate at the match query level; it need not be restated in alert copy.

---

### CAN-SPAM Unsubscribe Compliance

Alert unsubscribe requests must be honored with immediate suppression in `alert_subscriptions`. The legal requirement under CAN-SPAM is that unsubscribe requests are honored within 10 business days; the recommended implementation exceeds this requirement by suppressing immediately at the database level.

**Implementation rule:** Email unsubscribe links must include a signed token identifying the `user_id` and `alert_type`. When the link is followed, the system updates `alert_subscriptions` with `enabled = false` and writes a suppression timestamp. No login is required for unsubscribe to take effect.

---

## 10. Implementation Roadmap

The alert system is delivered in three phases. Each phase has a defined prerequisite, a defined alert type set, and a defined user-visible deliverable.

### Phase 1 — New Match / New Listing Alerts

**Prerequisites:**

The Phase 1 native column promotions from `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` §8 must be complete before this phase begins. Specifically, the following columns must exist as indexed native columns in `bridge_properties`:

- `latitude`, `longitude` — radius-based match queries
- `county_or_parish` — county-level location filter
- `property_sub_type` — property subtype filter
- `year_built` — year-built range filter
- `garage_yn`, `pool_private_yn`, `waterfront_yn`, `new_construction_yn` — boolean feature filters
- `association_fee`, `association_yn` — HOA cost and existence filters
- `mls_status` — board-specific status filter
- `original_list_price` — price delta filter

Additionally, the three future storage tables (`mls_alerts`, `alert_subscriptions`, `alert_dedup_log`) must be created by migrations written in the implementation phase.

**Alert types delivered:** `new_listing`, `new_match`

**Detection pattern:** Import-time only. No scheduled comparison job.

**Delivery channel:** Email only. In-app notification count incremented but no full panel UI.

**User-visible deliverable:** Buyers and tenants who have active criteria records and have opted in to alerts will receive email notifications when a Stellar MLS listing matching their criteria is imported. The email includes listing address, price, beds, baths, and a link to the listing detail page. `new_listing` alerts cover all new Active listings within the criteria geography. `new_match` alerts are personalized to criteria-matching listings only.

---

### Phase 2 — Price Change / Status Change Alerts

**Prerequisites:**

The following fields must be promoted to native indexed columns in `bridge_properties` before the scheduled comparison job can be built:

- `previous_list_price` — left side of price delta comparison
- `price_change_timestamp` — job watermark for price change detection
- `status_change_timestamp` — job watermark for status change detection
- `mls_status` — board-specific status diffing (already required in Phase 1 but specifically needed here for status change direction tracking)

The `last_alert_run` watermark infrastructure (singleton record or durable cache key) must be designed and implemented.

**Alert types delivered:** `price_reduction`, `price_increase`, `status_change`, `back_on_market`, `coming_soon`

**Detection pattern:** `price_reduction` and `price_increase` via scheduled comparison job. `status_change` via scheduled comparison job. `back_on_market` and `coming_soon` via import-time detection (no new column required).

**Delivery channel:** Email (per-alert for high-priority types). In-app notification panel with read-receipt tracking.

**User-visible deliverable:** Buyers and tenants who are watching matched listings receive email and in-app notifications when a listing's price drops or rises, when its status changes (Active → Pending, etc.), when it comes back on the market after going pending, or when it enters coming-soon status. Agents receive an aggregated daily digest of all status and price events on their managed listings.

---

### Phase 3 — Rental / Open House / Photo Alerts

**Prerequisites:**

- The Stellar For Lease feed must be confirmed active and populating rental-specific fields in `bridge_properties`.
- The following fields must be promoted to native indexed columns (in a dedicated rental-feed migration, separate from Phase 1 promotions): `availability_date`, `for_lease_yn`, `monthly_rent`, `lease_considered_yn`.
- The in-app notification panel UI and dashboard alert widget must be designed and built (UI implementation not in scope for this document).

**Alert types delivered:** `rental_available`, `open_house_scheduled`, `photos_updated`

**Detection pattern:** All three via import-time detection. `rental_available` transitions to a scheduled availability-date job once `availability_date` is a native column and rental feed volume warrants it.

**Delivery channel:** Email (daily digest for `photos_updated` and `open_house_scheduled`; per-alert push for `rental_available`). Full in-app notification panel live for all channels and alert types. Dashboard alert widget showing the 5 most recent unread alerts with listing card preview.

**User-visible deliverable:** The full alert surface is live. Tenant users receive notifications when a rental matching their criteria becomes available based on move-in date. Buyers and tenants receive weekly photo update digests for listings on their watchlists. Buyers receive open house notifications for listings they have matched with. All alert types are visible in the in-app notification panel and the dashboard widget. Agents receive the full digest including rental and open house events on their managed listings.
