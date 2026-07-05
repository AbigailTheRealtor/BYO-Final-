# Matching V2 — Consumption Slice 2: Candidate Discovery — Scope Proposal

**Status:** Draft for review / approval — **no code yet**
**Author:** (drafted for review)
**Date:** 2026-07-05
**Depends on (baseline, already merged):**

- `65e49106e` — §MatchingV2 C1 — read-only `DnaScoreRepository` + `MATCHING_V2` flag
- `b69696f9b` — §MatchingV2 C2 — read-only `DnaMatchService` over §F6 batch matcher

**Governance inherited from baseline:** everything in this slice is a **pure read-only consumer** of `dna_scores` and the existing listing/criteria tables. It writes nothing, generates nothing, persists no match results, and is inert when `MATCHING_V2_ENABLED=false`.

---

## 0. Approved decisions & the long-term architecture (READ FIRST)

### 0.1 Owner decisions (approved 2026-07-05)

| ID | Decision | Approved outcome |
|---|---|---|
| **D1** | Candidate source scope for Slice 2. | **On-platform `dna_scores`-addressed entities only.** Keeps Matching V2 fully read-only and avoids mixing matching universes before DNA generation exists everywhere. |
| **D2** | Hard filters on first rollout. | **Optional hard (preference) filters stay disabled initially.** Legal/compliance gates such as 55+ senior-community restrictions are **always enforced where applicable** — never behind the optional-filter flag. |
| **D3** | Bounding-box math reuse. | **Reuse the existing `PolygonBoundingBox` implementation in place.** Do not extract a shared helper until multiple consumers justify it. |
| **D4** | Tenant vs buyer criteria payload shape. | **Confirmed identical** — both `BuyerOfferListingCriteriaLoader` and `TenantOfferListingCriteriaLoader` emit a flat array consumed by `BuyerCriteriaPayload::__construct()` (the tenant loader adds one extra key, `preferred_lease_terms`, otherwise identical). No duplication of matching logic needed; if a future divergence appears, normalize rather than fork. |
| **D5** | Supporting `dna_scores` index. | **Deferred** to a later optimization phase unless profiling demonstrates it is required. No migration in this slice. |

### 0.2 ⚠️ `dna_scores`-as-candidate-universe is a Slice 2 implementation decision — NOT the long-term architecture

**This must not be forgotten.** Slice 2 draws its candidate universe from the current `dna_scores` population (D1) purely because that is the only DNA-enabled source that exists today. **The long-term architecture is provider-agnostic candidate discovery over the unified DNA layer.**

The design goal — which the code and config below are shaped to preserve — is:

> **Any listing, or any buyer/tenant profile, that has generated DNA must automatically become discoverable by Matching V2, regardless of which provider it originated from.**

DNA-enabled sources that must be first-class citizens of the discovery layer over time include, at minimum:

- Platform (on-platform) listings — *the only source wired in Slice 2*
- Stellar MLS
- RentCast
- Future MLS providers
- Future external property datasets

Once every source has generated DNA, candidate discovery must operate over the **unified DNA layer** and must **not** treat on-platform listings differently from MLS/RentCast/other-provider listings. Provider origin becomes irrelevant at discovery time — presence of DNA on the shared `(listing_type, listing_id, side)` axis is the only membership test.

**How the Slice 2 design protects this goal (anti-coupling guarantees):**

1. **Membership is defined by "has counterpart-side DNA," never by provider.** The shipped source (`ScoredEntityCandidateSource`) selects distinct `(listing_type, listing_id)` from `dna_scores` filtered only by `side`. It does **not** hardcode which providers/listing-types are eligible. Any new provider that writes DNA rows becomes discoverable with **zero discovery-layer code changes**.
2. **No listing-type allowlist by default.** `config('matching.candidate_discovery.allowed_listing_types')` defaults to **empty = all types**. `side` alone separates supply from demand. Type scoping is available for ops/testing but is *off* by default precisely so a new provider is not silently excluded.
3. **A `CandidateSourceInterface` seam exists from day one.** Adding, e.g., a future direct-provider source is an additive implementation behind the same interface; the orchestrator (`CandidateDiscoveryService`) never learns provider identities.
4. **The provider-specific coupling that *does* exist in Slice 2 (the criteria loaders, geo joins) lives only in the deferred Stage B**, which is not shipped active in this slice (see §5). The always-on discovery path (Stage A) is already provider-agnostic.

