# Matching V2 — Consumption Slice 2B: Candidate Narrowing + Compliance Gates — Scope Proposal

**Status:** Draft for review / approval — **no code yet**
**Date:** 2026-07-05
**Depends on (merged baseline):**

- `65e49106e` — §MatchingV2 C1 — read-only `DnaScoreRepository` + `MATCHING_V2` flag
- `b69696f9b` — §MatchingV2 C2 — read-only `DnaMatchService` over §F6 batch matcher
- `d3a8b82b1` — §MatchingV2 C3 — Candidate Discovery **Stage A** (provider-agnostic universe over `dna_scores`)

**Governance inherited:** pure read-only over `dna_scores` and existing listing/criteria tables. No writes, no generation, no persisted match results, no UI/API. Inert when `MATCHING_V2_ENABLED=false`. **No consumer-facing exposure in this slice.**

---

## 0. What this slice is

Slice 2A (C3) ships **Stage A**: the bounded, provider-agnostic candidate *universe* from `dna_scores`. Slice 2B is **Stage B**: it narrows that universe by (1) a **mandatory** listing-eligibility + 55+/senior-community legal gate that always runs, and (2) **optional, config-gated** geo and attribute narrowing applied only "where safe." Output remains the same `['listing_type','listing_id']` tuple shape that `DnaMatchService` consumes. Still no scoring (the §F6 kernels own that), still no UI.

```
Stage A (C3, shipped)                  Stage B (this slice, 2B)
┌───────────────────────┐   tuples    ┌──────────────────────────────────────┐  tuples  ┌────────────────┐
│ ScoredEntity          │ ──────────▶ │ CandidateNarrowingPipeline           │ ───────▶ │ DnaMatchService │
│ CandidateSource       │  (capped)   │  1. eligibility gate   (mandatory)   │ (final)  │   (slice 1)     │
│  (dna_scores universe)│             │  2. 55+ compliance gate(mandatory)   │          └────────────────┘
└───────────────────────┘             │  3. geo narrow    (optional, safe)   │
                                       │  4. attribute narrow (optional, safe)│
                                       └──────────────────────────────────────┘
                                              │ reads (read-only), per candidate, BATCHED
                                              ├── *_agent_auction_metas  (leasing_55_plus, property_type, budget, bedrooms)
                                              ├── *_agent_auctions        (is_approved, is_sold lifecycle; workflow_type meta)
                                              ├── property_location_dna   (geocoded_lat / geocoded_lng — SAME (type,id) key)
                                              └── criteria loaders → BuyerCriteriaPayload (subject envelope only)
```

---

## 1. The addressing chain — RESOLVED (the prerequisite finding)

This was the open risk from C3. It is now fully traced and there is **no remapping and no vocabulary mismatch** to fight.

### 1.1 `dna_scores` subject identity

Every `dna_scores` row is written by one of four observers with a **hardcoded `listing_type` string** and `$listing->id`:

| `listing_type` | `listing_id` = | `side` |
|---|---|---|
| `seller_agent` | `seller_agent_auctions.id` | `property` |
| `landlord_agent` | `landlord_agent_auctions.id` | `property` |
| `buyer_agent` | `buyer_agent_auctions.id` | `demand` |
| `tenant_agent` | `tenant_agent_auctions.id` | `demand` |

`listing_id` is always the `*_agent_auctions` **primary key `id`**. `CanonicalListingResolver::resolve($type,$id)` and the criteria loaders' `loadById($id, …)` both treat it as that PK — consistent end to end.

### 1.2 Same-key joins that Stage B relies on

- **Geo:** `property_location_dna` uses the **identical** `_agent`-suffixed vocabulary (`seller_agent`/`landlord_agent`) and the same PK as `listing_id`. So `('seller_agent', id)` joins directly to `property_location_dna.(listing_type, listing_id)` → `geocoded_lat`, `geocoded_lng`. **No translation needed.** (The bare `seller`/`landlord` strings used by the *older* `PropertyDnaProfile` layer are a different vocabulary we never touch here.)
- **Attributes:** `CanonicalListingResolver::resolve($type, $id)` → `CanonicalListing` for all four types (find-by-PK).
- **Criteria (subject only):** `BuyerOfferListingCriteriaLoader::loadById($id, $allowedUserIds)` / `TenantOfferListingCriteriaLoader::loadById(...)`. `$id` = the `*_agent_auctions.id` = `dna_scores.listing_id`. `$allowedUserIds` is an ownership ACL derived from `*AgentAuction::find($id)->user_id` (the `user_id` column exists on the auction row). These loaders additionally require `workflow_type='offer_listing'`, `is_approved=true`, `is_sold=false` — see the eligibility gate (§4.1).

