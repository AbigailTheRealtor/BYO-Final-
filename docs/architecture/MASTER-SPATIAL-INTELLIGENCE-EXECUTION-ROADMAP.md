# Master Spatial Intelligence — Execution Roadmap

> ## ⛔ SUPERSEDED — 2026-07-09
>
> **This document is superseded by [`SPATIAL-INTELLIGENCE-PLATFORM.md`](./SPATIAL-INTELLIGENCE-PLATFORM.md). Do not implement from it.**
>
> Retained for its **subsystem-safety analysis (§10), the "what keeps operating" matrix (§11), and Errata E-1/E-2**, all of which are carried forward. The following are **corrected** by the successor:
>
> - **Phase numbering** (§0.2's reconciliation table) — replaced by a single scheme, 0–9. See successor §14 for the old→new mapping.
> - **PD-5 / §0.4** — Phases 4 and 5 deferred wholesale post-launch. **Partly reversed:** Valhalla and listing-side travel-time ship in V1 (successor §10.3, SIA-D20, E-19). The buyer-side matcher replacement remains post-launch.
> - **§0.1 rule 2 / INV-4 / closing note** — "the maps cut is the single irreversible step." **Incorrect framing.** Once Google *data* is gone, both renderer states are lawful (E-15). The ordering (data before pixels) is unchanged and still binding.
> - **Phase 2 "FEMA NFHL stays a live API. Do not import."** — Reversed (E-11).
> - **Phase 2 Valhalla** — moves to the successor's Phase 4.
> - **Phase 5 Geoapify / Photon** — dropped (E-14).
> - **Phase 2 "CONUS bbox"** — scope is US + territories (E-3).
> - **Gate 1 "1,090 labelled rows"** — only **844** carry a rating (E-10).
> - **"43 Location DNA test files"** — unverified; re-baseline (E-21).
> - **Phase 0 test isolation** — the tree is partly ahead; `phpunit.xml` already blanks the key but **without `force="true"`** (E-20).
> - **Unsized work now sized:** Overture→taxonomy category mapping, and cross-source deduplication (successor §7.2).

**Status:** EXECUTION PLAN — **documentation only. No production code, tests, database, configuration, routes, or Git state changed by this document.** Becomes the official implementation plan upon architecture approval.
**Date:** 2026-07-09
**Owner:** Platform Architecture · Structure approved by product owner (Abigail) 2026-07-09
**Product direction (approved, binding):** **Version 1 launches on the Spatial Intelligence foundation. Google-dependent Location DNA will not be the production foundation at launch.** Phase 0 is mandatory; Phases 1–3 are required before V1. See §0.4.
**Scope:** BidYourOffer and BidYourAgent only. No unrelated or future products are in scope.
**Governing document:** [`docs/architecture/MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md`](./MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md) — every decision (SIA-D1…D15), principle (SIP-P1…P14), and gate referenced here is defined there.
**Builds upon:** `docs/launch-audits/location-dna-architecture-review.md`, `docs/LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md`, `docs/investigations/Google-Places-Root-Cause-Analysis.md`, `docs/bid-your-offer-v2.1-architecture-review.md`, `docs/canonical-field-mapping-spec.md`, `docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md`
**Contains:** one **erratum** to the governing document (§E-1) and one **numbering reconciliation** (§0.2).

> **Purpose.** Convert the approved architecture into a sequenced, reversible execution plan that never breaks a working system. The organising constraint is not engineering difficulty — it is that **the platform must keep operating for Seller, Landlord, Buyer, Tenant, MLS/Bridge, Property DNA, Target Market Intelligence, and Ask AI throughout every phase.**

---

## Table of Contents

- [0. How to read this roadmap](#0-how-to-read-this-roadmap)
  - [0.4 Approved Version 1 scope (product decision)](#04-approved-version-1-scope-product-decision-2026-07-09)
- [1. The eleven questions, answered](#1-the-eleven-questions-answered)
- [2. Cross-cutting invariants](#2-cross-cutting-invariants)
- [3. Phase 0 — Infrastructure Readiness](#phase-0--infrastructure-readiness)
- [4. Phase 1 — Provider Abstraction](#phase-1--provider-abstraction)
- [5. Phase 2 — Open Spatial Data Foundation](#phase-2--open-spatial-data-foundation)
- [6. Phase 3 — Location DNA Migration](#phase-3--location-dna-migration)
- [7. Phase 4 — Buyer & Tenant Location Intelligence](#phase-4--buyer--tenant-location-intelligence)
- [8. Phase 5 — Property DNA & Target Market Intelligence](#phase-5--property-dna--target-market-intelligence)
- [9. Phase 6 — Production Readiness](#phase-6--production-readiness)
- [10. Migration safety by subsystem](#10-migration-safety-by-subsystem)
- [11. What keeps operating during migration](#11-what-keeps-operating-during-migration)
- [12. Production launch gate](#12-production-launch-gate)
- [Appendix A — Code Reuse & Retirement Register](#appendix-a--code-reuse--retirement-register)
- [Appendix B — Database Change Register](#appendix-b--database-change-register)
- [Appendix C — Data Import Register](#appendix-c--data-import-register)
- [Appendix D — Errata to the governing document](#appendix-d--errata-to-the-governing-document)
- [Appendix E — Open Questions](#appendix-e--open-questions)

---

## 0. How to read this roadmap

### 0.1 The three rules that generate the entire sequence

Everything below follows from three facts established in the architecture document. If you remember nothing else:

1. **You cannot migrate on a synchronous queue.** `ComputeLocationDna` currently executes **inline inside the user's web request** (`QUEUE_CONNECTION=sync` in `.replit` `userenv.shared`, no `queue:work`, no `schedule:run`). Every migration step involves re-enriching listings. Doing that on a sync queue means re-enrichment blocks users and cannot be throttled, retried, or observed. **Infrastructure is Phase 0 because nothing else is safe without it.**
2. **Maps must be removed last.** Google's Maps Service Terms forbid using Google Maps Content "in conjunction with a non-Google map." While *any* Google Places or Geocoding data is still in use, the basemap must remain Google. This is a legal ordering constraint, not a preference (SIA-D10).
3. **The database table, not the provider, is the public contract.** `property_location_pois` is read directly by six consumers outside the read model. Migration therefore **changes who fills the table, not the table's shape** — for as long as possible.

### 0.2 Numbering reconciliation with the architecture document

The governing document's §17 uses a different phase decomposition. This roadmap is authoritative for **execution**; §17 remains authoritative for **rationale**. They map as follows. No decision changes.

| This roadmap | Architecture §17 | Note |
|---|---|---|
| **Phase 0** — Infrastructure Readiness | Phase 0 (infra items) | Split out; expanded. Cost protection and monitoring added. |
| **Phase 1** — Provider Abstraction | Phase 0 (safety items) + Phase 2 (seam work) | **Pulled earlier.** Google isolation is now its own phase, before any data work. |
| **Phase 2** — Open Spatial Data Foundation | Phase 1 (Spatial Core) + Phase 3 (routing infra) | Routing infrastructure moves earlier; routing *features* stay in Phase 4. |
| **Phase 3** — Location DNA Migration | Phase 2 (cut POI) | Unchanged in substance. |
| **Phase 4** — Buyer & Tenant Location Intelligence | Phase 3 (routing features) | Preference DNA + commute matching. |
| **Phase 5** — Property DNA & TMI + remaining Google removal | Phase 4 (geocode/autocomplete) + Phase 5 (maps) + Phase 6 (intelligence) | **Maps remain last *within* this phase** (§0.1 rule 2). |
| **Phase 6** — Production Readiness | *(new)* | Testing, cutover, certification. Not present in §17. |

### 0.3 Effort and calendar

| Phase | Engineer-weeks | Complexity | Reversible? | V1 launch set |
|---|---:|---|---|:--:|
| 0 — Infrastructure Readiness | 1–2 | Low | Yes | **✅ Required** |
| 1 — Provider Abstraction | 2–3 | Medium | Yes | **✅ Required** |
| 2 — Open Spatial Data Foundation | 3–4 | Medium | Yes (additive only) | **✅ Required** |
| 3 — Location DNA Migration | 2–3 | Medium-High | Yes (config flip) | **✅ Required** |
| 6a — Production Readiness (V1 scope) | 2–3 | Medium | N/A | **✅ Required** |
| **V1 launch subtotal** | **10–15** | | | |
| 4 — Buyer & Tenant Location Intelligence | 3–4 | High | Yes (net-new) | Post-launch |
| 5 — Property DNA & TMI + Google removal | 4–6 | High | **Partly — maps step is one-way** | Post-launch |
| 6b — Production Readiness (Google-removal scope) | 1–2 | Medium | N/A | Post-launch |
| **Total** | **18–27** | | | |

**Phases 0–3 (~8–12 weeks) are the Version 1 launch set** — see §0.4. Phase 6 (Production Readiness) is additionally required, scoped to the Phases 0–3 deliverables.

---

## 0.4 Approved Version 1 scope (product decision, 2026-07-09)

**Binding. This section governs every scoping statement elsewhere in this roadmap.**

Version 1 launches on the Spatial Intelligence foundation. Google Maps Platform will **not** sit underneath Location DNA in production at launch.

| # | Approved direction |
|---|---|
| **PD-1** | **Phase 0 is mandatory before anything else.** It is a correctness prerequisite, not preparation. |
| **PD-2** | **Phases 1, 2, and 3 are required before Version 1 launch.** They are not deferrable. |
| **PD-3** | **Google-dependent Location DNA must not remain the production foundation for V1.** Phase 3 removes Google Nearby Search from the enrichment path entirely. |
| **PD-4** | **Automatic Location DNA for Seller, Landlord, and MLS/Bridge listings at $0 marginal cost is a Version 1 requirement**, not a stretch goal. |
| **PD-5** | Phases 4 and 5 **enhance** the platform. They may land after launch. The Google-free Location DNA foundation must be complete **before** launch. |

### What this means precisely

**The V1 launch set is: Phase 0 → Phase 1 → Phase 2 → Phase 3 → Phase 6 (scoped).**

Phase 6 (Production Readiness) is **not** a post-Phase-5 activity. It is a *gate*, and it runs **twice**:

- **Phase 6a — V1 certification.** Performance, load, regression, and browser testing over the Phases 0–3 deliverables; monitoring live; rollback rehearsed; launch certification issued. **Excludes the destructive migration (B12)** and excludes any Phase 4/5 surface.
- **Phase 6b — Google-removal certification.** Re-run after Phases 4–5, covering routing, preference DNA, autocomplete, and the one-way maps cut. The destructive migration (B12) executes here.

### What remains on Google at V1 launch — and why that is consistent

Phase 3 removes Google from **Location DNA**. It does not remove Google from the whole application. At V1 launch the platform still uses Google Maps Platform for three surfaces, all outside the enrichment path:

| Surface | Status at V1 | Removed in |
|---|---|---|
| **Places Nearby Search** (Location DNA POIs) | ✅ **Gone.** 16 calls/listing → 0. | Phase 3 |
| Maps JavaScript (3 rendering surfaces) | Retained | Phase 5 |
| Places Autocomplete (address entry) | Retained — **and free**, once Phase 0 adds session tokens | Phase 5 |
| Geocoding (residual, near-vestigial) | Retained as a fallback | Phase 5 |

This satisfies PD-3 exactly: **no Google data is consulted to produce Location DNA.** It is also legally coherent — Google's "No Use With Non-Google Maps" clause (SIA-D10) permits Google geocoding and autocomplete alongside a Google basemap. It would *not* permit MapLibre alongside them, which is precisely why the maps cut is sequenced last and why Phases 4–5 remain post-launch.

Two consequences follow, and both are features rather than compromises:

1. **The Phase 3 rollback stays open through launch.** Because the Google code paths still exist until Phase 5, V1 can revert to `google_places` by config flip if the corpus underperforms in production. That reversibility is *lost* at Phase 5, which is the strongest argument for keeping Phase 5 post-launch.
2. **V1 closes compliance violation V1** (Google ratings persisted beyond the 30-day cap) because Phase 3 stops writing them. Violations V2 (browser-side public Nominatim) and V3 (single un-segregated key) close at Phase 5 and Phase 0 respectively.

---

## 1. The eleven questions, answered

### Q1. What must happen first before any Location DNA migration?

**Three things, in this order, all before a single line of spatial code:**

1. **A real queue worker.** Not an optimisation — a correctness prerequisite. Today `ComputeLocationDna::dispatch()` runs inline in the web request, and with `public int $tries = 3` a failing save retries inline up to **48 Google calls in one request**. Re-enrichment of ~1,226 listings on a sync queue is not a migration; it is an outage.
2. **Test isolation.** The 2026-07-05 incident (38,236 requests, ~$1,223, six days) was caused by the *test suite* reaching live Google. None of the four RCA remediations has been implemented. A migration multiplies test runs. Fix this before increasing the blast radius.
3. **Cost protection that actually works.** `GOOGLE_PLACES_ENABLED`, `daily_limit=100`, `hourly_limit=25` all exist in `config/google_places.php` and are wired to **nothing** — `grep -rn "google_places\." app/` returns zero hits.

Only then does Phase 1 (provider abstraction) begin.

### Q2. What infrastructure changes are required?

| Change | Why | Phase |
|---|---|---|
| Background queue worker (`queue:work`), Redis-backed | `ComputeLocationDna` must not run in-request (SIP-P13) | 0 |
| Scheduler (`schedule:run`) | `offers:expire-pending` is defined and **has never run** | 0 |
| Redis for cache + queue | `CACHE_DRIVER=file` cannot back a cross-process cache | 0 |
| Managed PostgreSQL 16 with **PostGIS 3.5.3** enabled | Already *available* on the instance, not installed | 0 |
| Real application server (not `php artisan serve`) | Production currently serves via the PHP dev server | 0 |
| One routing VM (Valhalla) | All hosted routing providers forbid storing results | 2 |
| Object storage + CDN (corpus, PMTiles) | Overture parquet, tile archive | 2 |
| Outbound-call monitoring + budget alerts | No outbound-call observability exists today | 0 |

**This is the single largest hidden dependency in the programme.** It is required for asynchronous DNA generation to work *at all*, independent of which map provider is chosen. See Appendix E, Q1 — the hosting target is a product-owner decision.

### Q3. What Google dependencies are removed first?

Ordered by **(cost eliminated × reversibility) ÷ risk**, subject to the ToS ordering constraint.

| Order | Dependency | Calls | Why this position |
|---:|---|---|---|
| **1** | **Nearby Search** (`LocationDnaPoiDistanceService`, `GooglePlacesPoiAdapter`) | 16/listing | **100% of the cost.** Behind an existing adapter seam. Fully reversible by config. Phase 3. |
| **2** | **Geocoding** (`LocationDnaGeocodeService`, 5 Livewire sites, `GeocodeSelleryLandlordListings`) | ~0 | Near-vestigial: 100% of the 667 Bridge rows carry coordinates; 10 of 11 DNA rows used `saved_meta`. Trivial. Phase 5. |
| **3** | **Places Autocomplete** (12 Livewire proxies + widget + ~25 legacy Blade forms) | per keystroke | Broad but shallow. **Note: adding `sessiontoken` in Phase 0 makes this free immediately** — Google bills Autocomplete per *session* at $0 on Essentials. Phase 5. |
| **4** | **Maps JavaScript** (3 surfaces) | page loads | **Must be last.** Rendering a non-Google basemap voids the right to use Google Places/Geocoding anywhere. Phase 5, final step. One-way. |

### Q4. What database changes are required?

**All additive until Phase 6.** Nothing is dropped while a consumer still reads it. Full register: Appendix B.

| Phase | Change | Destructive? |
|---|---|---|
| 0 | `CREATE EXTENSION postgis;` | No |
| 2 | New: `places`, `place_categories`, `boundaries`, `boundaries_parts`, `listing_locations` | No — additive |
| 2 | New: `corpus_imports` (provenance/version ledger) | No |
| 3 | `property_location_pois`: **populate `confidence` / `provenance_json` / `last_refreshed`** (columns exist since migration `2026_07_05_000001`, never written) | No |
| 3 | `property_location_dna`: rename `pois_fetch_version` → semantic `corpus_version` **via a new nullable column + dual-write**, not a rename | No |
| 4 | New: `location_preference_geometries`, `isochrone_cache`, `commute_cache` | No |
| 6 | Drop `property_location_pois.rating`, `.user_ratings_total`; drop `pois_fetch_version` | **Yes — final phase only** |

The `rating` / `user_ratings_total` columns stop being *written* in Phase 3 and are dropped only in Phase 6, after every reader is confirmed gone. Between those points they hold stale, ignored data — which is acceptable and reversible; dropping them early is neither.

### Q5. What data imports are required?

Full register with licence, cadence, and phase: **Appendix C**. Summary:

- **Phase 2 (blocking):** Overture Places (CONUS, `confidence ≥ 0.90`), Census TIGER boundaries, NCES CCD/EDGE, USGS PAD-US, **USGS Boat Ramps (CC0)**, CMS Hospital Star Ratings, FAA NASR, EPA National Walkability Index, GTFS + NTD.
- **Phase 2 (non-blocking):** NOAA CUSP shoreline, USGS/USFS trails, OSM extract (marinas, dog parks, golf).
- **Phase 4:** Valhalla graph build (Florida extract → CONUS).
- **Phase 5:** DOT National Address Database (geocoding fallback chain).
- **Not imported (live API, already working):** FEMA NFHL — retain `FemaFloodZoneAdapter` as-is.

### Q6. What existing Location DNA code can be reused?

**Most of it.** The pipeline was built with the right seams; they were simply wired to the wrong path. Full register: Appendix A.

| Reused | Why it survives |
|---|---|
| `LocationDnaRankingEngine` | Pure computation, no I/O. **Requires input-shape normalisation** — it currently consumes raw Google JSON (`$place['geometry']['location']['lat']`, `$place['types']`). |
| `LocationDnaRankingProfileService` | Per-category weights survive; re-tuned for the prominence prior. |
| `LocationDnaSummaryService` | Reads persisted rows, provider-agnostic. |
| `LocationDnaLifestyleScoreService` | **Pure distance tiers. Zero rating dependence.** Untouched except the beach narrative gate. |
| `LocationDnaPipelineRunner` | Stage orchestration and graceful-degradation contract preserved. |
| `LocationDnaPresenter` (`app/Presenters/`) | Promoted to the single read model, per `location-dna-architecture-review.md` §3. |
| `LocationProviderRegistry` + `CanonicalLocationMerger` | Built, tested, **unwired**. Phase 1 wires them. `capabilityHash()` already folds into `fetch_version`. |
| `PoiLookupAdapterInterface` | The seam. A new `CorpusPoiAdapter` implements it. |
| `LocationDnaVersionService` | Version-stamp mechanism generalised (SIA-D12). |
| `FemaFloodZoneAdapter`, `CensusTigerBoundaryAdapter`, `CensusSchoolDistrictAdapter` | Already open-data. **No change.** |
| `CommuteTimeAdapterInterface` + `CommuteTimeLookupService` | Contract, cache, and `max_destinations` cap already correct. Only the stub is replaced. |
| `CATEGORY_EXCLUSION_RULES` | Hard-won false-positive filters. Ported to the new taxonomy. |
| 43 Location DNA test files | Unusually strong coverage. The migration's primary safety net. |

### Q7. What existing Location DNA code should be retired?

| Retired | Phase | Reason |
|---|---|---|
| `GooglePlacesPoiAdapter` | 3 | Replaced by `CorpusPoiAdapter`. |
| `LocationDnaPoiDistanceService`'s Google fetch path (`NEARBY_API_URL`, `fetchRawCandidates`) | 3 | The 16 calls. The class survives; its network layer does not. |
| `PoiDistanceLookupService`'s 7-category taxonomy | 3 | Folded into the unified 23-category core (SIA-D6). |
| `LocationDnaPoiTileCache` | 3 | Exists solely to avoid metered calls. Disabled today anyway (`tile_precision => null`). |
| `LdnaBenchmarkTilePrecision`, `LdnaPoiCostReport` | 3 | Instruments for a cost that no longer exists. |
| `top_rated_dining` category + `TOP_RATED_DINING_*` constants | 3 | Definitionally rating-driven; lifestyle service never reads it (SIA-D5). |
| `rating` / `user_ratings_total` **writes** | 3 | Google ToS 30-day cap; persisted indefinitely today. |
| `fitness_center` as a distinct category | 3 | Provider artifact — same Google type as `gym`, split by a keyword hack. |
| `AgentLocationDnaController::generate()` + route + UI button | 3 | The manual button exists *only* because the API is metered. |
| `CommuteTimeStubAdapter` | 4 | Replaced by `ValhallaCommuteAdapter`. |
| `LocationDnaGeocodeService`'s Google branch | 5 | Fallback chain replaces it. |
| `GeocodeSelleryLandlordListings` | 5 | One-off backfill, obsolete once the chain exists. |
| `google-maps-script` Blade component + all `maps.googleapis.com` refs | 5 (last) | One-way. |
| `config/google_places.php`, `services.google.places_key` | 6 | After the final Google reference is gone. |

### Q8. How do we migrate without breaking the eight subsystems?

Answered in full in **§10**. The governing technique is a **four-property invariant** held at every phase boundary:

1. **The table shape is the contract.** `property_location_pois` and `property_location_dna.summary_json` / `.lifestyle_json` keep their shape. Only the *writer* changes.
2. **Shadow before switch.** Every new writer runs alongside the old one, writing to a shadow table, until a diff gate passes.
3. **Config flip, not code deploy.** Cutover is `capabilities['poi.default']`, reversible without a release.
4. **Role symmetry is explicit.** Seller / Landlord / Buyer / Tenant are quadruplicated by design (`CLAUDE.md`). Every change is verified across all four, plus `bridge`.

### Q9. What order should the phases be completed?

Strictly sequential through Phase 3. Phases 4 and 5 may overlap partially; nothing else may.

```
   ┌──────────── VERSION 1 LAUNCH SET (PD-2) ────────────┐
   │                                                      │
   0 ──► 1 ──► 2 ──► 3 ──────────────────► 6a ──►  🚀 LAUNCH
                     │                                    │
                     └────────────────────────────────────┘
                              │
             post-launch      ├──► 4 ──┐
                              │        ├──► 6b
                              └──► 5 ──┘
                                   └── maps step LAST within Phase 5 (one-way)
```

**Phase 3 is the launch-critical milestone**, not merely the value-delivery one: it is the phase that removes Google from the Location DNA foundation (PD-3) and delivers automatic enrichment at $0 marginal cost (PD-4).

Phases 4 and 5 are independent of each other **except** that the maps step in Phase 5 must not begin until Phase 3 has removed Places *and* Phase 5's geocode/autocomplete steps have completed (SIA-D10).

### Q10. What functionality can continue operating during the migration?

**All of it.** See §11 for the phase-by-phase matrix. Location DNA is currently generated for **11 of ~1,226 listings (~0.9%)** and Google Places has been **disabled since 2026-07-06**, so the migration begins from a position where the feature is already dark. There is no working consumer experience to protect until Phase 3 *restores* one — at 100% coverage.

Maps and address autocomplete continue to operate on Google throughout the V1 launch set and are swapped, not removed, in Phase 5 (§0.4).

### Q11. What must be completed before production launch?

**Per the approved product direction (§0.4, PD-1…PD-5): Phases 0, 1, 2, and 3, plus Phase 6a certification.** These are not deferrable. Version 1 launches on the Spatial Intelligence foundation; Google-dependent Location DNA will not be the production foundation.

Concretely, the **Production Launch Gate** (§12) requires:

- Phase 0 infrastructure complete — queue worker, scheduler, Redis, PostGIS, cost protection, test isolation.
- Phases 1–3 complete; **Gates 1, 2, and 3 passed.**
- **INV-1 verified in production: `ComputeLocationDna` makes zero outbound calls.** This is the operational proof of PD-3.
- **Automatic Location DNA on 100% of Seller, Landlord, and MLS/Bridge listings at $0 marginal cost** (PD-4).
- The two data-quality landmines — SABS staleness and FEMA "not mapped" — explicitly handled.
- Phase 6a: performance, load, regression, and browser testing; monitoring live; **rollback to `google_places` rehearsed** (still possible at V1, because Phase 5 has not yet removed the code paths).

**Phases 4 and 5 are post-launch enhancements** (PD-5). Google Maps JS, Places Autocomplete, and residual Geocoding remain in production at V1 — outside the enrichment path, and legally coherent alongside a Google basemap. Gate 5 (legal sign-off on audience labels) blocks Phase 5, not V1.

The **only** thing V1 does not deliver from the full architecture is the removal of Google from *map rendering and address entry*. It fully delivers the removal of Google from *intelligence*.

---

## 2. Cross-cutting invariants

These hold at **every** phase boundary. A phase is not done if any is violated.

| # | Invariant | Verified by |
|---|---|---|
| **INV-1** | No enrichment path makes an outbound metered call (SIP-P3) | Network guard test; outbound-call metric = 0 |
| **INV-2** | All four roles + `bridge` behave identically | Per-role test matrix (5 listing types) |
| **INV-3** | `property_location_pois` / `summary_json` / `lifestyle_json` shapes unchanged until Phase 6 | Contract/snapshot tests |
| **INV-4** | Any phase is revertible by config, except the Phase 5 maps step | Documented rollback rehearsal |
| **INV-5** | `null` means unknown, never zero (SIP-P12) | Lifestyle score tests; FEMA presenter test |
| **INV-6** | `SeniorCommunityComplianceGate` runs unconditionally | Existing test; never behind `hard_filters_enabled` |
| **INV-7** | No demographics feed any score, rank, audience, or match (SIA-D8) | Code review + input allowlist |
| **INV-8** | Frozen code untouched: `initializeLimitedService()`, `TenantAgentAuction`, `LOCKED_BidComparison` | Diff review |
| **INV-9** | Every derived artifact carries `corpus_version` / `scoring_version` / `routing_version` | Version-stamp tests |

---

## Phase 0 — Infrastructure Readiness

> **Nothing in this phase touches Location DNA. It exists because the platform cannot currently execute an asynchronous job, and every subsequent phase depends on that.**

### Objectives

1. Make asynchronous background processing real.
2. Make it impossible for a test run to spend money.
3. Make Google spend observable and bounded.
4. Enable PostGIS.

### Deliverables

**Queue architecture**
- Redis-backed queue; `QUEUE_CONNECTION=redis` replacing `sync` in the deployment environment.
- A supervised `queue:work` process with a dedicated `location-dna` queue and bounded concurrency.
- `ComputeLocationDna` moved onto that queue; `tries=3` retained but now *asynchronous* (today it means up to 48 inline Google calls per failing save).

**Scheduler**
- A supervised `schedule:run` cron. `offers:expire-pending` is defined in `app/Console/Kernel.php` and **has never executed in production**. Verify no other scheduled work is silently dead.

**Background processing**
- Real application server (not `php artisan serve`).
- Redis cache store (`CACHE_DRIVER=redis`); `file` cannot back a cross-process cache.

**Monitoring**
- Structured log + metric for every outbound Google request (endpoint, listing, count). **No outbound-call observability exists today** — the RCA could not reconstruct the incident partly for this reason.
- Queue depth, job failure rate, job duration.

**Cost protection**
- **Wire the `GOOGLE_PLACES_ENABLED` kill switch** and the `daily_limit` / `hourly_limit` circuit breaker. They exist in `config/google_places.php` and are referenced by *zero* application code.
- Implement the four RCA remediations, none of which has been done:
  1. Bind `PoiLookupAdapterInterface` → `StubPoiLookupAdapter` in `tests/TestCase.php`, and bind `LocationDnaPoiDistanceService` to a no-network instance.
  2. Blank `GOOGLE_PLACES_API_KEY` under `APP_ENV=testing` (via `phpunit.xml`, which overrides system env); add a guard test asserting it is blank.
  3. Resolve the HTTP client from the container in **both** POI callers, removing bare `new Client()` (which `Http::fake()` cannot intercept).
  4. Add a global stray-request guard so any un-mocked outbound call in tests fails loudly.
- **Add `sessiontoken` to Places Autocomplete.** Google bills Autocomplete **per session at $0, unlimited**, on Essentials; the code passes no token and therefore pays the per-request SKU. *Immediate, free saving; no architectural commitment.*
- Google Cloud: restrict the API key (scope + referrer/IP), set a daily quota cap, set a budget alert.
- Segregate the single API key — it is currently **both** embedded in every page's HTML **and** used for server-side billing.

**Production readiness**
- `CREATE EXTENSION postgis;` (3.5.3 available, not installed). `CREATE EXTENSION vector;` optional (0.8.0 available).
- Add a dirty-check to the DNA observers (dispatch only on address/coordinate change).

### Files / systems affected

`.replit` (or its replacement) · `config/queue.php` · `config/cache.php` · `config/google_places.php` · `app/Console/Kernel.php` · `app/Jobs/ComputeLocationDna.php` · `app/Providers/AppServiceProvider.php` · `app/Services/LocationDna/LocationDnaPoiDistanceService.php` (client injection only) · `app/Services/LocationDna/GooglePlacesPoiAdapter.php` (client injection only) · `tests/TestCase.php` · `phpunit.xml` · all 10 `app/Observers/Dna/*` · the 12 Livewire autocomplete proxies (session token) · new migration: `CREATE EXTENSION postgis`

### Dependencies

**A hosting decision (Appendix E, Q1).** This is the only true blocker in the entire programme. Everything else is engineering.

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Moving `sync` → `redis` changes job timing; code may implicitly rely on inline execution | **High** | Audit every `dispatch()` call site for post-dispatch assumptions. Livewire components that read DNA immediately after saving will now see `pending` — this is *correct* but user-visible. Add "generating…" states. |
| Enabling the scheduler runs `offers:expire-pending` for the first time ever | **High** | Dry-run against a production snapshot first. This command has never executed; its behaviour on a real backlog is unknown. |
| Hosting migration itself | Medium | Standard cutover; unrelated to spatial work. |
| Session tokens change autocomplete billing behaviour | Low | Verify in Google Cloud metrics that the per-request SKU drops to ~0. |

### Testing requirements

- Full suite green **with the network guard active** — currently 60 outbound attempts occur per full run.
- Guard test: `GOOGLE_PLACES_API_KEY` is blank under `APP_ENV=testing`.
- Guard test: `ComputeLocationDna` dispatched, not executed, when `Queue::fake()` is absent.
- Integration: a queued `ComputeLocationDna` completes out-of-band and the listing reaches `success`.
- Manual: `offers:expire-pending` dry-run output reviewed and approved.

### Rollback strategy

Revert environment config. All code changes are additive guards; the stubs and kill switch are inert when disabled. **Zero risk to existing behaviour** — Google Places is disabled in production today regardless.

### Definition of Done

- [ ] `QUEUE_CONNECTION=redis`; a supervised worker consumes the `location-dna` queue in production.
- [ ] `schedule:run` supervised; `offers:expire-pending` verified.
- [ ] Full test suite passes with a global stray-request guard; **zero** outbound attempts.
- [ ] `GOOGLE_PLACES_ENABLED=false` demonstrably short-circuits every Nearby Search caller.
- [ ] Autocomplete per-request SKU spend → ~$0 (session tokens live).
- [ ] Outbound-call metric emitting; budget alert configured.
- [ ] `postgis` extension installed; `SELECT postgis_version()` succeeds.
- [ ] API key segregated and restricted.
- [ ] INV-1 (in tests), INV-8 hold.

---

## Phase 1 — Provider Abstraction

> **Objective: make the application indifferent to who supplies geographic data — while changing no behaviour whatsoever.** This is the phase the `PHASE_8_...RECOMMENDATION.md` addendum called for, and the phase whose absence that addendum incorrectly assumed was already filled.

### Objectives

1. Route **every** POI read through `PoiLookupAdapterInterface`.
2. Wire `LocationProviderRegistry` and `CanonicalLocationMerger` into the runtime path (both are built, tested, and documented as "PURE and UNWIRED").
3. Normalise `LocationDnaRankingEngine` off raw Google JSON.
4. Unify the two divergent taxonomies behind one canonical definition.
5. **Ship zero behaviour change.** Google remains the sole enabled provider throughout.

### Deliverables

- **Correct a load-bearing false claim.** `PHASE_8_...RECOMMENDATION.md` states the intelligence engine is "already fully decoupled" from providers. It is not:
  - `LocationDnaPoiDistanceService` (the production path) calls Google **inline via raw Guzzle** at `NEARBY_API_URL`, entirely bypassing the adapter interface. The seam is wired only to `PoiDistanceLookupService`, the *secondary* buyer/tenant path.
  - `LocationDnaRankingEngine::rankCandidates()` consumes **raw Google response objects** — `$place['geometry']['location']['lat']`, `$place['types']`, `$place['rating']`, `$place['user_ratings_total']`.
- **Extract a `PoiCandidate` value object** as the engine's input contract. Adapt Google's shape into it. The engine no longer knows what a `geometry.location` is.
- **Route the production path through the interface.** `LocationDnaPoiDistanceService` delegates fetching to an injected `PoiLookupAdapterInterface`. Its ranking, exclusion, grouping, and persistence logic is untouched.
- **Wire the registry.** `AppServiceProvider` resolves adapters through `LocationProviderRegistry::effectiveBase('poi.default')` for **both** paths. `google_places` remains the only `enabled => true` POI provider.
- **Unify the taxonomy.** Lift `CATEGORIES`, `CATEGORY_GROUPS`, and `CATEGORY_EXCLUSION_RULES` into a shared, config-backed definition serving both point mode and area mode (SIA-D6, adopting `location-dna-architecture-review.md` §3 step 1). The buyer/tenant path's 7 categories become a *subset view* of the canonical set — no behaviour change yet.
- **Dual-run harness.** Infrastructure to run two adapters over the same input and diff results. Required by Gate 3 in Phase 3; built here.
- Extend the adapter contract to carry `confidence` / `last_refreshed` (already specified in `PoiLookupAdapterInterface`'s docblock but noted as "NOT persisted by this path") and begin **writing** `confidence` / `provenance_json` to `property_location_pois` (columns exist since `2026_07_05_000001`, never written).

### Files / systems affected

`app/Contracts/PoiLookupAdapterInterface.php` · `app/Services/LocationDna/LocationDnaPoiDistanceService.php` (**1,584 LOC — the largest single file in the programme**) · `LocationDnaRankingEngine.php` · `GooglePlacesPoiAdapter.php` · `PoiDistanceLookupService.php` · `Providers/LocationProviderRegistry.php` · `Providers/CanonicalLocationMerger.php` · `app/Providers/AppServiceProvider.php` · `config/location_providers.php` · new: `app/Services/LocationDna/PoiCandidate.php`, `app/Services/LocationDna/CanonicalCategoryRegistry.php`

### Dependencies

Phase 0 (test isolation — this phase heavily exercises the POI path).

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Refactoring a 1,584-LOC launch-critical file | **High** | Behaviour-preserving refactor only. Character-for-character output parity enforced by snapshot tests on `summary_json` and `lifestyle_json` before/after. |
| Ranking drift from the shape change | **High** | The engine is pure. Golden-master tests: run the current engine over the 1,090 persisted POI rows, capture `ranking_score` for every row, assert byte-identical after refactor. |
| Taxonomy unification changes buyer/tenant results | Medium | Do **not** change the buyer/tenant category set in this phase. Expose the canonical registry; keep the 7-category view. Behaviour change is deferred to Phase 3. |
| Registry wiring changes `capabilityHash()` → invalidates `fetch_version` → mass refetch | **High** | Google is disabled; refetch is a no-op. But verify `LdnaRefreshAll` is not scheduled. Stamp versions with `LdnaStampVersions` before wiring. |

### Testing requirements

- **Golden-master ranking test** over all 1,090 persisted POI rows: `ranking_score`, `rank`, and `ranking_reasons_json` byte-identical pre/post refactor.
- Snapshot tests: `summary_json` and `lifestyle_json` unchanged for all 11 enriched listings.
- `PoiLookupAdapterInterfaceContractTest` extended: both adapters satisfy the 9-key contract.
- `PoiLookupAdapterBindingTest` extended: registry resolves `google_places` when enabled, `stub` otherwise.
- `LocationProviderRegistryTest` (exists) extended for the live binding.
- INV-1, INV-2, INV-3 verified.

### Rollback strategy

Pure code revert. No schema change, no data change, no config semantics change. Google remains the enabled provider throughout, so a revert is a no-op functionally.

### Definition of Done

- [ ] `grep -rn "NEARBY_API_URL" app/` returns **only** `GooglePlacesPoiAdapter`.
- [ ] `LocationDnaRankingEngine` contains no `geometry`, `types`, `rating`, or `user_ratings_total` string literals.
- [ ] Both POI paths resolve through `LocationProviderRegistry`.
- [ ] Golden-master ranking parity: 1,090/1,090 rows identical.
- [ ] `confidence` / `provenance_json` are written on every new POI row.
- [ ] Dual-run harness exists and is exercised by a test.
- [ ] One canonical category registry; two views over it.
- [ ] **Zero behaviour change** demonstrated by snapshot parity.

---

## Phase 2 — Open Spatial Data Foundation

> **Additive only. Google is untouched. Nothing reads the new tables yet.**

### Objectives

1. Stand up the spatial substrate (PostGIS schema + indexes).
2. Import the owned corpus and authority overlays.
3. Stand up the routing engine.
4. Prove corpus quality against the Google baseline **before** anything depends on it.

### Deliverables

**Spatial database** — per Architecture §13:
- `places` (GERS-keyed, `geography(Point,4326)`, GiST + CLUSTER), `place_categories` (extensible taxonomy — adding a POI category becomes an `INSERT`), `boundaries` + `boundaries_parts` (`ST_Subdivide` ≤256 vertices), `listing_locations`, `corpus_imports` (version/provenance ledger).
- Backfill `listing_locations` from existing `property_location_dna.geocoded_lat/lng` and `bridge_properties.latitude/longitude` (**100% populated**). No geocoding required.

**Open geographic datasets** — Appendix C. Blocking set:
- **Overture Places** — DuckDB → CONUS bbox → `confidence ≥ 0.90` → staging → atomic swap. *Measure the actual row count and size; no official CONUS figure is published (Appendix E, Q2).*
- OSM extract for marinas / dog parks / golf courses (no federal registry exists).

**Boundaries**
- Census TIGER (ZCTA, place, county, school district). `CensusTigerBoundaryAdapter` and `CensusSchoolDistrictAdapter` already work — this materialises their data locally for `ST_Contains` instead of per-listing HTTP.
- USGS PAD-US 4.1 (protected areas, with **acreage** — the prominence signal for parks).
- **FEMA NFHL stays a live API.** `FemaFloodZoneAdapter` is correct, free, and authoritative. Do not import; do not change.

**Places authority overlays**
- CMS Hospital Overall Star Rating (join on CCN), NCES CCD/EDGE, **USGS Boat Ramps (CC0)**, FAA NASR, GTFS stops + NTD ridership, EPA National Walkability Index.

**Routing**
- Valhalla on a dedicated VM. **Florida extract first** (the current market), then CONUS. Benchmark build and serve RAM — all published figures are planet-scale (Appendix E, Q3).
- Expose `/route`, `/sources_to_targets`, `/isochrone`. No application code consumes it yet.

**Mapping foundation**
- Generate/host Protomaps PMTiles for the CONUS basemap. **Do not render it anywhere yet** — doing so would void the right to use Google Places (SIA-D10).

**Validation (the point of this phase)**
- **Gate 1 — Prominence prior.** Build the prior (authority membership, brand, corpus confidence, source agreement, geometry significance, notability) and score it against the **1,090 existing POI rows already labelled with Google ratings**. Pass = embarrassment rate ≤3%. Baselines from the audit: **19%** with no quality signal, **1%** with true review counts. **Zero API spend.**
- **Gate 2 — Corpus coverage.** Per-category coverage vs the Google baseline across the Florida footprint. Rural sparsity is the known risk.

### Files / systems affected

New migrations (additive) · new `app/Services/Spatial/*` (corpus ingest, KNN, containment) · new `app/Console/Commands/CorpusImport*.php` · **no existing Location DNA file is modified in this phase**

### Dependencies

Phase 0 (PostGIS, infra). Phase 1 (the canonical category registry defines what to import). Object storage. Routing VM.

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| **Gate 1 fails** — the open prominence prior cannot match review-count quality | **High** | This is the programme's pivotal unknown. Brand chains separate only 1.6× (616 vs 390 mean reviews) and cover just 36.5% of commercial POIs. **Contingency: Foursquare Places Premium, keyed by POI not listing, commercial categories only** (SIA-D14). The gate is cheap and early *precisely so this is discovered now.* |
| Overture CONUS coverage is thin in rural Florida | Medium | Gate 2. Supplement with OSM + FSQ open set. Degrade `data_completeness` honestly (SIP-P12) rather than fabricate. |
| Valhalla CONUS RAM exceeds budget | Medium | Florida-first. Serve RAM is low (mmap tiles); build RAM is the constraint and is a one-time batch cost. |
| Corpus import is a new operational surface | Medium | Staging-swap keyed on GERS ID; `corpus_imports` ledger; a stale corpus degrades gracefully and never blocks a request. |
| GTFS per-feed licensing (no blanket commercial grant) | Medium | Filter to feeds with `commercial_use_allowed` via Transitland (Appendix E, Q5). |

### Testing requirements

- **Gate 1** and **Gate 2** are the phase's acceptance tests.
- Spatial query correctness: KNN result equals brute-force Haversine for a 1,000-point sample.
- `ST_DWithin` used everywhere; **no** `ST_Distance(...) < x` (only the former is index-assisted).
- Index verification: `EXPLAIN ANALYZE` shows GiST usage on KNN and containment.
- Corpus import idempotency: two runs → identical row counts, identical `corpus_version`.
- Valhalla parity (Gate 4 precursor): drive times vs known ground truth on a sample corridor set.

### Rollback strategy

Drop the new tables and decommission the routing VM. **Google is untouched; no existing code path reads any of this.** Zero user impact.

### Definition of Done

- [ ] All Phase-2 tables exist with GiST indexes; `EXPLAIN` confirms index usage.
- [ ] `listing_locations` backfilled for 100% of Bridge rows and all geocoded listings.
- [ ] Corpus imported; row count and size **measured and recorded** in `corpus_imports`.
- [ ] **Gate 1 passed** (≤3% embarrassment) — or the Foursquare contingency formally triggered.
- [ ] **Gate 2 passed** (per-category coverage accepted by product owner).
- [ ] Valhalla serving Florida; `/isochrone` returns a valid polygon.
- [ ] PMTiles archive built and hosted, **not rendered**.
- [ ] INV-1 through INV-9 hold. Zero change to any existing behaviour.

---

## Phase 3 — Location DNA Migration

> **The Version 1 launch-critical milestone (PD-2, PD-3, PD-4).** Location DNA goes from 0.9% of listings to 100%, the marginal cost of a listing goes to zero, and Google leaves the intelligence foundation. **Version 1 does not ship without this phase.**

### Objectives

1. Replace Google Nearby Search with corpus queries.
2. Preserve `summary_json` / `lifestyle_json` / `property_location_pois` contracts.
3. Validate parity, then enable automatic generation for **every** listing including all 667 Bridge properties.
4. Delete the manual "Generate Location DNA" button.

### Deliverables

- **`CorpusPoiAdapter implements PoiLookupAdapterInterface`** — LATERAL KNN against `places`, authority-aware ranking, returning the canonical 9-key shape.
- **Flip the capability map:** `capabilities['poi.default']` base → `corpus`. `google_places` demoted to `enabled => false`.
- **Taxonomy activation** (SIA-D6): 23 categories, including the four approved in `LOCATION_DNA_PHASE_A...md` §5 and **never built** — `airport`, `urgent_care`, `highway_access`, `downtown`.
- **Wire the five invisible categories.** `school`, `hospital`, `gym`, `fitness_center`, `shopping_center` are fetched at Google cost today but map to **no thematic block, no lifestyle score, and no context** — approximately **25% of all POI spend, buying nothing** (corroborated by `LOCATION_DNA_AUDIT.md` §9). New thematic blocks: `family_infrastructure`, `healthcare_access`.
- **Retire the rating dependency** (SIA-D4): remove `top_rated_dining`; stop writing `rating` / `user_ratings_total`; replace the beach narrative's `ranking_score ≥ 45.0` gate with PAD-US area + NOAA shoreline adjacency; merge `fitness_center` into `gym`.
- **Retire** `LocationDnaPoiTileCache`, `LdnaBenchmarkTilePrecision`, `LdnaPoiCostReport`.
- **Enable automatic generation.** Remove `AgentLocationDnaController::generate()`, its route, and the agent-panel button. Backfill all listings via `ldna:refresh-all` — which now costs **$0** and makes no network call.
- **Rename** `LdnaRefreshAll`'s semantics: it currently *deletes all POI rows to force a fresh Google fetch*. It becomes a local recompute.

### Files / systems affected

`LocationDnaPoiDistanceService.php` (network layer removed) · new `CorpusPoiAdapter.php` · `LocationDnaSummaryService.php` (new thematic blocks) · `LocationDnaLifestyleScoreService.php` (beach gate) · `LocationDnaRankingProfileService.php` (re-tuned weights) · `config/location_providers.php` · `config/location_dna.php` · `AgentLocationDnaController.php` + route + `partials/location-dna-agent-panel.blade.php` · `LdnaRefreshAll.php`, `LdnaRerankAll.php` · **retired:** `GooglePlacesPoiAdapter.php`, `LocationDnaPoiTileCache.php`, `LdnaBenchmarkTilePrecision.php`, `LdnaPoiCostReport.php`

**Direct table consumers that must not break** (they bypass `LocationDnaPresenter`): `SellerOfferListing.php:1972`, `SellerOfferListingEdit.php:1638`, `LandlordOfferListing.php`, `LandlordOfferListingEdit.php`, `SellerOfferListingController.php:125`, `LandlordOfferListingController.php:176`, `Admin/DnaProfileController.php:45`, `Admin/DnaInspectorController.php:427`.

### Dependencies

Phase 1 (seam + engine normalisation), Phase 2 (corpus + Gates 1–2).

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| **The agent panel reads *all* ranks and displays top-3** — see **Erratum E-1**. Storing rank-1 only (as Architecture §13 proposed) silently regresses it. | **High** | **Store top-3 per category** (configurable `N`, default 3). This is 57 rows/listing, not 84 and not 19. Corrects the governing document. |
| POI selection drift changes which name a consumer sees | **High** | **Gate 3 — dual-run diff.** Run `CorpusPoiAdapter` alongside Google for the 13 enriched listings; diff rank-1 selection per category. Product-owner review of every diff. |
| `summary_json` / `lifestyle_json` shape change breaks Ask AI, Property DNA, Stellar, admin | **High** | INV-3. New thematic blocks are **additive keys**. Contract tests on both JSON documents. `LocationDnaPropertyContextService`, `LocationDnaIntelligenceContextService`, `LocationDnaMarketingContextService`, `LocationLifestyleBridgeGenerator`, `AskAiContextBuilderService` all read these — snapshot each. |
| Removing `top_rated_dining` breaks `nearest_top_rated_dining_miles` consumers | Medium | It appears in `matchmaker-nearby` and the admin card only; the lifestyle service explicitly does not read it. Remove the key **and** its consumers in the same change. |
| Backfilling 1,226 listings floods the queue | Medium | Phase 0 gave us a real queue. Chunk and rate-limit. It is CPU + DB only; no external service to overwhelm. |
| `capabilityHash()` change invalidates every `fetch_version` | Low | Intended. Recompute is now free. |

### Testing requirements

- **Gate 3 — dual-run diff**, product-owner signed off.
- Golden-master: `lifestyle_json` scores for the 11 enriched listings must be *explainable*, not identical (POI selection legitimately changes). Every delta reviewed.
- Contract tests: `summary_json` and `lifestyle_json` keys are a **superset** of today's.
- Per-role matrix (INV-2): `seller`, `landlord`, `seller_agent`, `landlord_agent`, `bridge`.
- **Network guard: `ComputeLocationDna` makes zero outbound calls.** This is INV-1 and the phase's headline assertion.
- All 43 existing Location DNA tests green.
- Admin DNA inspector and agent panel render top-3 per category (E-1 regression test).
- Load: enrich 1,226 listings end-to-end; record wall clock and p95 job duration.

### Rollback strategy

**Config flip.** Set `capabilities['poi.default']` base → `google_places`, `enabled => true`, re-enable the Google key. `fetch_version` mismatch triggers a refetch. Cost: one full catalogue refetch (~$628 at current volume). **No schema change is reverted** — `rating` columns still exist and simply resume being written.

Rollback window: unlimited, until Phase 5 removes the Google code paths.

### Definition of Done

- [ ] `ComputeLocationDna` makes **zero** outbound network calls (metric-verified in production).
- [ ] Location DNA present for **100%** of seller, landlord, and Bridge listings.
- [ ] Marginal cost per listing = **$0**.
- [ ] Gate 3 passed and signed off.
- [ ] `top_rated_dining` gone; `rating` / `user_ratings_total` no longer written.
- [ ] `airport`, `urgent_care`, `highway_access`, `downtown` live (Phase A §5 finally honoured).
- [ ] The five previously-invisible categories feed thematic blocks.
- [ ] `AgentLocationDnaController::generate()` and its UI button deleted.
- [ ] Top-3 per category preserved for the agent panel (E-1).
- [ ] INV-1 through INV-9 hold.
- [ ] **PD-3 satisfied: no Google data is consulted to produce Location DNA.**
- [ ] **PD-4 satisfied: automatic enrichment for Seller, Landlord, and MLS/Bridge at $0 marginal cost.**
- [ ] Rollback to `google_places` verified as still available (it remains so until Phase 5).

> **On completing this phase, the platform is ready for Phase 6a certification and Version 1 launch.**

---

## Phase 4 — Buyer & Tenant Location Intelligence

> **Post-launch (PD-5). Net-new capability; nothing to regress.** Drive-time and commute matching are impossible today at *any* price under a metered per-request model — no hosted routing provider permits storing computed results.

### Objectives

1. Make Location Preference DNA a first-class artifact (SIP-P7).
2. Deliver radius, polygon, drive-time, commute-time, and four travel modes.
3. Wire `important_places_json` into matching — it is captured today and **read by nothing**.
4. Land distance and compatibility scoring on both sides of `dna_scores`.

### Deliverables

- **`location_preference_geometries`** — one row per geometry, `geography(Geometry,4326)`, GiST-indexed. Kinds: `radius | polygon | isochrone | city | zip | county | important_place`. This closes the gap `bid-your-offer-v2.1-architecture-review.md` named explicitly: *"Buyer/Tenant Location **Preference** DNA is not a defined artifact… This is a real gap."* It implements the roadmap principle *"a searcher's **where** is DNA, not a filter."*
- **`ValhallaCommuteAdapter implements CommuteTimeAdapterInterface`.** The contract, `CommuteTimeLookupService`, its cache, and its `max_destinations` cap already exist and are correct. Only `CommuteTimeStubAdapter` (which returns `travel_time_minutes => null` unconditionally) is replaced.
- **`IsochroneEngine` + `isochrone_cache`.** The architectural key: *"which listings are within 30 minutes of my office"* resolves to **one** Valhalla isochrone → **one** indexed `ST_Contains` against the whole corpus. All-pairs precompute is explicitly rejected (quadratic).
- **Wire `important_places_json` into matching.** It already stores exactly the target model — `{type, address, lat, lng, distance_pref: "miles"|"minutes", distance_value, travel_mode: driving|walking|bicycling|transit}` — with multiple destinations. It has **zero rows** today (built, never populated), so there is **no data migration burden**. `BuyerCriteriaPayload`, `BuyerMatchQueryBuilder`, and `GeoEnvelopeNarrower` must begin consuming it.
- **Replace PHP Haversine + ray-cast PIP with PostGIS.** `BuyerMatchQueryBuilder` currently computes `latDelta = miles/69.0` bounding boxes over a `(latitude, longitude)` B-tree; `GeoEnvelopeNarrower` then re-runs exact Haversine and ray-casting in PHP. Both become `ST_DWithin` / `ST_Contains`.
- **Draw real isochrones.** `ImportantPlacesService` currently, and correctly, refuses to draw a circle for a "minutes" preference: *"an accurate isochrone cannot be drawn, and a plain radius would be a fake travel-time circle, which the audit forbids."* Phase 4 makes the honest drawing possible.
- **Populate `property_location_pois.travel_time_minutes`** — a column that has been `null` since inception.
- **New `dna_scores` keys** (both sides): `commute_convenience`, `location_compatibility`.

### Files / systems affected

`app/Services/Offers/ImportantPlacesService.php` · `app/Http/Livewire/OfferListing/Concerns/HasImportantPlaces.php` · `app/Services/Stellar/Matching/BuyerMatchQueryBuilder.php` · `app/Services/Dna/Relevance/Narrowers/GeoEnvelopeNarrower.php` · `app/Services/Dna/Relevance/CandidateAttributeProfile.php` · `app/Services/LocationDna/CommuteTimeLookupService.php` · `app/Services/LocationDna/LocationMatchEngine.php` · `app/Services/LocationDna/LocationDnaChipPresenter.php` (new preference kinds) · `resources/views/partials/location-dna/map-input.blade.php` (isochrone overlay) · **retired:** `CommuteTimeStubAdapter.php` · new: `location_preference_geometries`, `isochrone_cache`, `commute_cache`

### Dependencies

Phase 2 (Valhalla, PostGIS), Phase 3 (canonical taxonomy live).

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Replacing Haversine/PIP changes which listings match | **High** | Dual-run: PostGIS vs PHP over the existing 4 `location_dna_preferences` rows and synthetic geometries. Assert set equality within a tolerance; investigate every difference (PostGIS is more correct — geodesic vs planar). |
| Isochrone latency on the search path | Medium | Cache by `(origin_geohash, mode, minutes, routing_version)`. A cache hit is a pure containment query. |
| Transit mode has no engine | **High** | **Valhalla's GTFS support is experimental** and has known "unconnected regions" failures. **There is no national US transit graph** (SIA-D15). Ship drive/walk/bike in Phase 4; ship transit per-metro (OTP2) later, or degrade transit to "transit stop proximity" (GTFS + NTD) and label it honestly. |
| `map-input.blade.php` is 1,500 LOC | Medium | Touch only the overlay layer here; the full MapLibre rewrite is Phase 5. |
| Matching V2 remains flag-gated off | Low | Intended. `MatchResultPersister`'s hard production write-refusal stays until Phase 5. |

### Testing requirements

- Geometry parity: PostGIS vs PHP Haversine/PIP over a synthetic corpus; every divergence explained.
- Gate 4 — routing parity: Valhalla drive times vs ground truth on a sample corridor set.
- Isochrone containment: a listing known to be inside/outside a 30-minute polygon.
- `important_places_json` round-trip: capture → geometry → match → score.
- Four travel modes exercised; transit explicitly asserted as *unsupported or degraded*, never silently wrong.
- INV-5 (a missing commute is `null`, never `0`), INV-6 (HOPA gate).
- Per-role: buyer + tenant.

### Rollback strategy

`config('location_dna.commute_time.provider') => 'stub'`; hide commute UI; revert `BuyerMatchQueryBuilder` to the Haversine path behind a flag. All new tables are additive and unread on rollback. **Nothing pre-existing regresses** — none of this functionality exists today.

### Definition of Done

- [ ] `location_preference_geometries` populated for every buyer/tenant with saved geometry.
- [ ] Drive-time search returns in <1s at corpus scale.
- [ ] Radius, polygon, drive-time, commute-time all matchable.
- [ ] Driving / walking / bicycling supported; transit either supported per-metro or **honestly labelled unavailable**.
- [ ] Multiple destinations per criteria.
- [ ] `important_places_json` read by the matcher (it is read by nothing today).
- [ ] `travel_time_minutes` populated.
- [ ] `dna_scores` carries `commute_convenience` and `location_compatibility` on both sides.
- [ ] Gate 4 passed.
- [ ] INV-1 through INV-9 hold.

---

## Phase 5 — Property DNA, Target Market Intelligence & Final Google Removal

> **Post-launch (PD-5). Two independent workstreams in one phase.** The intelligence work is low-risk. The Google removal ends with a **one-way** step.
>
> **This phase removes Google from map rendering and address entry — not from Location DNA, which Phase 3 already handled.** Deferring it past launch is deliberate: it preserves the Phase 3 rollback path through V1 (§0.4).

### Objectives

1. Complete Property DNA (populate `location_score`; add spatially-derived attributes).
2. Complete Target Market Intelligence on a fully proprietary basis.
3. Remove the remaining Google dependencies — **geocoding, then autocomplete, then maps, in that order.**
4. Validate every scoring and intelligence output.

### Deliverables

**Property DNA completion**
- Populate `location_score` from the Spatial Core via `LocationLifestyleBridgeGenerator`. It is one of four columns hardcoded `null` (`location_score`, `condition_score`, `legal_score`, `compatibility_score`).
- Add spatially-derived property attributes obtainable from position alone and from no listing form: elevation (USGS 3DEP), coastal proximity, flood zone, transportation-noise exposure (BTS), dark-sky (VIIRS).
- **`condition_score` and `legal_score` stay `null`.** No source exists. Do not fabricate.
- Document (do not necessarily rename) that `ai_buyer_archetype_tags` and `ai_marketing_hooks` are **deterministic** — `PropertyDnaGenerator` makes **no OpenAI calls**, despite the `ai_` prefix, at **$0 per listing**.

**Target Market Intelligence completion**
- Enrich `PropertyIntelligenceProfileService` from FHFA HPI, Census Building Permits, BLS QCEW, HUD Fair Market Rents — **housing-stock and labour-market facts only.**
- **SIA-D8 is binding: no demographics.** `LOCATION_DNA_PHASE_A...md` §4 lists "Census demographic statistics by tract or ZIP" as a **Prohibited Input**. This resolves the conflict with `CENSUS_INTELLIGENCE_PHASE_5_3...md` in favour of Phase A.
- **Blocking legal item (Gate 5).** Phase A §8 prohibits the outputs "Ideal for families" and "Best for retirees." Today `LocationDnaLifestyleScoreService` emits lifestyle categories `'Families'` and `'Retirees'`; `PropertyIntelligenceProfileService` derives audiences `'Retirees'` and `'Move-Up Families'`; and `property_target_audiences` is injected into the **Ask AI response contract**, an AI surface producing consumer-facing text. The consumer badge (`matchmaker-target-audience.blade.php`) renders safe `archetype_tags`, and `AskAiComplianceGuardrailService` exists — so this **may already be mitigated**. **This roadmap asserts an unresolved reconciliation, not a violation.** It must be closed by counsel before this phase ships. Recommended reframe: "Park & School Proximity Score", "Single-Level Living & Healthcare Access Score" — describe the property, never the person.
- Wire `LocationDnaMarketingContextService` into the AI pipeline. It is built, tested, and deliberately never injected (a "DEFERRED HOOK").

**Ask AI intelligence layer**
- Ask AI consumes the enriched `summary_json` / `lifestyle_json` / `property_intelligence` — **facts in, prose out.** No raw provider payloads. No demographics. Preserve the existing refusal rules and `AskAiComplianceGuardrailService`.

**Final Google removal — strictly ordered**
1. **Geocoding.** Chain: MLS coords (100% of Bridge) → NAD → Census Geocoder → Geoapify. Retire `LocationDnaGeocodeService`'s Google branch and `GeocodeSelleryLandlordListings`. Preserve the git-C14.2 posture: *an honest NOT_FOUND is always preferable to a confident wrong-city result.*
2. **Autocomplete.** Photon (self-hosted) or Geoapify across 12 Livewire proxies, `byo-address-autocomplete`, and ~25 legacy Blade forms. **This also closes violation V2** — `map-input.blade.php:860` calls `nominatim.openstreetmap.org` **directly from end-user browsers**, which OSMF policy forbids and which the platform's own git-C14.2 note explicitly prohibits.
3. **Maps — LAST, ONE-WAY.** MapLibre GL + Protomaps across three surfaces. `map-input.blade.php` (**1,500 LOC** — custom click-drawing, three `Autocomplete` instances, a Data-layer polygon loader) is the single largest file in the programme, larger than the POI swap. It already sources boundaries from Nominatim and TIGERweb, so its data layer is half-migrated.

**Enablement**
- `DNA_SCORES_GENERATION_ENABLED=true` → `MATCHING_V2_ENABLED=true` → persistence, in that order, each observed.

### Files / systems affected

`PropertyDnaGenerator.php` · `PropertyIntelligenceProfileService.php` · `LocationLifestyleBridgeGenerator.php` · `LocationDnaMarketingContextService.php` · `AskAiContextBuilderService.php` · `AskAiResponseContractService.php` · `LocationDnaGeocodeService.php` · 12 Livewire autocomplete proxies · `components/byo-address-autocomplete.blade.php` · `components/google-maps-script.blade.php` (deleted) · `components/location-dna-map.blade.php` · `partials/location-dna/map-input.blade.php` · `components/stellar/property-map.blade.php` · ~25 legacy Blade forms · `config/dna_scores.php`, `config/matching.php` · **deleted:** `config/google_places.php`, `services.google.places_key`

### Dependencies

Phases 2–4. **Gate 5 (legal sign-off) blocks the TMI/audience work.** The maps step blocks on geocoding + autocomplete being fully off Google (SIA-D10).

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| **The maps step is irreversible.** Once a non-Google basemap renders, Google Places/Geocoding may not lawfully be used anywhere. | **High** | Sequence last. Explicit written go/no-go. Preconditions: Gates 1–4 passed; corpus refresh automated; Valhalla ≥30 days in production. |
| Legal review returns adverse on audience labels | **High** | Reframe to property attributes. The scores themselves are pure distance and unaffected — only the *labels* change. |
| Autocomplete migration spans ~38 call sites across 4 roles | Medium | Migrate the shared `byo-address-autocomplete` component first; legacy Blade forms follow. Per-role browser QA. |
| Enabling Matching V2 changes visible match results | Medium | Enable `dna_scores` generation first, observe, then `MATCHING_V2_ENABLED`, then persistence. Each independently reversible. `MatchResultPersister` refuses production writes by design until explicitly promoted. |
| Ask AI regression | Medium | Its inputs become *richer*, not different in shape. Contract tests on `AskAiResponseContractService`. |

### Testing requirements

- Full browser QA across all 13 forms embedding `map-input.blade.php`, all 4 roles.
- Address-resolution regression suite; the NOT_FOUND posture preserved.
- Geocode fallback chain: MLS hit, NAD hit, Census hit, Geoapify hit, total miss.
- `dna_scores` two-sided symmetry; `weights sum to 100` (`config/match_scoring.php`).
- Ask AI contract tests; refusal rules exercised for steering-shaped questions.
- INV-7 (no demographics) verified by input allowlist review.
- **Gate 5** — written legal sign-off.

### Rollback strategy

| Step | Rollback |
|---|---|
| Property DNA / TMI | Feature flags; `location_score` reverts to `null` |
| Ask AI wiring | Revert the context injection |
| Geocoding | Re-point the adapter chain |
| Autocomplete | Re-point the adapter |
| **Maps** | **None practical.** Days of work to restore. Gate explicitly. |
| Matching V2 | `MATCHING_V2_ENABLED=false` (already the default) |

### Definition of Done

- [ ] `location_score` populated; spatial attributes present; `condition_score` / `legal_score` honestly `null`.
- [ ] TMI enriched from housing-stock and labour-market data only; **zero demographic inputs**.
- [ ] **Gate 5 passed** — legal sign-off on audience labels recorded.
- [ ] `LocationDnaMarketingContextService` wired.
- [ ] `grep -rn "maps.googleapis.com" app/ resources/` → **zero** (excluding fonts).
- [ ] `GOOGLE_PLACES_API_KEY` deleted; `config/google_places.php` removed.
- [ ] Public-Nominatim browser calls removed (**V2 closed**).
- [ ] `dna_scores` generation on; Matching V2 on; persistence on.
- [ ] INV-1 through INV-9 hold.

---

## Phase 6 — Production Readiness

> **No new features. Prove the system.**
>
> **This phase runs TWICE (§0.4).** It is a gate, not a terminal step.
>
> | Pass | When | Scope | Destructive migration (B12)? |
> |---|---|---|---|
> | **6a — V1 certification** | After Phase 3, **before Version 1 launch** | Phases 0–3 deliverables only | ❌ **Excluded** |
> | **6b — Google-removal certification** | After Phases 4–5 | Routing, Preference DNA, autocomplete, the one-way maps cut | ✅ Executes here |
>
> Everything below applies to **both** passes unless marked **[6a only]** or **[6b only]**.

### Objectives

Certify performance, correctness, and reversibility; execute cutover; and — in 6b only — drop the last dead schema.

**Why 6a cannot be skipped.** Version 1 ships automatic Location DNA to 100% of listings, up from 0.9%. That is a ~110× increase in enrichment volume against a brand-new spatial substrate, on infrastructure that has never run a background worker. Certification is not ceremony; it is the first time this system is exercised at production scale.

### Deliverables

**Performance testing**
- KNN p95 at corpus scale (target: <50 ms per category).
- Isochrone → `ST_Contains` p95 (target: <1 s end-to-end).
- `ComputeLocationDna` p95 job duration; queue depth under a full backfill.
- `EXPLAIN ANALYZE` on every hot spatial query; GiST usage confirmed.

**Load testing**
- Full-catalogue re-enrichment (all listings) with wall-clock recorded — this is the `ldna:refresh-all` path, now free.
- Concurrent buyer searches issuing isochrone queries.
- Corpus monthly refresh executed under production-like load (staging swap + `CLUSTER` + `ANALYZE`).

**Regression testing**
- All 43 Location DNA test files, plus the per-role matrix across `seller`, `landlord`, `seller_agent`, `landlord_agent`, `bridge`, `buyer`, `tenant`.
- Golden-master comparison of `summary_json` / `lifestyle_json` against the Phase-3 baseline.
- Frozen-code diff review: `initializeLimitedService()`, `TenantAgentAuction`, `LOCKED_BidComparison` untouched (INV-8).

**Browser testing**
- **[6a]** Agent panel and admin DNA inspector render top-3 per category (E-1). Stellar property detail renders Nearby Amenities for enriched listings. Google maps and autocomplete still function unchanged — verify no collateral regression from the seam work.
- **[6b]** All 13 forms embedding `map-input.blade.php`; both `location-dna-map` surfaces; the Stellar property detail page. Four roles × create/edit/view.
- The three previously-dead placeholder cards (flood, commute, walkability): **[6a]** flood renders real data or is removed; **[6b]** commute and walkability likewise (`location-dna-architecture-review.md` §4).

**Monitoring**
- Dashboards: outbound-call count (**must be 0**), queue depth, job failure rate, corpus freshness (`corpus_imports.last_success`), Valhalla health, `data_completeness` distribution.
- Alerts: corpus staleness > 45 days; any outbound Google call; queue depth breach.

**Production cutover & rollback validation**
- **[6a]** **Rehearse the Phase 3 rollback in staging** (flip back to `google_places`, verify refetch). Still fully available at V1 — Phase 5 has not yet removed the code paths. **Re-rehearse in 6b as the final check before the one-way maps cut.**
- Cutover runbook with go/no-go criteria and named owners.

**Final launch certification**
- **[6a]** Complete the §12 *Version 1* gate. Extend `docs/launch-audits/bidyouroffer-launch-certification.md` and `bidyouragent-launch-certification.md` with a Spatial Intelligence section.
- **[6b]** Complete the §12 *Google-removal* gate.

**Schema cleanup — [6b only] (the only destructive migration in the programme)**
- Drop `property_location_pois.rating`, `.user_ratings_total` — after confirming zero readers.
- Drop `pois_fetch_version` once `corpus_version` is fully dual-written.
- **Deliberately deferred past V1.** These columns stop being *written* in Phase 3 and hold stale, ignored data through launch. That is acceptable and reversible; dropping them before launch is neither, and it would foreclose the Phase 3 rollback.

### Files / systems affected

`tests/**` · monitoring configuration · `docs/launch-audits/*` · one destructive migration

### Dependencies

**6a:** Phases 0–3. **6b:** Phases 4–5, plus **Gate 5** for anything touching audience labels.

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Load testing reveals KNN too slow at CONUS scale | Medium | Partition `places` by region (GiST cascades from the parent); H3 hex index as the designated v2 optimisation. |
| **The destructive migration is executed during 6a** | **High** | **B12 is 6b-only.** Running it at V1 would drop the `rating` columns the admin DNA card still reads, and would foreclose the Phase 3 rollback. Gate on `grep -rn "user_ratings_total" app/ resources/` → zero, and on Phase 5 being complete. Back up first. It is the **only** irreversible schema change. |
| Rollback rehearsal fails | **High** | Rehearse in 6a (Google path intact) and again in 6b **before** Phase 5's maps step. |
| **[6a]** V1 launches on a spatial substrate never run at scale | **High** | 6a exists for this. ~110× enrichment-volume increase (0.9% → 100% of listings) on brand-new infrastructure. Full-catalogue backfill under load, with p95 recorded, before launch. |
| Corpus refresh fails silently in production | Medium | `corpus_imports` ledger + staleness alert; a stale corpus degrades gracefully and never blocks a request. |

### Testing requirements

Everything above, plus a **documented rollback rehearsal** with timing.

### Rollback strategy

Phase 6 adds no functionality. Rollback = defer cutover. The destructive migration (6b) is the sole exception: back up `property_location_pois` before it runs.

### Definition of Done — 6a (Version 1 launch)

- [ ] §12 **Version 1** gate fully green.
- [ ] **Outbound Google calls from the enrichment path: 0**, sustained over 7 days in production. *(Maps/autocomplete calls continue and are expected — they are outside the enrichment path.)*
- [ ] Full-catalogue backfill executed; wall clock and p95 job duration recorded.
- [ ] Performance targets met and recorded.
- [ ] Phase 3 rollback rehearsed in staging and documented, **and confirmed still available in production**.
- [ ] Monitoring and alerts live; corpus-staleness alert configured.
- [ ] Launch certification docs updated with a Spatial Intelligence section.
- [ ] **B12 not executed.**
- [ ] INV-1 through INV-9 hold in production.

### Definition of Done — 6b (Google removal)

- [ ] §12 **Google-removal** gate fully green.
- [ ] `grep -rn "maps.googleapis.com" app/ resources/` → zero (excluding fonts).
- [ ] Rollback rehearsed immediately before the one-way maps cut.
- [ ] Destructive migration (B12) executed with a verified backup.
- [ ] Total outbound Google calls in production: **0**.

---

## 10. Migration safety by subsystem

The technique is uniform: **the persisted shape is the contract; only the writer changes.** Below, what each subsystem reads and what protects it.

| Subsystem | Reads | Breaks if… | Protection |
|---|---|---|---|
| **Seller listings** | `property_location_pois` **directly** (`SellerOfferListing.php:1972`, `SellerOfferListingEdit.php:1638`, `SellerOfferListingController.php:125`) — `orderBy('poi_category')->orderBy('rank')`, displays top-3 | rank>1 rows disappear (**E-1**); category keys change | Store top-3 (not rank-1); additive category keys only; per-role snapshot tests |
| **Landlord listings** | Same, via `LandlordOfferListing*` and `LandlordOfferListingController.php:176` | Same | Same |
| **Buyer criteria** | `location_dna_preferences` meta; `BuyerMatchQueryBuilder` (Haversine bbox); `LocationMatchEngine` | PostGIS geometry disagrees with PHP PIP/Haversine | Phase 4 dual-run set-equality; PostGIS is geodesic and *more* correct — every diff reviewed |
| **Tenant criteria** | Same as buyer. **`TenantAgentAuction` is frozen** (excluded from `HasListingLifecycle`, per `CLAUDE.md`) | Anyone refactors it | INV-8; it is read-only in this programme |
| **MLS / Bridge** | `bridge_properties.latitude/longitude` (**100% populated**); `ComputeLocationDna::dispatch('bridge', …)` from `LazyBridgeImportService` and `ImportBridgeProperties` | The dispatch gate (`isNew ‖ addressChanged`) is disturbed; geocode short-circuit (`geocode_source='saved_meta'`) breaks | Do not touch `BridgePropertyNormalizer::upsert()`. Bridge is the **largest beneficiary** — 667 listings become enrichable at $0 |
| **Property DNA** | `PropertyDnaProfile`; `LocationDnaIntelligenceContextService` (checks `available_categories` for `coastal_features`) | Category keys are renamed; `summary_json` loses a key | Additive-only key policy (INV-3); `fitness_center` merge is the sole rename — audit `available_categories` consumers first |
| **Target Market Intelligence** | `PropertyDnaProfile` archetype tags + Location DNA context | Audience labels change; demographics leak in | Gate 5 (legal); INV-7 |
| **Ask AI** | `summary_json`, `lifestyle_json`, `property_intelligence.property_target_audiences` via `AskAiContextBuilderService` / `AskAiResponseContractService` | Context keys change; a prohibited output reaches the prompt | Contract tests; `AskAiComplianceGuardrailService` retained; refusal rules exercised |
| **Admin DNA inspector** | `property_location_pois` incl. `rating`, `user_ratings_total` (`admin/dna/partials/location-dna-card.blade.php:168-176`) | `rating` columns dropped early | Columns stop being **written** in Phase 3; **dropped** only in Phase 6, after the view is updated |
| **Agent panel** | Top-3 per category + `location_narrative` | **E-1** | Store top-3 |
| **Compatibility scoring** | `ComputeCompatibilityScore` → `LocationMatchIntegrationService` | Match contract's 10-key shape changes | `LocationMatchEngine`'s contract is frozen for this programme |

**Role symmetry (INV-2).** `CLAUDE.md` states almost everything is quadruplicated by role. Every Location DNA change is verified across five listing types: `seller`, `landlord`, `seller_agent`, `landlord_agent`, `bridge`.

---

## 11. What keeps operating during migration

**Everything. Continuously.** Location DNA is currently generated for 11 of ~1,226 listings and Google Places has been disabled since 2026-07-06, so the migration starts from a position where the feature is already dark.

| Capability | P0 | P1 | P2 | P3 | P4 | P5 | P6 |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Seller / Landlord listing create & edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Buyer / Tenant criteria & search | ✅ | ✅ | ✅ | ✅ | ✅↑ | ✅ | ✅ |
| MLS / Bridge import | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bidding, offers, compatibility scoring | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property DNA | ✅ | ✅ | ✅ | ✅ | ✅ | ✅↑ | ✅ |
| Target Market Intelligence | ✅ | ✅ | ✅ | ✅ | ✅ | ✅↑ | ✅ |
| Ask AI | ✅ | ✅ | ✅ | ✅ | ✅ | ✅↑ | ✅ |
| **Location DNA (existing 11 listings)** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Location DNA (all listings, $0 marginal)** | ✗ | ✗ | ✗ | **✅ 🚀** | ✅ | ✅ | ✅ |
| Google Maps rendering | ✅ | ✅ | ✅ | ✅ 🚀 | ✅ | →MapLibre | ✅ |
| Address autocomplete | ✅ | ✅ | ✅ | ✅ 🚀 | ✅ | →Photon | ✅ |
| **Drive-time / commute matching** | ✗ | ✗ | ✗ | ✗ | **✅** | ✅ | ✅ |

`↑` = enriched. `🚀` = state at **Version 1 launch** (after Phase 3 + Phase 6a).

Maps and autocomplete are **swapped within Phase 5, never absent** — and they still run on Google at V1, outside the enrichment path (§0.4). The single user-visible change at V1 is that Location DNA appears on every listing instead of ~1%.

---

## 12. Production Launch Gate

### Required for **Version 1 launch** — Phases 0–3 + Phase 6a (mandatory, PD-2)

- [ ] **Infrastructure:** queue worker, scheduler, Redis, PostGIS, real app server. `offers:expire-pending` verified.
- [ ] **Cost protection:** kill switch wired and proven; daily/hourly caps enforced; budget alert; key restricted and segregated.
- [ ] **Test isolation:** all four RCA remediations shipped; suite makes zero outbound calls.
- [ ] **Gate 1** — prominence prior ≤3% embarrassment, **or** the Foursquare contingency formally triggered and budgeted.
- [ ] **Gate 2** — corpus coverage accepted per category.
- [ ] **Gate 3** — dual-run diff reviewed and signed off.
- [ ] **INV-1** — `ComputeLocationDna` makes zero outbound calls, metric-verified in production.
- [ ] Location DNA present for 100% of seller, landlord, and Bridge listings.
- [ ] **SABS staleness handled** — assigned-school claims are advisory-labelled or absent (NCES SABS is frozen at 2015-16, ~10 years stale). District boundaries from TIGER are current and may be shown.
- [ ] **FEMA "not mapped" never renders as "no flood risk"** (INV-5 / SIP-P12). Zone D = undetermined.
- [ ] `data_completeness` degrades honestly on unenriched listings; no listing is penalised for missing data.
- [ ] Rollback to `google_places` rehearsed in staging **and confirmed still available in production**.
- [ ] All 43 Location DNA tests green; per-role matrix green.
- [ ] **PD-3 satisfied:** no Google data is consulted to produce Location DNA.
- [ ] **PD-4 satisfied:** automatic Location DNA for Seller, Landlord, and MLS/Bridge at $0 marginal cost.
- [ ] **B12 (destructive migration) NOT executed** — it is Phase 6b only.
- [ ] Phase 6a certification issued.

> **Not required for V1:** Gate 4 (routing parity), Gate 5 (legal sign-off on audience labels), Phase 4 commute features, and Phase 5 Google removal. These gate the post-launch phases (PD-5).

### Additionally required before Google is fully removed — Phases 4–5 + Phase 6b (post-launch)

- [ ] **Gate 4** — routing parity.
- [ ] **Gate 5** — written legal sign-off on the §9.4 audience-label reconciliation.
- [ ] Geocoding and autocomplete fully off Google **before** the maps step (SIA-D10).
- [ ] Written go/no-go for the one-way maps cut, with named approver.
- [ ] Corpus refresh automated; Valhalla ≥30 days in production.
- [ ] Public-Nominatim browser calls removed (V2).
- [ ] Ratings no longer persisted (V1); single-key exposure resolved (V3).

### Additionally required before Matching V2 is enabled

- [ ] `dna_scores` generation observed for ≥7 days before `MATCHING_V2_ENABLED`.
- [ ] `SeniorCommunityComplianceGate` proven to run unconditionally (INV-6).
- [ ] `MatchResultPersister` production write-refusal removed only by explicit decision.
- [ ] Scoring framework Rule 2 (No Hidden Weighting) satisfied: weights disclosed, `scoring_framework_version` recorded.

---

## Appendix A — Code Reuse & Retirement Register

### Reused, unchanged

`FemaFloodZoneAdapter` · `CensusTigerBoundaryAdapter` · `CensusSchoolDistrictAdapter` · `FloodZoneLookupService` · `SchoolDistrictLookupService` · `BoundaryLookupService` · `LocationDnaVersionService` · `LocationDnaAuditService` · `LocationMatchEngine` · `LocationMatchInsightService` · `LocationMatchIntegrationService` · `LocationMatchAuctionExtractor` · `LocationPreferenceAnalyzer` · `LocationIntelligenceComposer` · `LocationDnaChipPresenter` · `PropertyDnaGenerator` · `BuyerTenantDnaGenerator`

### Reused, modified

| File | Change | Phase |
|---|---|---|
| `LocationDnaRankingEngine` | Input shape normalised off raw Google JSON | 1 |
| `LocationDnaPoiDistanceService` | Network layer removed; delegates to the adapter | 1→3 |
| `LocationDnaRankingProfileService` | Weights re-tuned for the prominence prior | 3 |
| `LocationDnaSummaryService` | Two new thematic blocks (additive) | 3 |
| `LocationDnaLifestyleScoreService` | Beach narrative gate re-sourced | 3 |
| `LocationDnaPipelineRunner` | Stage contract preserved; sources swapped | 3 |
| `LocationDnaGeocodeService` | Google branch → fallback chain | 5 |
| `LocationDnaPresenter` | Promoted to the single read model | 1→3 |
| `LocationProviderRegistry`, `CanonicalLocationMerger` | Wired (built, tested, currently inert) | 1 |
| `CommuteTimeLookupService` | Real adapter injected | 4 |
| `PoiDistanceLookupService` | 7-category view over the canonical registry | 1→3 |

### Retired

| File / concept | Phase | Reason |
|---|---|---|
| `GooglePlacesPoiAdapter` | 3 | Replaced by `CorpusPoiAdapter` |
| `LocationDnaPoiTileCache` | 3 | Avoided metered calls; disabled today anyway |
| `LdnaBenchmarkTilePrecision` | 3 | Benchmarks a retired cache. *Note: its own doc records every measurement as **TBD** — the "0.005 recommended production default" in `config/location_dna.php` was never actually measured.* |
| `LdnaPoiCostReport` | 3 | Reports a cost that no longer exists |
| `top_rated_dining` + `TOP_RATED_DINING_*` | 3 | Rating-derived by definition (SIA-D5) |
| `rating` / `user_ratings_total` writes | 3 | Google ToS 30-day cap (V1) |
| `fitness_center` as a distinct category | 3 | Provider artifact |
| `AgentLocationDnaController::generate()` + route + button | 3 | Existed only because the API was metered |
| `CommuteTimeStubAdapter` | 4 | Replaced by `ValhallaCommuteAdapter` |
| `GeocodeSelleryLandlordListings` | 5 | Obsolete backfill |
| `components/google-maps-script.blade.php` | 5 | One-way |
| `config/google_places.php`, `services.google.places_key` | 6 | After the last reference |
| `rating` / `user_ratings_total` **columns** | 6 | Only destructive migration in the programme |

---

## Appendix B — Database Change Register

| # | Change | Type | Phase | Reversible |
|---|---|---|---|---|
| B1 | `CREATE EXTENSION postgis` | Additive | 0 | Yes |
| B2 | `places`, `place_categories` | New | 2 | Yes |
| B3 | `boundaries`, `boundaries_parts` | New | 2 | Yes |
| B4 | `listing_locations` | New | 2 | Yes |
| B5 | `corpus_imports` | New | 2 | Yes |
| B6 | Write `property_location_pois.confidence` / `provenance_json` / `last_refreshed` | Populate existing columns | 3 | Yes |
| B7 | `property_location_dna.corpus_version` (new nullable; dual-write with `pois_fetch_version`) | Additive | 3 | Yes |
| B8 | `location_preference_geometries` | New | 4 | Yes |
| B9 | `isochrone_cache`, `commute_cache` | New | 4 | Yes |
| B10 | Populate `property_location_pois.travel_time_minutes` | Populate existing column | 4 | Yes |
| B11 | New `dna_scores` keys | Data | 4–5 | Yes |
| B12 | **Drop** `rating`, `user_ratings_total`, `pois_fetch_version` | **Destructive** | **6b — post-launch only** | **No — backup required** |

`property_location_pois` and `property_location_dna` **keep their shape through Phase 5.** Only the writer changes. This is what makes every rollback a config flip.

**B1–B7 are the only schema changes required for Version 1**, and all are additive. B12 must **not** run before launch: the admin DNA card still reads `rating` / `user_ratings_total`, and dropping them would foreclose the Phase 3 rollback that V1 deliberately keeps open (§0.4).

---

## Appendix C — Data Import Register

| Dataset | Licence | Access | Cadence | Phase | Blocking |
|---|---|---|---|---|---|
| Overture Places (CONUS, `confidence ≥ 0.90`) | CDLA-Permissive-2.0 (+Apache/CC0) | GeoParquet/S3 via DuckDB | Monthly | 2 | ✅ |
| Census TIGER (ZCTA, place, county, school district) | Public domain | Bulk | Annual | 2 | ✅ |
| NCES CCD / EDGE | Public domain | Bulk | Annual | 2 | ✅ |
| USGS PAD-US 4.1 | Public domain | Bulk gdb | Periodic | 2 | ✅ |
| **USGS Boat Ramps** | **CC0** | Bulk points | Static (2023) | 2 | ✅ |
| CMS Hospital Star Ratings | Public domain | DKAN REST / CSV | Annual | 2 | ✅ |
| FAA NASR | Public domain | Bulk (28-day AIRAC) | 28-day | 2 | ✅ |
| EPA National Walkability Index | Public domain | Bulk | Periodic | 2 | ✅ |
| GTFS (Transitland, `commercial_use_allowed`) | **Per-feed** | REST | Daily catalog | 2 | ✅ |
| NTD ridership | Public domain | Socrata | Annual | 2 | ✅ |
| OSM extract (marinas, dog parks, golf) | ODbL | Bulk PBF | Continuous | 2 | — |
| NOAA CUSP shoreline | Public | Bulk shapefile | Periodic | 2 | — |
| USGS / USFS trails | Public domain | Bulk + WFS | Periodic | 2 | — |
| **Valhalla graph** (FL → CONUS) | OSM/ODbL | Built from PBF | On demand | 2 | ✅ |
| Protomaps PMTiles (CONUS) | ODbL | Built or downloaded | Periodic | 2 | — |
| DOT National Address Database | Public domain | Bulk | ~2–3×/yr | 5 | — |
| USGS 3DEP, BTS Noise, VIIRS | Public domain | Raster | Periodic | 5 | — |
| FHFA HPI, Census BPS, BLS QCEW, HUD FMR | Public domain | Bulk + REST | Monthly–annual | 5 | — |
| **FEMA NFHL** | Public domain | **Live API — not imported** | — | — | — |

**Never imported:** ACS demographics (SIA-D8, Phase A §4 Prohibited Input) · FBI crime data · EPA EJScreen · Zillow ZHVI/ZORI · Falchi night-sky (CC BY-NC).

---

## Appendix D — Errata to the governing document

### E-1 — POI storage granularity (corrects Architecture §13 and §17 Phase 2)

**The architecture document states:** *"Persist rank-1 only → `location_snapshots` (19 rows/listing, not 84)."*

**This is wrong and would cause a silent regression.** Six consumers read `property_location_pois` directly, outside `LocationDnaPresenter`, and fetch **all ranks**:

- `SellerOfferListing.php:1972`, `SellerOfferListingEdit.php:1638` — `orderBy('poi_category')->orderBy('rank')`
- `SellerOfferListingController.php:125`, `LandlordOfferListingController.php:176`
- `Admin/DnaProfileController.php:45`, `Admin/DnaInspectorController.php:427`

The agent panel displays **top-3 per category** (`location-dna-architecture-review.md` §4: *"Top-3 per category … data exists (10 stored)"*).

**Correction.** Persist **top-N per category, N configurable, default 3** → **57 rows/listing**. Still a 32% reduction from 84 and, more importantly, an *O(listings × categories × N)* table rather than a Google-fetch artifact. At 1M listings that is 57M rows — so the long-term target remains query-time LATERAL KNN with `location_snapshots` as a thin display cache; but **N must be ≥3 for the agent panel**, not 1.

**Also affected:** the architecture document's recompute discussion notes that recompute "cannot resurrect an 11th candidate Google originally returned." With an owned corpus this caveat disappears entirely — any N is re-derivable at will, for free. This *strengthens* the case for a small N.

### E-2 — Tile precision was never measured

`config/location_dna.php` describes `0.005` as the *"recommended production default (see docs/ldna-tile-precision-benchmark.md)."* That benchmark records **every** hit-rate and stability value as `TBD` and states: *"These values must be measured before a production recommendation can be made."* The recommendation is unsubstantiated. Moot once `LocationDnaPoiTileCache` is retired in Phase 3, but recorded so it is not carried forward.

---

## Appendix E — Open Questions

| # | Question | Blocks | Owner |
|---|---|---|---|
| **Q1** | **Hosting target** for app server + queue worker + Redis + managed PostGIS + Valhalla VM | **Phase 0 — the only true blocker in the programme** | Product owner |
| Q2 | Actual row count and on-disk size of a CONUS Overture Places extract at `confidence ≥ 0.90` (no official figure published) | Phase 2 sizing | Engineering — measure |
| Q3 | Valhalla build/serve RAM for a CONUS graph (all published figures are planet-scale) | Phase 2 sizing | Engineering — benchmark Florida first |
| Q4 | Geoapify's permanent-storage permission appears in marketing copy, not the binding T&C | Phase 5 | Obtain written confirmation |
| Q5 | Per-feed GTFS licensing — no blanket commercial-use grant exists | Phase 2 | Engineering + Legal |
| Q6 | Overture per-source NOTICE obligations (Apache slice) | Phase 2 | Legal (one-time review) |
| Q7 | §9.4 audience-label reconciliation against Phase A §8 | **Phase 5 (Gate 5)** | **Legal counsel** |
| Q8 | Do we promote *assigned school* to a product claim? If yes, ATTOM is required (SABS is ~10 years stale) | Phase 5 | Product + Legal |
| Q9 | Transit routing: OTP2 per-metro, or degrade to GTFS stop proximity? *(Phase 4 is post-launch; this no longer gates V1.)* | Phase 4 | Product owner |
| ~~Q10~~ | ~~Is a Location-DNA-only launch (Phases 0–3) acceptable, deferring Google removal to post-launch?~~ | — | — |

### Q10 — RESOLVED (2026-07-09, product owner)

**The question as originally posed was the wrong one, and is withdrawn.** It framed Phases 0–3 as a lesser "Location-DNA-only" launch and treated Google removal as a single, deferrable event.

**Resolution (§0.4, PD-1…PD-5):** Version 1 launches on the Spatial Intelligence foundation. Phase 0 is mandatory; **Phases 1–3 are required before V1 and are not deferrable.** Google-dependent Location DNA will not be the production foundation at launch, and automatic Location DNA for Seller, Landlord, and MLS/Bridge listings at $0 marginal cost is a **Version 1 requirement**.

Google removal is not one event but two, and only the first is a launch concern:

| | Removed from | Phase | V1? |
|---|---|---|---|
| **Google in the intelligence foundation** | Location DNA POI enrichment | **3** | **✅ Required** |
| Google in presentation | Map rendering, address entry, residual geocoding | 5 | Post-launch (PD-5) |

Phases 4 and 5 **enhance** the platform (PD-5). Deferring Phase 5 past launch is not a concession — it deliberately preserves the Phase 3 rollback path through V1, which the one-way maps cut would destroy.

**Consequential change:** Phase 6 (Production Readiness) now runs twice — **6a** certifies V1 over Phases 0–3; **6b** certifies Google removal after Phases 4–5. The destructive migration (B12) moves to 6b.

---

## Closing note

Two things are worth restating, because they determine whether this plan is executable at all.

**First: Phase 0 is not preparation. It is a prerequisite for correctness.** The platform cannot presently execute a background job. `ComputeLocationDna` runs inline in the user's web request, `offers:expire-pending` has never run, and the test suite reaches live Google. None of this is caused by Google, and none of it is fixed by leaving Google. It must be fixed regardless of whether this architecture is ever approved.

**Second: Gate 1 is the cheapest decision-grade experiment available, and it is now on the critical path to launch.** It costs days, no API spend, and uses 1,090 labelled POI rows already sitting in the production database. It determines whether the entire programme needs a paid provider. **Run it before approving the budget for anything else.**

The evidence assembled in the governing document indicates it will pass. But the plan is arranged so that if it does not, the discovery happens in Phase 2 — before a single consumer-facing behaviour has changed, and before Google has been removed from anything.

**Third, on the approved V1 scope (§0.4).** Requiring Phases 1–3 before launch is the right call, and it is the *safer* one, not the braver one. Google Places has been disabled since 2026-07-06; Location DNA reaches 0.9% of listings. There is no working production foundation to preserve — only a metered one that could not have scaled. Shipping V1 on the corpus means the first time Location DNA runs at scale, it runs on the architecture it will keep.

The one thing worth holding firmly: **Phase 5 stays post-launch.** Not because it is hard, but because the maps cut is the single irreversible step in this programme, and V1 should not surrender its rollback path on launch day. Phase 3 already achieves what the product direction actually asks for — Google out of the intelligence foundation. Phase 5 removes Google from *pixels*, and pixels can wait.

---

**End of roadmap.** Amendments proceed by appending to Appendix D (Errata) and Appendix E (Open Questions) — never by silent edit. Phase-level scope changes require an update to the Decision Register in the governing document.
