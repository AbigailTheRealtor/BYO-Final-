# ASK_AI_SOURCE_MATRIX_V1

## Purpose

This document is the authoritative source routing matrix for the Ask AI feature on the Bid Your Offer platform. It governs which knowledge sources each question type is permitted to consume, and in what capacity (required, optional, or forbidden).

All downstream phases — classifier design, prompt engineering, source contracts, and OpenAI integration — must treat this document as the single source of truth. Any change to source eligibility requires a version increment (see [Versioning](#versioning)).

---

## Approved Question Types

| Identifier | Description |
|---|---|
| `property_standout` | Explains what makes a specific property distinctive — its features, condition, layout, or unique attributes — relative to typical listings. |
| `suited_audience` | Identifies the buyer or tenant profile most likely to value and pursue this property based on its characteristics and location context. |
| `buyer_tenant_match` | Assesses how well a specific buyer or tenant's stated criteria align with the terms and characteristics of a given listing. |
| `compatibility_signals` | Surfaces the specific field-level signals driving a compatibility score, giving agents and clients interpretable evidence. |
| `missing_data` | Identifies gaps in the listing record that reduce match quality, AI response quality, or buyer confidence, and suggests what to fill in. |
| `marketing_angles` | Generates narrative copy angles and positioning ideas an agent can use to market the property to its most likely audience. |
| `educational` | Answers general real estate, auction-process, or platform-related questions that do not require access to a specific listing or bid record. |
| `prohibited` | A catch-all classification for question types that fall outside approved use cases. No source data is consumed; the request is rejected. |

---

## Source Matrix

| Question Type | Required Sources | Optional Sources | Forbidden Sources | Expected Contract Status |
|---|---|---|---|---|
| `property_standout` | `property_intelligence` | `location_intelligence` | — | Approved |
| `suited_audience` | `property_intelligence` | `location_intelligence` | — | Approved |
| `buyer_tenant_match` | `compatibility` | `buyer_avatar`, `tenant_avatar` | — | Approved |
| `compatibility_signals` | `compatibility` | — | — | Approved |
| `missing_data` | `listing` | `missing_sources`, `warnings` | — | Approved |
| `marketing_angles` | `property_intelligence` | `location_intelligence` | — | Approved |
| `educational` | — | `governance_documents` | — | Approved |
| `prohibited` | — | — | all | Rejected — no response issued |

**Legend**

- **Required** — the source must be present and valid; if absent, the request must fail with a clear contract error before reaching the model.
- **Optional** — the source is used when available; its absence degrades quality but does not block the request.
- **Forbidden** — the source must not be attached to this question type under any circumstances, even if the caller provides it.

---

## Source Definitions

### `listing`

Raw structured data from the listing record as stored in the platform database. Covers all native columns and EAV meta keys belonging to the auction row (e.g., price, property type, address, status, photos flag, and disclosure fields). This is the lowest-level, unprocessed source.

### `property_intelligence`

A derived, enriched view of the listing record. Combines raw listing fields with computed signals such as feature completeness, notable attribute flags, and any pre-processed summaries that characterize the physical property. Does not include buyer or tenant profile data.

### `location_intelligence`

Geographic and market-context data associated with the property's location. Sourced from the platform's U.S. Census-based geography tables (`UsCity`, `UsState`, `UsCounty`, `UsZipCode`). May include county-level context, regional characteristics, and proximity signals. Never includes individual bid or user identity data.

### `buyer_avatar`

A structured summary of a buyer's stated purchase criteria — budget range, property type preferences, desired features, financing status, and timeline. Derived from a buyer listing or bid record. Used only in match-oriented question types.

### `tenant_avatar`

A structured summary of a tenant's stated leasing criteria — desired lease terms, unit preferences, move-in timeline, occupant count, and any special requirements. Derived from a tenant listing or bid record. Used only in match-oriented question types.

### `compatibility`

A pre-computed compatibility payload produced by the platform's match score helpers (e.g., `TenantBidMatchScoreHelper`). Contains the numeric match score, field-level signal breakdown, and the logical field groups used to generate the score. This source is the basis for all compatibility and match explanation question types.

### `offer_analysis`

Structured data from submitted bids and accepted bid summaries for a listing. Covers offer price, terms deltas, agent compensation, and bid status. Reserved for future question types that analyze competitive bid positioning. Currently no approved question type requires or permits this source; it is defined here to establish its contract boundary ahead of future phases.

### `governance_documents`

Platform-level reference content covering auction rules, fee structures, role definitions, workflow descriptions, and general real estate education material. Does not contain any user-specific, listing-specific, or bid-specific data. Used exclusively for `educational` questions.

---

## Versioning

**Current version:** `ASK_AI_SOURCE_MATRIX_V1`

This document is versioned monotonically. The version identifier appears in the document title header. Any addition, removal, or reclassification of a question type or source — including changes to Required / Optional / Forbidden designations — requires:

1. Incrementing the version number (e.g., `V1` → `V2`).
2. Updating the title header in this file.
3. Noting the nature of the change in a brief changelog entry appended below.

### Changelog

| Version | Date | Summary |
|---|---|---|
| V1 | 2026-06-02 | Initial release. Eight question types defined. Eight sources defined. |