### 1.3 Where 55+/senior data actually lives

| Side | Meaning | Source reachable from `(listing_type, listing_id)` |
|---|---|---|
| Demand (`buyer_agent`/`tenant_agent`) | seeker qualifies/wants 55+ | `leasing_55_plus` meta → `is_55_plus_eligible` (canonical `demand.age_targeted`) |
| Property (`seller_agent`/`landlord_agent`) | listing is an age-restricted 55+ community | `leasing_55_plus` **meta on the auction row** (raw `->info('leasing_55_plus')`). **NOT projected into the canonical layer** — must be read from meta. |
| Bridge MLS (out of scope in 2A/2B) | — | native `bridge_properties.senior_community_yn` |

**Key simplification:** both sides store senior status under the *same meta key* `leasing_55_plus` on their respective `*_agent_auctions` rows. The gate reads one meta key uniformly; only the *semantics* differ by side.

### 1.4 ⚠️ Compliance surface discovered (owner must be aware — OD-4)

`LockAndLeaveScoreService::scoreDemand()` **leaks 55+ into `dna_scores` today** on the demand side: `demand.age_targeted === true` adds `+15` to the `lock_and_leave` value, writes `inputs_json.age_targeted`, and writes the literal string `"55+ targeted"` into `explanation`. This is **generation-side** and therefore **outside this read-only slice's remit to fix**, but Slice 2B must not *depend* on that leak (it will read `leasing_55_plus` directly, not scrape `inputs_json`), and the owner should decide whether a separate generation-side ticket is warranted (OD-4).

---

## 2. Architecture

Same design language as C3: a small provider-agnostic seam plus value objects, all in `app/Services/Dna/Relevance/`. The **narrowing logic is provider-agnostic**; the **provider-specific reads live behind one resolver interface** (satisfying the "no provider branching" directive — the gate never learns which provider a candidate came from).

### 2.1 New classes

| Class | Responsibility |
|---|---|
| `CandidateAttributeProfile` (VO) | Normalized, provider-neutral fact sheet for one `(listing_type, listing_id)`: `isSeniorRestricted: ?bool`, `is55PlusEligible: ?bool`, `lat: ?float`, `lng: ?float`, `propertyType: ?string`, `isEligibleListing: bool` (approved + active + offer-listing), `side: 'property'\|'demand'`. `null` = "unknown / not present." |
| `CandidateAttributeResolverInterface` | `resolveMany(string $side, array $tuples): array<string,CandidateAttributeProfile>` — **batch** by design (keyed by `"type:id"`). One implementation ships now. Read-only. |
| `OnPlatformCandidateAttributeResolver` | The one shipped resolver. Batch-loads `leasing_55_plus` + `property_type` meta per type, lifecycle columns (`is_approved`,`is_sold`) + `workflow_type` meta per type, and `property_location_dna` geo per type. No per-candidate N+1. |
| `CandidateNarrowingPipeline` | Orchestrates the ordered narrowers over a `CandidateSet`, returns a narrowed `CandidateSet`. Mandatory gates always run; optional narrowers run only when `hard_filters_enabled` + data present. |
| `ListingEligibilityGate` (narrower) | Drops candidates that are not an approved, active, offer-listing auction (§4.1). Mandatory. |
| `SeniorCommunityComplianceGate` (narrower) | The 55+ legal gate (§4.2). Mandatory, never behind the optional flag. |
| `GeoEnvelopeNarrower` (narrower) | Optional bounding-box + exact Haversine/PIP narrowing, DemandToListings direction only (§4.3). |
| `AttributeNarrower` (narrower) | Optional categorical narrowing — property-type match only in 2B (§4.4). |
| `CandidateNarrower` (interface) | `narrow(CandidateSet, NarrowingContext): CandidateSet`. Uniform seam so gates/narrowers compose and are individually testable. |
| `NarrowingContext` (VO) | Carries the subject `(type,id,direction)`, the resolved subject `CandidateAttributeProfile`, the subject's optional `BuyerCriteriaPayload` (for geo/attr), and the resolved candidate profile map. |

### 2.2 Integration into C3

`CandidateDiscoveryService::discover()` gains an internal Stage B call **behind the flag**: after `ScoredEntityCandidateSource` returns the (over-fetched) Stage A set, it runs `CandidateNarrowingPipeline` and trims to the final cap. When `hard_filters_enabled=false`, only the two mandatory gates run; geo/attribute narrowers are skipped. The public `discover()` signature is unchanged.

