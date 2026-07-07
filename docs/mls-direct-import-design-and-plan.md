# Direct MLS Import — Design & Implementation Plan

> Status: **Design / sign-off — no production code changed.** Date: 2026-07-05.
> Two separate MLS-powered workflows on a shared, provider-agnostic lookup core.

## Headline

We already have (a) a live Bridge/Stellar OData integration, (b) a local `bridge_properties`
cache with full `raw_json`, (c) a URL/text listing-import pre-fill pipeline, and (d) a complete
property-vs-criteria match engine. The two new workflows are mostly *wiring*, not new engines.

- **Use Case A — Seller/Landlord listing prefill** (populates the create/edit form).
- **Use Case B — Buyer/Tenant match check** (analyzes a property against saved criteria; never
  populates a form).

They share only a lookup/cache/normalization core. Their user-facing routes and workflows are
fully separate.

## Locked decisions (owner: Abigail, 2026-07-05)

1. **Phase 3 = facts only.** Seller/Landlord prefill imports objective property fields only
   (address, price/rent, beds, baths, sqft, lot size, year built, property type, status, HOA,
   taxes, pool/garage/waterfront flags, coordinates, factual attributes). No photos, no
   PublicRemarks, no agent/private remarks, no showing instructions, no agent/office contacts.
   Reason: avoids Stellar MLS licensing risk (photo/remarks reuse, retention, rehosting). Users
   add photos/description manually. Fuller media only after written Stellar MLS confirmation.
2. **Provider-agnostic via a normalized `PropertyCandidate`.** Both consumers act on a normalized
   object, never a source-specific record. Sources: Bridge/Stellar (preferred), existing URL/text
   parser, manual entry (FSBO/off-market), future RESO/Bridge MLSs. Neither the match engine nor
   the prefill service knows the origin.
3. **B rollout gate:** new dedicated feature flag, default OFF.
4. **B criteria selection:** auto-select the preferred saved profile via `CriteriaListingResolver`,
   with a dropdown to switch.
5. **Lookup UX:** MLS # primary (resolved to ListingKey internally); address search is the fallback
   and shows a chooser when multiple units/properties match.

## Architecture

```
SOURCES → adapters → PropertyCandidate (normalized DTO) → A) Prefill  or  B) Match Analysis
```

- **Source adapters** are the only layer that knows the origin: `BridgePropertyCandidateAdapter`,
  `ParsedListingCandidateAdapter` (wraps the existing URL/text parser), `ManualEntryCandidateAdapter`,
  future MLS adapters.
- **`PropertyCandidate`** carries factual property attributes + provenance (`source`, `source_id`).
- **Consumers** are source-blind:
  - A) `MlsListingPrefillService` → facts-only canonical form keys (Seller/Landlord only).
  - B) `MlsPropertyMatchAnalysisService` → match engine (score + reasons + mismatches + fit bars).

## Shared lookup core (Phase 2)

- `BridgeListingLookupService`: `findByMlsNumber()`, `findByListingKey()`, `searchByAddress()`.
  Local `bridge_properties` first → Bridge API on miss (arbitrary OData `$filter`, already
  supported) → `BridgePropertyNormalizer::upsert()` (also dispatches `ComputeLocationDna`).
- New migration: index `listing_id` and address/`city` (only `listing_key` is indexed today).

## Use Case A — Seller/Landlord prefill

- `MlsListingPrefillService`: `PropertyCandidate` → facts-only canonical-key array, returning the
  same `['success','data','error']` shape as `MlsListingImportService::import()`.
- Reuses the existing `HasMlsImport` trait → `MlsFieldMap::forRole()` → `applyImportedFields()`
  preview/apply machinery, unchanged.
- Scoped to Seller + Landlord create/edit only. URL/text parser stays as a fallback tab and becomes
  a second `PropertyCandidate` source. `initializeLimitedService()` untouched (frozen).

## Use Case B — Buyer/Tenant match check

- `MlsPropertyMatchAnalysisService`: lookup → `PropertyCandidate` → `*CriteriaLoader::loadById()`
  → `BuyerCriteriaPayload` → `BuyerMatchScorer::score()` → `BuyerMatchResultBuilder::build()` →
  `BuyerResultViewMapper::mapOne()`.
- Output already provides: `total_score`, `category_bars` (Location/Price/Size/Type/Amenities/Fees/
  Lifestyle = property/financial/location fit), `why_this_matches`, `tradeoffs`, `missing_data`,
  `caution_flags`.
- New standalone `/match-check` route/controller/Livewire/view. Never writes a form. Behind the new
  feature flag (default OFF).
- Design cost: the scorer is typed to the concrete `BridgeProperty` model. Recommended: refactor its
  input to `PropertyCandidate` (Bridge path gets a `BridgeProperty → PropertyCandidate` mapper).

## Compliance boundary (A vs B)

- The "facts only" rule constrains **what A writes into a publishable form**.
- B reads restricted fields (PublicRemarks, CDD, etc.) **internally to score** and never republishes
  them — a different, lower-risk act. B keeps full internal field access.

## Phased plan

- **Phase 1** — Design (this doc). No code.
- **Phase 2** — Shared lookup + `PropertyCandidate` DTO + `BridgePropertyCandidateAdapter`. Backend
  only. Add indexes.
- **Phase 3** — Seller/Landlord facts-only prefill. Reuse pre-fill pipeline; keep URL/text fallback.
- **Phase 4** — Buyer/Tenant match check (`/match-check`), behind new flag, auto-select criteria +
  switch dropdown, MLS#-primary lookup. Wire scorer to `PropertyCandidate`.
- **Phase 5** — Location DNA + commute enrichment on B's report. Bridge rows get DNA from existing
  dispatch; other sources geocode-then-compute. Commute is a stub today; flood/schools are live
  lookups — surface "if available".
- **Phase 6** — Better matches via existing `BuyerMatchService::match()`.
- **Later** — manual-entry + other-MLS adapters; fuller media/remarks pending Stellar sign-off.

## Known dependencies / risks

- Commute provider is a stub (`travel_time_minutes` always null) — real commute needs a live provider.
- Flood zone & school district are live bbox lookups, not stored per property.
- Scorer↔`BridgeProperty` coupling is the one real refactor.
- Location DNA for a freshly-imported MLS row is async — first view may show "enriching…".
- MLS#/address lookups need new indexes to avoid table scans.

## Files (anticipated)

New: `BridgeListingLookupService`, `PropertyCandidate` DTO + source adapters,
`MlsListingPrefillService`, `MlsPropertyMatchAnalysisService`, `/match-check`
route+controller+Livewire+view, one migration (indexes), one config flag.
Edited: `HasMlsImport` (add MLS#/address entry points), shared MLS import modal, `BridgeApiService`
(optional single-record helper/`$select`), scorer input type.
Reused unchanged: `MlsFieldMap`, `MlsNormalizer`, `BridgePropertyNormalizer`, `BuyerMatchScorer`
internals, `BuyerMatchResultBuilder`, `BuyerResultViewMapper`, all 4 criteria loaders,
`CriteriaListingResolver`, `BuyerMatchService`, Location DNA pipeline.
