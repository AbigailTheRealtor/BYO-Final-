# Master Spatial Intelligence Architecture

> ## ⛔ SUPERSEDED — 2026-07-09
>
> **This document is superseded by [`SPATIAL-INTELLIGENCE-PLATFORM.md`](./SPATIAL-INTELLIGENCE-PLATFORM.md). Do not implement from it.**
>
> Retained for its **rationale, evidence base, and provider evaluation**, which remain sound. The following sections are **corrected** by the successor and must not be built as written:
>
> | Section | Defect | Corrected by |
> |---|---|---|
> | Everywhere "CONUS bbox" appears | Excludes Puerto Rico — **76 live Bridge listings** | Successor §4, E-3 |
> | §13.2 `places.geom geography(Point,4326)` | Parks/beaches/airports are areas | E-4 |
> | §13.2 partial index `WHERE category_key IS NOT NULL` | Dead index; predicate always true | E-6 |
> | §13.3 category-filtered KNN | Pathological without `btree_gist` | E-5 |
> | §13.3 `ST_Contains(...::geometry, ...)` | `ST_Contains` does not accept `geography` | E-7 |
> | §13.2 `location_snapshots` | Duplicates `property_location_pois` | E-9 |
> | §13.4 `CLUSTER` after swap | Takes `ACCESS EXCLUSIVE` on the live table | E-8 |
> | §7.3 six-term prominence prior | Unnecessary; authority-first ranking replaces it | E-12 |
> | §4.5 / §12.2 Foursquare Premium contingency | Withdrawn | E-13 |
> | §5 / §12.2 Geoapify | Dropped; no paid geographic services | E-14 |
> | §6 "FEMA stays a live API" | Cannot satisfy INV-5 ("not mapped" ≠ "safe") | E-11 |
> | §14.1 / §17 Phase 5 "point of no return" | The maps cut is reversible once Google **data** is gone | E-15 |
> | §17 phase numbering | Replaced by one scheme, 0–9 | Successor §14 |
> | §17 Phase 0 item 4 (stop persisting ratings) | Belongs in Phase 3, not Phase 0 | Successor §14 |
> | §14.2 Gate 1 "1,090 labelled rows" | Only **844** rows carry a rating | E-10 |

**Status:** ARCHITECTURE SPECIFICATION — **documentation only. No production code, tests, configuration, database, or Git state changed by this document.**
**Date:** 2026-07-09
**Owner:** Platform Architecture · Pending product-owner sign-off (Abigail)
**Supersedes / consolidates:** `docs/PHASE_8_PROVIDER_AGNOSTIC_LOCATION_INTELLIGENCE_RECOMMENDATION.md`, `docs/location-provider-capability-map-proposal.md`, `docs/canonical-field-mapping-spec.md` (§6 provider posture), `docs/LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` (§6 Data Source Plan only — §3/§4/§7/§8/§9 remain **in force**)
**Consumes / honors:** `docs/launch-audits/location-dna-architecture-review.md`, `docs/bid-your-offer-v1-master-roadmap.md` (Phase 8, Principles 2/21), `docs/beyond-mls-property-dna-roadmap.md` (CDM Principle; Location-Preference Principle), `docs/bid-your-offer-v2.1-architecture-review.md`, `docs/CENSUS_INTELLIGENCE_PHASE_5_3_GOVERNANCE_AND_ARCHITECTURE_PLAN.md`, `docs/investigations/Google-Places-Root-Cause-Analysis.md`, `docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md`
**Scope:** Every geographic, mapping, routing, Location DNA, Property DNA, Target Market Intelligence, Buyer/Tenant matching, and spatial-analysis feature across BidYourAgent and BidYourOffer.