### 2.3 Reuse (no logic duplication)

- **Geo math:** reuse `PolygonBoundingBox::fromPayload()` and the Haversine/point-in-polygon helpers already in `BuyerMatchScorer` / `LocationMatchEngine` (per C3 decision **D3**, reuse in place — do **not** extract a shared helper yet).
- **Attributes:** reuse `CanonicalListingResolver` / `CanonicalListing`.
- **Criteria:** reuse the two offer-listing loaders → `BuyerCriteriaPayload` (D4-confirmed identical shape).
- **dna_scores reads:** unchanged `DnaScoreRepository`.

---

## 3. Files to add / modify

### Add

| Path | Purpose |
|---|---|
| `app/Services/Dna/Relevance/CandidateAttributeProfile.php` | Normalized per-candidate fact VO. |
| `app/Services/Dna/Relevance/CandidateAttributeResolverInterface.php` | Batch resolver seam. |
| `app/Services/Dna/Relevance/OnPlatformCandidateAttributeResolver.php` | The shipped resolver (meta + lifecycle + geo, batched). |
| `app/Services/Dna/Relevance/NarrowingContext.php` | Context VO passed to each narrower. |
| `app/Services/Dna/Relevance/CandidateNarrower.php` | Narrower interface. |
| `app/Services/Dna/Relevance/CandidateNarrowingPipeline.php` | Ordered composition + final trim. |
| `app/Services/Dna/Relevance/Narrowers/ListingEligibilityGate.php` | Mandatory eligibility gate. |
| `app/Services/Dna/Relevance/Narrowers/SeniorCommunityComplianceGate.php` | Mandatory 55+ legal gate. |
| `app/Services/Dna/Relevance/Narrowers/GeoEnvelopeNarrower.php` | Optional geo narrowing. |
| `app/Services/Dna/Relevance/Narrowers/AttributeNarrower.php` | Optional property-type narrowing. |
| `tests/Unit/Dna/OnPlatformCandidateAttributeResolverTest.php` | Batch resolver correctness + N+1 guard. |
| `tests/Unit/Dna/SeniorCommunityComplianceGateTest.php` | The legal gate matrix (both directions, unknowns). |
| `tests/Unit/Dna/ListingEligibilityGateTest.php` | Draft/sold/non-offer-listing exclusion. |
| `tests/Unit/Dna/GeoEnvelopeNarrowerTest.php` | Bbox + Haversine/PIP inclusion/exclusion + fail-open. |
| `tests/Unit/Dna/CandidateNarrowingPipelineTest.php` | Ordering, mandatory-vs-optional, flag behavior, trim. |
| `tests/Feature/Dna/CandidateNarrowingComplianceTest.php` | End-to-end: seed dna_scores + metas + geo → discover → assert gated set → feeds DnaMatchService. |

### Modify

| Path | Change | Risk |
|---|---|---|
| `app/Services/Dna/Relevance/CandidateDiscoveryService.php` | After Stage A, run the narrowing pipeline behind the flag; over-fetch then trim. Inject the pipeline. | Medium — the one behavioral change; covered by tests + flag. |
| `config/matching.php` | Under `candidate_discovery`: add `overfetch_multiplier`, `senior_unknown_policy`, and geo/attribute sub-keys. `hard_filters_enabled` (already present, currently inert) becomes live for geo/attribute narrowers only — **never** gates the two mandatory gates. | Low — additive config. |
| `app/Providers/AppServiceProvider.php` | Bind `CandidateAttributeResolverInterface` → `OnPlatformCandidateAttributeResolver`; wire the pipeline + narrowers. | Low — additive bindings. |

**Not modified:** `DnaMatchService`, `BatchRelevanceMatcher`, `ScoredEntityCandidateSource`, `DnaScoreRepository`, any generator/observer, any migration. **No schema change.** The `initializeLimitedService()` frozen code and `TenantAgentAuction` trait exclusion are untouched.

---

## 4. Candidate narrowing strategy (ordered)

Narrowers run cheapest-and-most-selective first. Every narrower is **fail-open on missing data unless it is a mandatory legal gate with an explicit policy** — "narrow only where safe."

### 4.1 Listing eligibility gate — MANDATORY

