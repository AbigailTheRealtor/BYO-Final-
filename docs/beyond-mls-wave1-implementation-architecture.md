# Beyond-MLS Property DNA — Wave 1 Implementation Architecture

**Document type:** Companion implementation-architecture doc (Phase 1 — reconciliation)
**Companion to:** `docs/beyond-mls-property-dna-roadmap.md` (Version 2.4, FROZEN)
**Status:** Reconciliation & planning — **no code, no migrations, nothing committed**
**Date:** 2026-07-02
**Author:** Claude Code

> **Relationship to the frozen roadmap.** This document does **not** modify, expand, or reinterpret the frozen v2.4 roadmap. It is the separate companion architecture document mandated by the freeze policy. Its job is to map the roadmap's foundation (§F1–§F8) and Wave 1 onto the **actual codebase** so implementation reuses what exists instead of rebuilding it. Every roadmap concept keeps its frozen definition; this doc only says *where it already lives, what to extend, and what is genuinely new*.

---

## 1. Executive finding

The v2.4 roadmap was written greenfield, but the Bid Your Offer repo already contains roughly **60% of the F1–F8 foundation** in working form. The single most important consequence:

> **The Canonical Data Model should be a resolver/adapter layer over the storage and addressing scheme that already exist — not a new physical mega-table.** The `(listing_type, listing_id)` canonical addressing the roadmap prescribes is *already the convention* used by `property_location_dna`, `property_location_pois`, and `listing_compatibility_scores`.

Three facts reshape the plan:

1. **There is no single MLS/listing vocabulary — there are three**, all describing the same real-world attributes under different names:
   - **Bridge/RESO** → `BridgePropertyNormalizer` → `bridge_properties` (RESO snake_case + `raw_json`).
   - **Pasted-MLS-text** → `app/Services/ListingImport/` (`MlsFieldMap` / `MlsNormalizer`) → auction-creation forms.
   - **Per-role auction fields** → EAV meta via `saveMeta()`.
   The canonical model's real work is **reconciling these three vocabularies**, and `BridgePropertyNormalizer` is one adapter, not the target shape.

2. **The CLAUDE.md "seller/buyer = native columns, landlord/tenant = EAV" split is obsolete for extended fields.** The modern `OfferListing` flow persists virtually all DNA-relevant fields via `saveMeta()` for **all four roles** (verified: `SellerOfferListing.php:3743-3777` writes `waterfront`, `association_*`, `flood_zone_*`, `interior_features`, `pet_restrictions` all as EAV). Native columns are only the small legacy Hire-Agent core. **This greatly simplifies F2 role-symmetry**: the DNA input fields are already uniform EAV meta across roles, and the four `app/Exports/ListingFieldMaps/{Role}FieldMap.php` files are the authoritative `human-label → meta_key` registries — the ideal source for the BYO canonical adapter.

3. **The confidence signal (F4) does not exist anywhere.** Every existing score is either a deterministic coverage metric or a weighted match percent. This is the single cleanest net-new architectural piece.

---

## 2. Existing infrastructure inventory