Any future change that reintroduces per-provider branching into the discovery layer (rather than into DNA *generation*) is a regression against this decision and should be rejected in review.

---

## 1. Problem statement & where this slice sits

Slice 1 (`DnaMatchService`) deliberately left **candidate discovery out of scope**. Both the pure kernel (`BatchRelevanceMatcher`, header lines 17–20) and the entrypoint (`DnaMatchService`, header lines 21–23) require the **caller** to hand in an explicit candidate list:

```php
// DnaMatchService.php:46 / :67 — the seam this slice fills
public function matchListingAgainstDemands(string $listingType, int $listingId, array $candidates): RankedMatchSet
public function matchDemandAgainstListings(string $listingType, int $listingId, array $candidates): RankedMatchSet
// $candidates: array<int,array{listing_type:string,listing_id:int}>
```

Today no code produces that `$candidates` array for the DNA/§F6 path. My exploration confirmed that the **only** existing candidate pre-filter in the codebase is the Stellar/Bridge path (`BuyerMatchQueryBuilder` → `BuyerMatchService`), which narrows `bridge_properties` for the *legacy* MLS scorer — a **different matching universe** from `dna_scores`.

**Slice 2 delivers exactly one thing:** a read-only service that, given a subject `(listing_type, listing_id)` and a direction, produces a **bounded, deterministic candidate list** of `['listing_type' => …, 'listing_id' => …]` tuples suitable for direct hand-off to `DnaMatchService`. It performs **no scoring** — the §F6 kernels already own that.

```
┌─────────────────────┐   candidates[]   ┌──────────────────┐   scores    ┌───────────────────────┐
│ CandidateDiscovery  │ ───────────────▶ │  DnaMatchService  │ ─────────▶ │ BatchRelevanceMatcher │
│ Service  (Slice 2)  │  (type,id tuples)│   (Slice 1)       │            │  (§F6 pure kernel)     │
└─────────────────────┘                  └──────────────────┘            └───────────────────────┘
        │ reads (read-only)
        ├── dna_scores          (authoritative "who has been scored" universe)
        ├── criteria loaders    (BuyerOfferListingCriteriaLoader / Tenant sibling → BuyerCriteriaPayload)
        └── listing source geo/attrs (property_location_dna, property_auctions, landlord_auctions)
```

---

## 2. The critical addressing constraint (drives the whole design)

`dna_scores` is addressed by a **polymorphic canonical key**: `(listing_type, listing_id, score_key, side)` (migration `2026_07_02_000001_create_dna_scores_table.php:33–36`, unique index line 61). `side` ∈ `{property, demand}`. There is **no** `bridge_property_id` / MLS key column.

Consequence: the §F6 engine can only match entities that **already have `dna_scores` rows**. Those are the **on-platform** entities produced by the Phase 13 generators (addressed by `(listing_type, listing_id)`), **not** the external `bridge_properties` cache (addressed by `listing_key`). Therefore:

> **Candidate Discovery for the DNA path must draw candidates from the set of entities that actually have counterpart-side `dna_scores`.** `bridge_properties` is explicitly **out of scope** for this slice (see §12 and Owner Decision D1).

This makes `dna_scores` itself the authoritative candidate universe, which is elegant: a candidate that has no counterpart-side scores would contribute nothing to the match anyway (the kernel would receive an empty score-set for it), so filtering to score-bearing entities is both a correctness gate and the primary pool-limiting mechanism.

---

## 3. Proposed architecture

A single orchestrator with a small, source-pluggable seam. All new classes live alongside the existing Matching V2 code in `app/Services/Dna/Relevance/`.

### 3.1 New classes