Drops candidates that are not a *live marketplace listing*: keep only auctions with `is_approved=true`, `is_sold=false`, and `workflow_type='offer_listing'`. Rationale: the DNA observers fire on **every** save of the four `*_agent` types — including Hire-an-Agent records, drafts, and sold auctions — so the raw `dna_scores` universe contains non-marketplace subjects that must never be surfaced as candidates. This gate is the reason the criteria loaders' own `offer_listing/approved/!sold` constraints line up. Data is always present (lifecycle columns + one meta), so no fail-open ambiguity. **Owner decision OD-2** confirms this is desired.

### 4.2 Senior-community (55+) compliance gate — MANDATORY, never optional

Symmetric legal gate, direction-aware:

- **DemandToListings** (subject = seeker): if the **subject** seeker is *not* 55+ eligible → drop candidate listings that *are* senior-restricted.
- **ListingToDemands** (subject = listing): if the **subject** listing *is* senior-restricted → drop candidate seekers that are *not* 55+ eligible.

Both facts come from `leasing_55_plus` meta via the resolver (no criteria-loader dependency — robust even for records the loader would reject). **Unknown-data policy (OD-1):** default **fail-open** — treat unknown senior-restriction as *not restricted* (include), matching the existing Stellar gate (`senior_community_yn IS NULL` passes) and the legally-conservative principle that the FHA risk is *wrongly excluding* families, not over-including. Configurable via `senior_unknown_policy` (`open`|`closed`). This gate runs **regardless of `hard_filters_enabled`**.

### 4.3 Geo / location narrowing — OPTIONAL ("where safe"), DemandToListings only in 2B

When `hard_filters_enabled=true` **and** the subject seeker has a usable geographic envelope (radius/polygon/city/zip/county from its `BuyerCriteriaPayload`) **and** a candidate has `property_location_dna` coordinates: apply the coarse `PolygonBoundingBox` envelope then exact Haversine/point-in-polygon (reused helpers) over the small narrowed set. A candidate with **no** geocode is **kept** (fail-open — "where safe"). **Direction asymmetry (OD-5):** cheap only in DemandToListings (one subject envelope vs candidate points). The reverse (ListingToDemands) would require loading each candidate seeker's envelope (N criteria loads) and is **deferred**; geo narrowing is a no-op in that direction in 2B.

### 4.4 Attribute narrowing — OPTIONAL ("where safe"), minimal in 2B

When `hard_filters_enabled=true`: **property-type match only** — drop candidates whose `property_type` is categorically incompatible with the subject's `property_types`, when both are present; keep when either is unknown. Price/bed/bath narrowing is **deliberately excluded** from 2B (OD-6): tenant budget vs sale `list_price` semantics, null-tolerance, and range scoring belong to the §F6 scorer, not a hard pre-filter. Over-restricting here silently hides valid matches.

### 4.5 Cap handling

Stage A over-fetches by `overfetch_multiplier` (default **3×** the final cap, hard-ceilinged) so the mandatory gates have headroom to remove ineligible/senior-mismatched rows before the final trim to `cap`. If the narrowed set still exceeds `cap`, trim deterministically (same `(listing_type, listing_id)` order). `wasTruncated()` reflects the **post-narrow** truncation; a `log()` line records how many were dropped per gate so a heavily-narrowed pool is never mistaken for a healthy one.

---

## 5. Avoiding provider-specific branching

- The **narrowers consume only `CandidateAttributeProfile`** — a normalized, provider-neutral VO. They contain **zero** references to `seller_agent`, `bridge_properties`, meta keys, or provider names.
- All provider-specific reads are isolated in **`OnPlatformCandidateAttributeResolver`**, behind `CandidateAttributeResolverInterface`. A future `BridgeCandidateAttributeResolver` (reading `senior_community_yn`, native lat/lng) is an additive implementation; the gates do not change. This mirrors C3's `CandidateSourceInterface` decision and directly honors the "any DNA-enabled provider becomes discoverable" goal (C3 §0.2).
- In the current universe (C3 D1: `dna_scores` subjects only, all on-platform `_agent` types), the single on-platform resolver covers 100% of candidates — so there is no branching *and* no coverage gap today.

---

## 6. Performance