| Roadmap concept | Already in the codebase | Location |
|---|---|---|
| Source adapter (MLS→normalized) | ✅ Partial | `BridgePropertyNormalizer` → `bridge_properties`; `app/Services/ListingImport/*` |
| Canonical `(type,id)` addressing | ✅ Convention exists | `property_location_dna`, `property_location_pois`, `listing_compatibility_scores` |
| Property-side Location DNA + scores | ✅ Strong | `LocationDnaPipelineRunner`; `property_location_dna.lifestyle_json` (5 scores, `LDNA_LIFESTYLE_V1`) |
| Location source adapters (POI/flood/school/commute) | ✅ Built | `GooglePlacesPoiAdapter`, `FemaFloodZoneAdapter`, `CensusSchoolDistrictAdapter`, `CommuteTime*` |
| Per-side DNA stores (versioned) | ✅ Built | `property_dna_profiles`, `buyer_tenant_dna_profiles` |
| Symmetric pair-keyed match store (versioned + explanations) | ✅ Built | `listing_compatibility_scores` (+ `ComputeCompatibilityScore` → `CompatibilityEngine`) |
| Explanation strings (F5) | ✅ Built (neutral static maps) | `PropertyDnaExplanationService`, `CompatibilityExplanationService`, `BuyerTenantDnaExplanationService` |
| Agent↔client match engine | ✅ Built (separate concern) | `config/match_scoring.php`, `*BidMatchScoreHelper`, `bid_score_snapshots` |
| **Confidence (F4)** | ❌ **Net-new** | — |
| **True symmetric *quality* score** | ❌ **Net-new computation** | current `overall_score` is coverage-only |
| **Canonical field vocabulary (3-way reconciled)** | ❌ **Net-new** | three disjoint vocabularies today |
| **Demand-side Location Preference DNA (numeric, shared taxonomy)** | ❌ **Net-new** | `LocationMatchEngine` is boolean geo-overlap only |
| F7 Marketplace Intelligence | ❌ Net-new (read surface) | `ListingCompatibilityScore` is the raw material |
| F8 Learning loops / Behavioral DNA | ⚠️ Signals exist | `BidFunnelTimestamp`, `bid_score_snapshots`, funnel events |

---

## 3. F1–F8 + Wave-1 reconciliation (Reuse / Extend / New)

| Roadmap element | Verdict | How |
|---|---|---|
| **F1 Canonical Data Model** | **Extend** | Build a `CanonicalListingResolver` service over existing role listings (via `{Role}FieldMap`) + a canonical DTO; refactor `BridgePropertyNormalizer` into the Bridge adapter; add a `ListingImport` adapter. No mega-table for Wave 1. |
| **F1 source adapters / provenance** | **Extend / New** | Adapters exist (Bridge, ListingImport, Location). Per-field **provenance + confidence + freshness** metadata is new but lightweight. |
| **F2 DNA persistence** | **Extend** | Reuse the `property_location_dna` pattern: a new `(type,id,score_key,version)` `dna_scores` table for scalar §8 scores; keep `property_dna_profiles`/`buyer_tenant_dna_profiles` for per-side blobs. Non-destructive. |
| **F2 versioning** | **Reuse** | Version columns already standard (`version`, `scoring_framework_version`, `LDNA_LIFESTYLE_V1`, etc.). |
| **F3 Fair Housing** | **Extend** | Explanation services are already neutral static maps (safe). Add the **compliance filter enforcement point** over canonical inputs + generated outputs (policy layer). |
| **F4 Confidence & Completeness** | **New** | No confidence anywhere. Add `confidence` + a `data_completeness` derivation (from populated canonical fields). Cleanest new piece. |
| **F5 Explainability** | **Reuse** | Explanation services + `score_explanation`/`compatibility_narrative` already exist. Ensure every new score emits an explanation string via the same pattern. |
| **F6 Universal Relevance Scoring** | **Extend** | `listing_compatibility_scores` already has symmetric 0–100 category sub-scores (physical/financial/location/terms) + explanations. Add categories + Match Confidence; keep it as the relevance store. |
| **F7 Marketplace Intelligence** | **New (read view)** | Aggregate `listing_compatibility_scores` in reverse (demand→listing) into per-listing demand counts + segments. No new asked fields. |
| **F8 Learning / Behavioral DNA** | **Extend / New** | Lifecycle events partly captured (`BidFunnelTimestamp`, funnel snapshots). Behavioral DNA vector + re-weighting loop are new (roadmap-classified *Future*). |
| **Wave-1 Location scores** | **Reuse** | `walkability`, `coastal`, `convenience`, `commuter`, `family` already 0–100 in `lifestyle_json`. |
| **Wave-1 property/investment scores** | **New compute, inputs exist** | Boating, Lock-and-Leave, Pet-Friendliness, Waterfront-Lifestyle, Accessibility, Energy, Cash-Flow, Investment — all inputs present as Seller/Landlord EAV meta (see §5). |