| Class | Responsibility |
|---|---|
| `CandidateDiscoveryService` | Orchestrator. Given subject `(listing_type, listing_id)` + `MatchDirection`, returns a bounded `CandidateSet`. Enforces the feature flag, the cap, and deterministic ordering. **Read-only.** |
| `CandidateSet` (value object) | `final` immutable holder: the `array<int,array{listing_type,listing_id}>` tuples plus `total()`, `wasTruncated()`, `toArray()`. Mirrors the shape `DnaMatchService` consumes verbatim. |
| `MatchDirection` (enum) | `ListingToDemands` \| `DemandToListings`. Encapsulates the "which `side` am I looking for in candidates" decision so it can't be inverted by accident. |
| `ScoredEntityCandidateSource` | The **one shipped source** in this slice. Resolves the candidate universe from `dna_scores` (distinct `(listing_type, listing_id)` on the counterpart `side`), optionally narrowed by a cheap hard-attribute/geo pre-filter, then capped. Read-only. |
| `CandidateSourceInterface` | Seam: `resolve(CandidateQuery): CandidateSet`. Lets a future slice add a `BridgePropertyCandidateSource` without touching the orchestrator. Only one implementation ships now. |
| `CandidateQuery` (value object) | Normalized discovery request: counterpart `side`, allowed `listing_type`s, optional `BuyerCriteriaPayload`/geo envelope, cap, exclusions (self). |

### 3.2 Reuse (no new copies of existing logic)

- **Criteria normalization:** reuse `App\Services\Stellar\BuyerOfferListingCriteriaLoader` and its tenant sibling `TenantOfferListingCriteriaLoader` → `App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload`. This already turns `buyer_agent_auction_metas` (`workflow_type = offer_listing`) into the canonical criteria DTO (cities/zips/counties, radius searches, polygons, max price, property types, beds/baths, 55+ gate). **We do not re-parse metas.**
- **Geo envelope:** reuse `App\Services\Bridge\OData\PolygonBoundingBox::fromPayload(BuyerCriteriaPayload)` and the bounding-box math already proven in `BuyerMatchQueryBuilder::applyGeographicFilter()` (lines 87–166). Slice 2 will **extract** that bbox-building into a shared helper (see §4) rather than duplicate it, but the algorithm and constants (`LAT_MILES_PER_DEGREE = 69.0`) are unchanged.
- **Score existence / read seam:** reuse `DnaScoreRepository` for the actual score reads inside `DnaMatchService` (unchanged). Discovery only needs the *existence* of counterpart-side rows, which it queries directly against `dna_scores` via a new **read-only** repository method (see §4).

### 3.3 Data flow (happy path, `DemandToListings`)

1. Caller: `discover($listingType, $listingId, MatchDirection::DemandToListings)`.
2. Flag check — if `MATCHING_V2_ENABLED=false`, return an **empty `CandidateSet` with zero DB reads** (mirrors `DnaMatchService::inert()`).
3. Load the subject's criteria via the appropriate loader → `BuyerCriteriaPayload` (buyer or tenant).
4. Build a `CandidateQuery`: counterpart `side = property`, allowed listing types (e.g. `['seller','landlord']`), geo envelope from `PolygonBoundingBox::fromPayload`, hard attribute gates (price ceiling, min beds/baths, 55+), cap from config, exclude self.
5. `ScoredEntityCandidateSource::resolve()`:
   a. Base set = `SELECT DISTINCT listing_type, listing_id FROM dna_scores WHERE side = 'property' AND listing_type IN (…)`.
   b. Narrow by cheap hard gates joined from the listing source tables (see §5.2).
   c. Deterministic order + `LIMIT cap` (+1 to detect truncation).
6. Return `CandidateSet`.
7. Caller passes `CandidateSet::toArray()` straight into `DnaMatchService::matchDemandAgainstListings(...)`.

The `ListingToDemands` direction is symmetric with `side = 'demand'` and the demand-side listing types (`buyer`, `tenant`).

---

## 4. Files to add / modify

### Add