- **Batch resolution, no N+1.** `resolveMany()` groups candidate ids by `listing_type` and issues a *fixed* number of queries per type: one `whereIn` on `*_agent_auction_metas` for `leasing_55_plus`+`property_type`+`workflow_type`, one on the auction table for `is_approved`/`is_sold`, one on `property_location_dna` for geo. So Stage B is **O(number of listing_types)** queries (≤ ~8), not O(candidates). A unit test asserts the query count is bounded regardless of candidate count.
- **Over-fetch is bounded.** `overfetch_multiplier` × `cap` with a hard ceiling (e.g. 1000) prevents pathological pre-narrow sets.
- **Exact geo runs over the already-narrowed, capped set** (≤ a few hundred), reusing existing PHP helpers — never over the whole table.
- **Mandatory-only path is cheap.** With `hard_filters_enabled=false`, only two gates run off the batched profiles (no geo math, no criteria load).
- **Criteria loaded once** (subject only), never per candidate, and only when geo/attribute narrowing is active.

---

## 7. Read-only guarantees

- Only `SELECT`s: meta tables, auction lifecycle columns, `property_location_dna`, criteria loaders (already read-only), `dna_scores` via the unchanged repository.
- No writes, no `updateOrCreate`, no cache writes, no generation triggers, no migration.
- A feature test asserts row counts across `dna_scores`, the meta tables, and `property_location_dna` are unchanged after discovery+narrowing (mirrors the C3 read-only test).

---

## 8. Feature-flag behavior

- **Master gate unchanged:** `MATCHING_V2_ENABLED` (default false). Flag off → `discover()` returns empty with zero reads (Stage B never runs).
- **`hard_filters_enabled`** (default false) gates **only** the optional geo (§4.3) and attribute (§4.4) narrowers. The two **mandatory gates (§4.1, §4.2) always run** whenever V2 is on — they are *not* behind this flag (this is the concrete implementation of C3 decision D2: "hard filters disabled initially, but always enforce legal/compliance gates").
- **`senior_unknown_policy`** (`open` default) tunes the mandatory 55+ gate's behavior on missing data (OD-1).
- No UI/API surface is added. This slice is reachable only by internal service calls + tests.

---

## 9. Test plan

- **`OnPlatformCandidateAttributeResolverTest`** — correct profile per type; senior/property_type from meta; eligibility from lifecycle+workflow_type; geo from `property_location_dna`; unknowns → `null`; **bounded query count** for many candidates (N+1 guard via `DB::listen`/query counter).
- **`SeniorCommunityComplianceGateTest`** — full matrix: {subject eligible/not, candidate restricted/not/unknown} × {both directions}; assert fail-open under `open` and fail-closed under `closed`; assert the gate runs even with `hard_filters_enabled=false`.
- **`ListingEligibilityGateTest`** — drops draft (`is_approved=false`), sold (`is_sold=true`), and Hire-an-Agent (`workflow_type≠offer_listing`) subjects; keeps live offer-listings.
- **`GeoEnvelopeNarrowerTest`** — in/out of radius bbox + exact Haversine; polygon PIP; candidate with no geocode kept (fail-open); no-op in ListingToDemands direction.
- **`AttributeNarrowerTest`** — property-type mismatch dropped; unknown either side kept; price/beds never filtered.
- **`CandidateNarrowingPipelineTest`** — narrower ordering; mandatory-only vs full; final trim + post-narrow `wasTruncated()`; drop-count logging.
- **`CandidateNarrowingComplianceTest`** (feature) — seed `dna_scores` + `*_agent_auction_metas` (`leasing_55_plus`, `workflow_type`, `property_type`) + `property_location_dna`; run `discover()`; assert a non-eligible seeker never receives a senior-restricted listing; assert the narrowed tuples still feed `DnaMatchService` and rank; assert read-only.
- SQLite in-memory, `DatabaseTransactions`, matching the existing `tests/Unit/Dna` + `tests/Feature/Dna` conventions.

---

## 10. Explicit out-of-scope (this slice)

- **Scoring/ranking/tiering** — §F6 kernels.
- **Consumer-facing exposure** — no controller, route, Livewire, Blade. Any surfacing of narrowed candidates is a separate, explicitly-approved slice **and is where the 55+ gate becomes legally load-bearing at the point of display.**
- **Bridge / RentCast / other-provider candidate sources and their resolvers** — deferred (C3 D1); the resolver seam is ready for them.
- **ListingToDemands geo narrowing** (per-candidate envelopes) — deferred (OD-5).
- **Price/bedroom/bathroom/sqft hard pre-filters** — left to the scorer (OD-6).
- **Fixing the demand-side 55+ leak into `dna_scores`** (§1.4) — generation-side, separate ticket (OD-4).
- **Any `dna_scores` index/migration** — deferred (C3 D5).
- **`TenantAgentAuction` refactor** and anything inside `initializeLimitedService()` — frozen.

---

## 11. Risks & owner decisions