---

## 4. Canonical Data Model — concrete Wave-1 design

**Principle:** formalize, don't duplicate. The canonical model for Wave 1 is a **read/resolve layer**, not a schema migration.

```
                        ┌─────────────────────────────┐
   BYO role listings ──▶│  ByoListingAdapter          │
   (EAV via FieldMaps)  │   (uses {Role}FieldMap)     │──┐
                        └─────────────────────────────┘  │
   bridge_properties ──▶│  BridgeAdapter               │  │   ┌──────────────────────┐
   (RESO snake_case)    │   (refactor of Normalizer)  │──┼──▶│ CanonicalListing DTO │──▶ DNA / scores / matching
                        └─────────────────────────────┘  │   │  addressed by        │
   pasted MLS text  ───▶│  ListingImportAdapter        │  │   │  (listing_type, id)  │
   (MlsFieldMap keys)   │   (reconcile vocabulary)     │──┘   │  + per-field         │
                        └─────────────────────────────┘      │  provenance/conf/    │
                                                             │  freshness           │
                                                             └──────────────────────┘
```

- **CanonicalListing DTO** — a source-neutral, RESO-aligned field set (formalize the union of `BridgePropertyNormalizer`'s output + the `{Role}FieldMap` meta_keys + `MlsFieldMap` keys). Addressed by the **existing** `(listing_type, listing_id)` convention.
- **ByoListingAdapter (Wave-1 priority)** — reads a role listing's EAV meta through its `{Role}FieldMap` registry and emits canonical fields. This is the "BYO adapter" §F1 requires; it needs no schema change because the FieldMaps already exist.
- **BridgeAdapter** — split `BridgePropertyNormalizer` so `normalize()` (shape) is decoupled from `upsert()` (Bridge persistence); the normalize half becomes the Bridge→canonical adapter.
- **ListingImportAdapter** — reconcile `MlsFieldMap`/`MlsNormalizer` keys to the same canonical names (they already describe the same attributes).
- **Per-field metadata** — each canonical value carries `source`, `confidence`, `provenance`, `freshness` (§F1). New, but a thin wrapper.

**Deferred:** a physical canonical listing table. Not needed until multi-source listings must be queried uniformly; Wave-1 derived scores read *through* the resolver.

---

## 5. Wave-1 score set — inputs confirmed present

Property-side inputs are Seller/Landlord EAV meta (the property-describing roles); demand-side is the symmetric preference weight (§8 convention). Verified field homes:

| Wave-1 score | Property-side canonical inputs (exist as EAV meta) | Reuse status |
|---|---|---|
| Walkability / Coastal / Convenience / Commuter / Family (location) | `property_location_dna.lifestyle_json` | ✅ **Exists** (`LDNA_LIFESTYLE_V1`) |
| Boating / Waterfront-Lifestyle | `waterfront`, `water_access`, `water_view`, `water_frontage`, `waterfront_feet` (+ marina POIs, `coastal_score`) | New compute; inputs ✅ |
| Lock-and-Leave | `association_fee_includes`, `association_amenities`, gated `community`, `total_acreage`, `condition_prop`, property structure | New compute; inputs ✅ |
| Pet-Friendliness / Dog-Owner | `pets`, `pet_*` (Landlord richest: `pet_max_weight_lbs`, `pet_species_allowed`, …), fenced-yard, dog-park POIs | New compute; inputs ✅ (**best symmetric slice** — Tenant `pet_information` on demand side) |
| Accessibility | Tenant `accessibility_requirements` (demand); property single-level/features | New compute; asymmetric inputs |
| Energy-Efficiency | green/solar/`window_features`, HVAC/year fields | New compute; inputs partial |
| Cash-Flow / Investment | `minimum_annual_net_income`, `minimum_cap_rate`, `price_per_sqft`, `rent_roll_available` | New compute; inputs ✅ |

**Asymmetry note (natural, not a defect):** property-describing fields (`waterfront`, `association_*`, `interior_features`, `flood_zone_code`, `year_built`) exist on **Seller + Landlord** (supply) only; Buyer/Tenant carry criteria/preferences. This is exactly the supply/demand split §8's symmetric axis is built for — property score on the supply side, preference weight on the demand side.

---

## 6. The two genuine net-new architectural pieces

Everything else extends existing code. These two are new and should be designed first:

1. **F4 Confidence & Completeness.** Add `data_completeness` (derived from populated canonical fields per property-type-relevant set), `dna_confidence` (per score, from input completeness × source reliability × model version), and `match_confidence` (propagated, non-inflating). Add `confidence` columns to `dna_scores` (new) and `listing_compatibility_scores` (extend). *This is the roadmap's clearest differentiator that has zero existing implementation.*

2. **True symmetric quality score.** Today `overall_score`/DNA scores are **coverage metrics**, and the honest two-sided comparison (`BuyerPropertyCompatibilityService`) deliberately emits no number and is not persisted. F6 needs a persisted symmetric *quality* score. This is new computation over existing inputs — **not** a new table (reuse `listing_compatibility_scores`).

---

## 7. Proposed build sequence

- **Phase 1 (this doc)** — reconciliation. ✅ Complete on delivery. No code.
- **Phase 2 — Canonical resolver + BYO adapter + one vertical slice.** Build `CanonicalListingResolver` + `ByoListingAdapter` (via FieldMaps); implement **one** Wave-1 score end-to-end with F4 confidence + F5 explanation, persisted in a new `dna_scores` table (following the `property_location_dna` pattern), computed symmetrically. **Recommended first slice: Pet-Friendliness** (richest genuinely-symmetric data: Landlord pet policy ↔ Tenant pet profile). Alt: Lock-and-Leave (property-side, snowbird demand).
- **Phase 3 — Wave-1 score batch** on the canonical resolver; reuse the 5 Location scores; wire confidence/explanation uniformly.
- **Phase 4 — F6 relevance** (extend `listing_compatibility_scores` with categories + match confidence + true quality score).
- **Phase 5 — F7 Marketplace Intelligence** (reverse-aggregate read view).
- **Phase 6+ — Bridge/ListingImport adapters** to canonical; then F8 learning loops / Behavioral DNA (roadmap *Future*).

---

## 8. Risks & open decisions (for Phase 2 kickoff)

1. **Canonical vocabulary authority** — adopt RESO names (roadmap F1 mandate) as canonical, with `{Role}FieldMap` meta_keys + `BridgePropertyNormalizer` names + `MlsFieldMap` keys as the three source mappings. Confirm naming ownership.
2. **`dna_scores` table vs. extend existing profiles** — recommend a new normalized `dna_scores` table (`type,id,score_key,value,confidence,explanation,version,computed_at`) mirroring `property_location_dna`; keeps §8 scores queryable and symmetric without bloating the coverage-metric profile tables.
3. **Confidence formula** — define the completeness→confidence derivation before the first slice (it propagates everywhere).
4. **Compatibility-score semantics** — decide whether the new symmetric quality score lives beside the existing coverage `overall_score` (recommended: new `quality_score` column, coverage stays) to avoid breaking `phase-h-v1` consumers.
5. **First-slice pick** — Pet-Friendliness vs Lock-and-Leave (see Phase 2).

---

## 9. Compliance statement

No production code, schema, migration, or config was modified in producing this document. Nothing was committed. This is a companion planning artifact; the frozen v2.4 roadmap is unchanged. Phase 2 will begin only on your go-ahead and will introduce code changes under normal review.