| Path | Purpose |
|---|---|
| `app/Services/Dna/Relevance/CandidateDiscoveryService.php` | Orchestrator (public API in §6). |
| `app/Services/Dna/Relevance/CandidateSet.php` | Immutable result VO. |
| `app/Services/Dna/Relevance/MatchDirection.php` | Direction enum. |
| `app/Services/Dna/Relevance/CandidateQuery.php` | Normalized query VO. |
| `app/Services/Dna/Relevance/CandidateSourceInterface.php` | Source seam. |
| `app/Services/Dna/Relevance/ScoredEntityCandidateSource.php` | The one shipped source (over `dna_scores`). |
| `app/Support/Geo/BoundingBoxBuilder.php` *(or reuse in place — see D3)* | Shared bbox helper extracted from `BuyerMatchQueryBuilder`/`PolygonBoundingBox`. |
| `tests/Unit/Dna/CandidateDiscoveryServiceTest.php` | Unit tests (§10). |
| `tests/Unit/Dna/ScoredEntityCandidateSourceTest.php` | Source-level tests (§10). |
| `tests/Feature/Dna/CandidateDiscoveryFlagTest.php` | Flag on/off behavior (§10). |

### Modify

| Path | Change | Risk |
|---|---|---|
| `config/matching.php` | Add `candidate_discovery` block: `cap`, `allowed_listing_types` (**defaults empty = all types / provider-agnostic**, see §0.2), and `hard_filters_enabled` (reserved for the deferred Stage B, default `false`). No new env master-gate — reuse `v2_enabled`. | Low — additive config only. |
| `app/Services/Dna/Relevance/DnaScoreRepository.php` | Add **one** read-only method `distinctSubjects(string $side, array $listingTypes, int $limit): array` returning `[['listing_type'=>…,'listing_id'=>…], …]`. No change to existing methods. | Low — additive, read-only. |

> **Not modified:** `DnaMatchService`, `BatchRelevanceMatcher`, any generator, any migration, `BuyerMatchService`/`BuyerMatchQueryBuilder` (we *read* their helpers; if we extract shared bbox math we do it behind a delegating call so the Stellar path is byte-for-byte unchanged — see Owner Decision D3). No schema changes. No new migration.

---

## 5. Candidate selection strategy

### 5.1 Two-stage funnel — **Slice 2 ships Stage A + cap only; Stage B is deferred**

**Stage A — universe from `dna_scores` (authoritative, cheap, indexed) — SHIPS IN SLICE 2.**
`SELECT DISTINCT listing_type, listing_id FROM dna_scores WHERE side = :side [AND listing_type IN (:types)] AND NOT (listing_type = :selfType AND listing_id = :selfId) ORDER BY listing_type, listing_id LIMIT :cap+1`. The `listing_type IN (:types)` clause is **omitted by default** (provider-agnostic per §0.2) and applied only if `allowed_listing_types` is configured non-empty. This alone guarantees every candidate has usable counterpart scores and bounds the set to "things that have DNA on the counterpart side." On current data volumes this is small; the cap in Stage C is the hard ceiling regardless.

**Stage B — hard-attribute / geo / legal narrowing — DEFERRED to a follow-up slice.**

Per D2, optional preference filters are disabled initially. Slice 2 therefore does **not** build the geo/attribute joins at all — the ultimate form of "disabled." The section below documents the intended Stage B design so the follow-up slice has a spec, and records the two reasons deferral is safe:

1. **No consumer exposure in Slice 2.** Discovery output is consumed only by the §F6 kernels; nothing reaches an end user, so no legal-exposure event can occur from Slice 2 alone. The binding 55+/legal gate is a **mandatory, non-optional** part of both Stage B and the (separate, out-of-scope) consumer-exposure slice.
2. **Stage B's join sources need their own verification.** The on-platform senior-community / attribute / geo columns differ by role (seller native vs landlord EAV) and are not uniformly present; wiring them without verification would risk a silently-wrong legal gate. That verification belongs to the Stage B slice, not here.

**Deferred Stage B design (for the follow-up slice, not built now):**
Where the subject has criteria (price ceiling, min beds/baths, property type, 55+ legal gate, geographic envelope), narrow the Stage-A set by joining the listing source's cheap columns:
- On-platform seller listings → native columns on `property_auctions` (`starting_price`, `year_built`, `pool`, `garage`, `city_id/state_id/county_id`, …).
- On-platform landlord listings → `landlord_auction_metas` EAV (respecting the seller/landlord native-vs-meta asymmetry called out in `CLAUDE.md`).
- Geo → `property_location_dna.geocoded_lat/geocoded_lng` (keyed by the same `(listing_type, listing_id)`), filtered by the bounding box from `PolygonBoundingBox::fromPayload`.