| ID | Decision / risk | Options | Recommendation |
|---|---|---|---|
| **OD-1** | 55+ gate behavior on **unknown** candidate senior-status. | (a) fail-open (unknown = not restricted, include). (b) fail-closed (unknown = restricted, exclude for non-eligible seekers). | **(a)** — matches Stellar's `NULL`-passes gate and the FHA principle that wrongful *exclusion* of families is the real risk. Configurable via `senior_unknown_policy`. |
| **OD-2** | Mandatory **listing-eligibility** gate (drop draft/sold/Hire-an-Agent `dna_scores` subjects). | (a) include it as mandatory. (b) treat raw `dna_scores` universe as all-valid. | **(a)** — the observers score non-marketplace records; without this gate discovery would surface drafts and hire-agent records. |
| **OD-3** | Stage-A **over-fetch multiplier** before narrowing. | (a) 3× cap, hard-ceiling 1000. (b) other. | **(a)** — headroom for gate removals without unbounded pre-fetch. Tune after profiling. |
| **OD-4** | The demand-side **55+ leak into `dna_scores`** (`lock_and_leave` value / `inputs_json.age_targeted` / `"55+ targeted"` explanation). | (a) open a separate generation-side remediation ticket. (b) accept as-is. | Owner call. 2B does **not** depend on it, but it is a real age-data-in-artifact surface worth a decision. |
| **OD-5** | **Geo narrowing direction coverage** in 2B. | (a) DemandToListings only; defer ListingToDemands. (b) both now. | **(a)** — reverse direction needs N per-candidate criteria loads; cost/benefit doesn't justify it yet. |
| **OD-6** | **Attribute narrowing** breadth. | (a) property-type match only. (b) also price/beds. | **(a)** — price/bed pre-filters are unsafe (tenant budget vs sale price, null-tolerance) and belong to the scorer. |
| **R1** | On-platform `leasing_55_plus` meta may be **sparsely populated** → gate mostly fails-open. | — | Acceptable given no consumer exposure in 2B; the gate becomes strict-by-data-quality at the exposure slice. Log senior-unknown rate for visibility. |
| **R2** | Over-narrowing silently shrinks pools. | — | Per-gate drop-count `log()` + post-narrow `wasTruncated()`; feature test asserts counts. |
| **R3** | Criteria loader rejects a valid subject (not offer-listing / sold) → no envelope for geo. | — | Geo narrower fails-open (keeps candidates) when the subject envelope is absent; only the mandatory gates depend on meta, not the loader. |

---

## 11a. Implementation notes (as-built — §MatchingV2 C4)

Two defensible deviations from the scope above, both preserving the guarantees:

1. **Attribute reads go through batched meta queries, not per-candidate `CanonicalListingResolver::resolve()`.** `resolve()` is find-by-PK per id (an N+1 over the candidate set); `OnPlatformCandidateAttributeResolver` instead issues a fixed 3 queries per listing_type (meta / lifecycle / geo) via the query builder. This honors the §6 "no N+1" guarantee. The canonical layer also does **not** surface property-side 55+ (§1.3), so meta is the correct source regardless. The resolver seam still isolates all provider-specific reads.
2. **Geo runs exact Haversine + point-in-polygon directly over the already-capped set**, which subsumes the coarse `PolygonBoundingBox` pre-filter (that pre-filter exists for the SQL/OData paths that cannot run PHP geometry). At Stage-B scale (≤ cap candidates) the exact test is both correct and cheap, so the D3 bounding-box reuse is unnecessary here; `PolygonBoundingBox` remains untouched.

Also as-built: the `CandidateNarrower` interface operates on tuple arrays (the pipeline owns `CandidateSet` construction/trim); config adds `overfetch_multiplier` (3), `overfetch_ceiling` (1000), and `senior_unknown_policy` (`open`).

---

## 12. Summary of the ask

Approve (1) the resolved addressing chain in §1 as the basis for Stage B, (2) the two **mandatory** gates (eligibility + 55+) that run whenever V2 is on, independent of `hard_filters_enabled`, (3) the **optional** geo/attribute narrowers gated by `hard_filters_enabled` with fail-open "where safe" semantics, (4) the provider-agnostic resolver seam, and (5) decisions **OD-1…OD-6**. On approval this ships as one isolated commit (`§MatchingV2 C4`) — the classes in §3, the `CandidateDiscoveryService` Stage-B integration, additive config/bindings, and the §9 tests — pure read-only, no UI. **No code will be written until this scope is approved.**