> **Purpose.** Define the long-term Spatial Intelligence Platform (SIP) that replaces Google Maps Platform with an owned, open, production-grade geographic substrate. After this document, adding a POI category, a lifestyle score, a travel mode, or an entire data provider is a **configuration and data change**, never an intelligence-engine change — and the marginal cost of enriching one more listing is **zero**.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Guiding Principles](#2-guiding-principles)
3. [Overall System Architecture](#3-overall-system-architecture)
4. [Provider Evaluation](#4-provider-evaluation)
5. [Final Recommended Stack](#5-final-recommended-stack)
6. [Data Pipeline](#6-data-pipeline)
7. [Location DNA](#7-location-dna)
8. [Property DNA](#8-property-dna)
9. [Target Market Intelligence](#9-target-market-intelligence)
10. [Buyer & Tenant Matching](#10-buyer--tenant-matching)
11. [Spatial Scoring Framework](#11-spatial-scoring-framework)
12. [Data Sources](#12-data-sources)
13. [Database Design](#13-database-design)
14. [Migration Strategy](#14-migration-strategy)
15. [Risk Analysis](#15-risk-analysis)
16. [Future Expansion](#16-future-expansion)
17. [Implementation Roadmap](#17-implementation-roadmap)
18. [Launch Recommendation](#18-launch-recommendation)
- [Appendix A — Decision Register](#appendix-a--decision-register)
- [Appendix B — Supersession Map](#appendix-b--supersession-map)
- [Appendix C — Evidence Base](#appendix-c--evidence-base)
- [Appendix D — Open Questions](#appendix-d--open-questions)

---

## 1. Executive Summary

### 1.1 Vision

A **Spatial Intelligence Platform** that the company owns outright: a spatial database, a places corpus, a routing engine, and a library of authoritative public datasets, from which every location-derived fact on the platform is computed locally, automatically, for every listing, at zero marginal cost.

Location DNA, Property DNA, Target Market Intelligence, and Buyer/Tenant matching stop being *features that call an API* and become *queries against a corpus we control*.

### 1.2 The problem is the data model, not the provider

The central finding of the audit that produced this document:

> Today the platform **rents** geographic facts per-listing from a metered API and denormalizes them into a listing × POI cross-product. Every cost, scalability, compliance, and product limitation follows mechanically from that one decision.

Swapping Google for Geoapify, Overpass, or Foursquare preserves all of it. The architecture below removes the class of problem.

**Measured evidence (2026-07-09, production database):**

| Fact | Value |
|---|---|
| Listings in scope (native + Bridge) | **~1,226** |
| Listings with Location DNA | **11 (~0.9%)** |
| Bridge/MLS properties | 667 — **100% already carry latitude + longitude** |
| POI rows per enriched listing | **84** → 8.4M @ 100k listings, **84M @ 1M** |
| Google Nearby Search calls per listing | **16** (19 categories, 3 shared via `CATEGORY_GROUPS`) |
| Cost per cold listing | **$0.512** → **$512,000 to enrich 1M listings once** |
| POI categories fetched but invisible to every engine and user | **5 of 19** (`school`, `hospital`, `gym`, `fitness_center`, `shopping_center`) = **~25% of POI spend**, ~$12,800 wasted @ 100k listings |
| 2026-07-05 incident | **38,236 Nearby Search requests / ~$1,223 in 6 days**, generated by the **test suite** |
| Google Places status | **Disabled by owner since 2026-07-06** |

The economics are not a matter of taste:

| Listings | Google POI (one cold pass) | Owned corpus (fixed) |
|---|---|---|
| 1,226 (today) | $628 | ~$170–260/mo, **flat** |
| 10,000 | $5,120 | same |
| 100,000 | $51,200 | same |
| 1,000,000 | **$512,000** | same |

`ldna:refresh-all` deletes the POI cache before refetching, so **every ranking re-tune is a full-price re-fetch of the entire catalog.**

### 1.3 Why we are replacing Google Maps Platform

1. **Cost scales with listings.** The stated product goal — automatic intelligence on every platform listing *and* every MLS/Bridge listing — is unreachable at $0.512/listing. This is why `AgentLocationDnaController::generate()` exists: a manual "Generate Location DNA" button that exists **only because the API is metered**. Principle 21 ("Compute Whenever Possible") cannot be honored while compute costs money per unit.
2. **Licensing forecloses the architecture we want.** Google's Maps Service Terms cap caching of Place content at **30 days** (only `place_id` is storable indefinitely) and forbid using Google Maps Content "in conjunction with a non-Google map." The platform **today persists `rating` and `user_ratings_total` indefinitely** in `property_location_pois` and renders them in agent/admin views. Any owned-corpus or open-basemap architecture is legally incompatible with continuing to use Google Places data.
3. **Google supplies nothing authoritative.** It has no flood zones, no school-district boundaries, no protected-area polygons, no transit ridership, no clinical hospital quality. For the categories where a wrong answer is most costly, **free federal data is strictly better** (§4.4).
4. **The one thing Google uniquely supplies — business review data — is data we should not be ranking on.** See §1.5 and §7.
5. **Blast radius.** A metered call inside the enrichment path made a test-suite bug cost $1,223 in six days. Under the recommended architecture that incident is not cheaper; it is **structurally impossible**, because no listing-enrichment path makes an outbound metered call.

### 1.4 Goals

| # | Goal | Measure of success |
|---|---|---|
| G1 | Automatic Location DNA for **every** seller, landlord, and MLS/Bridge listing | 100% coverage; the "Generate" button is deleted |
| G2 | Automatic Target Market Intelligence for every listing | 100% coverage, $0 marginal |
| G3 | Automatic Buyer/Tenant compatibility scoring | `dna_scores` populated both sides; Matching V2 enabled |
| G4 | Predictable operating cost | Fixed monthly; **$0 marginal per listing**; no metered call in any enrichment path |
| G5 | Full buyer/tenant location vision | radius, polygon, drive-time, commute-time, 4 travel modes, multiple destinations, any future POI category |
| G6 | No further provider migration | Corpus is owned; providers are swappable adapters behind a registry |
| G7 | Fair Housing defensibility | Every consumer-facing score derives from physical/civic facts, never from people |

### 1.5 Design philosophy

**Describe the place, not the people. Own the facts, don't rent them. Compute, don't call.**

Three convictions follow:

- **Prominence, not popularity.** The ranking engine's dependence on Google star ratings is an accident of the provider, not a product requirement. Measured: removing ratings entirely changes the top-ranked POI in **46.6%** of listing-category pairs and surfaces an embarrassing result in **19%** of cases. But supplying only a *prominence* signal (review count, no stars) recovers **73.8%** agreement and cuts embarrassment to **1%**. Stars alone recover only 51.5%. **Publix beats "B S Food & Gas" because it has 2,385 reviews, not because it has 4.6 stars.** Prominence is reconstructible from open data (brand, source count, corpus confidence, polygon area, authoritative registry membership). Stars are not — and are not needed.
- **Authority beats sentiment.** For hospitals, CMS publishes clinical star ratings free. For parks, USGS PAD-US publishes polygons with area and managing agency. For boat ramps, USGS publishes a CC0 national inventory. Each of these is a *better* answer than a crowd rating, and each is free and permanently storable.
- **Facts are cheap; opinions are expensive and legally hazardous.** Every prohibited output in `LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` §8 — "best neighborhood," "safe area," "ideal for families," "best for retirees" — is an *opinion about people*. Every allowed output is a *measurable distance*. The architecture is arranged so that the cheap, safe thing is also the easy thing.

### 1.6 Long-term benefits

- **Cost becomes an infrastructure line item**, decoupled from listing count, refresh frequency, POI category count, and traffic.
- **Ranking iteration becomes free.** The `scoring_version` recompute-from-cache mechanism (already designed in `location-dna-architecture-review.md` §1–2) extends to `fetch_version`: with an owned corpus, *even a query/category redesign* requires no API call. Adding "urgent care" or "airports" becomes a config row and a re-query, not a $51k catalog refetch.
- **New capabilities become possible, not merely cheaper.** Drive-time search, isochrone polygons, commute scoring, and multi-destination matching are all *impossible today at any price* under a metered per-request model (no hosted routing provider permits storing computed results — §4.3). Self-hosting is the only path to them.
- **Compliance posture improves.** Owned public-domain and permissively-licensed data can be cached forever, redistributed, and audited. Three live violations (§15.4) are closed as a side-effect.
- **Vendor independence.** The corpus is ours. Providers become adapters.

---

## 2. Guiding Principles

These extend, and do not replace, the platform-wide principles in `bid-your-offer-v1-master-roadmap.md` and `beyond-mls-property-dna-roadmap.md`. Where a principle is inherited, its source is named.

| # | Principle | Statement |
|---|---|---|
| **SIP-P1** | **Own the corpus** | Any fact consulted more than once, for more than one listing, is stored locally and refreshed on a schedule. We never pay per-lookup for a fact that does not change per-lookup. |
| **SIP-P2** | **Compute whenever possible** *(inherits Principle 21)* | If a value can be derived from geometry, an owned dataset, or an existing user selection, it is computed — never asked for, never purchased. |
| **SIP-P3** | **Zero marginal cost in the enrichment path** | No listing-enrichment or DNA-generation code path may make an outbound metered API call. This is an architectural invariant, enforced by test and by a network guard, not a convention. |
| **SIP-P4** | **Provider abstraction, corpus permanence** | Providers are adapters behind `LocationProviderRegistry`. The **corpus** is the durable asset; the **provider** is an implementation detail of how it was populated. |
| **SIP-P5** | **Source-neutral canonical model** *(inherits CDM Principle)* | No intelligence engine reads a provider-specific field. Everything normalizes into the canonical envelope (`canonical-field-mapping-spec.md`) before any engine touches it. |
| **SIP-P6** | **One taxonomy, two entry modes** *(adopts `location-dna-architecture-review.md` §3)* | A single canonical category taxonomy, exclusion set, and ranking core, entered via **point mode** (a listing's coordinate) or **area mode** (a buyer/tenant geometry). Never two taxonomies. |
| **SIP-P7** | **A searcher's *where* is DNA, not a filter** *(inherits `beyond-mls-property-dna-roadmap.md`)* | Every buyer/tenant geometry — polygon, radius, isochrone, important place — is a first-class, durable, enrichable **Location Preference DNA** object on the *same* vocabulary as property-side Location DNA. |
| **SIP-P8** | **Authority over sentiment** | Where an authoritative public registry exists (CMS, PAD-US, USGS, FEMA, NCES, FAA, NTD), it is the base source. Crowd sentiment is never the base source, and after §7 is never a source at all. |
| **SIP-P9** | **Prominence, not popularity** | POI ranking uses a *prominence prior* (authority membership, brand, corpus confidence, source count, geometry) — never consumer star ratings. |
| **SIP-P10** | **Cache-first, version-stamped** | Every derived artifact carries `corpus_version`, `scoring_version`, and (where routed) `routing_version`. A version mismatch triggers the cheapest sufficient recompute, never a blanket refetch. |
| **SIP-P11** | **Describe the place, not the people** *(inherits Phase A §9)* | Consumer-facing outputs describe physical and civic facts. No demographic input. No audience label naming a protected class. **When in doubt, the answer is no.** |
| **SIP-P12** | **Graceful unknown** | `null` means *unknown*, never *zero* and never *absent-therefore-good*. "Not mapped by FEMA" is never rendered as "no flood risk." An unenriched listing is not penalized. |
| **SIP-P13** | **Async by construction** | Enrichment is queued, idempotent, and never blocks a web request. *(This is currently violated in production — see §15.1 R1.)* |
| **SIP-P14** | **Reversible until the boat is burned** | Every migration phase is config-reversible except the final basemap cut, which is sequenced last and gated explicitly (§14). |

---

## 3. Overall System Architecture

### 3.1 Layer model

```
┌────────────────────────────────────────────────────────────────────────────┐
│ L5  CONSUMPTION                                                            │
│     Stellar consumer pages · Agent panel · Admin DNA · Ask AI · Marketing  │
│     ── all read the Location DNA Read Model. None reads a provider. ──     │
└──────────────────────────────────┬─────────────────────────────────────────┘
                                   │  LocationDnaPresenter (single read model)
┌──────────────────────────────────┴─────────────────────────────────────────┐
│ L4  INTELLIGENCE ENGINES        (pure, deterministic, no I/O, no AI)       │
│  ┌───────────────┐ ┌──────────────┐ ┌───────────────┐ ┌─────────────────┐  │
│  │ Location DNA  │ │ Property DNA │ │ Target Market │ │ Matching Engine │  │
│  │ Core          │ │ Engine       │ │ Engine        │ │ (V2)            │  │
│  │ ranking·      │ │ archetypes·  │ │ audiences·    │ │ candidate·      │  │
│  │ summary·      │ │ coverage     │ │ positioning   │ │ narrow·gate·    │  │
│  │ lifestyle·    │ │ scores       │ │               │ │ rank·persist    │  │
│  │ narrative     │ └──────────────┘ └───────────────┘ └─────────────────┘  │
│  └───────────────┘         ┌────────────────────┐                          │
│                            │ Spatial Scoring    │  12+ proprietary scores  │
│                            │ Framework          │  → dna_scores (2-sided)  │
│                            └────────────────────┘                          │
└──────────────────────────────────┬─────────────────────────────────────────┘
                                   │  canonical envelope (value+provenance+confidence)
┌──────────────────────────────────┴─────────────────────────────────────────┐
│ L3  SPATIAL SERVICES                                                       │
│  ┌─────────────┐ ┌────────────┐ ┌────────────┐ ┌───────────┐ ┌───────────┐ │
│  │ Proximity   │ │ Containment│ │ Commute    │ │ Isochrone │ │ Boundary  │ │
│  │ (KNN)       │ │ (PIP)      │ │ Engine     │ │ Engine    │ │ Resolver  │ │
│  └──────┬──────┘ └─────┬──────┘ └─────┬──────┘ └─────┬─────┘ └─────┬─────┘ │
└─────────┼──────────────┼──────────────┼──────────────┼─────────────┼───────┘
          │              │              │              │             │
┌─────────┴──────────────┴──────────────┴──────────────┴─────────────┴───────┐
│ L2  SPATIAL CORE  —  PostgreSQL 16 + PostGIS 3.5                           │
│     places · place_categories · boundaries · listing_locations ·           │
│     location_preference_geometries · isochrone_cache · commute_cache       │
│     GiST indexes · KNN (<->) · ST_DWithin · ST_Contains · ST_Subdivide     │
└─────────┬──────────────────────────────────────────────────┬───────────────┘
          │ ingest (batch, versioned, staging-swap)          │ query (local, µs–ms)
┌─────────┴───────────────────────────────┐  ┌───────────────┴───────────────┐
│ L1  DATA CORPUS (owned, refreshed)      │  │ L1b  ROUTING ENGINE (owned)   │
│  Overture Places  · FEMA NFHL           │  │  Valhalla (self-hosted)       │
│  Census TIGER     · NCES CCD/EDGE       │  │  matrix · isochrone · P2P     │
│  USGS PAD-US      · USGS Boat Ramps     │  │  drive · walk · bike          │
│  CMS Star Ratings · EPA Walkability     │  │  ┌─────────────────────────┐  │
│  FAA NASR         · GTFS + NTD          │  │  │ OTP2 (per-metro, later) │  │
│  NOAA · USGS 3DEP · BTS Noise · VIIRS   │  │  │ transit routing         │  │
└─────────────────────────────────────────┘  │  └─────────────────────────┘  │
                                             └───────────────────────────────┘
┌────────────────────────────────────────────────────────────────────────────┐
│ L0  RESOLUTION & PRESENTATION  (the only layer that may call out)          │
│  Geocoding: MLS coords → NAD → Census → Geoapify(paid fallback)            │
│  Autocomplete: Photon (self-host) or Geoapify                              │
│  Basemap: MapLibre GL + Protomaps PMTiles (self-host) | MapTiler           │
└────────────────────────────────────────────────────────────────────────────┘

           AI LAYER (orthogonal, read-only consumer of L4 outputs)
           OpenAI marketing narrative · Ask AI — receive FACTS only,
           never raw provider payloads, never demographics.
```

### 3.2 Component responsibilities

| Component | Responsibility | Must never |
|---|---|---|
| **Spatial Core (L2)** | Authoritative store of all geometry. Answers proximity, containment, and nearest-neighbour. | Call an external service |
| **Data Corpus (L1)** | Owned, versioned copies of public datasets. Refreshed by batch jobs. | Be queried live from a vendor at request time |
| **Routing Engine (L1b)** | Travel time, distance matrices, isochrones, for drive/walk/bike. | Be a hosted API (see §4.3 — they forbid storing results) |
| **Spatial Services (L3)** | Thin, pure adapters translating engine questions into PostGIS/Valhalla queries. | Contain product logic |
| **Location DNA Core (L4)** | Single taxonomy, exclusion rules, ranking, summary, lifestyle, narrative. Point mode + area mode. | Know which provider populated the corpus |
| **Property DNA Engine (L4)** | Deterministic archetypes + coverage scores from listing fields. | Call OpenAI (it does not today, and must not) |
| **Target Market Engine (L4)** | Audience/positioning derivation from housing-stock + location facts. | Consume demographics (Phase A §4) |
| **Matching Engine (L4)** | Two-sided compatibility over `dna_scores` + geometry. | Bypass `SeniorCommunityComplianceGate` |
| **Read Model** | `LocationDnaPresenter` — the single public API for all consumers. | Be bypassed by a consumer reaching into tables |
| **Resolution/Presentation (L0)** | The *only* layer permitted an outbound call, and never inside enrichment. | Be invoked from `ComputeLocationDna` |
| **AI Layer** | Narrative generation over facts. | Receive raw provider payloads, ratings, or demographics |

### 3.3 The single most important interaction

> **`ComputeLocationDna` makes zero outbound network calls.**

Today it makes 16 (Google Nearby) plus up to 1 (Google Geocoding). Under this architecture it performs: one geocode *lookup* (usually a no-op — MLS coordinates are already present in 100% of Bridge rows), one PostGIS KNN query per category against the local corpus, one boundary point-in-polygon set, and one optional Valhalla call to a **local** process. This is the invariant (SIP-P3) from which every cost and reliability property in this document derives.

---

## 4. Provider Evaluation

### 4.1 Evaluation criteria

Providers are judged on: (a) may we **store** results permanently, (b) does cost scale with **listings** or with **infrastructure**, (c) **licensing** for a commercial SaaS, (d) **authority** of the data, (e) **operational** burden.

Criterion (a) is decisive and eliminates most candidates before price is considered.

### 4.2 Places, geocoding, autocomplete, maps

| Provider | Verdict | Reasoning |
|---|---|---|
| **Overture Maps — Places** | ✅ **Recommended — base POI corpus** | 75M+ places, monthly releases, per-record `confidence`, `brand`, `sources[]`, stable GERS IDs. **CDLA-Permissive-2.0** for the bulk (Meta ~59M, Microsoft ~7.4M); Foursquare slice Apache-2.0; AllThePlaces CC0. **ODbL share-alike does not govern the Places theme.** Storable forever, redistributable, zero per-query cost. Consumed as GeoParquet from S3 via DuckDB. Bulk-only — no hosted API, which is precisely what we want. |
| **Foursquare OS Places** | ◑ **Optional supplement** | Apache-2.0, ~100M+ POIs, monthly. Strongest exactly where OSM is weakest (restaurants/retail). Heavily overlapping with Overture (which ingests it). **`popularity` and `rating` are stripped from the open set** — Premium-only. Adopt only if Overture coverage measurably underperforms. |
| **OpenStreetMap (data)** | ✅ **Recommended — supplement** | ODbL. Authoritative for marinas, dog parks, golf courses, beaches, trails — categories with no federal registry. Consumed as a bulk extract, not via a live API. Attribution required. Displaying counts/scores is a **Produced Work** (ODbL §4.3): attribution only, no obligation to open anything; internal derivative databases trigger no share-alike (§4.5(c)). |
| **Overpass API** | ❌ **Rejected as a runtime dependency** | Public-instance policy **explicitly disallows** "setting up an app for more than just OSM mappers and relying on the public instances as backend." ~10k req/day, <1GB/day. Self-hosting is possible (a US extract, not the planet) but redundant once we hold a bulk extract. **This supersedes the `poi.default` base binding in `config/location_providers.php`.** |
| **PostGIS** | ✅ **Recommended — spatial substrate** | Already available on the production Postgres 16 instance (`postgis 3.5.3`, not installed). Indexed KNN (`<->`) is ~7.8 ms on 2.24M rows vs ~14 s naive. Directly resolves the `bid-your-offer-v2.1-architecture-review.md` "canonical read model" scalability finding. |
| **Nominatim** | ❌ **Rejected for production** | Public instance: max 1 req/s, single-thread, **forbids autocomplete, systematic/bulk queries, and POI-area downloads**; "any app whose primary function is related to geocoding must run their own service." *Note: `resources/views/partials/location-dna/map-input.blade.php:860` calls the public instance directly from end-user browsers today — a live policy violation (§15.4).* Self-hosting is viable but Photon/Geoapify serve our needs at lower operational cost. |
| **Photon** | ✅ **Recommended — autocomplete (self-hosted)** | Purpose-built type-ahead over OSM. Public endpoint is throttled and unsuitable; **self-host**. Forward + reverse. Free-text only (no structured query on the public API). |
| **Pelias / Geocode Earth** | ◑ **Considered, not recommended** | Best-in-class US address coverage (OpenAddresses + TIGER interpolation + OSM + WOF) and hosted plans explicitly place **"no restrictions"** on storing results. But the self-hosted stack is a 6-service microservice deployment — the heaviest operational burden of any candidate, for a problem MLS coordinates already solve. Revisit if geocoding volume ever becomes primary. |
| **Geoapify** | ✅ **Recommended — paid geocoding fallback (flat rate)** | Free 3,000 credits/day; $59/mo for 10k/day. **Uniquely permits permanent storage of geocodes with no time limit** (contrast: Google 30 days; LocationIQ free tier 48h; Stadia Starter forbids server-side caching). Watch the *per-day*, not per-month, cap for bursty backfills. *Flagged: the storage permission appears in marketing copy, not the binding T&C — obtain written confirmation (Appendix D, Q3).* |
| **MapLibre GL** | ✅ **Recommended — map rendering** | BSD-3. The `google.maps.*` replacement. No per-load fee. |
| **Protomaps (PMTiles)** | ✅ **Recommended — tile hosting (self-host)** | Single-file tile archive on object storage; **no per-request fee** — storage + CDN egress only (Cloudflare R2 = zero egress). Planet basemap ~120 GB (z0–15); a US extract is far smaller. ODbL Produced Work, attribution required. |
| **MapTiler Cloud** | ◑ **Acceptable managed alternative** | Flex $30/mo. Choose only if we decline to operate tile storage. |
| **`tile.openstreetmap.org`** | ❌ **Rejected** | OSMF tile policy: commercial services "should be especially aware that access may be withdrawn at any point"; bulk/prefetch prohibited. Not a production dependency. |
| **Google Maps Platform** | ❌ **Exit** | 30-day content caching cap; `place_id` the sole indefinitely-storable field; **"No Use With Non-Google Maps"** makes any hybrid illegal. Nearby Search is Pro/Enterprise-only at **$32/1k**. Ratings live in the **Place Details *Enterprise* SKU ($20/1k)** — not Essentials, not Pro. |

### 4.3 Routing — and the clause that decides it

For a corpus-wide precompute workload, **the licensing question settles the engine choice before price is discussed.**

| Provider | May we store computed routes/isochrones? |
|---|---|
| Google Routes | ❌ ≤30 days (ToS §3.2.3) |
| HERE | ❌ 30-day cap + explicit "no location-asset repository" |
| GraphHopper (commercial) | ❌ temporary client-side only |
| Stadia Maps | ❌ no server-side caching below Standard tier |
| TravelTime | ❌ only if licensed in the Order; then 60-day refresh + delete |
| Mapbox | ◑ geocodes yes (paid); routing caching unconfirmed for 2026 |
| Geoapify | ◑ practically yes; **legally unconfirmed** |
| **Self-hosted** | ✅ **Unrestricted — the data is ours** |

Therefore: **self-host.** Among open engines:

| Engine | P2P | Matrix | Isochrone | Walk/Bike | Transit | Traffic | Verdict |
|---|:--:|:--:|:--:|:--:|:--:|:--:|---|
| **Valhalla** | ✅ | ✅ | ✅ | ✅ | ◑ exp. | ✅ | ✅ **Recommended.** The only open engine with all five core capabilities in one process. mmap tiles keep *serve* RAM well below *build* RAM. Traffic tiles update without a rebuild. |
| OSRM (MLD) | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | Fastest matrix; **no native isochrone**. Fallback only. |
| GraphHopper OSS | ✅ | ❌ | ✅ | ✅ | ◑ | ❌ | **Matrix is paywalled out of the OSS core** — disqualifying. |
| **openrouteservice** | ✅ | ✅ | ✅ | ✅ | ❌ | ◑ | Viable (GraphHopper core + free self-hosted matrix/isochrone). Higher heap (~100 GB planet). **`LOCATION_DNA_PHASE_A...md` §6 recommended ORS; this document supersedes that in favour of Valhalla** for native multimodal + traffic + lower serve-RAM. |
| OTP2 | ✅ | ❌ | ◑ sandbox | ◑ | ✅ **core** | ❌ | ✅ **Recommended for transit only, per-metro.** |

**On nationwide transit:** there is **no single national US GTFS feed** — hundreds of agency feeds, feed-ID collisions, divergent calendars. The largest US single OTP instance is NY State DOT. Valhalla's GTFS support is experimental and has known "unconnected regions" failures. **Decision: deploy OTP2 per-metro, on demand. Do not attempt a national transit graph.**

### 4.4 Authoritative datasets — where open data *beats* Google

| Category | Source | License | Why it wins |
|---|---|---|---|
| Hospital quality | **CMS Hospital Overall Star Rating** | Public domain | Clinical outcomes, not crowd sentiment. **A free, superior replacement for Google hospital ratings.** |
| Parks / open space | **USGS PAD-US 4.1** | Public domain | Real polygons + area + managing agency. Distinguishes Seminole City Park from an unnamed islet — the exact failure ratings were papering over. |
| Boat ramps | **USGS Boat Ramp Locations (2023)** | **CC0** | A national inventory. Replaces a `keyword => 'boat ramp'` text hack. |
| Flood | **FEMA NFHL** | Public domain | *Already integrated.* Google has none. |
| Boundaries | **Census TIGER** | Public domain | *Already integrated.* Google has none. |
| Schools | **NCES CCD / EDGE** | Public domain | Directory + geocodes + district boundaries. |
| Walkability | **EPA National Walkability Index** | Public domain | Block-group, national, published formula, commercially usable. |
| Transit quality | **GTFS + NTD ridership** | Public domain | Ridership is a better quality signal than reviews. |
| Airports | **FAA NASR** | Public domain | **The listing pipeline has zero airport support today** despite Phase A §5 approving it. |
| Light pollution | **VIIRS (EOG)** | Public domain | ⚠️ *Not* the Falchi World Atlas — that is **CC BY-NC**, unusable commercially. |

### 4.5 Rejected: paid rating overlays

A prior draft of this analysis recommended **Foursquare Places Premium (~$18.75/1k)** as a minimal paid ratings overlay. **That recommendation is withdrawn.** Three findings retire it:

1. **No consumer ever sees a rating.** Verified across all Stellar views: `matchmaker-nearby.blade.php` renders `{{ $label }} — {{ $poiName }} … {{ $miles }} mi`. Ratings are an internal ranking input only.
2. **Prominence suffices** (§1.5): 1% embarrassment vs 19%, and prominence is reconstructible from Overture + authority registries.
3. **The rating-dependent surface is three items, not nineteen** (§7.4), one of which (`top_rated_dining`) we are deleting on product grounds.

**Contingency (not baseline):** if the prominence prior fails validation (§14.2 Gate 1), acquire Foursquare Places Premium — keyed by **POI, not by listing**, restricted to commercial categories. *Not Google* (the non-Google-map clause forecloses it). *Not Yelp* (24-hour cache cap and an explicit anti-blending clause that a composite `ranking_score` would violate).

---

## 5. Final Recommended Stack

| Function | Production choice | License | Cost model |
|---|---|---|---|
| **Maps** | MapLibre GL | BSD-3 | $0 |
| **Tile hosting** | Protomaps PMTiles on object storage + CDN | ODbL (Produced Work) | Storage + egress (~$20/mo) |
| **Routing** | **Valhalla, self-hosted** (drive/walk/bike, matrix, isochrone) | MIT | Fixed (~$150/mo box) |
| **Transit routing** | OTP2, per-metro, deferred | LGPL | Fixed, per-metro |
| **Geocoding** | MLS coords → NAD → Census Geocoder → **Geoapify** | mixed / public domain | $0–59/mo flat |
| **Address autocomplete** | **Photon** self-hosted (fallback: Geoapify) | Apache-2.0 / ODbL | $0 |
| **Places** | **Overture Places** (+ OSM supplement, + FSQ optional) | CDLA-Permissive / ODbL / Apache | $0 marginal |
| **Spatial storage** | **PostgreSQL 16 + PostGIS 3.5** | PostgreSQL / GPL | Existing |
| **Caching** | Redis (required — see §15.1 R1) | BSD | Fixed |
| **Boundaries** | Census TIGER; NCES EDGE; PAD-US | Public domain | $0 |
| **Federal datasets** | FEMA NFHL, FAA NASR, NTD, CMS, HRSA | Public domain | $0 |
| **Environmental** | NOAA, USGS 3DEP, EPA (AirNow, Walkability), BTS Noise, VIIRS | Public domain | $0 |
| **Demographics** | **NONE in scoring.** ACS display-only, guardrailed, deferred. | Public domain | $0 |

**Total recurring: ~$170–260/month, flat. Marginal cost per listing: $0.**

> **Infrastructure prerequisite.** This stack cannot run on the current deployment target. See §15.1 R1 — the platform presently deploys to a Replit VM via `php artisan serve`, with `QUEUE_CONNECTION=sync`, `CACHE_DRIVER=file`, **no queue worker, and no scheduler**. A conventional application host with a background worker, Redis, managed Postgres+PostGIS, and one routing VM is a **hard dependency of Phase 1**, and is independently required to make *any* asynchronous DNA generation correct.

---

## 6. Data Pipeline

### 6.1 End-to-end flow

```
  ADDRESS (listing form) ──┐
  MLS record (Bridge) ─────┤
                           ▼
        ┌─────────────────────────────────────┐
        │ 1. RESOLUTION                       │   Cost: ~$0
        │    MLS coords (100% of Bridge)      │   ← short-circuits; geocode_source='saved_meta'
        │    → NAD point → Census → Geoapify  │
        └───────────────┬─────────────────────┘
                        ▼  listing_locations.geom :: geography(Point,4326)
        ┌─────────────────────────────────────┐
        │ 2. SPATIAL ENRICHMENT (local only)  │   Cost: $0 · Latency: ms
        │    a. Proximity  — KNN per category │   PostGIS  <->  LATERAL
        │    b. Containment— flood, district, │   ST_Contains / ST_Intersects
        │       county, ZIP, place            │
        │    c. Authority  — CMS, PAD-US, NTD │   join on corpus
        │    d. Routing    — drive/walk/bike  │   Valhalla (localhost)
        └───────────────┬─────────────────────┘
                        ▼  ranked POIs + boundaries + travel times
        ┌─────────────────────────────────────┐
        │ 3. LOCATION DNA CORE (pure)         │
        │    exclusion → prominence rank →    │
        │    summary_json → lifestyle_json →  │
        │    narrative                        │
        └───────────────┬─────────────────────┘
                        ▼
        ┌─────────────────────────────────────┐   ┌──────────────────────────┐
        │ 4. PROPERTY DNA (pure, $0)          │◄──┤ listing fields / EAV meta│
        │    archetypes · coverage scores     │   └──────────────────────────┘
        └───────────────┬─────────────────────┘
                        ▼
        ┌─────────────────────────────────────┐
        │ 5. TARGET MARKET INTELLIGENCE       │   housing-stock + location facts
        │    positioning · audiences          │   ✗ NO demographics (Phase A §4)
        └───────────────┬─────────────────────┘
                        ▼
        ┌─────────────────────────────────────┐
        │ 6. SPATIAL SCORING FRAMEWORK        │  → dna_scores (side=property)
        │    12+ proprietary scores           │
        └───────────────┬─────────────────────┘
                        │
  Buyer/Tenant criteria ┤→ Location Preference DNA → dna_scores (side=demand)
                        ▼
        ┌─────────────────────────────────────┐
        │ 7. MATCHING (V2)                    │  eligibility → HOPA gate →
        │    two-sided compatibility          │  narrowers → rank → persist
        └───────────────┬─────────────────────┘
                        ▼
        ┌─────────────────────────────────────┐
        │ 8. AI LAYER (facts in, prose out)   │  Ask AI · marketing narrative
        │    receives FACTS ONLY              │  ✗ no raw payloads, no demographics
        └─────────────────────────────────────┘
```

### 6.2 Trigger model

| Event | Action |
|---|---|
| Listing created / address or coords changed | Enqueue `ComputeLocationDna` (dirty-check on address+coords) |
| Bridge import: new record, or address/coord change | Enqueue (already gated correctly by `BridgePropertyNormalizer::upsert()`) |
| `scoring_version` mismatch | **Recompute from local data — no fetch** |
| `corpus_version` mismatch (monthly Overture refresh) | Re-query corpus — **still no outbound call** |
| `routing_version` mismatch | Re-run Valhalla locally |
| Buyer/tenant geometry saved | Enqueue `ComputeLocationPreferenceDna` |

The `fetch_version` / `scoring_version` split from `location-dna-architecture-review.md` §1 is **preserved and generalized**. Its original rationale — *"Conflating them into one stamp is the trap: it would force a full 16-call refetch for a pure weight tweak"* — dissolves once the corpus is owned: **both** paths become free CPU passes. `fetch_version` is renamed `corpus_version` to reflect that it now tracks *our snapshot*, not a vendor's API.

---

## 7. Location DNA — Redesign

### 7.1 What Location DNA is (unchanged)

> *"Location DNA describes the property's location features — not who should live there."* — `LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` §9

Phase A §3 (Allowed Inputs), §4 (Prohibited Inputs), §7 (Allowed Outputs), §8 (Prohibited Outputs), and §9 (Fair Housing Safeguards) **remain in force, unamended.** Only §6 (Data Source Plan) is superseded.

### 7.2 The canonical category taxonomy

One taxonomy, config-driven, serving both point mode and area mode (SIP-P6). This closes the current defect in which the listing pipeline defines **19** categories and the buyer/tenant adapter defines **7** (`schools, parks, shopping, hospitals, gyms, airports, downtown`) — with **airports present buyer-side and entirely absent listing-side**, despite Phase A §5 approving them.

Categories carry a **base source** (authority-first), a **prominence strategy**, and a **thematic block**. Adding a category is a config row.

| # | Category | Base source | Prominence signal | Thematic block | Status |
|---|---|---|---|---|---|
| 1 | `grocery_store` | Overture | brand ∪ confidence ∪ source-count | daily_convenience | existing |
| 2 | `pharmacy` | Overture | brand ∪ confidence | daily_convenience | existing |
| 3 | `restaurant` | Overture (+FSQ) | confidence ∪ source-count | daily_convenience | existing |
| 4 | `coffee_shop` | Overture | brand ∪ confidence | daily_convenience | existing |
| 5 | `shopping_center` | Overture | brand ∪ area | daily_convenience | **now wired** (was invisible) |
| 6 | `gym` | Overture | brand ∪ confidence | daily_convenience | **now wired** (was invisible) |
| 7 | `school` | **NCES CCD/EDGE** | authority membership | family_infrastructure | **now wired** (was invisible) |
| 8 | `hospital` | **CMS + HRSA** | **CMS star rating (clinical)** | healthcare_access | **now wired** (was invisible) |
| 9 | `urgent_care` | HRSA / Overture | authority membership | healthcare_access | **new** (Phase A §5 approved, never built) |
| 10 | `park` | **USGS PAD-US** | **polygon area** ∪ managing agency | outdoor_recreation | existing, re-sourced |
| 11 | `dog_park` | OSM | tag richness | outdoor_recreation | existing |
| 12 | `waterfront_park` | PAD-US ∩ NOAA shoreline | area ∪ shoreline adjacency | outdoor_recreation | existing, re-sourced |
| 13 | `trail` | USGS/USFS trails | length | outdoor_recreation | **new** |
| 14 | `golf_course` | OSM `leisure=golf_course` | area ∪ tag richness | outdoor_recreation | existing, re-sourced |
| 15 | `beach` | **NOAA CUSP** + OSM `natural=beach` | shoreline length ∪ PAD-US | coastal | existing, re-sourced |
| 16 | `beach_access` | State coastal + OSM | authority membership | coastal | existing |
| 17 | `marina` | OSM (no federal registry) | tag richness ∪ berth count | coastal | existing |
| 18 | `boat_ramp` | **USGS Boat Ramps (CC0)** | **authority membership** | coastal | existing, re-sourced |
| 19 | `transit_station` | **GTFS** | **NTD ridership** | transportation | existing, re-sourced |
| 20 | `gas_station` | Overture | brand | transportation | existing |
| 21 | `airport` | **FAA NASR** | **class / enplanements** | transportation | **new** (approved Phase A, never built) |
| 22 | `highway_access` | TIGER / OSM `motorway_junction` | — | transportation | **new** (approved Phase A, never built) |
| 23 | `downtown` | Census Places + OSM | population ∪ area | transportation | **new** (approved Phase A, never built) |

**Removed:** `top_rated_dining` (§7.4). **Merged:** `fitness_center` into `gym` — they resolve to the same Google type and are differentiated only by a keyword hack; the distinction is a provider artifact, not a product concept.

**New thematic blocks:** `family_infrastructure`, `healthcare_access`. This closes the `LOCATION_DNA_AUDIT.md` §9 finding that five categories were "fetched from Google Places (at API cost) but currently invisible to the end user" — **~25% of all POI spend.**

### 7.3 The prominence prior (replaces business-review ranking)

> **⛔ RETIRED — do not implement.** The six-term prominence prior described in this section is **superseded by authority-first ranking** (governing SSOT `SPATIAL-INTELLIGENCE-PLATFORM.md` §9.2/§9.3; **E-12**; amended **SIA-D4**). It is retained here for rationale only. Its weights `w_a…w_f` were never assigned numeric values; reviving it would require a formal architecture decision that defines them. The validation gate below (Gate 1) now scores **authority-first ranking** against **844 rated rows across 13 listings** (**E-10**), not "1,090."

`LocationDnaRankingEngine` currently computes `consumer_relevance_score` and `review_confidence_score` from Google `rating` and `user_ratings_total`. These are removed and replaced by a **prominence prior**, `P ∈ [0,1]`, composed from openly-licensed signals:

```
P(poi) = w_a · authority_membership      // in CMS / PAD-US / NCES / USGS / FAA / GTFS registry
       + w_b · brand_presence            // Overture `brand` non-null, or brand:wikidata
       + w_c · corpus_confidence         // Overture `confidence` ∈ [0,1]
       + w_d · source_agreement          // normalized len(sources[])
       + w_e · geometry_significance     // polygon area (parks/beaches), length (trails/shoreline)
       + w_f · notability                // Wikidata QID / Wikipedia sitelink present
```

`ranking_score` retains its existing shape — a weighted sum of `category_match_score`, `distance_score`, and now `prominence_score` — so `LocationDnaRankingProfileService` per-category weights survive, re-tuned. The **five-tier distance ladder** (`<0.5mi=100, <1=85, <2=70, <5=50, <10=30, else 10`) is untouched.

**Category weighting is inverted from Google's assumptions.** Where an authoritative registry exists, `w_a` dominates and the other terms are near-zero: a hospital's rank is decided by CMS, a park's by PAD-US area, a boat ramp's by USGS membership, a transit stop's by NTD ridership. Prominence heuristics apply only to the ~6 commercial categories where no registry exists.

**Validation gate.** The prior must be validated against the **1,090 existing POI rows already labeled with Google ratings** (see §14.2 Gate 1) before cutover. This is a labeled ground-truth set the platform already owns; the experiment requires no API spend.

### 7.4 Product review — what stays, what dies

| Feature | Verdict | Reasoning |
|---|---|---|
| Nearby Amenities (label — name — distance) | **Essential** | The richest consumer surface. Distances are rating-independent. |
| Five lifestyle scores (coastal, walkability, convenience, commuter, family) | **Essential** | Pure distance tiers. **Zero rating dependence. This is proprietary IP.** |
| Flood zone | **Essential** | FEMA. Must render "not mapped" ≠ "safe" (SIP-P12). |
| School district boundary | **Valuable** | TIGER. Do **not** promote to *assigned school* without a maintained zone source (§15.3). |
| Property DNA archetypes / positioning | **Essential** | Deterministic, $0, no Google, no OpenAI. |
| `LocationDnaMarketingContextService` | **Valuable — wire it** | Built and tested; deliberately never injected into the AI prompt. |
| Interactive Location DNA map | **Valuable** | Rebuild on MapLibre. |
| **`top_rated_dining`** | **REMOVE** | Exists *only* because Google made it easy. Definitionally rating-driven (`rating × min(reviews/50, 1)`). The lifestyle score service explicitly does not read it. It surfaces as a single "Top Dining" distance row. It is the canonical example of a feature-because-the-API-had-it, and it carries the ToS liability. |
| Persisted `rating` / `user_ratings_total` | **REMOVE** | Google permits ≤30-day caching; these are stored indefinitely. Replace with derived `prominence` + `confidence` — precisely the discipline `GooglePlacesPoiAdapter` already applies on the buyer/tenant path. |
| Beach narrative quality gate (`ranking_score ≥ 45.0`) | **REWORK** | Replace with PAD-US area + NOAA shoreline adjacency. More honest and more defensible than a review-count proxy. |
| `fitness_center` (distinct from `gym`) | **MERGE** | Provider artifact. |
| Walkability / commute / appreciation placeholder cards | **BUILD or REMOVE** | Shipping dead "coming soon" cards is worse than omitting them (`location-dna-architecture-review.md` §4). Walkability and commute are now buildable; appreciation belongs to §16. |

---

## 8. Property DNA

### 8.1 Current state (verified)

`PropertyDnaGenerator` is **fully deterministic**: keyword/enum mapping over listing fields and EAV meta. It makes **no OpenAI calls** — the `ai_` column prefixes (`ai_buyer_archetype_tags`, `ai_marketing_hooks`) are misleading. Persistence is append-only versioned into `property_dna_profiles` under a Postgres advisory lock. Cost per listing: **$0**. `BuyerTenantDnaGenerator` is symmetric.

Four score columns are hardcoded `null`: `location_score`, `condition_score`, `legal_score`, `compatibility_score`.

### 8.2 Expansion

1. **Populate `location_score` from the Spatial Core.** This is the natural bridge: Property DNA gains a location dimension computed by Location DNA, not a second pipeline. It becomes a `LocationLifestyleBridgeGenerator` consumer.
2. **Rename `ai_*` columns** (or document loudly) — a deterministic field named `ai_*` invites a future engineer to feed it to a model. Low-risk hygiene; a rename is a migration and therefore Phase 6.
3. **Add spatially-derived property attributes** that require no listing input: elevation (USGS 3DEP), coastal proximity, flood zone, noise exposure (BTS), dark-sky (VIIRS). These are *property facts derived from position* — squarely within Phase A §3 Allowed Inputs, and unobtainable from any listing form.
4. **`condition_score` and `legal_score` stay null** until a non-spatial source exists. Do not fabricate.

Property DNA remains the **supply-side** artifact. It must never consume demographics.

---

## 9. Target Market Intelligence

### 9.1 What TMI actually is today

There is **no** `TargetMarketIntelligence` class and **no** `target_market_*` table. `CENSUS_INTELLIGENCE_PHASE_5_3...md` states plainly: *"There is no existing 'Target Market DNA' entity."*

What exists is `PropertyIntelligenceProfileService`, which deterministically derives `property_target_audiences` and `property_positioning` from `PropertyDnaProfile` archetype tags plus Location DNA context. **It already requires no Google, no OpenAI, and no purchased data.**

### 9.2 Can TMI be fully proprietary? Yes — it already is.

TMI needs **no commercial business-review API**. It should be enriched exclusively from geography, infrastructure, environment, and housing-stock economics:

| Signal | Source | License |
|---|---|---|
| Price trajectory | FHFA HPI (county/ZIP/tract) | Public domain |
| New supply | Census Building Permits (BPS) | Public domain |
| Employment base | BLS QCEW (county × industry) | Public domain |
| Rental yield context | HUD Fair Market Rents (Small-Area, ZIP) | Public domain |
| Amenity context | Location DNA (owned corpus) | — |
| Environmental context | FEMA, NOAA, EPA, USGS | Public domain |

**Explicitly excluded: Zillow ZHVI/ZORI** — proprietary, commercial-use terms unverified. FHFA + HUD + BPS are public-domain substitutes.

### 9.3 The Fair Housing constraint on TMI — binding

`LOCATION_DNA_PHASE_A...md` §4 lists **"Census demographic statistics by tract or ZIP"** as a **Prohibited Input**. `CENSUS_INTELLIGENCE_PHASE_5_3...md` independently rates a demographic "Target Market DNA" as *"textbook FHA steering & disparate-impact"* and recommends against it.

> **Decision SIA-D8 (below): demographics — including ACS — MUST NOT feed any score, ranking, recommendation, audience derivation, or match. Any future demographic use is display-only, guardrailed, separately approved, and out of scope for this architecture.**

This resolves the governance conflict between Phase 5.3 and Phase A **in favour of Phase A**. Every economic signal in §9.2 is a *housing-stock or labour-market fact*, not a demographic composition statistic.

### 9.4 A governance reconciliation item (not an asserted violation)

Phase A §8 lists **"Ideal for families"** and **"Best for retirees"** as *prohibited outputs*. Yet:

- `LocationDnaLifestyleScoreService` emits lifestyle categories including `'Families'` and `'Retirees'` into `lifestyle_json`.
- `PropertyIntelligenceProfileService` derives audiences including `'Retirees'` and `'Move-Up Families'`.
- `property_target_audiences` is injected into the **Ask AI response contract** — an AI surface that produces consumer-facing text.

The consumer badge component (`matchmaker-target-audience.blade.php`) renders `archetype_tags` (e.g. `amenity:waterfront`), **not** these audience labels, and an `AskAiComplianceGuardrailService` exists. So this may already be mitigated in practice.

**This document does not assert a violation. It asserts an unresolved reconciliation, and requires it be closed before Phase 6.** `bid-your-offer-v2.1-architecture-review.md` reached the same conclusion independently, calling compliance *"the most serious omission, and it is launch-blocking for a real estate platform."*

**Recommended reframe — describe the property, never the person:**

| Risky | Safe |
|---|---|
| "Family Convenience Score" | **"Park & School Proximity Score"** |
| "Retirement Score" | **"Single-Level Living & Healthcare Access Score"** |
| Audience: "Retirees" | Attributes: single-story, low-maintenance, 0.6 mi to hospital |
| "Great for families" | "0.3 mi to Seminole City Park; 0.8 mi to grocery" |

Amenity facts are objective and defensible. Audience labels naming a protected class are not. **This requires legal review, not an architect's judgement.**

---

## 10. Buyer & Tenant Matching

### 10.1 The gap this closes

`bid-your-offer-v2.1-architecture-review.md` names it precisely:

> *"Buyer/Tenant Location **Preference** DNA is not a defined artifact… without it, map-search intent can't be matched against location DNA on identical axes. **This is a real gap.**"*

And `beyond-mls-property-dna-roadmap.md` states the principle:

> *"A searcher's **where** is DNA, not a filter."*

This architecture implements that principle (SIP-P7).

### 10.2 Current state (verified)

Two disconnected layers:

- **Intent capture — already complete.** `important_places_json` stores exactly the long-term vision: `{type, address, lat, lng, distance_pref: "miles"|"minutes", distance_value, travel_mode: driving|walking|bicycling|transit}` — multiple destinations, miles-or-minutes, four travel modes. **It currently has zero rows** (feature built, never populated). No migration burden; a clean slate.
- **Matching — reads none of it.** `BuyerMatchQueryBuilder` and `GeoEnvelopeNarrower` consume only cities/ZIPs/counties/radius/polygons. Grep confirms matching never reads `important_places`, `max_commute_minutes`, or `distance_value`. Geometry is PHP Haversine + ray-cast point-in-polygon over a `(latitude, longitude)` B-tree bounding box. **There is no spatial index and no PostGIS.**
- **Commute — a stub.** `CommuteTimeStubAdapter` returns `travel_time_minutes => null` unconditionally; `AppServiceProvider` binds it unconditionally. `property_location_pois.travel_time_minutes` has never been populated. `ImportantPlacesService` correctly refuses to draw a fake circle for a "minutes" preference: *"an accurate isochrone cannot be drawn, and a plain radius would be a fake travel-time circle, which the audit forbids."*

### 10.3 Target design

**Location Preference DNA** becomes a first-class artifact, symmetric with property-side Location DNA, on the same canonical taxonomy (SIP-P6/P7).

```
location_preference_geometries          ← one row per geometry, geography(Geometry,4326)
   kind: radius | polygon | isochrone | city | zip | county | important_place
   travel_mode, travel_minutes          ← for isochrone kinds
   resolved_isochrone_geom              ← materialized from Valhalla, cached
```

**Query patterns, by preference kind:**

| Preference | Mechanism | Cost |
|---|---|---|
| Radius (miles) | `ST_DWithin(listing.geom, center, meters)` + GiST | µs |
| Polygon | `ST_Contains(ST_Subdivide(polygon), listing.geom)` | µs |
| City / ZIP / County | boundary join | µs |
| **Drive-time / commute-time** | **one** Valhalla isochrone → `ST_Contains(isochrone, listing.geom)` | one local routing call, then µs |
| Distance to POI category | `LATERAL … ORDER BY places.geom <-> listing.geom LIMIT 1` | ~ms |
| Exact per-listing commute (result page) | Valhalla matrix over the ~25 visible listings | one local call |

**The isochrone-then-containment pattern is the architectural key.** "Which listings are within 30 minutes of my office?" resolves to **one** routing call producing a polygon, then a single indexed containment test against the entire corpus — collapsing N per-listing route calls into one. All-pairs precompute is explicitly rejected (quadratic). For a small set of *known* destinations (major employers, airports), precompute a column. An **H3 hex-grid hybrid** — resolving travel time per destination × hex and mapping hexes to listings — is the recognised optimization at scale and is the designated v2 path.

### 10.4 Scoring and compatibility

Two-sided scores land in the **existing** `dna_scores` table (`side ∈ {property, demand}`, with `value`, `data_completeness`, `confidence`, `explanation`, `inputs_json`, `version`). This table already holds **1,430 rows across 460 listings** — the two-sided foundation is built and switched off.

New score keys:

| Key | Property side | Demand side |
|---|---|---|
| `commute_convenience` | isochrone reach to employment centres | fit vs stated destinations + modes |
| `location_compatibility` | composite of location scores | composite of preference geometries |

**Display rules (inherited, binding):** `confidence` and `data_completeness` are internal-only and never rendered. Per `BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md` **Rule 2 — No Hidden Weighting**, any weighting must be disclosed in plain language and recorded in `scoring_framework_version`.

**Gates (binding, unchanged):** `SeniorCommunityComplianceGate` (HOPA/55+) runs unconditionally and is **never** placed behind `hard_filters_enabled`. `MatchResultPersister`'s hard production write-refusal remains until Matching V2 is explicitly promoted.

---

## 11. Spatial Scoring Framework

### 11.1 Canonical score contract

Every score is a pure function of owned spatial facts:

```
score = Σ (w_i · tier(distance_i))   normalized to 0–100
data_completeness = |inputs present| / |inputs required|
confidence ≤ data_completeness
explanation = deterministic, factual, non-steering sentence
```

Rules: distance tiers reuse the existing five-tier ladder. Missing inputs reduce `data_completeness`; they never impute a zero (SIP-P12). Every score is recomputable offline from persisted inputs (`scoring_version`).

### 11.2 The score catalogue

**Tier 1 — existing, retain (all pure distance, zero rating dependence):**

| Score | Composition |
|---|---|
| `coastal` | beach ×1.0 + marina ×0.5 |
| `walkability` | grocery ×1.0 + restaurant ×0.8 + coffee ×0.7 + pharmacy ×0.5 |
| `convenience` | grocery ×1.0 + pharmacy ×0.8 + coffee ×0.5 |
| `commuter` | transit ×1.0 + gas_station ×0.6 |
| `family` *(rename → `park_school_proximity`)* | park ×1.0 + dog_park ×0.6 + waterfront_park ×0.5 + grocery ×0.7 |

**Tier 2 — new, FHA-safe, buildable from the corpus:**

| Score | Inputs | Note |
|---|---|---|
| `beach_lifestyle` | NOAA shoreline + PAD-US + beach_access | |
| `boating_access` | **USGS boat ramps** + marinas + USACE navigable waterways | |
| `outdoor_recreation` | PAD-US area + trails length + golf | promote the currently-hidden `outdoor` sub-score |
| `healthcare_access` | **CMS star ratings** + HRSA + urgent_care distance | authority-ranked |
| `pet_friendly` | dog parks + trails + open space | |
| `walkability_v2` | **EPA National Walkability Index** + POI proximity | index as base tile, POIs for address precision |
| `commuter_convenience` | **Valhalla isochrones** + GTFS + **NTD ridership** + FAA | the one buyers act on |
| `luxury_context` | FHFA HPI + waterfront/open-space adjacency | property-context, not people |
| `investment_appeal` | FHFA HPI + BPS permits + BLS QCEW + HUD FMR | housing-stock economics only |
| `vacation_appeal` | coastal + recreation + airport access | |
| **`single_level_healthcare_access`** *(replaces "Retirement")* | single-story + healthcare + walkability + low noise | property attributes, not age |

**Tier 3 — recommended additions (highest differentiation, all free):**

| Score | Inputs | Why |
|---|---|---|
| **`climate_resilience`** | FEMA NFHL + NOAA SLR + wildfire risk + USGS seismic | **The single highest-differentiation score available. No major portal ships it. Entirely free data.** |
| `storm_surge_exposure` | NOAA surge models | Decisive in Florida, the current market |
| `elevation_advantage` | USGS 3DEP | Cheap, objective, correlates with insurability |
| `quiet_score` | BTS National Transportation Noise Map | ⚠️ Name it for the *source* (transportation noise), never "quiet neighborhood" — Phase A §8 prohibits the latter as a demographic proxy |
| `dark_sky` | **VIIRS** (public domain) | Never Falchi (CC BY-NC) |
| `air_quality_context` | EPA AirNow / AQS | |

**Prohibited, permanently:** any score derived from crime statistics, area racial/ethnic composition, familial-status targeting, "neighborhood safety," "neighborhood desirability," or school *quality* ratings used as a ranking input. Phase A §4/§8; reinforced by *US v. Meta* (2022), *NFHA v. Redfin* (2022, $4M), *Louis v. SafeRent* (2024, $2.275M).

---

## 12. Data Sources

### 12.1 Free — public domain / permissive (the baseline stack)

| Domain | Source | License | Access | Cadence |
|---|---|---|---|---|
| Places | Overture Places | CDLA-Permissive-2.0 (+Apache/CC0) | GeoParquet on S3 (DuckDB) | Monthly |
| Places (supplement) | OpenStreetMap | ODbL | Bulk PBF extract | Continuous |
| Places (optional) | Foursquare OS Places | Apache-2.0 | Parquet / Iceberg | Monthly |
| Flood | FEMA NFHL | Public domain | ArcGIS REST | Rolling |
| Boundaries | Census TIGER/Line | Public domain | Bulk + ArcGIS | Annual |
| Schools | NCES CCD / EDGE | Public domain | Bulk | Annual |
| Parks | USGS PAD-US 4.1 | Public domain | Bulk gdb + ArcGIS | Periodic |
| Boat ramps | USGS Boat Ramp Locations | **CC0** | Bulk points | Static (2023) |
| Trails | USGS Nat'l Digital Trails / USFS | Public domain | Bulk + WFS | Periodic |
| Hospital quality | **CMS Hospital Overall Star Rating** | Public domain | DKAN REST + CSV | Annual (measures quarterly) |
| Health centres | HRSA | Public domain | Bulk + API | Periodic |
| Walkability | EPA National Walkability Index | Public domain | Bulk + ArcGIS | Periodic |
| Transit stops | GTFS via Mobility Database / Transitland | **Per-feed** | REST + bulk | Daily catalog |
| Transit quality | National Transit Database | Public domain | Socrata | Annual |
| Airports | FAA NASR | Public domain | Bulk (28-day AIRAC) | 28-day |
| Shoreline | NOAA CUSP | Public | Bulk shapefile | Periodic |
| Elevation | USGS 3DEP / EPQS | Public domain | REST + raster | Periodic |
| Noise | BTS Nat'l Transportation Noise Map | Public domain | Raster + WMS | Biennial |
| Light | VIIRS (EOG) | Public domain | GeoTIFF / GEE | Monthly |
| Air quality | EPA AirNow / AQS | Public domain | REST (free key) | Real-time / annual |
| Hazard | NOAA (SLR, surge, normals), USGS (seismic), USFS (wildfire) | Public domain | Mixed | Varies |
| Economics | FHFA HPI · Census BPS · BLS QCEW · HUD FMR | Public domain | Bulk + REST | Monthly–annual |
| Addresses | DOT National Address Database | Public domain | Bulk | ~2–3×/yr |
| Geocoding | Census Geocoder | Public domain | REST (batch 10k) | Continuous |

### 12.2 Optional paid (flat-rate, never per-listing)

| Source | Cost | Why | Necessity |
|---|---|---|---|
| **Geoapify** | $0–59/mo | Geocoding fallback with **permanent storage rights**; SLA | Convenience |
| **MapTiler** | $30/mo | Managed tiles, if we decline Protomaps self-hosting | Convenience |
| **Foursquare Places Premium** | ~$18.75/1k **per POI** | Rating + foot-traffic `popularity` | **Contingency only** — if §14.2 Gate 1 fails |
| **ATTOM** | Quote | Maintained **school attendance zones** | If assigned-school is promoted to a product claim (§15.3) |

### 12.3 Future

EV charging (NREL AFDC), insurance/claims history, FBI CDE *(display-only, never scored — see §11.2 prohibitions)*, Overture Transportation & Buildings themes, parcel geometry, neighborhood trend models, Walk Score licensed badge.

### 12.4 Explicitly excluded

| Source | Reason |
|---|---|
| **Google Places / Geocoding / Maps JS** | Cost model, 30-day cache cap, non-Google-map clause |
| **Public Nominatim / Overpass / OSM tiles** | Usage policies forbid production app-backend use |
| **Yelp Open Dataset** | Non-commercial license |
| **Yelp Fusion** | 24-hour cache cap; anti-blending clause conflicts with composite scoring |
| **Falchi World Atlas (night sky)** | CC BY-NC |
| **EPA EJScreen** | Removed Feb 2025; bundles race layers (Phase A §4) |
| **Zillow ZHVI/ZORI** | Proprietary; commercial terms unverified |
| **ACS demographics as a scoring input** | Phase A §4 Prohibited Input |

---

## 13. Database Design

### 13.1 Principles

Store each **place once**. Derive per-listing facts by query. Persist only what is displayed or must be version-stamped. This replaces the current listing × POI cross-product (**84 rows/listing → 84M @ 1M listings**) with a corpus proportional to *geography*, not to *listings*.

### 13.2 Core tables

```sql
-- The owned places corpus. One row per real-world place, shared by every listing.
places (
  gers_id            text primary key,        -- Overture stable ID; survives refresh
  geom               geography(Point,4326) not null,
  category_key       text not null references place_categories,
  name               text,
  brand              text,
  confidence         numeric(4,3),            -- Overture existence confidence
  source_count       smallint,
  prominence         numeric(4,3),            -- derived prior (§7.3), recomputed on scoring_version
  authority_ref      text,                    -- CMS CCN, NCES ID, FAA LID, PAD-US unit …
  authority_metric   numeric,                 -- CMS stars, PAD-US acres, NTD ridership
  attrs              jsonb,
  corpus_version     text not null,
  first_seen, last_seen timestamptz
);
CREATE INDEX places_geom_gist   ON places USING gist (geom);
CREATE INDEX places_cat_geom    ON places USING gist (geom) WHERE category_key IS NOT NULL;
CREATE INDEX places_cat         ON places (category_key);
CLUSTER places USING places_geom_gist;   -- re-cluster after monthly swap

-- Extensible taxonomy. Adding a POI category is an INSERT, not a code change.
place_categories (
  category_key       text primary key,
  label              text,
  thematic_block     text,
  base_source        text,          -- overture | osm | cms | padus | usgs | faa | gtfs | nces
  prominence_strategy text,         -- authority | brand | area | ridership | confidence
  exclusion_rules    jsonb,
  enabled            boolean default true
);

-- All polygonal geography: flood zones, school districts, ZCTAs, places, counties, PAD-US units.
boundaries (
  id bigserial primary key,
  kind               text not null,           -- flood_zone | school_district | zcta | county | place | protected_area
  external_ref       text,
  attrs              jsonb,                   -- FLD_ZONE, SFHA_TF, district name, acres …
  geom               geography(MultiPolygon,4326) not null,
  corpus_version     text not null
);
CREATE INDEX boundaries_geom_gist ON boundaries USING gist (geom);
CREATE INDEX boundaries_kind      ON boundaries (kind);
-- Large polygons are stored pre-subdivided (ST_Subdivide, ≤256 vertices) in boundaries_parts.

-- Supply side: one point per listing, replacing scattered decimal lat/lng columns.
listing_locations (
  listing_type text, listing_id bigint,
  geom         geography(Point,4326) not null,
  geocode_source text,                        -- saved_meta | nad | census | geoapify
  primary key (listing_type, listing_id)
);
CREATE INDEX listing_locations_geom_gist ON listing_locations USING gist (geom);

-- Demand side: Location Preference DNA — first-class, durable, enrichable (SIP-P7).
location_preference_geometries (
  id bigserial primary key,
  subject_type text, subject_id bigint,       -- buyer/tenant criteria
  kind         text not null,                 -- radius | polygon | isochrone | city | zip | county | important_place
  place_type   text,                          -- Work | School | Grocery | …
  travel_mode  text,                          -- driving | walking | bicycling | transit
  travel_minutes smallint,
  geom         geography(Geometry,4326),      -- resolved: circle, polygon, or isochrone
  routing_version text,
  attrs        jsonb
);
CREATE INDEX lpg_geom_gist ON location_preference_geometries USING gist (geom);
CREATE INDEX lpg_subject   ON location_preference_geometries (subject_type, subject_id);

-- Routing artifacts. Storable because the engine is ours.
isochrone_cache (
  origin_geohash text, mode text, minutes smallint,
  geom geography(MultiPolygon,4326),
  routing_version text, computed_at timestamptz,
  primary key (origin_geohash, mode, minutes, routing_version)
);
CREATE INDEX isochrone_geom_gist ON isochrone_cache USING gist (geom);

-- Thin display/version snapshot: rank-1 per category only (19 rows/listing, not 84).
location_snapshots (
  listing_type text, listing_id bigint, category_key text,
  place_gers_id text, distance_miles numeric(8,3),
  travel_minutes_drive smallint, travel_minutes_walk smallint,
  ranking_score numeric(6,2), prominence numeric(4,3),
  corpus_version text, scoring_version text, routing_version text,
  primary key (listing_type, listing_id, category_key)
);
```

### 13.3 Query patterns

```sql
-- Nearest place per category for one listing (replaces 16 Google calls)
SELECT c.category_key, p.name, ST_Distance(l.geom, p.geom)/1609.344 AS miles
FROM   place_categories c
CROSS JOIN LATERAL (
  SELECT p.* FROM places p
  WHERE  p.category_key = c.category_key
  ORDER BY p.geom <-> l.geom          -- index-assisted KNN
  LIMIT  1
) p
CROSS JOIN listing_locations l
WHERE  l.listing_type = $1 AND l.listing_id = $2 AND c.enabled;

-- "Listings within a 30-minute drive of my office"  → ONE routing call, then containment
SELECT ll.listing_type, ll.listing_id
FROM   listing_locations ll
JOIN   isochrone_cache i
  ON   i.origin_geohash = $1 AND i.mode='auto' AND i.minutes=30
WHERE  ST_Contains(i.geom::geometry, ll.geom::geometry);
```

Always `ST_DWithin(...)`, never `ST_Distance(...) < x` — only the former is index-assisted.

### 13.4 Caching, materialization, refresh

| Concern | Decision |
|---|---|
| **Nearest-POI** | **Query-time LATERAL KNN by default.** Indexed KNN is fast enough (~ms) that the 84M-row precompute is unjustified. `location_snapshots` exists for display stability and version stamping, not for speed. |
| **Materialized views** | Avoid. Stock `REFRESH MATERIALIZED VIEW` is whole-table and takes `ACCESS EXCLUSIVE`. Prefer per-listing recompute on trigger — POIs change slowly, listings change often. |
| **Corpus refresh** | Monthly. Load Overture GeoParquet → DuckDB (filter to CONUS bbox, `confidence >= 0.90`) → staging table → **atomic swap** → `CLUSTER` + `ANALYZE`. Key on GERS ID; consume the GERS changelog for deltas once volumes justify it. |
| **Version stamps** | `corpus_version` (data snapshot) · `scoring_version` (weights/exclusions) · `routing_version` (graph build). A mismatch triggers the cheapest sufficient recompute — **never an API call.** |
| **Tile cache** | The Google-era POI tile cache (`LocationDnaPoiTileCache`) is **retired**. It exists solely to avoid metered calls. *Note: it is disabled today anyway (`tile_precision => null`), and its "0.005 recommended production default" was never actually measured — `ldna-tile-precision-benchmark.md` records every value as TBD.* |
| **Application cache** | Redis. `CACHE_DRIVER=file` cannot serve cross-process workers. |
| **Scaling** | LIST/RANGE partition `places` by region if it exceeds ~50M rows; GiST index cascades from the parent. Keep `ANALYZE` fresh. |

---

## 14. Migration Strategy

**No big-bang rewrite.** Six phases, each independently shippable, each config-reversible — except the last, which is sequenced last precisely because it is not.

### 14.1 The sequencing constraint that governs everything

> Google's Maps Service Terms forbid using Google Maps Content "in conjunction with a non-Google map."

Therefore: **while any Google Places or Geocoding data remains in use, the basemap must remain Google.** Maps migrate **last**. This is not a preference; it is the legal ordering of the work.

```
Phase 0  Safety & no-regret         Google stays.  Reduces cost & risk NOW.
Phase 1  Spatial Core (shadow)      Google stays.  Zero user impact.
Phase 2  Cut POI → corpus           Rollback = config flip.
Phase 3  Routing (net-new)          Nothing to roll back.
Phase 4  Geocode + autocomplete     Rollback = config flip.
Phase 5  Maps → MapLibre            ◄── POINT OF NO RETURN
Phase 6  Scores · TMI · Matching V2
```

### 14.2 Validation gates

| Gate | Blocks | Test |
|---|---|---|
| **Gate 1 — Authority-first ranking** *(supersedes "prominence prior")* | Phase 2 | **Superseded to authority-first ranking (SSOT §9.2/§9.3; E-12).** Score the corpus nearest-3 per listing × category pair vs the frozen Google ground truth — **844 rated rows across 13 listings** (E-10), not 1,090. Pass = embarrassment rate ≤3% (baseline: 19% with no signal, 1% with true review counts). **Zero API spend. This is the pivotal experiment.** |
| **Gate 2 — Corpus coverage** | Phase 2 | Per-category coverage of the owned corpus vs the Google baseline across the Florida footprint. Rural sparsity is the risk. |
| **Gate 3 — Dual-run diff** | Phase 2 | Shadow-compute Location DNA from the corpus alongside Google for enriched listings; diff rank-1 selections. Adopts the dual-run pattern from `location-dna-architecture-review.md` §3. |
| **Gate 4 — Routing parity** | Phase 3 | Valhalla drive times vs known ground truth on a sample corridor set. |
| **Gate 5 — Compliance sign-off** | Phase 6 | Legal review of §9.4 (audience labels) and §11.2 (prohibited scores). |

### 14.3 Rollback

| Phase | Rollback | Cost | Window |
|---|---|---|---|
| 0 | Revert config | none | any |
| 1 | Drop shadow tables | none | any — Google untouched |
| 2 | `capabilities['poi.default'] → google_places` | refetch fee | any, if key re-enabled |
| 3 | `commute_time.provider => 'stub'`; hide commute UI | none | any |
| 4 | Re-point geocode/autocomplete adapters | none | any |
| 5 | Restore Google Maps JS across 3 surfaces | days | **effectively one-way** |

`fetch_version` already folds `LocationProviderRegistry::capabilityHash()`, so a provider flip correctly invalidates caches. That mechanism is retained.

**Preconditions for Phase 5:** Gates 1–4 passed; corpus refresh automated; Valhalla in production ≥30 days; explicit written decision that Google is not returning.

---

## 15. Risk Analysis

### 15.1 Operational — the blocking risk

**R1 (CRITICAL). The production environment cannot execute this architecture, and cannot correctly execute the *current* one.**

`.replit` sets, in `userenv.shared` (which applies to deployment):

```
QUEUE_CONNECTION = "sync"
CACHE_DRIVER     = "file"
deploymentTarget = "vm"
run = php artisan serve --host=0.0.0.0 --port=5000
```

There is no `queue:work` and no `schedule:run` anywhere in the deployment configuration.

Consequences, all present today:

- `ComputeLocationDna::dispatch()` executes **inline, inside the user's web request** — 16 blocking Google calls on save. With `public int $tries = 3`, a failing save retries inline up to **48 calls in one request**.
- This is **root cause #1 of the 2026-07-05 incident**, and it is baked into the environment, not the code.
- The scheduled command `offers:expire-pending` **never runs** (no `schedule:run`).
- `CACHE_DRIVER=file` cannot back a cross-process cache; the tile cache's own config comments warn that a non-shared store "silently defeats the tile cache."
- Production is served by `php artisan serve` — the PHP built-in dev server.

**SIP-P13 ("Async by construction") is violated in production today.** No amount of provider migration fixes this. **Mitigation: a real application host with a background worker, Redis, managed Postgres + PostGIS, and one routing VM is a hard dependency of Phase 1** — and is independently required for asynchronous DNA generation to work at all.

**R2 (Medium).** We now operate a data pipeline (monthly Overture refresh, periodic Valhalla graph builds). *Mitigation: both are batch jobs off the request path; staging-swap keyed on GERS; a stale corpus degrades gracefully.*

**R3 (Medium).** Valhalla US-wide build RAM is extrapolated from published planet figures; no vendor publishes US-specific numbers. *Mitigation: benchmark a Florida extract first (the current market), then CONUS. Serve-RAM is low because tiles are mmap'd.*

### 15.2 Technical

**R4 (High → mitigated by Gate 1).** The prominence prior is unvalidated. Brand-chain POIs average 616 reviews vs 390 for non-brand — real separation, but only 1.6×, and brands are just 36.5% of commercial POIs. *Mitigation: Gate 1, against data we already own, before any cutover. Contingency: Foursquare Premium (§4.5).*

**R5 (Medium).** `LocationDnaRankingEngine.rankCandidates()` consumes **raw Google JSON** (`$place['geometry']['location']['lat']`, `$place['types']`). It is *not* provider-agnostic, contrary to the claim in `PHASE_8_...RECOMMENDATION.md`. And the production POI path (`LocationDnaPoiDistanceService`) calls Google inline via raw Guzzle, **bypassing `PoiLookupAdapterInterface` entirely** — the seam is wired only to the secondary buyer/tenant path. *Mitigation: normalizing the engine's input shape and routing the production path through the seam is prerequisite Phase 2 work that no existing document accounts for.*

**R6 (Medium).** Behavioural drift in POI selection. *Mitigation: Gate 3 dual-run diffing; 43 existing Location DNA test files provide unusually strong coverage.*

**R7 (Low, sharp).** Phase 5 is one-way. *Mitigation: sequence last; gate explicitly.*

### 15.3 Data quality — two landmines unrelated to Google

**R8 (High).** **NCES SABS school-attendance zones are frozen at 2015-16** (~10 years stale). Buyers decide on assigned school; a confidently wrong answer is worse than none. *Mitigation: surface district boundaries (TIGER, current) only; label any attendance-zone claim advisory, or license ATTOM.*

**R9 (High).** FEMA "not mapped," Zone D ("undetermined"), and legacy paper FIRMs must **never** render as "no flood risk." *Mitigation: SIP-P12 enforced at the presenter.*

**R10 (Medium).** Rural/exurban sparsity compounds across NAD gaps, Census interpolation (rural median error 147 m vs 45 m urban), OSM thinness, and missing small-agency GTFS — precisely where Location DNA is most differentiating. *Mitigation: Gate 2; `data_completeness` degrades honestly rather than silently.*

**R11 (Medium).** Overture is monthly; brand-new small businesses lag Google's live index. **This is the one honest UX regression in the entire migration.** Immaterial for institutional POIs.

### 15.4 Licensing & compliance — three live violations, closed as a side-effect

| # | Violation | Closed by |
|---|---|---|
| V1 | Google `rating`/`user_ratings_total` persisted indefinitely (ToS cap: 30 days) and rendered in agent/admin views | Phase 0 (stop persisting) / Phase 2 (remove entirely) |
| V2 | `map-input.blade.php:860` calls `nominatim.openstreetmap.org` **from end-user browsers** — OSMF policy forbids autocomplete use and requires an identifying User-Agent a browser cannot set. The platform's own C14.2 decision note says *"Do NOT use the public `nominatim.openstreetmap.org` service in production."* | Phase 4 |
| V3 | A single un-segregated API key is both embedded in every page and used for server-side billing | Phase 0 |

**Also unresolved:** the `GOOGLE_PLACES_ENABLED` kill switch, written in response to the incident, is **wired to nothing** — `grep -rn "google_places\." app/` returns zero hits. `daily_limit=100` and `hourly_limit=25` are equally inert.

**Fair Housing** is treated as a first-class architectural concern (SIP-P11, §9.3, §9.4, §11.2), answering the v2.1 review's finding that its absence is *"launch-blocking for a real estate platform."*

### 15.5 Scaling & performance

Query-time KNN is index-assisted and ~ms at millions of rows. Containment is index-assisted with `ST_Subdivide`. The corpus grows with *geography* (bounded); listings grow independently. The removal of the 84-rows-per-listing cross-product is the single largest scalability change in this document.

---

## 16. Future Expansion

Ordered by (differentiation × feasibility) ÷ risk.

| Opportunity | Source | Note |
|---|---|---|
| **Climate resilience & insurability** | FEMA + NOAA + USGS + USFS | **Highest-differentiation opportunity available. Free. No major portal ships it.** |
| **EV charging** | NREL Alternative Fuels Data Center | Free, clean API, growing buyer relevance |
| **Trails & boat launches** | USGS/USFS + USGS CC0 ramps | Already in the Tier-2 corpus |
| **Noise** | BTS noise map | Name for the source, never "quiet neighborhood" |
| **Weather & climate normals** | NOAA | Feeds vacation/retirement-adjacent scoring |
| **Insurance context** | FEMA + wildfire + surge | Derived, not purchased |
| **Neighborhood trends** | FHFA HPI + BPS + QCEW | ⚠️ Never "up and coming" (Phase A §8 prohibits it as a gentrification/demographic proxy) |
| **Investment forecasting** | HPI + permits + employment | Model, clearly labelled inference, not fact |
| **Walk Score badge** | Licensed | Only for brand recognition; EPA index + our POIs already compute the substance |
| **Crime** | FBI CDE | **Display-only if ever, never scored.** Agency-level granularity, recognised race proxy (§11.2) |
| **Knowledge graph** | property ↔ neighborhood ↔ audience ↔ story | The v2.1 review names its absence as the hardest-to-copy retrieval quality |
| **Vector store** | pgvector **0.8.0 is already available** on this Postgres instance | Answers the v2.1 scalability finding without new infrastructure |
| **H3 hex-grid commute index** | H3 + Valhalla | The recognised scale pattern for drive-time search |
| **Overture Transportation / Buildings themes** | Overture | Same corpus, same pipeline |
| **AI: fact-grounded location stories** | L4 outputs | Facts in, prose out. Never demographics. Distinguish fact from inference visually (v2.1 §UX) |

---

## 17. Implementation Roadmap

Sizing is engineer-weeks, excluding QA. Phases 0–2 deliver the entire stated V1 goal.

---

### Phase 0 — Safety & No-Regret · ~1–2 wks · complexity **LOW**

**Do this regardless of every other decision in this document. It keeps Google, reduces cost immediately, and commits to nothing.**

**Objectives.** Stop the bleeding; close compliance gaps; make the environment capable of asynchronous work.

**Deliverables.**
1. Wire the `GOOGLE_PLACES_ENABLED` kill switch and the daily/hourly caps (currently inert).
2. **Add `sessiontoken` to Places Autocomplete.** Google bills Autocomplete **per session at $0, unlimited**, on Essentials; the code passes no token and therefore pays the per-request SKU ($2.83/1k). **This is pure, immediate savings.**
3. Implement the four unimplemented RCA remediations: bind `PoiLookupAdapterInterface` → stub in tests; blank the key under `APP_ENV=testing`; resolve the HTTP client from the container in both POI callers (removing bare `new Client()`); add a global stray-request guard.
4. Stop persisting `rating` / `user_ratings_total`; write the existing, unwritten `confidence` / `provenance_json` columns instead (migration `2026_07_05_000001`).
5. Segregate the API key (browser-referrer key vs server key); set a Google daily quota cap and budget alert.
6. **Provision real infrastructure** (R1): queue worker, Redis, managed Postgres + PostGIS. `CREATE EXTENSION postgis;`
7. Add a dirty-check to the DNA observers.

**Dependencies.** None. **Risk.** Low. **Testing.** Existing 43 Location DNA test files; add a guard test asserting no outbound calls in `ComputeLocationDna`. **Rollback.** Revert config.
**Success criteria.** Zero live Google calls from the test suite; autocomplete cost → ~$0; a queued job actually queues; `postgis` installed.

---

### Phase 1 — Spatial Core (Shadow) · ~3–4 wks · complexity **MEDIUM**

**Objectives.** Stand up the owned corpus and prove it, with Google untouched and zero user impact.

**Deliverables.**
1. `places`, `place_categories`, `boundaries`, `listing_locations` with GiST indexes (§13).
2. Overture US ingest: DuckDB → CONUS bbox + `confidence ≥ 0.90` → staging → swap. Measure the actual row count and size (Appendix D, Q1).
3. Authority overlays: FEMA, TIGER, NCES, PAD-US, **USGS boat ramps**, CMS, EPA Walkability, FAA, GTFS+NTD.
4. Unified 23-category taxonomy (§7.2), including the four Phase-A-approved-but-never-built categories.
5. **Build the prominence prior and run Gate 1** against the 1,090 labeled rows.
6. Shadow-compute Location DNA; run Gate 2 and Gate 3.

**Dependencies.** Phase 0 (PostGIS, infra). **Risk.** Medium — corpus coverage is the unknown.
**Testing.** Gates 1–3. Coverage report per category vs the Google baseline.
**Rollback.** Drop shadow tables. Google is untouched throughout.
**Success criteria.** Gate 1 ≤3% embarrassment; Gate 2 coverage acceptable per category; Gate 3 diffs explainable.

---

### Phase 2 — Cut the Listing Pipeline · ~2–3 wks · complexity **MEDIUM-HIGH**

**Objectives.** Achieve G1, G4. Delete 16 API calls per listing.

**Deliverables.**
1. Normalize `LocationDnaRankingEngine` off raw Google JSON (R5).
2. Route the production path through `PoiLookupAdapterInterface`; implement a `CorpusPoiAdapter`.
3. Replace Nearby Search with LATERAL KNN. Persist rank-1 only → `location_snapshots` (**19 rows/listing, not 84**).
4. Retire `top_rated_dining`; merge `fitness_center` into `gym`; wire the five previously-invisible categories into new thematic blocks.
5. Retire `LocationDnaPoiTileCache`.
6. **Turn on automatic Location DNA for every seller, landlord, and Bridge listing** (all 667). **Delete `AgentLocationDnaController::generate()`.**

**Dependencies.** Phase 1 gates. **Risk.** Medium-high — touches the launch-critical working path.
**Testing.** Dual-run diff continues in production for one cycle; the 43 test files; snapshot tests on `summary_json` / `lifestyle_json`.
**Rollback.** Flip `capabilities['poi.default']` back to `google_places` (requires re-enabling the key).
**Success criteria.** 100% Location DNA coverage; **zero outbound calls** from `ComputeLocationDna`; per-listing marginal cost $0.

---

### Phase 3 — Routing & Commute · ~3–4 wks · complexity **HIGH**

**Objectives.** Achieve G5. Deliver a capability that does not exist today at any price.

**Deliverables.**
1. Valhalla on a dedicated VM; Florida extract first, then CONUS.
2. `ValhallaCommuteAdapter implements CommuteTimeAdapterInterface` — the contract, cache, and `max_destinations` cap already exist.
3. `IsochroneEngine` + `isochrone_cache`; isochrone → `ST_Contains` search.
4. **`location_preference_geometries`** — Location Preference DNA as a first-class artifact (SIP-P7), closing the v2.1 gap.
5. Wire `important_places_json` into `BuyerCriteriaPayload` and the narrowers. **It is captured today and read by nothing.**
6. Populate `travel_time_minutes` — null since inception. Draw real isochrones where `ImportantPlacesService` currently, correctly, refuses to draw a fake circle.

**Dependencies.** Phase 1 (PostGIS), infra VM. **Risk.** High — new infrastructure; RAM figures extrapolated (R3).
**Testing.** Gate 4 (routing parity). Load test the isochrone→containment path.
**Rollback.** `commute_time.provider => 'stub'`; hide commute UI. Nothing pre-existing regresses.
**Success criteria.** Drive-time search returns in <1s at corpus scale; four travel modes; multiple destinations.

---

### Phase 4 — Geocoding & Autocomplete · ~2–3 wks · complexity **MEDIUM**

**Objectives.** Remove the remaining non-map Google surfaces. Close V2.

**Deliverables.** MLS coords → NAD → Census → Geoapify chain. Photon (or Geoapify) autocomplete across 12 Livewire proxies, `byo-address-autocomplete`, and ~25 legacy Blade forms. **Remove the browser-side public-Nominatim calls.**

**Dependencies.** None beyond Phase 0. **Risk.** Medium — breadth, not depth.
**Testing.** Address-resolution regression suite; preserve the "honest NOT_FOUND over confident wrong-city" posture recorded in git-C14.2.
**Rollback.** Re-point adapters. **Success criteria.** Zero Google geocode/autocomplete calls; V2 closed.

---

### Phase 5 — Maps · ~3–4 wks · complexity **HIGH** · ⚠️ ONE-WAY

**Objectives.** Complete the Google exit.

**Deliverables.** MapLibre GL + Protomaps PMTiles across three surfaces: `map-input.blade.php` (**1,500 LOC — the single largest item in this roadmap, larger than the POI swap**, with custom click-drawing, three Autocomplete instances, and a Data-layer polygon loader), `location-dna-map.blade.php`, and the Embed-API iframe. Note `map-input` already sources its boundaries from Nominatim and TIGERweb — its data layer is half-migrated already.

**Dependencies.** **Phases 2 and 4 complete** — no Google Places or Geocoding data may remain in use (§14.1).
**Risk.** High, and irreversible. **Testing.** Full browser QA across all 13 embedding forms.
**Rollback.** None practical. **Gate.** Explicit written decision.
**Success criteria.** No `maps.googleapis.com` reference remains; `GOOGLE_PLACES_API_KEY` deleted.

---

### Phase 6 — Intelligence · ~4–6 wks · complexity **MEDIUM**

**Objectives.** Achieve G2, G3, G7.

**Deliverables.** The Tier-2/Tier-3 score catalogue (§11.2). TMI enrichment from FHFA/BPS/QCEW/HUD. FHA reframe of audience labels (§9.4). Wire `LocationDnaMarketingContextService` into the AI pipeline. Enable `dna_scores.generation_enabled`, then `MATCHING_V2_ENABLED`, then persistence.

**Dependencies.** Phases 2–3; **Gate 5 (legal sign-off) is blocking.**
**Risk.** Medium technical, **high legal**. **Testing.** Score validation; `weights sum to 100`; two-sided symmetry.
**Rollback.** Feature flags — every subsystem is already flag-gated and inert by default.
**Success criteria.** Every listing carries Location DNA, Property DNA, TMI, and property-side `dna_scores`; buyers carry demand-side scores; matching runs on both.

---

**Total: ~18–26 engineer-weeks.** Phases 0–2 (~7 weeks) satisfy the complete Version 1 goal set.

---

## 18. Launch Recommendation

### 18.1 Version 1 architecture

**PostGIS + Overture Places + federal authority overlays, with Google retained only for maps and autocomplete until Phase 4/5.**

Concretely, V1 = **Phases 0, 1, 2**. That yields:

| V1 Goal | Achieved? | By |
|---|---|---|
| Automatic Location DNA — every seller listing | ✅ | Phase 2 |
| Automatic Location DNA — every landlord listing | ✅ | Phase 2 |
| Automatic Location DNA — every MLS/Bridge listing | ✅ | Phase 2 (coords already present in 100% of rows) |
| Automatic Target Market Intelligence | ✅ | Already deterministic and $0; pending the §9.4 reframe |
| Automatic Buyer/Tenant compatibility scoring | ✅ | Enable `dna_scores` + Matching V2 (Phase 6 for full catalogue) |
| Predictable operating costs | ✅ | ~$170–260/mo flat |
| No surprise API bills as we scale | ✅ | **Zero metered calls in any enrichment path** (SIP-P3) |

### 18.2 Are paid providers still required?

**No — with two flat-rate conveniences and one contingency.**

1. **Geoapify (~$59/mo, optional).** Geocoding fallback for the small tail of platform listings lacking coordinates. NAD + Census cover most of it free. Justified by SLA and permanent-storage rights, not necessity.
2. **MapTiler (~$30/mo, optional).** Only if we decline to self-host Protomaps.
3. **A ratings overlay — contingency only.** Purchased *only* if Gate 1 fails. Then: Foursquare Places Premium, keyed by POI not by listing, commercial categories only. **Not Google** (the non-Google-map clause forecloses it). **Not Yelp** (24-hour cache + anti-blending clause).

**Nothing in the enrichment path is metered. That is the whole point.**

### 18.3 Future architecture

Phases 3–6 add: self-hosted Valhalla routing; Location Preference DNA; drive-time, commute-time, and four-mode matching; per-metro OTP2 transit; the full proprietary score catalogue; H3 hex indexing; pgvector-backed retrieval (**already available on the current instance**); and a knowledge graph. None requires a further provider migration, because **the corpus is ours.**

### 18.4 Tradeoffs, stated plainly

We trade a **variable cost we cannot control** for a **fixed operational burden we can.** We trade Google's live business index for **authoritative federal data that is better in 10 of 23 categories** and monthly-fresh in the rest. We trade a rented ranking signal for a proprietary one we own, can explain, and can defend in a Fair Housing review. We accept ~18–26 engineer-weeks, a monthly data pipeline, and a real hosting bill.

In exchange: Location DNA goes from **0.9% of listings to 100%**; drive-time matching becomes possible for the first time; the manual "Generate" button disappears; three compliance violations close; and the class of incident that cost $1,223 from a test run becomes **structurally impossible**.

The honest regression is **freshness of brand-new small businesses**. It is immaterial for schools, hospitals, parks, transit, flood zones, and boat ramps — which is most of what a buyer actually asks about.

### 18.5 Final recommendation

> **Eliminate Google Maps Platform. Do not replace it with another metered geographic API. Replace the data model.**

Build the Spatial Core. Own the corpus. Let Location DNA, Property DNA, Target Market Intelligence, and Buyer/Tenant matching all read from it. Provider identity stops being an architectural concern.

This is the only architecture that satisfies *"automatic for every listing"* and *"predictable cost"* simultaneously — and it is the one that never needs another provider migration.

**Two actions are recommended before this document is approved:**

1. **Begin Phase 0 immediately.** It is free, it reduces cost this week, it closes three compliance gaps, and it commits to nothing in this document. The autocomplete session-token fix alone pays for itself instantly.
2. **Run Gate 1.** The prominence-prior validation costs days, zero API spend, and uses data already in the database. It is the single experiment that determines whether V1 requires any paid provider at all.

**The evidence assembled here indicates it will not.**

---

## Appendix A — Decision Register

| ID | Decision | Rationale | Status |
|---|---|---|---|
| **SIA-D1** | Own a local Places corpus (Overture, + OSM supplement) | Zero marginal cost; permanent storage; permissive license | Proposed |
| **SIA-D2** | PostGIS as the spatial substrate | Available today; resolves the v2.1 "canonical read model" gap | Proposed |
| **SIA-D3** | Self-hosted **Valhalla** for routing/isochrones/matrix | Only open engine with all five capabilities; **all hosted providers forbid storing results** | Proposed |
| **SIA-D4** | Remove business-review data entirely; rank on a **prominence prior** + authority | Consumers never see ratings; prominence recovers 99% of tail quality; closes a ToS violation | Proposed, **gated by Gate 1** |
| **SIA-D5** | Delete `top_rated_dining`; merge `fitness_center` into `gym` | Provider artifacts, not product concepts | Proposed |
| **SIA-D6** | One canonical taxonomy, point mode + area mode | Adopts `location-dna-architecture-review.md` §3 | Proposed |
| **SIA-D7** | **Location Preference DNA** as a first-class artifact | Implements the roadmap's "searcher's *where* is DNA" principle; closes the v2.1 gap | Proposed |
| **SIA-D8** | **No demographics in any score, rank, audience, or match** | Phase A §4; Census 5.3 FHA analysis; resolves their conflict in favour of Phase A | **Binding** |
| **SIA-D9** | Reframe audience/score names away from protected classes | Phase A §8; *Meta* / *Redfin* / *SafeRent* precedent | Proposed, **requires legal review** |
| **SIA-D10** | Maps migrate **last** (one-way) | Google's "No Use With Non-Google Maps" clause | **Binding** |
| **SIA-D11** | Real queue worker + Redis + managed PostGIS are a Phase 1 hard dependency | R1; SIP-P13 is violated in production today | **Blocking** |
| **SIA-D12** | `corpus_version` / `scoring_version` / `routing_version` replace `fetch_version` / `scoring_version` | Generalizes `location-dna-architecture-review.md` §1 | Proposed |
| **SIA-D13** | Query-time KNN; no 84M-row precompute; `location_snapshots` for display only | Indexed KNN is ~ms; precompute is unjustified | Proposed |
| **SIA-D14** | Withdraw the earlier "Foursquare Premium required" recommendation | Superseded by the ratings-visibility, prominence, and authority findings | Proposed |
| **SIA-D15** | OTP2 per-metro; never a national transit graph | No national GTFS feed; Valhalla transit not production-ready | Proposed |
| **SIA-D16** | **Phase 1 (Provider Abstraction) delivered in 5 controlled batches**, Google the sole enabled provider throughout, zero behaviour change | Batch 1 (production POI fetch → `NearbyPoiFetcherInterface`), Batch 2 (registry authoritative + `CanonicalLocationMerger` wired), Batch 3 (canonical envelope persistence), Batch 4 (dual-run harness), Batch 5 (DoD8 snapshot-parity + certification closeout). Each batch: isolated branch, merge commit, byte-parity proof | **Delivered** (2026-07-15) |
| **SIA-D17** | **POI `confidence` formula extracted to `PoiConfidenceScorer`**, one formula shared by the adapter envelope and the persistence writer | Owner-approved contract: unrated found → `0.5`; rated found → `round(0.6 + 0.3·min(1, reviews/200), 3)` (0 reviews `0.6` … 200+ `0.9`); `not_found`/`error` → `null`. Single source of truth prevents adapter/persistence drift | **Binding** (Batch 3) |
| **SIA-D18** | **`provenance_json` written on `not_found` / `error` rows** (`provider`, `method=api`, `license`, `contributors`, `raw_ref=null`); `confidence` stays `null` on those rows | Owner decision: provenance is a property of the *lookup attempt*, not of a found place, so it is recorded even when nothing was found; `confidence` has no meaning without a rated place. `raw_ref = place_id` only on found rows (spec §8 `google-tos` licensing — no Place content persisted) | **Binding** (Batch 3) |
| **SIA-D19** | **Canonical category registry consumer-migration deferred to Phase 3** | `CanonicalCategoryRegistry` (D5) and its two parity-locked views (SELLER_LANDLORD / BUYER_TENANT-subset over CORPUS) are built and tested in Phase 1, but **no runtime consumer reads them yet** — migrating consumers is behaviour-visible and belongs with the Phase 3 provider cutover, not the zero-behaviour-change Phase 1 | Proposed → **Phase 3** |
| **SIA-D20** | **Dual-run harness compares rank-order + membership only, never scores** | `ranking_score` is set-relative (E-48): raw-score diffs are set-composition artifacts. The harness (Deliverable 6 / Gate 3 infrastructure) built now, on a stub/self-diff basis, without waiting for a Phase-2 provider | **Binding** (Batch 4; see E-48) |

## Appendix B — Supersession Map

| Prior decision | Source | Disposition |
|---|---|---|
| POI source = Geoapify **or** OSM Overpass | `LOCATION_DNA_PHASE_A...md` §6 | **Superseded** by SIA-D1. Overpass forbids app-backend use; Overture is storable, permissive, and richer. |
| Drive/walk time = OpenRouteService (self-hosted) | `LOCATION_DNA_PHASE_A...md` §6 | **Superseded** by SIA-D3 (Valhalla). *Self-hosting was already the sanctioned direction; only the engine changes.* |
| Geocoding = Google **or** Nominatim | `LOCATION_DNA_PHASE_A...md` §6 | **Superseded**: MLS coords → NAD → Census → Geoapify. Public Nominatim is policy-barred. |
| Allowed / Prohibited Inputs & Outputs; Fair Housing safeguards | `LOCATION_DNA_PHASE_A...md` §3,§4,§7,§8,§9 | **RETAINED, UNAMENDED, BINDING.** |
| `poi.default` base = `osm_overpass`; Google = overlay/fallback | `config/location_providers.php`; capability-map proposal | **Superseded** by SIA-D1: base = owned corpus. The registry mechanism itself is **retained**. |
| Google = "premium quality overlay" for rating/reviews | `PHASE_8_...RECOMMENDATION.md` | **Superseded** by SIA-D4. Ratings are removed, not re-sourced. |
| "The intelligence engine is already independent of any provider" | `PHASE_8_...RECOMMENDATION.md` | **Corrected.** `LocationDnaRankingEngine` consumes raw Google JSON; the production POI path bypasses the adapter seam (R5). |
| `fetch_version` requires a fresh vendor fetch | `location-dna-architecture-review.md` §1 | **Generalized** (SIA-D12): with an owned corpus, both version paths are free local recomputes. |
| Canonical envelope: `{value, source, confidence, provenance, last_refreshed, human_corroborated}` | `canonical-field-mapping-spec.md` §1 | **RETAINED.** `cache_policy` per provider retained; `google-tos` entry becomes historical. |
| Unified Location DNA engine; point/area modes; dual-run cutover | `location-dna-architecture-review.md` §3 | **ADOPTED** as SIA-D6 and Gate 3. |
| git-C14.2 address resolution deferred post-launch | `match-check-...c14.2...md` | **Honored.** Phase 4 delivers it; the "honest NOT_FOUND over confident wrong-city" posture is preserved. |
| Demographic Target Market DNA is "textbook FHA steering" | `CENSUS_INTELLIGENCE_PHASE_5_3...md` | **AFFIRMED** and elevated to SIA-D8 (binding). |
| Foursquare Premium as the minimal paid overlay | prior audit (this engagement) | **WITHDRAWN** — SIA-D14. Retained only as a Gate-1 contingency. |
| LOCKED bid comparison & match score | `LOCKED_BidComparison_System_Summary.md` | **Untouched.** Nothing here modifies bid-comparison scoring. |
| `initializeLimitedService()`; `TenantAgentAuction` trait exclusion | `CLAUDE.md` | **Untouched.** |

## Appendix C — Evidence Base

All figures were measured read-only against the production database and source tree on 2026-07-09. No code, test, configuration, database, or Git state was modified.

| Claim | Method |
|---|---|
| 11 of ~1,226 listings have Location DNA | `count()` on `property_location_dna` vs listing tables |
| 100% of 667 Bridge rows carry lat+lng | `whereNotNull('latitude')->whereNotNull('longitude')` |
| 84 POI rows/listing; 629 distinct POIs from 1,090 rows (1.73× dedup at 13 listings) | aggregate over `property_location_pois` |
| 16 Nearby calls/listing; $0.032/call | `LocationDnaPoiDistanceService::CATEGORY_GROUPS`; `LocationDnaPoiCostReporter::GOOGLE_PLACES_COST_PER_CALL_USD` |
| 56% mean rating-dependence of `ranking_score` | computed over all 21 profiles in `LocationDnaRankingProfileService` |
| Removing ratings: 46.6% top-1 change; 82% worse-rated; −0.47★ | replayed the real `LocationDnaRankingEngine` over all 1,090 persisted POIs |
| Prominence-only: 73.8% agreement, 1% embarrassment vs 19% with no signal | four-world ablation over the same 1,090 rows, 86 well-regarded baselines |
| Brand chains: 616 vs 390 mean reviews; 36.5% of commercial POIs | aggregate over commercial categories |
| 5 categories fetched but in no thematic block (~25% of POI spend) | `LocationDnaSummaryService::THEMATIC_BLOCKS` vs `CATEGORIES`; corroborated by `LOCATION_DNA_AUDIT.md` §9 |
| Consumers never see a POI rating | grep of all `resources/views/components/stellar/` and `resources/views/stellar/` |
| `postgis 3.5.3` and `vector 0.8.0` available, not installed | `pg_available_extensions` |
| `QUEUE_CONNECTION=sync`, no worker, no scheduler | `.replit` `userenv.shared`; grep for `queue:work` / `schedule:run` |
| Kill switch inert | `grep -rn "google_places\." app/` → 0 hits |
| 38,236 requests / ~$1,223 / 6 days | `docs/investigations/Google-Places-Root-Cause-Analysis.md` |

## Appendix D — Open Questions

| # | Question | Blocks | Owner |
|---|---|---|---|
| Q1 | Actual row count and on-disk size of a CONUS Overture Places extract at `confidence ≥ 0.90` (no official figure published) | Phase 1 sizing | Engineering — measure |
| Q2 | Valhalla build/serve RAM for a CONUS graph (all published figures are planet-scale) | Phase 3 sizing | Engineering — benchmark FL first |
| Q3 | Geoapify's permanent-storage permission appears in marketing copy, not the binding T&C | Phase 4 | Obtain written confirmation |
| Q4 | Do we promote *assigned school* to a product claim? If yes, ATTOM is required (SABS is 10 years stale) | Phase 6 | Product + Legal |
| Q5 | §9.4 audience-label reconciliation against Phase A §8 | **Phase 6 (Gate 5)** | **Legal counsel** |
| Q6 | Target hosting platform for worker + Redis + PostGIS + Valhalla | **Phase 0/1 (blocking)** | Product owner |
| Q7 | Per-feed GTFS licensing (no blanket commercial-use grant) | Phase 3 | Engineering + Legal |
| Q8 | Overture per-source NOTICE obligations (Apache slice) | Phase 1 | Legal review (one-time) |

---

**End of specification.** This document is the single source of truth for geographic intelligence across BidYourAgent and BidYourOffer. Amendments proceed by appending to the Decision Register (Appendix A) and the Supersession Map (Appendix B) — never by silent edit.