Stage B is **coarse and over-inclusive by design** (bounding box, not exact Haversine; `>=`/`<=` hard gates, not scored ranges) — exactly like the Stellar pre-filter. Exact geo/ranking stays where it belongs: the §F6 kernel and its aggregator. If `hard_filters_enabled=false`, Stage B is skipped entirely and only Stage A + cap apply (safest default for first rollout — see D2).

**Stage C — deterministic order + cap.**
`ORDER BY listing_type ASC, listing_id ASC LIMIT cap+1`. The `+1` lets us set `CandidateSet::wasTruncated()` truthfully. Ordering is deterministic and index-friendly (no `RAND()`, no time-dependence — consistent with the kernel's determinism guarantees and safe for test snapshots).

### 5.2 The legal 55+ gate

The senior-community gate (`BuyerMatchQueryBuilder:66–71`) is a **legal-compliance filter, not a preference**. Slice 2 preserves it as a **hard** Stage-B gate whenever `hard_filters_enabled=true`. **Open item:** if hard filters are disabled for first rollout, the 55+ gate would not apply at discovery time. Because Slice 2 is non-consumer-facing and unflagged in production (§9), this is acceptable for now, but it is called out explicitly as **Owner Decision D2** — the safe answer may be "55+ gate always applies even when other hard filters are off."

---

## 6. Public API (proposed signatures — for review, not yet implemented)

```php
namespace App\Services\Dna\Relevance;

final class CandidateDiscoveryService
{
    public function __construct(
        private readonly CandidateSourceInterface $source,
        // criteria loaders resolved per role; see D4 for injection shape
    ) {}

    /**
     * @return CandidateSet  empty (no DB reads) when MATCHING_V2 is disabled
     */
    public function discover(
        string $listingType,
        int $listingId,
        MatchDirection $direction,
        ?int $cap = null,          // defaults to config('matching.candidate_discovery.cap')
    ): CandidateSet;
}

final class CandidateSet
{
    /** @return array<int,array{listing_type:string,listing_id:int}> — feeds DnaMatchService directly */
    public function toArray(): array;
    public function total(): int;
    public function wasTruncated(): bool;
}
```

Intended call site (illustrative — this slice ships the discovery service, **not** a new consumer wiring):

```php
$candidates = $discovery->discover('buyer', $buyerId, MatchDirection::DemandToListings);
$ranked     = $dnaMatch->matchDemandAgainstListings('buyer', $buyerId, $candidates->toArray());
```

---

## 7. How candidate pools are limited

1. **Universe restriction (Stage A):** only entities with counterpart-side `dna_scores`. Structural, not tunable.
2. **Hard-attribute/geo pre-filter (Stage B):** optional, config-gated; over-inclusive bounding box.
3. **Hard cap (Stage C):** `config('matching.candidate_discovery.cap')`, default **200** (matches the Stellar `DEFAULT_CANDIDATE_CAP` for consistency). Enforced as SQL `LIMIT`, never post-hoc in PHP over a huge collection.
4. **Deterministic ordering** ensures the capped slice is stable and reproducible.
5. **Self-exclusion:** the subject `(listing_type, listing_id)` is filtered out of its own candidate set.
6. **Truncation is observable:** `wasTruncated()` + a `log()`/debug line so a silently-capped pool is never mistaken for "the whole market."

---

## 8. Performance considerations

- **Single indexed query for Stage A.** `dna_scores` has the composite unique index `(listing_type, listing_id, score_key, side)` and a `score_key` index. `DISTINCT listing_type, listing_id WHERE side = ?` is covered on the left-prefix of the unique index only partially (`side` is the 4th column); **Owner Decision D5:** consider whether a supporting index `(side, listing_type, listing_id)` is worth adding in a later slice. For current data volume this is not required; flagged, not assumed.
- **No N+1.** Discovery returns lightweight tuples; the per-candidate score reads happen once, in bulk, inside Slice 1 (`DnaScoreRepository`), which already returns plain arrays so a subject's scores are read once and reused across pairings.
- **Cap before hydration.** We select scalar `(listing_type, listing_id)` pairs, never Eloquent models, and cap in SQL. Stage B joins are only against already-narrowed sets.
- **Bounding box, not Haversine, at the DB.** Exact trig runs only in the kernel/aggregator over ≤ `cap` candidates.
- **Inert path is free.** Flag-off returns before any query — zero DB cost, same guarantee as `DnaMatchService::inert()`.
- **Complexity ceiling:** worst case is `O(cap)` hydration downstream; discovery itself is one-to-three indexed queries independent of total marketplace size.

---

## 9. Read-only guarantees

- No `INSERT`/`UPDATE`/`DELETE`/`upsert` anywhere in the new code. Only `SELECT`.
- No writes to `dna_scores`, no regeneration triggers, no cache writes to any generation artifact.
- No new migration; no schema mutation (index question in D5 is deferred, not part of this slice).
- The new `DnaScoreRepository::distinctSubjects()` is a pure query method, consistent with the class's existing read-only governance comment.
- A unit test asserts read-only behavior (e.g. wrap discovery in a DB transaction and assert zero affected rows / no dirty models), mirroring the baseline's governance testing posture.

---

## 10. Feature-flag behavior

- **Master gate:** reuse the existing `config('matching.v2_enabled')` (`MATCHING_V2_ENABLED`, default `false`). **No new env master-gate** — Candidate Discovery is a sub-capability of Matching V2 and must never be reachable when V2 is off.
- When disabled: `discover()` returns an **empty `CandidateSet` with zero DB reads** — the exact inert contract Slice 1 established.
- **Sub-config (behavioral, not a gate):** `config('matching.candidate_discovery.*)` — `cap`, `allowed_listing_types`, `hard_filters_enabled`. These tune behavior only when V2 is already on.
- **Independence preserved:** unrelated to `DNA_SCORES_GENERATION_ENABLED` (generation) and to `BYA_COMPATIBILITY_*` (legacy compatibility engine). This slice does not touch those.
- Production default remains **fully inert** (V2 off).

---

## 11. Test plan

**Unit — `ScoredEntityCandidateSourceTest`**
- Returns only entities that have counterpart-side scores (seed `property` + `demand` rows; assert the demand-direction query returns only `property`-side subjects).
- Excludes the subject itself.
- Respects `allowed_listing_types`.
- Cap enforced; `wasTruncated()` true at `cap+1`, false at `cap`.
- Deterministic ordering (same seed → identical tuple order across runs).
- Stage B off (`hard_filters_enabled=false`) → pure Stage A + cap.

**Unit — `CandidateDiscoveryServiceTest`**
- Flag off → empty set, **zero queries** (assert with a query counter / `DB::listen`).
- Both directions map to the correct counterpart `side`.
- `cap` override argument beats config default.
- Delegates to the injected `CandidateSourceInterface` (mock the source; assert the `CandidateQuery` it receives — side, types, envelope, exclusions).

**Unit — geo/hard-filter narrowing (Stage B)**
- Bounding box from a radius `BuyerCriteriaPayload` excludes an out-of-box `property_location_dna` point and includes an in-box one (reuses `PolygonBoundingBox` fixtures already exercised by the Stellar tests).
- 55+ legal gate excludes senior-community listings for a non-eligible subject.
- Price ceiling / min beds/baths null-tolerance matches the Stellar semantics (null passes the pre-filter, exact gate deferred to kernel).

**Feature — `CandidateDiscoveryFlagTest`**
- End-to-end: seed scores → `discover()` → feed result into `DnaMatchService` → assert a non-empty `RankedMatchSet` when V2 on; empty when off.

**Read-only assertion test**
- Run discovery inside a transaction; assert no rows written to any table.

**Test infra:** SQLite in-memory per `CLAUDE.md` (`php artisan test`), consistent with existing `tests/Unit/Dna/DnaScoreRepositoryTest.php` and `DnaMatchServiceTest.php`.

---

## 12. Explicit out-of-scope (this slice)

- **Scoring / ranking / tiering** — owned by §F6 kernels; untouched.
- **`bridge_properties` (external MLS) as a candidate source** — deferred to a later slice; needs a bridge-side `dna_scores` addressing story first (see D1). Slice 2 covers on-platform entities only.
- **Persistence of match results or candidate pools** — nothing is written.
- **Pagination / cursoring** beyond a single hard cap.
- **Consumer-facing exposure** — no controller, no Livewire, no route, no Blade. Discovery is a service only; wiring it into any user flow is a separate, explicitly-approved slice.
- **New generation, backfill, or `dna_scores` writes.**
- **Exact geo (Haversine / point-in-polygon) at discovery time** — only bounding boxes here.
- **Refactoring `BuyerMatchService`/`BuyerMatchQueryBuilder`** or the Stellar path (beyond an optional, behavior-preserving extraction — D3).
- **Index additions to `dna_scores`** (D5 is a recommendation for a future slice, not part of this one).
- **`TenantAgentAuction` refactor** and anything inside `initializeLimitedService()` — frozen per `CLAUDE.md`.

---

## 13. Risks & owner decisions

> **D1–D5 are APPROVED — see §0.1 for the resolved outcomes.** The table below is retained for provenance (the options considered) and for the still-live *risks* R1–R3.

| ID | Decision / risk | Options | Recommendation |
|---|---|---|---|
| **D1** | Candidate source scope. | (a) On-platform `dna_scores`-addressed entities only. (b) Also include `bridge_properties`. | **(a)** for Slice 2. `bridge_properties` has no `dna_scores` addressing yet; forcing it in would break the read-only/consistency guarantees. |
| **D2** | Stage B hard filters on first rollout, and the 55+ legal gate. | (a) Ship with `hard_filters_enabled=false` (Stage A + cap only). (b) Enable hard filters incl. 55+. (c) Hard filters off **but 55+ always on**. | **(c)** — keep the legal gate unconditional even if other attribute gates are deferred. Owner to confirm. |
| **D3** | Bounding-box math reuse. | (a) Extract shared `BoundingBoxBuilder`, have both Stellar and DNA paths call it. (b) Call `PolygonBoundingBox::fromPayload` from discovery and leave `BuyerMatchQueryBuilder` untouched. | **(b)** first (zero risk to the live Stellar path); extract later if duplication grows. |
| **D4** | Criteria loader injection for both roles. | Inject a small resolver that picks `BuyerOfferListingCriteriaLoader` vs `TenantOfferListingCriteriaLoader` by `listing_type`. | Confirm the tenant loader emits the same `BuyerCriteriaPayload` shape (exploration indicates a sibling exists); if not, a thin adapter is needed. Flagged. |
| **D5** | `dna_scores` index for `DISTINCT (side, listing_type, listing_id)`. | (a) Add `(side, listing_type, listing_id)` index in a future migration. (b) Rely on existing indexes. | **(b)** for now (data volume low); revisit if discovery latency shows up. **Not** part of this slice (it would be a write/migration). |
| **R1** | Silent truncation misread as "whole market." | — | Mitigated by `wasTruncated()` + a `log()` line; surfaced in tests. |
| **R2** | Buyer criteria live in `buyer_agent_auction_metas` (offer-listing), **not** `BuyerCriteriaAuction`. | — | Reuse `BuyerOfferListingCriteriaLoader` (already handles this correctly) — do **not** read `BuyerCriteriaAuction` directly. |
| **R3** | Seller-native vs landlord-EAV asymmetry in Stage B joins. | — | Respect the documented asymmetry; landlord gates read `landlord_auction_metas`, seller gates read native columns. |

---

## 14. Summary & implementation boundary (APPROVED)

Scope approved 2026-07-05 with decisions D1–D5 per §0.1 and the provider-agnostic long-term architecture per §0.2.

**Slice 2 (this commit) ships:** the provider-agnostic **Stage A** discovery over `dna_scores` — `CandidateDiscoveryService`, `CandidateSet`, `CandidateQuery`, `MatchDirection`, `CandidateSourceInterface`, `ScoredEntityCandidateSource` — plus the additive `config/matching.php` block and the read-only `DnaScoreRepository::distinctSubjects()` method, with the §11 tests. Flag-inherits-`v2_enabled`; default cap 200; fully read-only; inert when V2 is off.

**Deferred to a follow-up slice:** Stage B (geo/attribute narrowing + the mandatory 55+/legal gate), consumer-facing exposure, and any additional provider sources. These carry their own scope + approval.
