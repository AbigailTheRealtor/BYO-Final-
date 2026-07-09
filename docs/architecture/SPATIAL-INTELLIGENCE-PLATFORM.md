# Spatial Intelligence Platform — Master Architecture

**Version:** 3.1 — *Google-free by design; parallelized execution*
**Status:** ✅ **APPROVED — 2026-07-09, by Abigail (product owner).** This document is the **single source of truth** for geographic intelligence across BidYourAgent and BidYourOffer. Implementation proceeds from this document only. Amendments require an entry in the Decision Register (Appendix A) or the Errata (Appendix B) — never a silent edit.
**Date:** 2026-07-09
**Owner:** Platform Architecture · Product owner: Abigail
**Approval scope:** Architecture, phases, gates, invariants, and the parallelization plan. **Implementation is underway:** Phase 0 Batch 1 landed in `3fdc4a714`, with a follow-up in `f3dab6f24`; it produced errata **E-37** and **E-38** and open question **Q11**. Phase 0 remains blocked on Q1 (hosting target) for items 3–5; items 1, 2, 6, and 7 are unblocked.
**Scope:** BidYourOffer and BidYourAgent. Every geographic, mapping, routing, Location DNA, Property DNA, Buyer/Tenant DNA, Target Market Intelligence, matching, and spatial-analysis feature.

**Supersedes and consolidates:**
- `MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md` (rationale retained; schema and phases corrected)
- `MASTER-SPATIAL-INTELLIGENCE-EXECUTION-ROADMAP.md` (execution retained; phase numbering replaced)
- `GOOGLE-MAPS-PLATFORM-MIGRATION-INVENTORY.md` — **not superseded.** A subordinate *tracking checklist*; its phase labels are re-keyed by §14.

**Retains, unamended and binding:** `LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` §3 (Allowed Inputs), §4 (Prohibited Inputs), §7 (Allowed Outputs), §8 (Prohibited Outputs), §9 (Fair Housing Safeguards). Only its §6 (Data Source Plan) is superseded.

**Untouched by this programme:** `initializeLimitedService()`, the `TenantAgentAuction` Livewire component, and `LOCKED_BidComparison` scoring. See INV-8. **`TenantAgentAuction` contains three direct Google calls that this freeze prevents us from instrumenting — a known exception to INV-11, recorded in E-38 and blocking on Q11.**

---

## Table of Contents

1. [What changed in v3.0](#1-what-changed-in-v30)
2. [Vision and goals](#2-vision-and-goals)
3. [Guiding principles](#3-guiding-principles)
4. [Google: legacy dependency, not fallback](#4-google-legacy-dependency-not-fallback)
5. [Nationwide scope — the exact extent](#5-nationwide-scope--the-exact-extent)
6. [System architecture](#6-system-architecture)
7. [The Spatial Core — PostGIS schema](#7-the-spatial-core--postgis-schema)
8. [Category taxonomy, mapping, and deduplication](#8-category-taxonomy-mapping-and-deduplication)
9. [POI ranking — authority first](#9-poi-ranking--authority-first)
10. [Flood zone strategy](#10-flood-zone-strategy)
11. [Address resolution and entry](#11-address-resolution-and-entry)
12. [Map rendering](#12-map-rendering)
13. [Routing and travel-time strategy](#13-routing-and-travel-time-strategy)
14. [Data sources](#14-data-sources)
15. [Import, refresh, and versioning](#15-import-refresh-and-versioning)
16. [Extension seams — how future capabilities attach](#16-extension-seams--how-future-capabilities-attach)
17. [Phases](#17-phases)
    - [17.1 Parallelization plan](#171-parallelization-plan)
18. [Gates and invariants](#18-gates-and-invariants)
19. [Rollback policy](#19-rollback-policy)
20. [Cost](#20-cost)
21. [Risks](#21-risks)
22. [Version 1 launch gate](#22-version-1-launch-gate)
- [Appendix A — Decision Register](#appendix-a--decision-register)
- [Appendix B — Errata](#appendix-b--errata)
- [Appendix C — Open questions](#appendix-c--open-questions)
- [Appendix D — Verification commands](#appendix-d--verification-commands)
- [Appendix E — Evidence base](#appendix-e--evidence-base)

---

## 1. What changed in v3.0

Two sets of changes: a **product decision** (Google-free by design), and the **seven blocking findings** from the independent architecture audit.

### 1.1 Product decision — Google is legacy, not a fallback

| # | Change | Consequence |
|---|---|---|
| 1 | **Google is never an approved fallback.** No credential is assumed to exist. | §4 |
| 2 | **Every remaining Google surface must degrade honestly when the credential is absent** — Phase 0, before anything else. | INV-12; §4.3 |
| 3 | **Rollback means reverting code, not re-enabling a vendor.** | §19 |
| 4 | **Gate 3's dual-run against live Google is dead.** Replaced by a diff against the 1,090 POI rows already frozen in the database, plus human adjudication. | §18, G3 |
| 5 | **The safety net becomes shadow-then-flip.** Phase 3 splits: **3a** shadow-writes and changes nothing a user sees; **3b** flips the read path and deletes code. | §17 |
| 6 | **Address entry and map rendering move into Version 1** (old Phase 8 → new Phases 4 and 5). A listing form nobody can type an address into is not launchable. | §17 |
| 7 | **The address corpus (NAD + OpenAddresses) moves into Phase 2.** | §15.2 |
| 8 | **No test may depend on a Google credential.** The suite passes with the key unset. | INV-11 |
| 9 | **`sessiontoken` is downgraded from "do this regardless" to conditional.** It optimises a Google SKU we are removing; do it only if telemetry shows non-zero Autocomplete spend. | §17 Phase 0 |
| 10 | **Version 1 grows from 13–20 to 21–31 engineer-weeks.** This is the price of the decision, stated plainly. | §20 |

### 1.2 Audit findings — seven blocking defects, corrected

| # | Defect in v2.0 | Correction |
|---|---|---|
| A1 | `places` PK `(corpus_version, gers_id)`, `gers_id NOT NULL`. **GERS IDs are an Overture concept** — CMS, NCES, FAA, USGS, GTFS, and OSM rows have none. Seven of 23 categories could not be inserted. | Surrogate `place_id`; `UNIQUE (corpus_version, source, source_ref)`; `gers_id` nullable. §7.2 |
| A2 | Four tables referenced but never defined: `place_categories`, `isochrone_cache`, `place_category_mappings`, `place_authority_links`. | DDL added. §7.2 |
| A3 | **INV-3 contradicted Phase 3.** INV-3 said keys are additive-only; Phase 3 removes `top_rated_dining` — verified as a `summary_json` key rendered on the **consumer-facing** `matchmaker-nearby.blade.php`. | INV-3 rewritten with two named, bounded exceptions. §18 |
| A4 | Bridge compute-on-read contradicted the trigger model, the Phase 3 DoD, and live code (`ImportBridgeProperties.php:161`). It also solved a problem that does not exist: **667 listings × 23 × 3 ≈ 46k rows.** | **Persist Bridge POIs at V1.** Compute-on-read documented as a Phase 9+ scaling path with a row-count trigger. §15.4 |
| A5 | Gate 1 said "sample 50 listings." **Only 13 listings have POI rows.** | Sample **listing × category pairs** (260 available; 844 rated). §9.3 |
| A6 | **The Phase 3 rollback was not a config flip.** It also deleted a controller, a route, a UI button, three view consumers, and two commands. | **Phase 3 splits into 3a / 3b.** §17, §19 |
| A7 | **V1 did not close compliance violation V1.** Google caps *caching* at 30 days, not writing. Stopping writes leaves 844 rated rows in place until teardown. | Phase 3b **nulls the values**, retains the columns. §9.4 |

Eleven further significant findings (B1–B11) are folded in and recorded as errata E-22 … E-32.

---

## 2. Vision and goals

A **Spatial Intelligence Platform the company owns outright**: a spatial database, a places corpus, a routing engine, an address corpus, and a library of authoritative public datasets, from which every location-derived fact on the platform is computed locally, automatically, for every listing, at **zero marginal vendor cost**.

Location DNA, Property DNA, Buyer/Tenant DNA, Target Market Intelligence, and matching stop being *features that call an API* and become *queries against a corpus we control.*

| # | Goal | Measure of success |
|---|---|---|
| G1 | Automatic Location DNA for **every** seller, landlord, and MLS/Bridge listing, nationwide | 100% coverage; the manual "Generate" button is deleted |
| G2 | Lifestyle and commute search — *"10 minutes to a grocery store," "5 minutes to the beach," "15 minutes to work"* | Real routed minutes, three modes, multiple destinations |
| G3 | Two-sided compatibility scoring | `dna_scores` populated on both sides; Matching V2 enabled |
| G4 | Predictable operating cost | Fixed monthly; **$0 marginal vendor cost per listing**; no metered call anywhere |
| G5 | **Zero dependence on Google Maps Platform** | §4; Appendix D returns zero on every check |
| G6 | No further provider migration, ever | The corpus is owned; providers are swappable adapters |
| G7 | Fair Housing defensibility | Every consumer-facing score derives from physical and civic facts, never from people |
| G8 | The architecture supports Property DNA, Buyer/Tenant DNA, TMI, lifestyle matching, and AI recommendations **without redesign** | §16 |

### The problem is the data model, not the provider

Today the platform **rents** geographic facts per-listing from a metered API and denormalises them into a listing × POI cross-product. Every cost, scalability, compliance, and product limitation follows mechanically from that one decision.

| Listings enriched once | Google Nearby Search | Owned corpus |
|---|---:|---|
| 1,226 (today) | $628 | flat |
| 10,000 | $5,120 | flat |
| 100,000 | $51,200 | flat |
| 1,000,000 | **$512,000** | flat |

And `ldna:refresh-all` deletes the POI cache before refetching, so **every ranking re-tune is a full-price refetch of the entire catalogue.** Under an owned corpus, re-tuning is a free CPU pass. That property — not the dollar figure — is what makes the intelligence iterable.

---

## 3. Guiding principles

| # | Principle | Statement |
|---|---|---|
| **SIP-P1** | Own the corpus | Any fact consulted more than once, for more than one listing, is stored locally and refreshed on a schedule. We never pay per-lookup for a fact that does not change per-lookup. |
| **SIP-P2** | Compute whenever possible | If a value can be derived from geometry, an owned dataset, or an existing user selection, it is computed — never asked for, never purchased. |
| **SIP-P3** | **Zero metered cost in the enrichment path** | No listing-enrichment or DNA-generation path may make an outbound **metered** call. Local services (PostGIS, Valhalla on localhost) are not outbound. Enforced by a network guard test, not by convention. |
| **SIP-P4** | Provider abstraction, corpus permanence | Providers are adapters behind a registry. The **corpus** is the durable asset; the provider is an implementation detail of how it was populated. |
| **SIP-P5** | Source-neutral canonical model | No intelligence engine reads a provider-specific field. Everything normalises into the canonical envelope before any engine touches it. |
| **SIP-P6** | One taxonomy, two entry modes | A single canonical category taxonomy, exclusion set, and ranking core — entered via **point mode** (a listing's coordinate) or **area mode** (a buyer/tenant geometry). Never two taxonomies. |
| **SIP-P7** | A searcher's *where* is DNA, not a filter | Every buyer/tenant geometry — polygon, radius, isochrone, important place — is a first-class, durable, enrichable object on the *same* vocabulary as property-side Location DNA. |
| **SIP-P8** | Authority over sentiment | Where an authoritative public registry exists (CMS, PAD-US, USGS, FEMA, NCES, FAA, GTFS/NTD), it is the base source. Crowd sentiment is never a base source. |
| **SIP-P9** | Cache-first, version-stamped | Every derived artifact carries `corpus_version`, `scoring_version`, and where routed `routing_version`. A mismatch triggers the cheapest sufficient recompute, never a refetch. |
| **SIP-P10** | **Describe the place, not the people** | Consumer-facing outputs describe physical and civic facts. No demographic input. No audience label naming a protected class. **When in doubt, the answer is no.** |
| **SIP-P11** | Graceful unknown | `null` means *unknown* — never *zero*, never *absent-therefore-good*. "Not mapped by FEMA" is never rendered as "no flood risk." An unenriched listing is never penalised. **A missing credential is a form of unknown** (SIP-P15). |
| **SIP-P12** | Async by construction | Enrichment is queued, idempotent, and never blocks a web request. *(Violated in production today — §21 R1.)* |
| **SIP-P13** | **No vendor is a rollback plan** | Reversibility comes from code we control: shadow tables, feature flags over our own implementations, and `git revert`. Never from re-enabling a third party. |
| **SIP-P14** | **Extensible by data, not by code** | Adding a POI category, a dataset, a boundary layer, or a score must be a data + config change. If it requires an engine change, the seam is wrong. |
| **SIP-P15** | **Degrade honestly on a missing credential** | Any surface still backed by a third-party credential must render a truthful unavailable state when that credential is absent. It must never crash, and it must never silently show a wrong or empty result as though it were correct. |
| **SIP-P16** | **Build in parallel, cut over in sequence** | The legal and technical ordering constraints bind the moment a replacement *becomes live*, not the work that produces it. Every replacement is developed behind a flag defaulting to the incumbent. Cutovers fire one at a time, in the mandated order, each rehearsed and independently revertible. |

---

## 4. Google: legacy dependency, not fallback

**Product-owner decision, 2026-07-09, binding (SIA-D25).** The platform is **Google-free by design**, not *Google with an emergency fallback*.

### 4.1 The distinction, stated precisely

These are different things, and conflating them is how a "temporary" vendor dependency becomes permanent.

| | **Legacy load-bearing dependency** *(permitted, temporarily)* | **Approved fallback** *(rejected)* |
|---|---|---|
| **What it is** | Google code in the production request path **today**, whose removal *right now* would break a user-facing surface | A Google path deliberately kept reachable **after** its replacement ships, to be re-enabled if the replacement disappoints |
| **Why it exists** | Because we have not yet built the replacement | Because we do not trust the replacement |
| **Lifetime** | Until its replacement ships. Dated, tracked, and phased out | Indefinite, by design |
| **Credentials** | May exist today; **must never be assumed to exist** | Retained deliberately |
| **New code** | May never call it | May call it |
| **Tests** | May never require it | Depend on it |
| **Rollback** | `git revert` the phase commit | Flip a config switch back to Google |
| **Status** | A liability being paid down | An architectural component |

### 4.2 The operational rules

1. **No Google provider is ever `enabled => true` after its replacement lands.**
2. **No config switch may exist whose purpose is reverting to Google.** Feature flags gate *our* implementations against each other, never a vendor.
3. **No test may pass because Google answered.** The suite passes with `GOOGLE_PLACES_API_KEY` unset. (INV-11.)
4. **No new code may import a Google symbol.** Enforced by the Appendix D greps at every phase gate.
5. **Rollback is `git revert` of the phase commit, rehearsed** (§19).
6. **Every Google surface still in the request path degrades honestly when the credential is absent** (SIP-P15, INV-12).
7. **Google OAuth (`GOOGLE_CLIENT_ID`) is out of scope.** It is an identity provider, not Maps Platform. Removing it is a separate product decision.

### 4.3 What "degrade honestly" means, and why it is Phase 0's first task

The Google Maps Platform credential does not only power enrichment. Measured against the working tree:

| Surface | Files | What breaks without a credential |
|---|---:|---|
| Maps JS loader + Embed iframe | **8** | Map canvases fail to initialise |
| Browser `google.maps.places.Autocomplete` | **49** | **The first field of every listing-creation flow, all four roles** |
| Livewire Autocomplete proxies | 12 | Type-ahead returns nothing |
| Server-side Geocoding | 6 | `HasMlsImport`'s save-time geocode fails **silently** |

Today these fail invisibly. A dead key produces a broken canvas, an inert input, and a `REQUEST_DENIED` body that the caller reads as "no result." **Nobody is told.** That is why the credential's true state is currently unknowable without spending money to ask.

**Phase 0 therefore guards each surface on `config('services.google.places_key')` being non-empty:**

- **Maps** render a labelled *"Map unavailable"* panel, not a broken canvas.
- **Address fields** fall back to free-text entry plus the pin-confirmation step — which Phase 4 requires regardless.
- **Geocoding** returns an honest `NOT_FOUND`, never a silent null. *(This preserves the git-C14.2 posture: an honest NOT_FOUND is always preferable to a confident wrong-city result.)*

Two consequences, both intended:

**The credential's state stops mattering.** Alive, dead, or revoked next Tuesday — nothing crashes, and no listing form is one console click from breaking.

**Telemetry answers the question for free.** The Phase 0 outbound-call metric reports, within hours, whether Google is called at all and whether those calls return `200` or `REQUEST_DENIED`. **We learn the credential's state from our own logs, not from a paid API call.**

### 4.4 Removal schedule

| Surface | Replaced by | Phase | Code deleted |
|---|---|---|---|
| Places Nearby Search (enrichment) | `CorpusPoiAdapter` | **3b** | 3b |
| Persisted `rating` / `user_ratings_total` **values** | Authority + `confidence` | **3b** (nulled) | columns dropped Phase 10 |
| Server + browser Geocoding | Owned chain (§11) | **4** | 4 |
| Places Autocomplete (12 proxies + 49 files) | Owned typeahead (§11) | **4** | 4 |
| Maps JS, render surfaces, Embed iframe | MapLibre + PMTiles (§12) | **5** | 5 |
| `config/google_places.php`, `services.google.places_key` | — | **7** | 7 |
| `rating` / `user_ratings_total` **columns** | — | **10** | 10 (destructive) |

---

## 5. Nationwide scope — the exact extent

**"Nationwide" means the United States and its territories**, defined once here and referenced by every import filter.

| Extent | Included | Notes |
|---|---|---|
| CONUS (lower 48 + DC) | ✅ | |
| Alaska | ✅ | Crosses the antimeridian — bbox filters fail here |
| Hawaii | ✅ | |
| **Puerto Rico** | ✅ | **76 live Bridge listings today.** Thinner OSM/Overture coverage; no NCES SABS; partial GTFS |
| US Virgin Islands | ✅ | Low volume; include for completeness |
| Guam, American Samoa, N. Mariana Is. | ◑ | Include if the dataset ships them; never block an import on their absence |

**This replaces every occurrence of "CONUS bbox" in the superseded documents.** Filter Overture and OSM by **country/region code (`US`, `PR`, `VI`, `GU`, `AS`, `MP`)**, never by a bounding box.

**Consequences:**
- Use `geography`, never a projected CRS. Albers CONUS (EPSG:5070) does not cover AK, HI, or PR.
- **Gate 2 must report per-category coverage for Puerto Rico and Alaska separately**, and must include a **dataset × territory matrix**. Four datasets I would bet against for PR and require explicit verification: **EPA Walkability Index, USGS Boat Ramps, NOAA CUSP, DOT NAD.**
- Valhalla's graph is built from a US extract that **includes the PR tiles**. PR is road-disconnected from CONUS; that is correct and must not be "fixed."

---

## 6. System architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ L5  CONSUMPTION                                                             │
│     Stellar consumer pages · Agent panel · Admin DNA · Ask AI · Marketing   │
│     ── all read the Read Model. None reads a provider or a raw table. ──    │
└───────────────────────────────────┬─────────────────────────────────────────┘
                                    │  LocationDnaPresenter (single read model)
┌───────────────────────────────────┴─────────────────────────────────────────┐
│ L4  INTELLIGENCE ENGINES        (pure, deterministic, no I/O, no AI)        │
│  ┌──────────────┐ ┌─────────────┐ ┌──────────────┐ ┌───────────────────┐    │
│  │ Location DNA │ │ Property DNA│ │ Target Market│ │ Matching Engine   │    │
│  │ point + area │ │ archetypes  │ │ Intelligence │ │ (two-sided, V2)   │    │
│  └──────────────┘ └─────────────┘ └──────────────┘ └───────────────────┘    │
│                   ┌───────────────────────┐                                 │
│                   │ Spatial Scoring       │  →  dna_scores (property|demand)│
│                   │ Framework             │     ← THE universal join point  │
│                   └───────────────────────┘                                 │
└───────────────────────────────────┬─────────────────────────────────────────┘
                                    │  canonical envelope {value, source,
                                    │  confidence, provenance, last_refreshed}
┌───────────────────────────────────┴─────────────────────────────────────────┐
│ L3  SPATIAL SERVICES  (thin, pure adapters — no product logic)              │
│   Proximity(KNN) · Containment(PIP) · Commute(matrix) · Isochrone · Boundary│
│   Geocode · Typeahead                                                       │
└───────┬──────────────────────────────────────────────────┬──────────────────┘
        │                                                  │
┌───────┴──────────────────────────────────┐  ┌────────────┴──────────────────┐
│ L2  SPATIAL CORE — PostgreSQL 16 +       │  │ L1b  ROUTING (owned)          │
│     PostGIS 3.5 + btree_gist + pg_trgm   │  │  Valhalla, self-hosted        │
│  places · place_categories · boundaries  │  │  matrix · isochrone · P2P     │
│  boundaries_parts · listing_locations    │  │  drive · walk · bike          │
│  addresses · isochrone_cache             │  │  (transit: NOT attempted)     │
│  place_authority_links · corpus_imports  │  │  tiles mmap'd; built offline  │
└───────┬──────────────────────────────────┘  └───────────────────────────────┘
        │ ingest (batch, versioned, staged)
┌───────┴─────────────────────────────────────────────────────────────────────┐
│ L1  DATA CORPUS (owned, refreshed, US + territories)                        │
│  Overture Places · OSM subset · FEMA NFHL (+coverage) · Census TIGER/ZCTA    │
│  NCES CCD/EDGE · USGS PAD-US · USGS Boat Ramps · CMS · FAA NASR             │
│  GTFS + NTD · EPA Walkability · NOAA CUSP · DOT NAD + OpenAddresses         │
└─────────────────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────────────────┐
│ L0  PRESENTATION  — no credential, no vendor                                │
│  Basemap: MapLibre GL + Protomaps PMTiles (self-hosted, no API key)         │
└─────────────────────────────────────────────────────────────────────────────┘

        AI LAYER (orthogonal, read-only consumer of L4 outputs)
        Receives FACTS ONLY. Never raw provider payloads. Never demographics.
```

### The single most important invariant

> **`ComputeLocationDna` makes zero outbound calls.**

Today it makes 16 Google Nearby Search calls plus up to one Geocoding call. Under this architecture it performs: a geocode *lookup* (usually a no-op — 100% of Bridge rows already carry coordinates), one PostGIS KNN per category against the local corpus, one boundary containment set, and one matrix call to **Valhalla on localhost**. That is SIP-P3, and every cost and reliability property below derives from it.

---

## 7. The Spatial Core — PostGIS schema

### 7.1 Extensions

```sql
CREATE EXTENSION postgis;      -- 3.5.3 available, not installed
CREATE EXTENSION btree_gist;   -- 1.7   available — REQUIRED, see §7.3
CREATE EXTENSION pg_trgm;      -- 1.6   available — address typeahead, Phase 4
-- Deferred, available when needed, no new infrastructure:
-- h3 4.2.3    (hex commute index, post-launch scale)
-- vector 0.8.0 (semantic retrieval, post-launch)
```

### 7.2 Core tables

```sql
-- Extensible taxonomy. Adding a POI category is an INSERT, not a code change (SIP-P14).
CREATE TABLE place_categories (
  category_key    text PRIMARY KEY,
  label           text NOT NULL,
  thematic_block  text,                 -- daily_convenience | coastal | healthcare_access | …
  base_source     text NOT NULL,        -- overture | osm | cms | nces | padus | usgs | faa | gtfs
  rank_strategy   text NOT NULL,        -- authority | distance | brand | area | ridership
  exclusion_rules jsonb,                -- ported CATEGORY_EXCLUSION_RULES
  enabled         boolean NOT NULL DEFAULT true
);

-- Source taxonomy → canonical category. Data, never code (§8.2).
CREATE TABLE place_category_mappings (
  source          text NOT NULL,        -- overture | osm
  source_category text NOT NULL,        -- Overture category slug, or an OSM tag expression
  category_key    text NOT NULL REFERENCES place_categories,
  PRIMARY KEY (source, source_category)
);

-- The owned places corpus. One row per real-world place, shared by every listing.
-- ▲ A1: surrogate PK. GERS IDs are an Overture concept; CMS, NCES, FAA, USGS,
--   GTFS, and OSM rows have none and must still be insertable.
CREATE TABLE places (
  place_id         bigserial,
  corpus_version   text NOT NULL,
  source           text NOT NULL,       -- overture | osm | cms | nces | padus | usgs | faa | gtfs
  source_ref       text NOT NULL,       -- GERS id | OSM type/id | CCN | NCES id | FAA LID | stop_id
  gers_id          text,                -- ▲ nullable: Overture rows only
  geom             geography(Geometry,4326) NOT NULL,  -- ▲ Point | Polygon | LineString
  centroid         geography(Point,4326) NOT NULL,     -- ▲ markers, and routing snap points
  category_key     text NOT NULL REFERENCES place_categories,
  name             text,
  brand            text,
  confidence       numeric(4,3),        -- Overture existence confidence
  source_count     smallint,
  authority_metric numeric,             -- CMS stars | PAD-US acres | NTD ridership | enplanements
  attrs            jsonb,
  first_seen       timestamptz,
  last_seen        timestamptz,
  PRIMARY KEY (corpus_version, place_id),
  UNIQUE (corpus_version, source, source_ref)
) PARTITION BY LIST (corpus_version);

-- ▲ A2/E-5: composite GiST. Without btree_gist this index cannot exist, and a
--   category-filtered KNN degrades to an index walk with a filter (§7.3).
CREATE INDEX places_cat_geom ON places USING gist (category_key, geom);

-- Resolved authority ↔ corpus pairings, persisted so a refresh does not re-litigate them (§8.2).
CREATE TABLE place_authority_links (
  authority_source text NOT NULL,       -- cms | nces | faa | usgs
  authority_ref    text NOT NULL,       -- CCN | NCES id | FAA LID
  place_source     text NOT NULL,       -- overture | osm
  place_source_ref text NOT NULL,
  match_method     text NOT NULL,       -- spatial_name | manual | exact
  match_score      numeric(4,3),
  reviewed_by      text,
  PRIMARY KEY (authority_source, authority_ref)
);

-- All polygonal geography: flood zones, FEMA coverage, school districts,
-- ZCTAs, counties, places, protected areas.
CREATE TABLE boundaries (
  id             bigserial PRIMARY KEY,
  kind           text NOT NULL,   -- flood_zone | flood_coverage | school_district
                                  -- | zcta | county | place | protected_area
  external_ref   text,
  attrs          jsonb,           -- FLD_ZONE, SFHA_TF, ZONE_SUBTY, district name, acres …
  geom           geography(MultiPolygon,4326) NOT NULL,
  corpus_version text NOT NULL
);
CREATE INDEX boundaries_kind_geom ON boundaries USING gist (kind, geom);

-- ▲ E-24: ST_Subdivide does not accept geography. Subdivide at IMPORT time:
--   INSERT … SELECT id, (ST_Dump(ST_Subdivide(geom::geometry, 256))).geom::geography …
CREATE TABLE boundaries_parts (
  boundary_id bigint NOT NULL REFERENCES boundaries,
  geom        geography(Polygon,4326) NOT NULL
);
CREATE INDEX boundaries_parts_geom ON boundaries_parts USING gist (geom);

-- Supply side: one point per listing. Replaces scattered decimal lat/lng columns.
CREATE TABLE listing_locations (
  listing_type   text   NOT NULL,
  listing_id     bigint NOT NULL,
  geom           geography(Point,4326) NOT NULL,
  geocode_source text,   -- saved_meta | nad | openaddresses | tiger | census
  PRIMARY KEY (listing_type, listing_id)
);
CREATE INDEX listing_locations_geom ON listing_locations USING gist (geom);

-- Owned address corpus. Serves BOTH geocoding and typeahead (§11). Phase 2 import.
CREATE TABLE addresses (
  id            bigserial PRIMARY KEY,
  source        text NOT NULL,          -- nad | openaddresses | tiger
  number        text, street text, unit text,
  city          text, state text, postcode text,
  normalized    text NOT NULL,          -- lowercased, abbreviations expanded
  geom          geography(Point,4326) NOT NULL,
  precision     text NOT NULL           -- rooftop | parcel | interpolated
);
CREATE INDEX addresses_geom     ON addresses USING gist (geom);
CREATE INDEX addresses_trgm     ON addresses USING gin  (normalized gin_trgm_ops);
CREATE INDEX addresses_city_zip ON addresses (state, city, postcode);

-- Routing artifacts. Storable because the engine is ours. Phase 6.
CREATE TABLE isochrone_cache (
  origin_geohash  text     NOT NULL,
  mode            text     NOT NULL,    -- auto | pedestrian | bicycle
  minutes         smallint NOT NULL,
  routing_version text     NOT NULL,
  geom            geography(MultiPolygon,4326) NOT NULL,
  computed_at     timestamptz NOT NULL,
  PRIMARY KEY (origin_geohash, mode, minutes, routing_version)
);
CREATE INDEX isochrone_geom ON isochrone_cache USING gist (geom);

-- Provenance / version ledger. Every import writes exactly one row.
CREATE TABLE corpus_imports (
  id bigserial PRIMARY KEY,
  dataset text, corpus_version text, row_count bigint, bytes bigint,
  territory_coverage jsonb,             -- ▲ E-32: {"US":true,"PR":true,"AK":true,…}
  started_at timestamptz, finished_at timestamptz, status text, notes jsonb
);
```

**Deliberately absent: `location_snapshots`.** `property_location_pois` **is** the display/version snapshot table. It already carries `distance_miles`, `travel_time_minutes`, `data_source`, `rank`, `confidence`, `provenance_json`, `last_refreshed`, `pois_fetch_version`, `pois_scoring_version`. Four of those have never been written. The writer changes; the shape does not.

### 7.3 The KNN pathology, and why `btree_gist` is not optional

The naive nearest-POI query is:

```sql
SELECT p.* FROM places p
WHERE p.category_key = 'boat_ramp'
ORDER BY p.geom <-> l.geom
LIMIT 1;
```

With only `gist(geom)`, PostgreSQL walks the geometry index **in distance order** and discards rows failing the category predicate. If boat ramps are 0.01% of a 25M-row table and the nearest is 40 miles away, that walk is enormous. Any benchmark quoting single-digit milliseconds here was almost certainly run **without a category predicate**.

With `gist(category_key, geom)` via `btree_gist`, the KNN operates *within* the category.

> **⚠ This is an assumption, not a proven fact.** Composite `gist(btree_col, geom)` KNN ordering is well established for `geometry`. For **`geography`** it must be demonstrated. **Phase 2's first task is a one-day spike**, gated on `EXPLAIN` showing `Index Scan … Order By`. If it fails, fall back to **23 per-category partial indexes** (`CREATE INDEX … WHERE category_key = 'boat_ramp'`), which is often faster anyway. Benchmark against the **sparse** categories — `boat_ramp`, `airport`, `marina`, `urgent_care` — never the dense ones, which will look fine either way and tell you nothing.

### 7.4 Geometry, not points

`places.geom` is `geography(Geometry,4326)`. Parks, beaches, airports, golf courses, and protected areas are **areas**; `ST_Distance` on their geometry measures to the nearest edge, which is what "0.3 miles to Seminole City Park" means to a human. A centroid measurement on a 2,000-acre park can be a mile wrong, and PAD-US **acreage** — the ranking signal for parks — cannot be computed from a point.

`centroid` is carried alongside for map markers and as Valhalla's snap point.

**"5 minutes to the beach" routes to the beach's nearest *access point*, not to a polygon edge** — a shoreline edge may be unreachable by road. Where a `beach_access` node exists, route to it; otherwise route to the nearest edge and stamp `confidence < 1.0`.

### 7.5 Canonical queries

```sql
-- Nearest places per enabled category, for one listing. Replaces 16 Google calls.
-- ▲ E-23: corpus_version is bound from PHP. No session GUC — a GUC is fragile
--   under pooled connections and queue workers.
SELECT c.category_key, p.name,
       ST_Distance(l.geom, p.geom) / 1609.344 AS miles
FROM   place_categories c
CROSS JOIN listing_locations l
CROSS JOIN LATERAL (
  SELECT p.* FROM places p
  WHERE  p.category_key = c.category_key
    AND  p.corpus_version = :corpus_version
  ORDER BY p.geom <-> l.geom          -- index-assisted by gist(category_key, geom)
  LIMIT  3                             -- top-3. The table holds top-10 today.
) p
WHERE l.listing_type = :type AND l.listing_id = :id AND c.enabled;

-- "Listings within a 30-minute drive of my office" → ONE routing call, then containment.
SELECT ll.listing_type, ll.listing_id
FROM   listing_locations ll
JOIN   isochrone_cache i
  ON   i.origin_geohash = :gh AND i.mode = 'auto' AND i.minutes = 30
WHERE  ST_Covers(i.geom, ll.geom);     -- ▲ ST_Covers, not ST_Contains.
                                       --   ST_Contains does not accept geography.
```

Three standing rules:
- Always `ST_DWithin(a, b, meters)`; **never** `ST_Distance(a,b) < x`. Only the former is index-assisted.
- Never mix `geography` and `geometry` on the same axis. A cast discards the index.
- `ST_Subdivide` runs at **import** time, on `geometry`, never at query time.

---

## 8. Category taxonomy, mapping, and deduplication

### 8.1 The canonical taxonomy — 23 categories

One taxonomy, data-driven, serving both point mode and area mode (SIP-P6). This closes the defect in which the listing pipeline defines **19** categories and the buyer/tenant adapter defines **7** — with `airport` present buyer-side and entirely absent listing-side, despite Phase A §5 approving it.

| # | Category | Base source | Rank strategy | Thematic block | Status |
|---|---|---|---|---|---|
| 1 | `grocery_store` | Overture | distance + confidence floor | daily_convenience | existing |
| 2 | `pharmacy` | Overture | distance + confidence floor | daily_convenience | existing |
| 3 | `restaurant` | Overture (+OSM) | distance + confidence floor | daily_convenience | existing |
| 4 | `coffee_shop` | Overture | distance + confidence floor | daily_convenience | existing |
| 5 | `shopping_center` | Overture | distance + brand | daily_convenience | **now wired** |
| 6 | `gym` | Overture | distance + confidence floor | daily_convenience | **now wired**; absorbs `fitness_center` |
| 7 | `school` | **NCES CCD/EDGE** | **authority** | family_infrastructure | **now wired** |
| 8 | `hospital` | **CMS + HRSA** | **CMS clinical stars** | healthcare_access | **now wired** |
| 9 | `urgent_care` | HRSA / Overture | authority | healthcare_access | **new** |
| 10 | `park` | **USGS PAD-US** | **polygon acreage** | outdoor_recreation | existing, re-sourced |
| 11 | `dog_park` | OSM | tag richness | outdoor_recreation | existing |
| 12 | `waterfront_park` | PAD-US ∩ NOAA CUSP | acreage + shoreline adjacency | outdoor_recreation | existing, re-sourced |
| 13 | `trail` | USGS / USFS | length | outdoor_recreation | **new** |
| 14 | `golf_course` | OSM | area | outdoor_recreation | existing, re-sourced |
| 15 | `beach` | **NOAA CUSP** + OSM | shoreline length | coastal | existing, re-sourced |
| 16 | `beach_access` | State coastal + OSM | authority | coastal | existing |
| 17 | `marina` | OSM (no federal registry) | tag richness | coastal | existing |
| 18 | `boat_ramp` | **USGS Boat Ramps (CC0)** | **authority** | coastal | existing, re-sourced |
| 19 | `transit_station` | **GTFS** | **NTD ridership** | transportation | existing, re-sourced |
| 20 | `gas_station` | Overture | distance + brand | transportation | existing |
| 21 | `airport` | **FAA NASR** | **class / enplanements** | transportation | **new** |
| 22 | `highway_access` | TIGER / OSM `motorway_junction` | distance | transportation | **new** |
| 23 | `downtown` | Census Places + OSM | population + area | transportation | **new** |

**Removed:** `top_rated_dining` — definitionally rating-driven, the lifestyle score service never reads it, and it holds only 15 rows. **It is a `summary_json` key rendered on the consumer-facing `matchmaker-nearby.blade.php`**, so its removal is a bounded exception to INV-3, not an additive change (§18).

**Merged:** `fitness_center` → `gym`. *Verified low blast radius:* it appears only in `LocationDnaRankingProfileService` and `LocationDnaPoiDistanceService`, **never in `LocationDnaSummaryService`** — because it is one of the five invisible categories. It changes `poi_category` values, not `summary_json` keys.

**New thematic blocks:** `family_infrastructure`, `healthcare_access`. These finally make visible the five categories fetched at Google cost today (`school`, `hospital`, `gym`, `fitness_center`, `shopping_center`) that map to **no thematic block, no lifestyle score, and no context** — roughly **25% of all POI spend, buying nothing**.

### 8.2 Two work items that appear in no prior document

**Overture → canonical category mapping.** Overture ships hundreds of categories in a hierarchical taxonomy. Mapping them onto these 23, and porting `CATEGORY_EXCLUSION_RULES` (the hard-won false-positive filters — the reason a gas station's food mart doesn't rank as a grocery store), is iterative, judgement-heavy work. **It is where POI quality actually lives**, and it is invisible in every prior estimate.

> **Budget: 1–2 engineer-weeks, Phase 2.** Store it in `place_category_mappings`, not code (SIP-P14). Expect to re-tune after Gate 2.

**Cross-source deduplication.** The prior schema implied you could join CMS onto Overture via a CCN. **You cannot** — Overture carries no CCN. Attaching a CMS hospital, an NCES school, or an FAA airport to its Overture row is fuzzy name + spatial matching with a real error rate. Getting it wrong produces a duplicate hospital 200 feet from itself, both ranked.

> **Budget: 1–2 engineer-weeks, Phase 2.** The authority record is authoritative for identity and `authority_metric`; the Overture row is authoritative for `brand` / `confidence` / `source_count`. Match on `ST_DWithin(150 m)` + normalised-name trigram similarity ≥ 0.6; human-review the ambiguous tail; persist the resolved pairing in `place_authority_links`.

---

## 9. POI ranking — authority first

### 9.1 Why the prominence prior is unnecessary

Three measured facts:

1. **No consumer ever sees a rating.** `matchmaker-nearby.blade.php` renders `{{ $label }} — {{ $poiName }} … {{ $miles }} mi`. Ratings are an internal ranking input only.
2. **For six of the 23 categories an authoritative registry already decides.** Hospitals → CMS. Schools → NCES. Parks → PAD-US acreage. Boat ramps → USGS membership. Transit → NTD ridership. Airports → FAA class.
3. **Google's ratings are sparsest precisely there.** Only **844** of 1,090 persisted POI rows carry a rating, and coverage inverts against importance:

| Category | Rows | With rating | Authority source |
|---|---:|---:|---|
| `transit_station` | 55 | 12 (**22%**) | GTFS + NTD |
| `school` | 56 | 24 (**43%**) | NCES |
| `hospital` | 55 | 33 (**60%**) | CMS |
| `restaurant` | 55 | 48 (87%) | none |
| `beach`, `marina`, `boat_ramp` | 55 ea | 50 (91%) | NOAA, OSM, USGS |

The signal a prominence prior would laboriously reconstruct is already absent where a wrong answer costs something, and abundant only where it costs nothing.

### 9.2 The ranking rule

```
Authority categories (6):
    rank by authority_metric, then distance.

Commercial categories (~6: grocery, restaurant, coffee, pharmacy, gym, gas):
    filter   confidence >= 0.90
    prefer   brand IS NOT NULL OR source_count >= 2      (tie-break only)
    order by distance

OSM-sourced categories (marina, dog_park, golf, trail):
    filter   tag completeness
    order by distance
```

That is the whole ranking engine. **Publix outranks "B S Food & Gas" because Overture knows Publix is a brand appearing in four sources — not because it has 4.6 stars.**

`ranking_score` retains its shape (a weighted sum of `category_match_score`, `distance_score`, and now `authority_score`), so `LocationDnaRankingProfileService`'s per-category weights survive, re-tuned. The **five-tier distance ladder** (`<0.5mi=100, <1=85, <2=70, <5=50, <10=30, else 10`) is untouched.

`consumer_relevance_score` and `review_confidence_score` are removed from `LocationDnaRankingEngine`. **The Foursquare Places Premium contingency is withdrawn.**

### 9.3 Gate 1 — a sanity check, not a purchase decision

**Only 13 listings have POI rows.** (Verified: `seller_agent` 7, `landlord_agent` 3, `seller` 3 — and **zero** Bridge rows.) Gate 1 therefore samples **listing × category pairs**: 13 × 20 = 260 available, of which 844 individual rows carry a rating.

Compare the corpus's nearest-3 per pair against the frozen Google output and look for **embarrassments** — a ranked "grocery store" that is a gas station; a "hospital" that is a dentist's office. Pass ≤3%. **Zero API spend; the data is already in the database.**

**If it fails, the fix is the category mapping and exclusion rules (§8.2), not a purchased ratings feed.**

### 9.4 Closing the ratings compliance violation — properly

Google's terms permit caching Place content for **no more than 30 days**. Only `place_id` may be stored indefinitely. The platform persists `rating` and `user_ratings_total` without expiry.

**Stopping the writes does not close the violation** — 844 rated rows would remain. Therefore, in **Phase 3b**:

```sql
UPDATE property_location_pois SET rating = NULL, user_ratings_total = NULL;
```

**Columns retained** (so a `git revert` of 3b finds the schema it expects); **content purged** (so the violation is closed). The admin DNA card and agent panel are updated in the same change to render `prominence` / `confidence` instead of stars. The columns are dropped in Phase 10, after the soak.

---

## 10. Flood zone strategy

**Import FEMA NFHL into PostGIS. Do not rely on the live API.** This reverses both prior documents.

1. **"Outside FEMA flood zones" is a stated buyer-side search requirement.** A filter over the entire corpus cannot be served by a per-listing HTTP call.
2. **A live API in the enrichment path is a throughput and availability dependency.** Unmetered, so SIP-P3 is not violated in the letter — but backfilling the catalogue through FEMA's ArcGIS REST endpoint is a batch liability, and an outage means listings enrich with a null flood zone.
3. **Decisive: you cannot honestly render "not mapped" without the coverage footprint.** SIP-P11 and INV-5 require that *"not mapped" never renders as "no flood risk."* A live point query returning no polygon is **ambiguous** — it means *either* "outside a hazard area" *or* "FEMA has never mapped here" *or* "the request timed out." Only the imported **effective-panel coverage footprint** distinguishes them. The live-API design structurally cannot satisfy the invariant these documents call launch-blocking.

```
boundaries.kind = 'flood_zone'      ← S_FLD_HAZ_AR (FLD_ZONE, SFHA_TF, ZONE_SUBTY)
boundaries.kind = 'flood_coverage'  ← effective NFHL panel footprint

Resolution, per listing:
   inside a flood_zone polygon           → that zone           (flood_source='corpus')
   inside coverage, no hazard polygon    → 'X — outside SFHA'  (flood_source='corpus')
   outside coverage                      → 'not mapped'        (flood_source='unmapped')
   corpus stale / mid-LOMR               → live FemaFloodZoneAdapter (flood_source='live')
```

Zone **D** means *undetermined*. Zone **A** without a base flood elevation means *mapped, no BFE*. **None of these renders green.**

> **⚠ Legal framing, and it is not optional for a real-estate platform.** FEMA data is public domain, but **a derived flood-zone display is not an official flood determination** — that requires a FIRMette or a Letter of Map Amendment. Every flood surface must carry an explicit *"Informational only. Not an official FEMA flood determination."* label. This is a launch-gate item (§22).

**Size: 10–40 GB — the largest single import.** Subdivide at import (§7.2). Refresh quarterly. This is the main reason Phase 2 is 5–7 weeks, not 3–4.

---

## 11. Address resolution and entry

> **Moved into Version 1 (Phase 4).** Under a Google-free architecture, address entry is not a presentation nicety — it is the first field of every listing-creation flow, across all four roles, on **49 files and 12 Livewire proxies**.

### 11.1 Geocoding chain

```
MLS coordinates  (100% of 667 bridge_properties rows)   → geocode_source='saved_meta'
      ↓ miss
DOT National Address Database        (rooftop)          → 'nad'
      ↓ miss
OpenAddresses                        (rooftop/parcel)   → 'openaddresses'
      ↓ miss
Census TIGER range interpolation     (interpolated)     → 'tiger'
      ↓ miss
Census Geocoder  (free federal REST, L0 only)           → 'census'
      ↓ miss
HONEST NOT_FOUND                                        → null
```

**Near-vestigial in practice:** 100% of Bridge rows already carry coordinates, and 10 of 11 `property_location_dna` rows used `saved_meta`. Only one ever used Google.

**Accuracy regression is expected and accepted.** Census interpolation is address-range, not rooftop: published median error ~45 m urban, **~147 m rural**. Mitigated by the **pin-confirmation UX**, which must be live before this ships. **Assert no silent ZIP-centroid fallback** — historically a kilometre-scale error mode. **Preserve the git-C14.2 posture: an honest NOT_FOUND is always preferable to a confident wrong-city result.**

### 11.2 Typeahead — owned, in the database we already run

City, county, and ZIP suggestions are **already DB-backed** (`us_cities` 25,830 rows, `us_counties` 3,067, `us_states` 56). Only **street-address** typeahead is a genuine gap.

Serve it from the `addresses` table with `pg_trgm` (§7.2). **No new service, no vendor, no credential.**

**Consolidate all 49 files onto the shared `byo-address-autocomplete` component *before* swapping the provider.** This turns 49 reverts into one flag and is **the single highest-leverage de-risking step in the programme.**

> **This is the recommendation I hold with the least confidence.** Google's fuzzy free-text tolerance is genuinely excellent. Build **structured** typeahead first (street prefix scoped by the already-DB-backed city/ZIP selection), pair it with pin-confirmation, and **A/B against the current implementation before committing** (Gate 4). If first-field completion rate drops, buy Photon or Geoapify and do not be precious about it — *a paid non-Google geocoder is not a Google fallback, and does not violate SIA-D25.*

### 11.3 Also closed here

`map-input.blade.php:860,874` calls `nominatim.openstreetmap.org` **directly from end-user browsers.** OSMF policy forbids autocomplete use and requires an identifying User-Agent a browser cannot set. The platform's own git-C14.2 note states: *"Do NOT use the public nominatim.openstreetmap.org service in production."* **Phase 4 removes it.**

---

## 12. Map rendering

> **Moved into Version 1 (Phase 5).** With no assumed credential, a Google basemap is not a thing we can rely on rendering.

**MapLibre GL (BSD-3) + Protomaps PMTiles**, self-hosted on object storage behind a CDN. **No API key. No per-load fee.** Cloudflare R2 charges zero egress.

| Surface | LOC | Notes |
|---|---:|---|
| `partials/location-dna/map-input.blade.php` | **1,586** | The largest file in the programme, larger than the POI swap. Custom click-drawing, 3 `Autocomplete` instances, a Data-layer polygon loader. **Its boundary data already comes from Nominatim + Census TIGERweb, not Google** — the data layer is half-migrated. Embedded in **13 forms.** |
| `components/location-dna-map.blade.php` | 800 | Read-only. 3 `Map` instantiations. |
| `stellar/buyer/results.blade.php` | 349 | **Consumer-facing** marker map. *Missed by two earlier audits, which asserted no such map existed.* Treat regression risk as High. |
| `components/stellar/property-map.blade.php` | 61 | The only Embed-API iframe, and the only surface exposing the key in an `iframe src`. |
| `add_listing.blade.php:1644` | — | A vestigial `myMap()` centred on London. **Delete. Do not port.** |

Also delete two orphaned Blade backups with zero references (`hire_tenant_agent/add.blade09012024.php`, `buyer_criteria/add-bid.blade1.php`), and drop the `libraries=drawing` loader parameter — `DrawingManager` is never instantiated; all four occurrences are comments saying so.

**Ordering (unchanged, and still binding):** maps swap **after** Google data is gone (Phase 3b removes Places; Phase 4 removes Geocoding and Autocomplete). Google's "No Use With Non-Google Maps" clause forbids Google Maps *Content* alongside a *non-Google map*. With zero Google data in the system, **both renderer states are lawful** — so the swap is a flag, not a one-way door. Attribution: *"© OpenStreetMap contributors"* on every rendered map (ODbL, Produced Work).

---

## 13. Routing and travel-time strategy

### 13.1 Engine choice — settled by licensing, not price

**May we store the computed result?** decides the engine before cost is discussed.

| Provider | May we store routes / isochrones? |
|---|---|
| Google Routes | ❌ ≤30 days |
| HERE | ❌ 30-day cap + "no location-asset repository" |
| GraphHopper (commercial) | ❌ temporary client-side only |
| Stadia, TravelTime | ❌ restricted |
| Mapbox, Geoapify | ◑ unconfirmed |
| **Self-hosted** | ✅ **Unrestricted — the data is ours** |

**Valhalla** is the only open engine with point-to-point, matrix, isochrone, and drive/walk/bike in one process. OSRM has no native isochrone. GraphHopper OSS paywalls the matrix out of its core.

**Transit: not attempted.** There is no national US GTFS graph — hundreds of agency feeds, feed-ID collisions, divergent calendars. Valhalla's GTFS support is experimental with known unconnected-region failures. Ship drive/walk/bike; degrade "transit" to **transit-stop proximity + NTD ridership** and **label it honestly as such.**

### 13.2 Deployment

- **Build once, offline, on a temporary large instance** (spot/preemptible). Build RAM for a US graph is the binding constraint and is **unmeasured** (Q3). Benchmark one state, extrapolate, build US + PR once.
- **Ship tiles to object storage; serve from a small VM.** Valhalla `mmap`s its tiles, so serve-RAM is a fraction of build-RAM. 8–16 GB is the working assumption.
- Expose `/route`, `/sources_to_targets`, `/isochrone` on localhost. **A Valhalla call is not an outbound call** and does not violate SIP-P3.

### 13.3 What ships in V1, and what waits

The seam already exists and is already correct. `CommuteTimeAdapterInterface` takes `(originLat, originLng, destinations[], travelModes[])` and returns `travel_time_minutes` per destination × mode — **precisely a Valhalla `sources_to_targets` matrix call.** `CommuteTimeLookupService` already caches and enforces `max_destinations` (10). `property_location_pois.travel_time_minutes` already exists as a `smallint` with **zero rows written.** Replacing `CommuteTimeStubAdapter` is **a class and a config flip.**

The *buyer-side* work is the expensive part, and it separates cleanly.

| | **V1 (Phase 6)** | **Post-launch (Phase 8)** |
|---|:--:|:--:|
| Valhalla deployed nationwide | ✅ | |
| `ValhallaCommuteAdapter` replaces the stub | ✅ | |
| `travel_time_minutes` populated | ✅ | |
| **"10 minutes to a grocery store"** (listing-side) | ✅ | |
| **"5 minutes to the beach"** (listing-side) | ✅ | |
| Drive / walk / bike | ✅ | |
| **"15 minutes to work"** — isochrone → `ST_Covers`, **additive** narrower behind a flag | ✅ | |
| `location_preference_geometries` as a durable artifact | | ✅ |
| Replacing PHP Haversine / ray-cast PIP **wholesale** | | ✅ |
| Multiple destinations × all modes in the matcher | | ✅ |
| Transit routing | | ✗ never, as designed |

The V1 isochrone search is **additive** — a narrower running *alongside* the existing Haversine path behind `MATCHING_ISOCHRONE_ENABLED`. It does not replace working code, so it carries no parity risk. The wholesale replacement, where PostGIS's geodesic answer will legitimately disagree with PHP's planar one and every difference must be explained, waits for Phase 8.

**Abort path.** If the US graph will not fit a sane instance, Phase 6 ships `LOCATION_DNA_COMMUTE_PROVIDER=stub` and V1 launches with **miles instead of minutes** — degraded, honest, identical to today. **Nothing else in V1 depends on Valhalla.**

**Rollback nuance:** a buyer who saved a "minutes" preference while the flag was on must, on rollback, see a **labelled "unavailable"** — never a silent radius. `ImportantPlacesService` already enforces exactly this: *"a plain radius would be a fake travel-time circle, which the audit forbids."* **A fake minutes figure is worse than an honest mile figure**, and no phase trades one for the other.

### 13.4 Query patterns

| Question | Mechanism | Cost |
|---|---|---|
| "10 minutes to a grocery store" | 5 nearest by KNN → **one** Valhalla matrix call → `min()` | one local call |
| "5 minutes to the beach" | nearest `beach_access` node, else nearest polygon edge → matrix | one local call |
| "15 minutes to my office" | **one** isochrone → **one** indexed `ST_Covers` over the whole corpus | one local call, then µs |
| Radius (miles) | `ST_DWithin(listing.geom, center, meters)` | µs |
| Polygon | `ST_Covers` against pre-subdivided parts | µs |
| City / ZIP / county | boundary join | µs |
| Per-listing commute on a results page | matrix over the ~25 visible listings | one local call |

All-pairs precompute is rejected outright (quadratic). At scale, `h3 4.2.3` — **available today, uninstalled** — provides the hex-grid hybrid with no new infrastructure.

---

## 14. Data sources

### 14.1 Free — public domain or permissive (the entire baseline)

| Domain | Source | Licence | Access | Cadence |
|---|---|---|---|---|
| Places | **Overture Places** | CDLA-Permissive-2.0 (+Apache/CC0) | GeoParquet on S3 via DuckDB | Monthly |
| Places (supplement) | OpenStreetMap | ODbL | Bulk PBF extract | Quarterly |
| **Flood** | **FEMA NFHL + coverage footprint** | Public domain | Bulk gdb (**imported**, §10) | Quarterly |
| Boundaries | Census TIGER/Line + ZCTA | Public domain | Bulk | Annual |
| Schools | NCES CCD / EDGE | Public domain | Bulk | Annual |
| Parks | USGS PAD-US 4.1 | Public domain | Bulk gdb | Periodic |
| Boat ramps | USGS Boat Ramp Locations | **CC0** | Bulk points | Static (2023) |
| Trails | USGS Nat'l Digital Trails / USFS | Public domain | Bulk + WFS | Periodic |
| Hospital quality | **CMS Hospital Overall Star Rating** | Public domain | DKAN REST / CSV | Annual |
| Health centres | HRSA | Public domain | Bulk + API | Periodic |
| Walkability | EPA National Walkability Index | Public domain | Bulk | Periodic |
| Transit stops | GTFS via Transitland | **Per-feed** | REST + bulk | Weekly |
| Transit quality | National Transit Database | Public domain | Socrata | Annual |
| Airports | FAA NASR | Public domain | Bulk (28-day AIRAC) | 28-day |
| Shoreline | NOAA CUSP | Public | Bulk shapefile | Periodic |
| **Addresses** | **DOT NAD + OpenAddresses** | Public domain | Bulk | 2–3×/yr |
| Geocoding fallback | Census Geocoder | Public domain | REST (batch 10k) | Continuous |
| Routing graph | OSM via Geofabrik | ODbL | PBF → Valhalla tiles | Quarterly |
| Basemap tiles | Protomaps PMTiles | ODbL (Produced Work) | Built or downloaded | Periodic |

### 14.2 Paid services

**None.**

> If Gate 4 shows an unacceptable regression in address-entry completion rate, a **paid non-Google geocoder** (Photon self-hosted, or Geoapify) may be adopted. **That is not a Google fallback and does not violate SIA-D25.** It would be recorded as a new Decision Register entry with its cost.

### 14.3 Licensing constraints that bind future work

- **ODbL (OSM, PMTiles, Valhalla graph).** Internal derivative databases trigger nothing. Displaying distances and rendering maps is a **Produced Work** — attribution only. **But exposing OSM-derived POI names and coordinates through a public API would distribute a Derivative Database and trigger share-alike.** Every row carries `source`, so a future decision to distribute can exclude ODbL-derived rows without a re-architecture. **This is a constraint on future API productization, recorded now.**
- **GTFS has no blanket commercial-use grant** — it is per-feed. Filter to `commercial_use_allowed` via Transitland. Transit coverage will be patchy **by design** and must be labelled so.
- **Overture's Apache-licensed slice carries NOTICE obligations** (Q6, one-time legal review).
- **FEMA** — §10's disclaimer requirement.

### 14.4 Excluded, permanently

Google Places / Geocoding / Maps JS · public Nominatim, Overpass, OSM tiles · Yelp · Falchi World Atlas (CC BY-NC) · EPA EJScreen (bundles race layers) · Zillow ZHVI/ZORI · **ACS demographics as a scoring input** (Phase A §4; SIA-D8) · FBI crime data (recognised race proxy; display-only if ever, **never scored**).

---

## 15. Import, refresh, and versioning

### 15.1 Partition-swap

`places` is `PARTITION BY LIST (corpus_version)`. Load month *N+1* into a new partition, validate it, then flip one config value. No `ACCESS EXCLUSIVE`, no rename dance, and **rollback is flipping back** — the old partition is still there. Drop *N−1* after a soak.

`corpus_version` is **resolved in PHP and bound as a query parameter** — never read from a session GUC, which is fragile under pooled connections and queue workers.

`CLUSTER` and `ANALYZE` run on the **staging partition before the flip**, never on the live table.

Once volumes justify it, consume the Overture **GERS changelog** for deltas rather than reloading monthly. Not a V1 concern.

### 15.2 Volumes — estimates that must be measured

| Dataset | Estimate | Confidence |
|---|---|---|
| Overture Places, US + territories, `confidence ≥ 0.90` | 15–30M rows, 10–20 GB with indexes | **Low (Q2)** |
| **FEMA NFHL + coverage** | **10–40 GB** | Low (Q4) |
| **Addresses (NAD + OpenAddresses)** — *moved into Phase 2* | 60–100M rows, 20–40 GB incl. the GIN trigram index | Low |
| Census TIGER + ZCTA | 2–5 GB | Medium |
| PAD-US 4.1 | 3–8 GB | Medium |
| NCES, CMS, FAA, USGS ramps, NTD, EPA WI | <1 GB combined | High |
| GTFS (filtered) | <1 GB | High |
| OSM subset | 1–3 GB after tag filter | Medium |
| **Steady state** | **80–150 GB** | Today it is **119 MB.** |
| **Peak, during a corpus refresh** | **+10–20 GB** (two live `places` partitions plus a soak window) | Size the instance for peak, not steady state. |

### 15.3 Trigger model

| Event | Action |
|---|---|
| Listing created; address or coordinates changed | Enqueue `ComputeLocationDna` (dirty-checked) |
| **Bridge import: new record, or address/coord change** | **Enqueue** — already gated correctly by `BridgePropertyNormalizer::upsert()` |
| `scoring_version` mismatch | Recompute from local data. **No fetch.** |
| `corpus_version` mismatch | Re-query the corpus. **No outbound call.** |
| `routing_version` mismatch | Re-run Valhalla locally |
| Buyer/tenant geometry saved | Enqueue `ComputeLocationPreferenceDna` *(Phase 8)* |

### 15.4 Bridge listings: persist at V1

**Persist Bridge POIs exactly like platform listings.** `ImportBridgeProperties.php:161` already dispatches `ComputeLocationDna::dispatch('bridge', …)`, and `LocationDnaPipelineRunner:178` already branches on it. **667 listings × 23 categories × 3 ranks ≈ 46,000 rows.** That is nothing.

> **Compute-on-read with a Redis geohash cache is a Phase 9+ scaling path**, triggered when `property_location_pois` for `listing_type='bridge'` exceeds ~5M rows. Designing it now would be premature, would contradict the trigger model, and would make "100% coverage" unverifiable.

---

## 16. Extension seams — how future capabilities attach

**SIP-P14: extensible by data, not by code.**

| Future capability | Attaches at | Engine change? |
|---|---|---|
| **Add a POI category** (EV charging, library) | `place_categories` + `place_category_mappings` rows + an importer | **No** |
| **Add a dataset** (NREL AFDC, wildfire, storm surge) | `boundaries.kind` or `places.source` + importer + ledger row | **No** |
| **Add a lifestyle score** | `dna_scores.score_key` + a pure scorer reading L3 | **No** |
| **Property DNA `location_score`** | `LocationLifestyleBridgeGenerator` consumes the Read Model | **No** — one of four columns hardcoded `null` today |
| **Buyer DNA / Tenant DNA** | `dna_scores.side = 'demand'` — **already carries 429 rows** | **No** |
| **Target Market Intelligence** | `PropertyIntelligenceProfileService` + FHFA HPI, Census BPS, BLS QCEW, HUD FMR | **No** |
| **Lifestyle matching** | Two-sided `dna_scores` join on `score_key` | **No** |
| **Commute compatibility** | `dna_scores.score_key = 'commute_convenience'`, both sides | **No** |
| **AI recommendations / Ask AI** | Reads `LocationDnaPresenter` — **facts in, prose out** | **No** |
| **Semantic retrieval / knowledge graph** | `vector 0.8.0`, available, uninstalled | **No new infrastructure** |
| **Drive-time search at scale** | `h3 4.2.3`, available, uninstalled | **No new infrastructure** |
| **Spatially-derived property attributes** (elevation, noise, dark sky) | `places` / `boundaries` + `PropertyDnaGenerator` | **No** |

**The four seams that make this true, and must not be compromised:**

1. **`dna_scores` is the universal join point.** It exists, it is two-sided, and it holds **1,001 property-side and 429 demand-side rows today.** Every future intelligence feature lands here. **Nothing else needs inventing.**
2. **`LocationDnaPresenter` is the only public read model.** Six consumers bypass it today (`SellerOfferListing:1972`, `SellerOfferListingEdit:1638`, both offer-listing controllers, `Admin/DnaProfileController:45`, `Admin/DnaInspectorController:427`). We *freeze the table shape* so they keep working — but **no new consumer may bypass it.**
3. **The canonical envelope** `{value, source, confidence, provenance, last_refreshed, human_corroborated}` — already six columns on `property_location_pois`. Four have never been written.
4. **The AI layer receives facts, never payloads.** No raw provider JSON, no ratings, **no demographics.** `AskAiComplianceGuardrailService` and its refusal rules are retained unchanged.

**Three constraints that protect the future, non-negotiable:**

- **SIA-D8 — no demographics in any score, rank, audience derivation, or match.** Phase A §4 lists Census demographic statistics by tract or ZIP as a Prohibited Input. The Census 5.3 analysis independently rates a demographic "Target Market DNA" as *"textbook FHA steering & disparate-impact."* Precedent: *US v. Meta* (2022), *NFHA v. Redfin* (2022, $4M), *Louis v. SafeRent* (2024, $2.275M). Any future demographic use is display-only, guardrailed, separately approved, and out of scope.
- **SIP-P11 — `null` means unknown.** Every future score inherits `data_completeness` and degrades honestly rather than imputing zero.
- **SIA-D9 — audience labels describe the property, never the person.** "Family Convenience Score" → **"Park & School Proximity Score."** "Retirement Score" → **"Single-Level Living & Healthcare Access Score."** **Requires legal review (Gate 7)**, and blocks Phase 9, not V1.

---

## 17. Phases

**Eleven phases, 0–10.** V1 is Phases 0–7.

| # | Phase | Weeks | Reversible | V1 |
|---|---|---:|---|:--:|
| **0** | Infrastructure, Safety & Graceful Degradation | 1–2 | Yes | ✅ |
| **1** | Provider Abstraction | 2–3 | Yes (pure refactor) | ✅ |
| **2** | Spatial Foundation (incl. address corpus) | **5–7** | Yes (additive only) | ✅ |
| **3a** | Location DNA — shadow compute | 1 | Yes (nothing user-visible) | ✅ |
| **3b** | Location DNA — cutover & retirement | 1–2 | `git revert` | ✅ |
| **4** | Address Resolution & Entry | **3–5** | Flag + `git revert` | ✅ |
| **5** | Map Rendering | **4–5** | Flag + `git revert` | ✅ |
| **6** | Routing & Travel Time | 2–3 | Flag → stub | ✅ |
| **7** | Certification, V1 Launch & Google Code Deletion | 2–3 | N/A | ✅ |
| | **Version 1 subtotal** | **21–31** | | |
| **8** | Preference DNA & Full Commute Matching | 3–4 | Yes | post |
| **9** | Intelligence Expansion (Property DNA, TMI, scores, Matching V2) | 4–6 | Yes (flags) | post |
| **10** | Schema Teardown (the only destructive migration) | 1 | **No — backup required** | post |
| | **Total** | **29–42** | | |

### Old → new phase mapping

| v2.0 | Inventory doc | **v3.0** |
|---|---|---|
| 0 | 0 | **0** |
| 1 | 1 | **1** |
| 2 | 2 | **2** (+ address corpus) |
| 3 | 3 | **3a / 3b** |
| 8a / 8b | 5a / 5b | **4** ← *moved into V1* |
| 8c | 5c | **5** ← *moved into V1* |
| 4 | — | **6** |
| 5 | 6a | **7** |
| 6 | — | **8** |
| 7 | — | **9** |
| 9 | 6b | **10** |

---

## 17.1 Parallelization plan

**SIP-P16 — build in parallel, cut over in sequence.**

The dependencies between Phases 4, 5, and 6 constrain the **cutover**, not the **build**. Google's "No Use With Non-Google Maps" clause forbids *rendering* MapLibre while Google data is still consulted. It says nothing about *writing* MapLibre. Every replacement is therefore developed behind a flag defaulting to the incumbent, and only the flip waits its turn.

**The legal ordering constrains three config flips, not five months of engineering** (SIA-D33).

Serial execution is **21–31 engineer-weeks**. With the tracks below run concurrently, calendar time is roughly **14–20 weeks**. Same scope, same gates, same invariants, same cutover order.

> **Parallelism converts calendar time into headcount; it does not create time.** This plan assumes **2–3 engineers working concurrently**. With one engineer none of it reduces calendar time — it only changes the order of work, and in that case Track C (Valhalla) should still be pulled early, because its dominant risk is unmeasured and its dominant cost is waiting rather than typing.

### 17.1.1 Work tracks

| Track | Contents | Depends on | Can start |
|---|---|---|---|
| **A — Intelligence** *(critical path)* | Phase 1 abstraction → Phase 2 corpus, category mapping, dedup, FEMA → Gates 1–2 → Phase 3a shadow → Gate 3 → Phase 3b cutover | Phase 0 | Immediately after Phase 0 |
| **B — Address** | `addresses` import (NAD + OpenAddresses + TIGER) · **49-file consolidation** · `pg_trgm` typeahead · geocode chain (Phase 4) | Phase 0 + the `addresses` DDL | **Week ~3.** Touches no POI table, no corpus, no ranking engine. **Fully independent of Track A.** |
| **C — Routing** | Valhalla one-state benchmark · US + PR graph build · serve VM · `ValhallaCommuteAdapter` · `IsochroneEngine` (Phase 6) | Phase 0 + `listing_locations` | **Week ~3.** The graph build is `OSM PBF → tiles`. **It needs nothing from the corpus.** |
| **D — Maps** | PMTiles archive · `location-dna-map` · `stellar/buyer/results` · `property-map` iframe · `map-input.blade.php` rewrite (Phase 5) | PMTiles archive **+ Track B's consolidation** | **Week ~5** (see Guard 1) |
| **E — Importers** | FEMA · TIGER/ZCTA · PAD-US · NCES · CMS · FAA · GTFS+NTD · EPA WI · NOAA CUSP · USGS ramps · OSM subset | Phase 2 schema | Eleven mutually independent importers. Highly parallel. |
| **F — Certification harness** | Load rigs, perf dashboards, per-role matrix, monitoring | Phase 0 telemetry | Week ~6. The *harness* parallelizes; the *run* (Phase 7) does not. |

**Track C is the most under-recognised.** Pulling the Valhalla graph build to week 3 costs nothing and removes the single largest unmeasured risk (Q3, build RAM) from the launch critical path. **If a US + PR graph will not fit a sane instance, you learn it in month one rather than month five**, while the `stub` abort still costs nothing.

### 17.1.2 The four guards

These keep parallel risk flat. Each is mandatory.

**Guard 1 — Consolidation before maps.** `map-input.blade.php` is **1,586 LOC and contains three `Autocomplete` instances and one `Geocoder` inside the map.** Track B (address) and Track D (maps) would otherwise edit the largest file in the programme concurrently.

> **Track B's first deliverable is consolidating all 49 files onto the shared `byo-address-autocomplete` component.** Once it lands, `map-input.blade.php` contains no autocomplete, and Track D may rewrite it freely. The consolidation is not merely the highest-leverage de-risking step (§11.2) — **it is the precondition for parallelism.**

**Guard 2 — Build in parallel, cut over in sequence.** The mandated cutover order is unchanged and non-negotiable: **3b → 4 → 5.** Each flip is a flag, each is rehearsed, each is independently `git revert`-able. What changes is that all three are *ready* when the first fires. Phase 6's routing cutover is independent of that chain and may land at any point after Gate 6. *(SIA-D10 and §12 are satisfied: no non-Google basemap renders until Google data is gone.)*

**Guard 3 — Do not build the address trigram index during Gate 1/2 benchmarking.** A GIN trigram index over 60–100M address rows is heavy sustained IO on **the same Postgres instance** where Track A is measuring KNN p95 against the sparse categories. It would silently corrupt the measurement Gate 2 depends on. Schedule the index build outside that window and record the timestamps in `corpus_imports`.

**Guard 4 — One owner for the shared files.** `app/Providers/AppServiceProvider.php` (POI, commute, and address adapter bindings), `config/location_dna.php`, and `phpunit.xml` are touched by Tracks A, B, and C. Individually trivial conflicts; collectively a constant tax. Nominate an owner, or serialise the edits.

### 17.1.3 What must not be parallelized

| Item | Why |
|---|---|
| **Overture category mapping → cross-source dedup** | Dedup consumes the mapping's output; both are judgement-heavy and feed back through Gate 2. Brooks's law applies — adding people slows it. Together 2–4 wk, and **the true floor on Phase 2 regardless of headcount.** Even with all eleven importers finishing in a week, Phase 2 cannot compress below ~4 weeks. |
| **3a → Gate 3 → 3b** | Phase 3a exists to be re-run and diffed patiently. Under SIA-D25 it is the **only** safety net before cutover, because there is no Google to fall back to. Compressing it defeats its purpose. |
| **The Phase 7 certification run** | By definition it follows every cutover. Its harness (Track F) parallelizes; the run does not. |
| **The three cutover flips** | Guard 2. |

### 17.1.4 Critical path

```
wk   0    2    4    6    8   10   12   14   16   18   20
     ├────┤
  0  Infra · safety · graceful degradation   ← Q1 blocks items 3–5
     └────┬─────────────────────────────────────────────────────────
          │
  A       ├──1 Abstraction──┤
          │                 ├──2 Corpus · mapping · dedup · FEMA────┤
          │                                        (Gate 1, Gate 2)─┤
          │                                                         ├─3a─┤
          │                                                    (Gate 3) ├─3b─┤
  B       ├──addresses import──┤                                              │
          │      ├──49-file consolidation──┤ ← unlocks Track D               │
          │                    ├──pg_trgm typeahead · geocode chain──┤        │
          │                                                                   ├─cut 4─┤
  C       ├──Valhalla 1-state benchmark──┤                                            │
          │        ├──US+PR graph build (batch)──┤                                    │
          │                     ├──adapter · travel_time · isochrone──┤ (Gate 6)      │
  D                      ├──PMTiles──┤                                                │
                              ├──3 small surfaces──┤                                  │
                                   ├──map-input rewrite (long pole)──┤                │
                                                                                      ├─cut 5─┤
  E       ├──11 importers, mutually independent──┤                                            │
  F                   ├──certification harness──┤                                             │
                                                                                              ├──7 Cert──┤
```

**Critical path:** Phase 0 → 1 → 2 *(mapping/dedup floor)* → 3a → Gate 3 → 3b → flip 4 → flip 5 → 7.

### 17.1.5 What parallelization does not fix

- **Q1, the hosting target, still gates Phase 0, and Phase 0 gates all six tracks.** Parallelism buys nothing until that decision is made. *(Phase 0 items 1, 2, 6, and 7 need no hosting decision and remain the safest available work.)*
- **Gate 3 is still a baseline diff over 13 listings**, not a dual-run. Running tracks concurrently does not strengthen it (R4).
- **The Phase 2 mapping/dedup floor (~4 wk) is irreducible.**
- **The three unmeasured quantities (Q2, Q3, Q4) remain unmeasured** until someone measures them. Track C exists partly to retire Q3 early.

---

### Phase 0 — Infrastructure, Safety & Graceful Degradation · 1–2 wk · LOW

> Nothing here touches Location DNA. It exists because the platform cannot currently execute a background job, and because **four production surfaces fail invisibly if the Google credential is absent.**

**Deliverables**

1. **Graceful degradation on every Google surface (SIP-P15, INV-12) — do this first.** Guard on `config('services.google.places_key')` being non-empty.
   - Maps → a labelled *"Map unavailable"* panel, not a broken canvas.
   - Address fields → free-text entry + pin confirmation.
   - Geocoding → an honest `NOT_FOUND`, never a silent null.

   **This makes the credential's state irrelevant to correctness.** Nothing crashes whether the key is alive, dead, or revoked tomorrow.

2. **Outbound-call telemetry.** Structured log + metric per Google request: endpoint, listing, count, **HTTP status.** Within hours this reports whether Google is called at all, and whether calls return `200` or `REQUEST_DENIED`. **This answers the credential question from our own logs. No paid probe. Ever.**

3. Real application server (not `php artisan serve`). Redis. `QUEUE_CONNECTION=redis`, `CACHE_DRIVER=redis`. Supervised `queue:work` on a dedicated `location-dna` queue.

4. Supervised `schedule:run`. **`offers:expire-pending` is defined in `app/Console/Kernel.php` and has never executed.** Dry-run it against a production snapshot and review the output **before** the cron goes live.

5. `CREATE EXTENSION postgis; CREATE EXTENSION btree_gist; CREATE EXTENSION pg_trgm;`

6. **Test isolation.** The tree is *partly* ahead of the prior documents:
   - `phpunit.xml:33-36` already blanks the key — **but without `force="true"`**, so it cannot override a real system env var. *That is the exact incident mechanism.* Add `force="true"` and a guard test.
   - Its comment claims *"tests/TestCase.php enforces this."* **It does not.** No `PoiLookupAdapterInterface` stub binding, no `Http::preventStrayRequests()`. Add both.
   - `LocationDnaPoiDistanceService:449`, `GooglePlacesPoiAdapter:60`, `LocationDnaGeocodeService:198` fall back to a bare `new Client()`, which `Http::fake()` cannot intercept. Remove the fallback; resolve from the container.
   - **INV-11: the full suite passes with `GOOGLE_PLACES_API_KEY` unset.**

7. **Wire `GOOGLE_PLACES_ENABLED` as a hard guard**, defaulting `false`. `grep -rn "google_places\." app/` returns **0** today: the circuit breaker written in response to a $1,223 incident does not exist in code. *(The daily/hourly caps are near-moot under a Google-free architecture; wire the switch, skip the meters.)*

8. Dirty-check on the 10 DNA observers. **"Generating…" UI states** wherever a Livewire component reads DNA immediately after save — under a real queue it will correctly see `pending`, and that is user-visible.

9. **Conditional, decided by telemetry from item 2:** add `sessiontoken` to the 12 Livewire Autocomplete proxies. *(Google bills Autocomplete per session at $0 on Essentials; the code passes no token and pays the per-request SKU.)* **This optimises a vendor SKU we are removing in Phase 4.** Do it only if the metric shows non-zero, successful Autocomplete spend. Otherwise skip — it is not free work.

10. **Not in this phase:** API-key segregation. Under SIA-D25 we do not invest in Google credential hygiene; we remove the credential. If telemetry shows the key is live and billing, revisit as a one-week stopgap.

**Dependencies:** the hosting decision (Q1) for items 3, 4, 5. **Items 1, 2, 6, 7 need nothing and should start immediately.**
**Risks:** `sync → redis` changes job timing — audit every `dispatch()` call site. Enabling the scheduler runs `offers:expire-pending` for the first time ever — **mandatory dry-run.**
**Rollback:** revert environment config. All code changes are additive guards, inert when disabled.
**Done when:** every Google surface degrades honestly with the key unset · telemetry emitting with HTTP status · worker consumes the queue · **suite green with `GOOGLE_PLACES_API_KEY` unset and zero outbound attempts** · `SELECT postgis_version()` succeeds · INV-1, INV-8, INV-11, INV-12 hold.

---

### Phase 1 — Provider Abstraction · 2–3 wk · MEDIUM

> Make the application indifferent to who supplies geographic data. **Ship zero change to persisted output.**

**Deliverables**

1. **Correct a load-bearing false claim.** `PHASE_8_...RECOMMENDATION.md` asserts the intelligence engine is "already fully decoupled." It is not. `LocationDnaPoiDistanceService` (the production path) calls Google **inline via raw Guzzle**, bypassing the adapter seam entirely — the seam is wired only to the secondary buyer/tenant path. And `LocationDnaRankingEngine::rankCandidates()` consumes **raw Google JSON** (`$place['geometry']['location']['lat']`, `$place['types']`).
2. Extract a **`PoiCandidate` value object** as the engine's input contract. The engine stops knowing what a `geometry.location` is.
3. Route the production path through `PoiLookupAdapterInterface`. Ranking, exclusion, grouping, and persistence logic untouched.
4. Wire `LocationProviderRegistry` and `CanonicalLocationMerger` — both built, tested, and currently inert.
5. Unify the taxonomy into one `CanonicalCategoryRegistry`. Buyer/tenant remains a **7-category subset view.** *The four never-built categories (`airport`, `urgent_care`, `highway_access`, `downtown`) are registered `enabled => false`* — their base sources do not exist until Phase 2.
6. **Baseline-diff harness** (not a dual-run harness). It compares a candidate adapter's output against the **1,090 POI rows frozen in the database.** *(A live dual-run against Google is impossible under SIA-D25 and is not attempted.)*
7. Begin **writing** `confidence` / `provenance_json` / `last_refreshed` to `property_location_pois` — columns present since `2026_07_05_000001`, never written.

**Note on "zero behaviour change."** Google Places has been disabled since 2026-07-06 and the credential may be dead, so the network path is already inert. Parity is therefore proven against **persisted data**, not against live Google:

> **Golden-master test:** run the current engine over all 1,090 persisted POI rows; capture `ranking_score`, `rank`, `ranking_reasons_json`; assert **byte-identical** after the refactor. This is pure computation. It requires no network and no credential.

**Dependencies:** Phase 0 (test isolation).
**Risks:** refactoring `LocationDnaPoiDistanceService` — **1,584 LOC, launch-critical.**
**Rollback:** pure code revert. No schema change, no config-semantics change.
**Done when:** `grep -rn "NEARBY_API_URL" app/` returns **only** `GooglePlacesPoiAdapter` · `LocationDnaRankingEngine` contains no `geometry`/`types`/`rating`/`user_ratings_total` literals · golden-master parity 1,090/1,090 · one canonical registry, two views · baseline-diff harness exercised by a test.

---

### Phase 2 — Spatial Foundation · 5–7 wk · MEDIUM

> **Additive only. Nothing reads the new tables yet.**

**Deliverables**

1. **Day one: the `btree_gist` + geography KNN spike** (§7.3). Gate on `EXPLAIN` showing `Index Scan … Order By`. Fall back to 23 partial indexes if it fails. **Everything downstream rests on this.**
2. PostGIS schema per §7, `places` LIST-partitioned by `corpus_version`.
3. Backfill `listing_locations` from `property_location_dna.geocoded_lat/lng` and `bridge_properties.latitude/longitude` (**100% populated**). No geocoding required.
4. **Overture Places, US + territories** (`US`, `PR`, `VI`, `GU`, `AS`, `MP` — **not a CONUS bbox**), `confidence ≥ 0.90`, DuckDB → staging partition → validate → flip.
5. **FEMA NFHL import + coverage footprint** (§10). The largest single import.
6. **Address corpus: DOT NAD + OpenAddresses + TIGER interpolation** → `addresses`, with the `pg_trgm` GIN index. *(Moved here from the old Phase 8; Phase 4 consumes it.)* **Owned and executed by Track B, not by Track A** (§17.1). **The GIN index build must not overlap Gate 1/2 benchmarking** — Guard 3.
7. Boundaries: Census TIGER + ZCTA, PAD-US 4.1.
8. Authority overlays: CMS, NCES CCD/EDGE, USGS Boat Ramps, FAA NASR, GTFS + NTD, EPA Walkability, NOAA CUSP, USGS/USFS trails.
9. OSM subset: marinas, dog parks, golf courses, beaches.
10. **Overture → canonical category mapping** (§8.2). 1–2 wk.
11. **Cross-source deduplication** → `place_authority_links` (§8.2). 1–2 wk.
12. Protomaps PMTiles archive built and hosted. **Not rendered yet.**
13. **Gate 1** (rank sanity) and **Gate 2** (corpus coverage + **dataset × territory matrix**).

**Dependencies:** **Phase 1** — the canonical category registry defines *what to import.* Phase 0 (PostGIS, infra). Object storage.
**Files:** new migrations · new `app/Services/Spatial/*` · new `app/Console/Commands/CorpusImport*.php` · **no existing Location DNA file is modified.**
**Rollback:** drop the new tables. **Zero user impact** — no existing code path reads any of this.
**Done when:** the KNN spike passed or the fallback adopted · `EXPLAIN ANALYZE` confirms index usage **on the sparse categories** · `listing_locations` backfilled 100% · row counts, bytes, and **territory coverage** recorded in `corpus_imports` · import idempotency proven · KNN agrees with brute-force Haversine on a 1,000-point sample · **Gates 1 and 2 passed** · zero change to existing behaviour.

---

### Phase 3a — Location DNA, shadow compute · 1 wk · LOW

> **The safety net that replaces the Google fallback. Nothing a user sees changes.**

`CorpusPoiAdapter implements PoiLookupAdapterInterface` — LATERAL KNN against `places`, authority-aware ranking (§9.2), returning the canonical 9-key shape. It writes to a **shadow table**, `property_location_pois_shadow`, identical in shape.

Run it across every listing. **Nothing reads the shadow table.** The production path is untouched.

**Gate 3 — corpus baseline diff.** Diff shadow output against the **1,090 frozen Google rows** across the 13 enriched listings: rank-1 selection per category, distance deltas, exclusion-rule hits. **Then human adjudication**: for a sample of newly enriched listings, a person checks the nearest grocery store, school, and hospital **against ground truth on a map** — not against Google.

> **This is weaker than the dual-run it replaces**, and that is the honest cost of having no credential. It covers 13 listings, not the catalogue. It is compensated by the fact that 3a is *free to run repeatedly* and *changes nothing*: iterate on category mappings and exclusion rules until the diff is explainable, then proceed.

**Rollback:** drop the shadow table. **Nothing to revert.**
**Done when:** Gate 3 signed off by the product owner, with every diff explained.

---

### Phase 3b — Location DNA, cutover & retirement · 1–2 wk · MEDIUM-HIGH

> **The launch-critical milestone.** Location DNA goes from 0.9% of listings to 100%, marginal cost goes to zero, and Google leaves the intelligence foundation.

**Deliverables**

1. Flip `capabilities['poi.default']` → `corpus`. Delete the Google POI fetch path.
2. Activate all 23 categories, including the four approved in Phase A §5 and never built.
3. Wire the five previously-invisible categories into `family_infrastructure` and `healthcare_access`.
4. **Remove `top_rated_dining`** — the key **and its three view consumers** in the same change (a bounded INV-3 exception). **Merge `fitness_center` → `gym`.**
5. **`UPDATE property_location_pois SET rating = NULL, user_ratings_total = NULL`** — columns retained, content purged (§9.4). Update the admin card and agent panel to render `prominence` / `confidence`.
6. Re-source the beach narrative gate from PAD-US acreage + NOAA shoreline adjacency.
7. Retire `LocationDnaPoiTileCache`, `LdnaBenchmarkTilePrecision`, `LdnaPoiCostReport`, `GooglePlacesPoiAdapter`.
8. **Persist top-3 per category** (`N` configurable, **≥3**). The table holds **top-10** today — a 70% reduction. The agent panel displays top-3, and six consumers read the table directly ordered by `rank`.
9. **Bridge listings persist** like every other listing type (§15.4).
10. **Delete `AgentLocationDnaController::generate()`, its route, and the agent-panel button** — it exists only because the API was metered. Backfill via `ldna:refresh-all`, which now costs **$0** and makes no network call.

**Must not break** (they bypass the Read Model): `SellerOfferListing:1972` · `SellerOfferListingEdit:1638` · `LandlordOfferListing*` · `SellerOfferListingController:125` · `LandlordOfferListingController:176` · `Admin/DnaProfileController:45` · `Admin/DnaInspectorController:427`.

**Rollback:** **`git revert` of the 3b commit, redeploy.** *Not a config flip* — 3b deletes a controller, a route, a UI button, three view consumers, and two commands. **The 3a shadow table remains available for re-diffing.** Rehearse the revert in staging before merging.
**Done when:** `ComputeLocationDna` makes **zero outbound calls**, metric-verified · Location DNA on **100%** of seller, landlord, and Bridge listings · marginal cost **$0** · `top_rated_dining` gone with its consumers · `rating` values nulled, columns intact · top-3 preserved for the agent panel · revert rehearsed.

---

### Phase 4 — Address Resolution & Entry · 3–5 wk · HIGH · *moved into V1*

**Deliverables.** The geocoding chain (§11.1) replacing `LocationDnaGeocodeService`'s Google branch and retiring `GeocodeSelleryLandlordListings`. **Consolidate all 49 files onto the shared `byo-address-autocomplete` component first**, then swap the provider once. Owned `pg_trgm` typeahead over `addresses`. Remove the browser-side public-Nominatim calls (§11.3). `HasMlsImport` inherits the chain automatically — it calls `LocationDnaGeocodeService`, not Google directly.

**Dependencies:** Phase 2 (the `addresses` corpus). Pin-confirmation UX live before this ships.
**Gate 4 — address parity:** fallback-chain coverage (MLS hit / NAD hit / OpenAddresses hit / TIGER hit / Census hit / total miss); **A/B on first-field completion rate**; NOT_FOUND posture preserved; **no silent ZIP-centroid fallback.**
**Rollback:** `ADDRESS_PROVIDER` flag on the shared component + `git revert`. *After consolidation, one flag — not 49 reverts.*
**Done when:** zero Google geocode or autocomplete calls · browser Nominatim gone · Gate 4 passed · 49 files on one component.

---

### Phase 5 — Map Rendering · 4–5 wk · HIGH · *moved into V1*

**Deliverables.** MapLibre GL + Protomaps PMTiles across the four render surfaces and the Embed iframe (§12). Delete the vestigial London map, the two orphaned Blade backups, and the `libraries=drawing` parameter.

**Dependencies:** **Phase 3b and Phase 4 complete** — no Google Places or Geocoding data may remain in use before a non-Google basemap renders (§12).
**Gate 5 — map parity:** browser QA across **13 forms embedding `map-input.blade.php` × 4 roles**; polygon draw/edit/delete; radius circle; city/county/ZIP overlay; `location_dna_preferences` JSON round-trips byte-identically; saved geometries render on reload; consumer-facing `stellar/buyer/results` marker count matches result count; empty-results state; mobile viewport.
**Rollback:** `MAP_PROVIDER` flag + `git revert`. The Google implementation stays in-tree until Phase 7, **inert and unreachable — legacy, not fallback.**
**Done when:** `grep -rn "google\.maps\." resources/` → **zero** · no `maps.googleapis.com` request in any page load · OSM attribution rendered.

---

### Phase 6 — Routing & Travel Time · 2–3 wk · HIGH

Per §13.3. **Dependencies, split — this phase is *not* blocked on Phase 5:**
- **Graph build, serve VM, `ValhallaCommuteAdapter`, `travel_time_minutes`, listing-side minutes, `IsochroneEngine`, and the isochrone→`ST_Covers` *query*** depend only on **Phase 0** (infra) and **Phase 2** (`places`, `listing_locations` — backfilled in Phase 2's first week). **Track C may run from week ~3.**
- **Only the buyer-side isochrone *map overlay*** depends on **Phase 5**, because it renders on MapLibre.

Gate 6 may therefore be passed **before** the Phase 5 cutover. Routing has no Google surface, so it is outside the 3b → 4 → 5 cutover chain entirely (§17.1, Guard 2).

**Gate 6 — routing parity** against ground truth on a sample corridor set, **including one Puerto Rico corridor.**
**Rollback:** `commute_time.provider => 'stub'`; `MATCHING_ISOCHRONE_ENABLED=false`; a saved "minutes" preference degrades to a **labelled unavailable**, never a silent radius.
**Done when:** Gate 6 passed **or Valhalla formally aborted to `stub`** · `travel_time_minutes` populated · minutes render for drive/walk/bike · isochrone search <1 s at corpus scale · INV-5 holds.

---

### Phase 7 — Certification, V1 Launch & Google Code Deletion · 2–3 wk

> **No new features. Prove the system.** V1 ships automatic Location DNA to 100% of listings, up from 0.9% — a **~110× increase in enrichment volume**, against a brand-new spatial substrate, on infrastructure that has never run a background worker. Certification is not ceremony.

**Performance.** KNN p95 <50 ms per category **on the sparse categories**; 23 categories ⇒ ~1.15 s per listing enrichment, on a queue — that is the number to hold. Isochrone → containment p95 <1 s. `EXPLAIN ANALYZE` on every hot query.
**Load.** Full-catalogue backfill; wall clock and p95 job duration recorded. Concurrent isochrone searches. A corpus refresh under production-like load.
**Regression.** All Location DNA tests — **re-baseline the count; "43" is unverified (`find` returns 29–47 depending on the pattern)** — plus the per-role matrix across `seller`, `landlord`, `seller_agent`, `landlord_agent`, `bridge`, `buyer`, `tenant`.
**Monitoring.** Outbound-call count **must be 0**. Queue depth, job failure rate, corpus freshness, Valhalla health, `data_completeness` distribution. Alert on corpus staleness > 45 days.
**Rollback rehearsal.** `git revert` of 3b and of 5, executed in staging, timed, documented.

**Google code deletion.** By this point every Google surface has a replacement in production. Delete `config/google_places.php`, `services.google.places_key`, `components/google-maps-script.blade.php`, and every remaining reference. **Appendix D returns zero on checks 1–5.** *(`GOOGLE_CLIENT_ID` / OAuth remains — not Maps Platform.)*

**The destructive migration does not run here.** See Phase 10.

---

### Phases 8–10 — post-launch

**Phase 8 — Preference DNA & Full Commute Matching** (3–4 wk). `location_preference_geometries` as a first-class durable artifact (SIP-P7), closing the gap `bid-your-offer-v2.1-architecture-review.md` names: *"Buyer/Tenant Location Preference DNA is not a defined artifact… This is a real gap."* Wire `important_places_json` into matching — it stores exactly the target model and has **zero rows** today, so **no data-migration burden.** Replace PHP Haversine bounding boxes and ray-cast PIP with `ST_DWithin` / `ST_Covers`, with a dual-run set-equality campaign: PostGIS is geodesic and *more correct*; every divergence is investigated, never assumed. New `dna_scores` keys: `commute_convenience`, `location_compatibility`.

**Phase 9 — Intelligence Expansion** (4–6 wk). Populate Property DNA's `location_score`. Spatially-derived attributes: elevation (USGS 3DEP), coastal proximity, transportation-noise exposure (BTS), dark-sky (VIIRS). **`condition_score` and `legal_score` stay `null` — no source exists; do not fabricate.** TMI from FHFA HPI, Census BPS, BLS QCEW, HUD FMR — **housing-stock and labour-market facts only, zero demographics.** Tier-2/3 scores including `climate_resilience` (FEMA + NOAA + USGS + USFS — **the highest-differentiation score available; no major portal ships it; entirely free data**). Wire `LocationDnaMarketingContextService`, built and tested and deliberately never injected. Enable `DNA_SCORES_GENERATION_ENABLED` → observe 7 days → `MATCHING_V2_ENABLED` → persistence, in that order. **Gate 7 (legal sign-off on audience labels) is blocking.**

**Phase 10 — Schema Teardown** (1 wk). **The only destructive migration in the programme.** Drop `property_location_pois.rating`, `.user_ratings_total`, and `pois_fetch_version`, after confirming zero readers, after the 60-day soak, and **after a verified backup.** Deferred this late because a `git revert` of Phase 3b would restore code expecting those columns.

---

## 18. Gates and invariants

### Gates

| Gate | Blocks | Test |
|---|---|---|
| **1 — Rank sanity** | Phase 3a | 260 listing × category pairs against the **844** rated rows. Pass ≤3% embarrassment. **Zero API spend.** Failure ⇒ fix category mapping, not buy ratings. |
| **2 — Corpus coverage** | Phase 3a | Per-category coverage **plus a dataset × territory matrix**, reported separately for FL, PR, AK, and rural CONUS. |
| **3 — Corpus baseline diff** | Phase 3b | Shadow output vs the 1,090 frozen Google rows, **plus human adjudication against ground truth.** Product owner signs off every diff. *(Replaces the impossible live dual-run.)* |
| **4 — Address parity** | Phase 5 | Fallback-chain coverage; **A/B on first-field completion rate**; NOT_FOUND posture; no ZIP-centroid fallback. |
| **5 — Map parity** | Phase 7 | 13 forms × 4 roles; geometry round-trips byte-identically; consumer marker map verified. |
| **6 — Routing parity** | Phase 7 | Valhalla vs ground truth, incl. one PR corridor. **Or a formal abort to `stub`.** |
| **7 — Legal sign-off** | **Phase 9** | Written counsel review of audience labels (SIA-D9) and prohibited scores. **Does not block V1.** |

### Invariants — a phase is not done if any is violated

| # | Invariant | Verified by |
|---|---|---|
| **INV-1** | No enrichment path makes an outbound call | Network guard test; `outbound_google_requests_total == 0` |
| **INV-2** | All four roles + `bridge` behave identically | Per-role matrix (5 listing types) |
| **INV-3** | `summary_json` / `lifestyle_json` / `property_location_pois` shapes change **additively only** — with exactly **two documented exceptions in Phase 3b** (`top_rated_dining` removal, `fitness_center` merge), each removing all consumers in the same change, each covered by a snapshot test | Contract + snapshot tests |
| **INV-4** | Every phase is revertible: by flag, by shadow table, or by `git revert` — **never by re-enabling a vendor** | Documented rollback rehearsal |
| **INV-5** | `null` means unknown, never zero; FEMA "not mapped" never renders as "no flood risk"; a missing commute is `null`, never `0` | Lifestyle score tests; FEMA presenter test |
| **INV-6** | `SeniorCommunityComplianceGate` runs unconditionally, **never behind `hard_filters_enabled`** | Existing test |
| **INV-7** | **No demographics feed any score, rank, audience, or match** | Code review + input allowlist |
| **INV-8** | Frozen code untouched: `initializeLimitedService()`, `TenantAgentAuction`, `LOCKED_BidComparison` | Diff review |
| **INV-9** | Every derived artifact carries `corpus_version` / `scoring_version` / `routing_version` | Version-stamp tests |
| **INV-10** | **Adding a POI category, dataset, or score requires no engine change** (SIP-P14) | Add a throwaway category in a test; assert no `app/Services/LocationDna/*` diff |
| **INV-11** | **No test depends on a Google credential.** The suite passes with `GOOGLE_PLACES_API_KEY` unset. ⚠️ **Enforced in tests via `tests/bootstrap.php` (E-37). Production telemetry coverage is incomplete until Q11 closes — see E-38.** | Guard test; CI runs with the key absent |
| **INV-12** | **Every surface still backed by a third-party credential degrades honestly when it is absent** (SIP-P15) | Render tests with the key unset |

### What keeps operating throughout

Location DNA is generated for **11 of ~1,226 listings** and Google Places has been disabled since 2026-07-06 — the migration begins from a position where the feature is already dark. **There is no working consumer experience to protect until Phase 3b restores one, at 100% coverage.**

Maps and address entry are **swapped, never absent**, and — from Phase 0 onward — degrade to a labelled unavailable state rather than a broken one if the credential vanishes mid-migration.

---

## 19. Rollback policy

**SIP-P13: no vendor is a rollback plan.** Reversibility comes from code we control.

| Phase | Rollback mechanism |
|---|---|
| 0 | Revert environment config. All code changes are additive guards, inert when disabled. |
| 1 | Pure code revert. No schema change, no config-semantics change. |
| 2 | Drop the new tables. Zero user impact — nothing reads them. |
| **3a** | **Drop the shadow table. Nothing to revert.** |
| **3b** | **`git revert` the 3b commit, redeploy.** The 3a shadow table remains for re-diffing. Rehearsed in staging before merge. |
| 4 | `ADDRESS_PROVIDER` flag on the shared component, then `git revert`. |
| 5 | `MAP_PROVIDER` flag, then `git revert`. |
| 6 | `commute_time.provider => 'stub'`; `MATCHING_ISOCHRONE_ENABLED=false`. Saved "minutes" preferences degrade to a **labelled unavailable**, never a silent radius. |
| 7 | Certification adds no functionality; rollback = defer cutover. The Google **code deletion** in this phase is `git revert`-able until Phase 10 drops the columns. |
| 8 | Flag back to the Haversine path. |
| 9 | `MATCHING_V2_ENABLED=false` (already the default). |
| 10 | **Restore from backup.** The only irreversible step. |

**Explicitly not a rollback mechanism:** re-enabling `google_places`, re-issuing a credential, or flipping `poi.default` back to a Google adapter. **No such path is built, and no config switch exists whose purpose is reverting to Google.**

**The honest trade.** A Google rollback would have let us un-ship the corpus after launch. We give that up, and in exchange we get: a shadow table we can diff for free and re-run indefinitely (3a), a rehearsed `git revert` (3b), a vendor-independent architecture, and a compliance violation actually closed rather than paused. **The safety net is a shadow table and git, not a third party.**

---

## 20. Cost

**Estimates from public list prices. Corpus volumes are unmeasured (Q2, Q3, Q4) and must be benchmarked before an instance class is committed.**

### Ongoing, monthly

| Line | Estimate |
|---|---:|
| Managed Postgres 16 + PostGIS, 150–300 GB, 4–8 vCPU | $70–200 |
| Application server + queue worker | $30–60 |
| Redis (cache + queue) | $10–20 |
| Valhalla serve VM (8–16 GB, mmap tiles, ~100 GB disk) | $40–90 |
| Object storage + CDN — PMTiles, corpus artifacts (Cloudflare R2, **zero egress**) | $2–5 |
| **Total** | **~$150–375/mo, flat** |

### One-time

Valhalla graph builds and Overture ingests run on spot instances: realistically **under $100 in compute across the entire programme.** The real one-time cost is **29–42 engineer-weeks**, of which **21–31** deliver Version 1.

**The Google-free decision costs ~8–11 engineer-weeks** — Phases 4 and 5 moving from post-launch into V1. That is the price, stated plainly, and it buys: no credential to manage, no vendor to trust, no clause to comply with, and no surface that breaks when someone revokes a key.

### Marginal cost per listing: **$0 in vendor spend**

> **Precisely:** $0 **vendor** cost. Enrichment consumes CPU, database IO, and cache — real but small, and it grows with the catalogue rather than with a price list. At a million listings that becomes a capacity line item, not a bill from someone else. **That distinction is the entire point**, and it is what makes `climate_resilience`, `walkability_v2`, and every future score free to iterate on rather than a $51,000 catalogue refetch.

### The prerequisite nobody can engineer around

**None of this runs on the current deployment target.** `.replit` sets `QUEUE_CONNECTION=sync`, `CACHE_DRIVER=file`, `deploymentTarget=vm`, and serves production via `php artisan serve` — the PHP built-in development server. There is no `queue:work` and no `schedule:run` anywhere in the deployment configuration. `ComputeLocationDna::dispatch()` therefore executes **inline, inside the user's web request**, and with `public int $tries = 3` a failing save retries inline up to **48 blocking calls in one request.** That is root cause #1 of the 2026-07-05 incident, and it is baked into the environment, not the code.

**The hosting decision (Q1) is the only true blocker in the programme.**

---

## 21. Risks

| # | Risk | Sev | Mitigation |
|---|---|---|---|
| **R1** | **The production environment cannot execute this architecture — or the current one.** SIP-P12 is violated in production today. | **Critical** | Phase 0 hard dependency. Independently required for any asynchronous DNA generation to work at all. |
| **R2** | **The Google credential's state is unknown, and four production surfaces fail invisibly without it.** | **Critical** | **Phase 0 item 1 makes the answer irrelevant to correctness; item 2 reveals it from telemetry.** No paid probe. |
| **R3** | **Address autocomplete quality regression.** Google's fuzzy free-text tolerance is genuinely excellent, on the first field of every listing-creation flow. | **High** | The single largest UX risk, and it is now **inside V1.** Structured typeahead + pin confirm; **Gate 4 A/B**; a paid *non-Google* geocoder is an acceptable answer. |
| **R4** | **No Google fallback ⇒ Gate 3 is a static baseline diff over 13 listings, not a dual-run over the catalogue.** | **High** | Phase 3a is free to re-run indefinitely and changes nothing. Human adjudication against ground truth. Rehearsed `git revert`. **Accepted cost of SIA-D25.** |
| **R5** | **`btree_gist` + geography KNN is an untested assumption**, and §7.3 rests on it. | **High** | One-day spike, Phase 2 day one, gated on `EXPLAIN`. Fallback (23 partial indexes) already specified. |
| **R6** | **Category mapping and cross-source dedup are underestimated** and are where quality lives. | **High** | Sized explicitly (2–4 wk). Gates 1 and 2 detect failure before any consumer-facing change. |
| **R7** | Valhalla US graph build RAM is extrapolated from planet-scale figures. | Medium | Benchmark one state; build once offline on spot. **Clean abort: ship `stub`, launch with miles.** |
| **R8** | Rural, territorial, and Alaskan sparsity compounds across Overture, OSM, NAD, and small-agency GTFS — precisely where Location DNA is most differentiating. | Medium | Gate 2's dataset × territory matrix. `data_completeness` degrades honestly rather than fabricating. |
| **R9** | Overture is monthly; brand-new small businesses lag a live index. | Medium | **The one honest UX regression in the migration.** Immaterial for schools, hospitals, parks, transit, flood zones, and boat ramps — which is most of what a buyer asks about. |
| **R10** | **NCES SABS attendance zones are frozen at 2015-16 (~10 years stale).** A confidently wrong assigned school is worse than none. | **High** | Surface **district boundaries** (TIGER, current) only. Attendance-zone claims advisory-labelled, or ATTOM licensed (Q8). |
| **R11** | FEMA "not mapped," Zone D, and legacy paper FIRMs rendering as "no flood risk"; **or a derived zone read as an official determination.** | **High** | §10's coverage import makes the distinction representable. INV-5 enforces it. **The "not an official FEMA determination" label is a launch-gate item.** |
| **R12** | GTFS has no blanket commercial-use grant. | Medium | Filter to `commercial_use_allowed`. Transit coverage is patchy **by design** and must be labelled so. |
| **R13** | ODbL share-alike if OSM-derived rows are ever distributed via a public API. | Low | Internal use triggers nothing. Per-row `source` tagging makes future exclusion trivial. **Recorded as a constraint on API productization.** |
| **R14** | Corpus refresh fails silently. | Medium | `corpus_imports` ledger + staleness alert (>45 days). A stale corpus degrades gracefully and **never blocks a request.** |
| **R15** | The destructive migration runs before Phase 10. | **High** | It would drop columns a `git revert` of 3b expects. Gate on zero readers, Phase 7 complete, and a verified backup. |

---

## 22. Version 1 launch gate

**V1 = Phases 0 → 1 → 2 → 3a → 3b → 4 → 5 → 6 → 7.**

- [ ] **Infrastructure:** queue worker, scheduler, Redis, PostGIS + btree_gist + pg_trgm, real app server. `offers:expire-pending` dry-run reviewed, then verified live.
- [ ] **Graceful degradation (INV-12):** every credential-backed surface renders a truthful unavailable state with the key unset.
- [ ] **Test isolation (INV-11):** the full suite passes with `GOOGLE_PLACES_API_KEY` unset; **zero** outbound attempts.
- [ ] **Gate 1** — rank sanity ≤3% embarrassment over 260 listing × category pairs.
- [ ] **Gate 2** — corpus coverage accepted per category, with a **dataset × territory matrix** covering FL, PR, AK, rural CONUS.
- [ ] **Gate 3** — baseline diff reviewed, every difference explained, human adjudication complete, product owner signed off.
- [ ] **Gate 4** — address parity; first-field completion rate not materially degraded.
- [ ] **Gate 5** — map parity across 13 forms × 4 roles.
- [ ] **Gate 6** — routing parity, **or** Valhalla formally aborted to `stub` and V1 ships with miles.
- [ ] **INV-1** — `ComputeLocationDna` makes zero outbound calls, metric-verified in production, sustained 7 days.
- [ ] Location DNA present for **100%** of seller, landlord, and MLS/Bridge listings, **nationwide including Puerto Rico**.
- [ ] Marginal vendor cost per listing = **$0**.
- [ ] **`rating` / `user_ratings_total` values nulled** — the 30-day caching violation actually closed, not merely paused.
- [ ] **SABS staleness handled** — assigned-school claims advisory-labelled or absent; TIGER district boundaries may be shown.
- [ ] **FEMA "not mapped" never renders as "no flood risk."** Zone D = undetermined. **Every flood surface carries "Informational only. Not an official FEMA flood determination."**
- [ ] `data_completeness` degrades honestly; **no listing is penalised for missing data.**
- [ ] **`git revert` of Phase 3b and Phase 5 rehearsed in staging, timed, documented.**
- [ ] Per-role matrix green across `seller`, `landlord`, `seller_agent`, `landlord_agent`, `bridge`.
- [ ] **Appendix D checks 1–5 return zero.** `GOOGLE_PLACES_API_KEY` deleted; `config/google_places.php` removed.
- [ ] INV-1 … INV-12 hold in production.
- [ ] **The destructive migration has NOT been executed.** It is Phase 10 only.

**Not required for V1:** Gate 7 (legal sign-off on audience labels), Phase 8 preference-DNA matching, Phase 9 intelligence expansion.

---

## Appendix A — Decision Register

**Approved 2026-07-09 by Abigail (product owner).** Amendments proceed by **appending** — never by silent edit.

| ID | Decision | Status |
|---|---|---|
| **SIA-D1** | Own a local Places corpus (Overture + OSM supplement) | **Approved** |
| **SIA-D2** | PostGIS as the spatial substrate | **Approved** |
| **SIA-D3** | Self-hosted **Valhalla** for routing, isochrones, matrix | **Approved** |
| **SIA-D4** | ~~Six-term prominence prior~~ → **authority-first ranking + confidence floor + distance** | **Approved** (amended) |
| **SIA-D5** | Delete `top_rated_dining`; merge `fitness_center` into `gym` | **Approved** |
| **SIA-D6** | One canonical taxonomy; point mode + area mode | **Approved** |
| **SIA-D7** | Location Preference DNA as a first-class artifact (Phase 8) | **Approved** |
| **SIA-D8** | **No demographics in any score, rank, audience, or match** | **Binding** |
| **SIA-D9** | Reframe audience labels away from protected classes | **Approved** — implementation gated on legal review (Gate 7) |
| **SIA-D10** | Maps swap **after** Google data is gone; data before pixels | **Binding** |
| **SIA-D11** | Real queue worker + Redis + managed PostGIS are a Phase 0 hard dependency | **Blocking** |
| **SIA-D12** | `corpus_version` / `scoring_version` / `routing_version` | **Approved** |
| **SIA-D13** | Query-time KNN; `property_location_pois` is the display snapshot | **Approved** |
| **SIA-D14** | Foursquare Premium withdrawn entirely | **Approved** |
| **SIA-D15** | Never a national transit graph; degrade transit to stop proximity + ridership | **Approved** |
| **SIA-D16** | **Scope is US + territories (incl. Puerto Rico), never CONUS** | **Approved** |
| **SIA-D17** | **`places.geom` is `geography(Geometry,4326)`; `btree_gist` composite index mandatory (spike-gated)** | **Approved** |
| **SIA-D18** | **FEMA NFHL is imported, with its coverage footprint, and labelled "not an official determination"** | **Approved** |
| ~~**SIA-D19**~~ | ~~Google retained as a disabled fallback for ≥60 days~~ | **SUPERSEDED by SIA-D25** |
| **SIA-D20** | **Valhalla ships in V1, split: listing-side minutes + additive isochrone now; wholesale matcher replacement post-launch** | **Approved** |
| **SIA-D21** | **No paid geographic services at V1.** A paid *non-Google* geocoder remains an acceptable Gate-4 answer | **Approved** |
| **SIA-D22** | ~~Bridge Location DNA computed on read~~ → **persisted at V1**; compute-on-read is a Phase 9+ scaling path | **Approved** (amended per A4) |
| **SIA-D23** | **`location_snapshots` is not built.** `property_location_pois` is the display snapshot | **Approved** |
| **SIA-D24** | **Extensibility is an invariant (INV-10), not an aspiration** | **Approved** |
| **SIA-D25** | **Google-free by design. Google is a legacy dependency to be removed, never an approved fallback. No credential is assumed to exist.** | **Binding — product owner, 2026-07-09** |
| **SIA-D26** | **No vendor is a rollback plan (SIP-P13).** Rollback is shadow table + flag + `git revert` | **Binding** |
| **SIA-D27** | **Every credential-backed surface degrades honestly when the credential is absent (SIP-P15, INV-12).** Phase 0's first task | **Binding** |
| **SIA-D28** | **Address entry (Phase 4) and map rendering (Phase 5) move into Version 1.** V1 grows to 21–31 engineer-weeks | **Approved** |
| **SIA-D29** | **Phase 3 splits into 3a (shadow, reversible by dropping a table) and 3b (cutover, reversible by `git revert`)** | **Approved** |
| **SIA-D30** | **`places` uses a surrogate PK.** GERS IDs are Overture-only and cannot key authority-sourced rows | **Approved** |
| **SIA-D31** | **Phase 3b nulls `rating` / `user_ratings_total` values while retaining the columns.** Stopping writes does not close the violation | **Approved** |
| **SIA-D32** | **The credential's state is determined by Phase 0 telemetry, never by a paid probe** | **Binding — product owner** |
| **SIA-D33** | **Build in parallel, cut over in sequence (SIP-P16).** The legal ordering constrains three config flips, not five months of engineering. Six concurrent tracks; cutovers remain serial in the order 3b → 4 → 5. Guard 1 (consolidation before maps) is a precondition, not a preference. | **Approved** |

---

## Appendix B — Errata

Errata are recorded against the superseded documents. This appendix is **self-contained** — no reader should need a prior version of this file.

### Carried forward from v2.0

| # | Erratum | Evidence |
|---|---|---|
| **E-1** | "Persist rank-1 only (19 rows/listing)" would silently regress the agent panel, which displays **top-3**; six consumers read `property_location_pois` directly, ordered by `rank`. Persist **top-3**. *The table holds **top-10** today (ranks 1–10, ~98 rows each; 83.8 rows/listing) — so this is a **70% reduction**, not an increase over rank-1.* | `SellerOfferListing:1972`; `Admin/DnaInspectorController:427`; rank histogram |
| **E-2** | `config/location_dna.php` calls `0.005` the *"recommended production default."* `ldna-tile-precision-benchmark.md` records **every** value as `TBD`. Unsubstantiated. Moot once the tile cache is retired in Phase 3b. | Both files |
| **E-3** | **"CONUS bbox" is wrong.** `bridge_properties` = 589 FL, **76 PR**, 1 VA, 1 "OC". Scope is US + territories; filter by country/region code, not bbox. | `GROUP BY state_or_province` |
| **E-4** | `places.geom` as `geography(Point,4326)` cannot represent parks, beaches, airports, or golf courses, and cannot yield PAD-US acreage. Use `geography(Geometry,4326)` + a `centroid` column. | §7.4 |
| **E-5** | The prior KNN query is the classic category-filter pathology. Requires `btree_gist` + `gist(category_key, geom)`. Any quoted single-digit-millisecond benchmark almost certainly had no category predicate. | §7.3 |
| **E-6** | `CREATE INDEX … USING gist (geom) WHERE category_key IS NOT NULL` is a **dead index** — `category_key` is `NOT NULL`, so the predicate is always true. | prior §13.2 |
| **E-7** | **`ST_Contains` does not accept `geography`.** The isochrone containment query would error, or discard the GiST index via casts. Use `ST_Covers`. | prior §13.3 |
| **E-8** | `CLUSTER` after the swap takes `ACCESS EXCLUSIVE` on the live table. Cluster the **staging partition before** the flip. | prior §13.4 |
| **E-9** | `location_snapshots` and `property_location_pois` are two tables doing one job, while the roadmap simultaneously declares the latter an immutable contract. **`location_snapshots` is not built.** | prior §13.2 vs Roadmap §0.1 rule 3 |
| **E-10** | Gate 1's labelled set is **844 rows**, not 1,090. Only 844 of 1,090 POI rows carry a Google rating. | `count(rating)` |
| **E-11** | **"FEMA NFHL stays a live API. Do not import."** Reversed. Without the coverage footprint, "outside a hazard zone," "never mapped," and "request failed" are indistinguishable — so INV-5 cannot be satisfied. And a nationwide flood *filter* cannot be served per-listing over HTTP. | §10 |
| **E-12** | The six-term prominence prior is unnecessary. Consumers never see a rating; six categories are decided by authority registries; and Google's ratings are sparsest **precisely there** (transit 22%, school 43%, hospital 60%). | §9.1 |
| **E-13** | The Foursquare Places Premium contingency is **withdrawn**, not deprioritised. Follows from E-12. | §9.2 |
| **E-14** | Geoapify (paid; storage right appears in marketing copy, not the binding T&C) and Photon (an extra Elasticsearch service) are both avoidable. Owned NAD + OpenAddresses + `pg_trgm`. **Reversible at Gate 4** — a paid *non-Google* geocoder does not violate SIA-D25. | §11.2, §14.2 |
| **E-15** | **The maps cut is not one-way.** Google's clause forbids Google Maps *Content* with a *non-Google map*. With zero Google data, both renderer states are lawful. The ordering is unchanged and still binding. | §12, §19 |
| ~~**E-16**~~ | ~~Persisting POIs for Bridge listings is unbounded at national scale; compute on read.~~ | **SUPERSEDED by E-28.** Premature: 667 × 23 × 3 ≈ 46k rows. |
| **E-17** | Available-but-uninstalled extensions include **`btree_gist` 1.7, `pg_trgm` 1.6, `h3` 4.2.3, `pgrouting` 3.8.0, `postgis_raster` 3.5.3** — not merely `postgis` and `vector`. `h3` makes the hex commute index far cheaper than the prior "future work" framing assumed. | `pg_available_extensions` |
| **E-18** | Three incompatible phase numbering schemes existed, plus two incompatible V1 scopes and two incompatible geocoding fallbacks. **One scheme, §17.** | Cross-document diff |
| **E-19** | **Valhalla belongs in V1.** The prior deferral rested on "nothing depends on it," which is circular. `CommuteTimeAdapterInterface` is already a matrix contract; `travel_time_minutes` already exists and is unwritten; `CommuteTimeStubAdapter` is a one-class swap. The buyer-side matcher replacement is the expensive part, and it separates cleanly. | §13.3 |
| **E-20** *(corrected by E-37)* | Test-isolation status is **partly ahead** of the prior documents: `phpunit.xml:33-36` already blanks the key — **but without `force="true"`**, so it cannot override a system env var, which is the incident mechanism. ~~Its comment claims `tests/TestCase.php` enforces this; **it does not.**~~ **Correction:** `tests/TestCase.php` did carry **partial** protections — it binds `Tests\Support\BlocksGooglePlacesHttpClient` into the container and calls `guardAgainstLiveGooglePlacesKey()`. What it lacked was `Http::preventStrayRequests()` and a `PoiLookupAdapterInterface` stub binding. Both POI callers already accept an injected client but fall back to `new Client()`. **See E-37: adding `force="true"` is by itself insufficient.** | `phpunit.xml`; `tests/TestCase.php:40-57` |
| **E-21** | The "43 Location DNA test files" figure is unverified. `find` returns 29 on `*LocationDna*`/`*Ldna*` and 47 on a broader pattern. **Re-baseline before using it as a Definition-of-Done criterion.** | `find tests -type f` |

### New in v3.0

| # | Erratum | Evidence |
|---|---|---|
| **E-22** | **`places` PK `(corpus_version, gers_id)` with `gers_id NOT NULL` is unbuildable.** GERS IDs are an Overture concept; CMS, NCES, FAA, USGS, GTFS, and OSM rows have none. **Seven of 23 categories could not be inserted into their own table.** | §7.2 |
| **E-23** | v2.0 queried `current_setting('app.corpus_version')` — a session GUC, fragile under pooled connections and queue workers. Bind it from PHP. | §7.5 |
| **E-24** | **`ST_Subdivide` does not accept `geography`.** `boundaries_parts` must be built via `ST_Subdivide(geom::geometry)::geography` **at import time**, never at query time. | §7.2 |
| **E-25** | **Four tables were referenced but never defined:** `place_categories` (used in the canonical KNN), `isochrone_cache`, `place_category_mappings`, `place_authority_links`. | §7.2 |
| **E-26** | **INV-3 contradicted Phase 3.** `top_rated_dining` is a `summary_json` key rendered on the **consumer-facing** `matchmaker-nearby.blade.php` — its removal is not additive. INV-3 now carries two named, bounded exceptions. | `LocationDnaSummaryService`; `matchmaker-nearby.blade.php` |
| **E-27** | **`fitness_center` merge is lower-risk than v2.0 implied.** It appears only in `LocationDnaRankingProfileService` and `LocationDnaPoiDistanceService`, **never in `LocationDnaSummaryService`** — it is one of the five invisible categories. It changes `poi_category` values, not `summary_json` keys. | grep |
| **E-28** | **Bridge compute-on-read was premature and self-contradictory.** It conflicted with the trigger model, the Phase 3 DoD, and live code (`ImportBridgeProperties.php:161`, `LocationDnaPipelineRunner:178`). **667 × 23 × 3 ≈ 46k rows.** Persist at V1. | §15.4 |
| **E-29** | **Gate 1 cannot "sample 50 listings."** Only **13** listings have POI rows (`seller_agent` 7, `landlord_agent` 3, `seller` 3; **zero** Bridge). Sample listing × category pairs. | `GROUP BY listing_type` |
| **E-30** | **The Phase 3 rollback was never a config flip.** It deletes a controller, a route, a UI button, three view consumers, and two commands. Split into 3a / 3b; rollback is `git revert`. | §17, §19 |
| **E-31** | **V1 did not close compliance violation V1.** Google caps *caching* at 30 days, not writing. 844 rated rows would have persisted through launch. Null the values in 3b. | §9.4 |
| **E-32** | **Territorial dataset coverage was asserted, not verified.** Gate 2 now requires a dataset × territory matrix. Four datasets to verify explicitly for PR: **EPA Walkability, USGS Boat Ramps, NOAA CUSP, DOT NAD.** | §5 |
| **E-33** | **Partition-by-version doubles corpus storage during a refresh.** Size the instance for peak, not steady state. | §15.2 |
| **E-34** | **"Marginal cost per listing = $0" is a vendor-cost claim, not a compute claim.** Stated precisely so it survives scrutiny. | §20 |
| **E-35** | **The Google credential is not only an enrichment dependency.** It backs 8 map/embed files, 49 browser Autocomplete instantiations, 12 Livewire proxies, and 6 geocoding call sites. Removing it without replacements takes down address entry on every listing form. **This is why Phases 4 and 5 move into V1.** | grep of `resources/views/` |
| **E-36** | **A live dual-run against Google (old Gate 3) is impossible under SIA-D25.** Replaced by a baseline diff against 1,090 frozen rows plus human adjudication. This is weaker, covers 13 listings, and is the accepted cost of the decision. | §18 G3 |

### New in v3.1 — discovered during Phase 0 Batch 1 implementation

Recorded 2026-07-09 against commits `3fdc4a714` (Batch 1) and `f3dab6f24`. Both errata are **implementation findings that contradict an approved step**; neither was applied as a silent edit.

| # | Erratum | Evidence |
|---|---|---|
| **E-37** | **The approved S1a fix does not work on its own.** `phpunit.xml`'s `<server name="…" force="true"/>` writes `$_SERVER` and **never `getenv()`**. This host (Replit) injects `GOOGLE_PLACES_API_KEY` as a real **process environment variable**, so `getenv()` kept returning the live key, `tests/TestCase::detectLiveGooglePlacesKey()` kept finding it, and the suite refused to run: **2,194 failed / 3,416 passed at baseline**, with 3,996 occurrences of the guard message. Verified pre-existing via `git stash` — not introduced by Batch 1. **`tests/bootstrap.php` is hereby the approved test-safety mechanism** (`phpunit.xml bootstrap="tests/bootstrap.php"`): it blanks the credential across `getenv()` / `$_ENV` / `$_SERVER` *before Laravel boots*, setting it to an **empty string** rather than unsetting it, because phpdotenv's immutable repository would otherwise let `.env` repopulate it. `force="true"` is retained as defence in depth; the `TestCase` guard is retained as a fail-closed backstop. Result: **212 failed / 5,403 passed**, the residual failures being pre-existing order-dependent flakes unrelated to Google. **This erratum also corrects E-20**, which wrongly asserted `tests/TestCase.php` had no protections. | `phpunit.xml`; `tests/bootstrap.php`; baseline vs. post-Batch-1 `--testsuite=Unit` runs |
| **E-38** | **The bare-Guzzle census in E-35 and in the Batch 1 report was undercounted.** Both audits grepped for `new Client(` and therefore missed every **fully-qualified** `new \GuzzleHttp\Client()`. The true count of bare clients issuing requests to `maps.googleapis.com` outside `app/Services/LocationDna/` was **18, across 12 files** — not 4. Every one bypasses `GoogleOutboundTelemetryMiddleware`, so **INV-11's "zero outbound attempts" cannot be asserted from telemetry until they are routed through the container.** Three were fixed in `TenantOfferListing.php` (`f3dab6f24`), leaving **15**. **Three of those are in `TenantAgentAuction.php` (lines 2214, 2330, 2440), which is frozen under INV-8 and was not touched.** The other twelve sit in non-frozen files that are outside any approved batch. The role-symmetric spread (Seller / Buyer / Landlord / Tenant × Auction / OfferListing × Create / Edit) is exactly what `CLAUDE.md`'s quadruplication rule predicts. The correct detection pattern is `grep -rnE 'new\s+\\?(GuzzleHttp\\)?Client\s*\('`. See **Q11**. | `grep -rnE` census, 2026-07-09 |

**E-38 — full census.** Frozen rows are blocked on Q11; the rest await batch scoping.

| File | Google clients | Status |
|---|---|---|
| `app/Http/Livewire/TenantAgentAuction.php` | 3 | 🔒 **Frozen (INV-8) — known exception, see Q11** |
| `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` | 3 | ✅ Fixed in `f3dab6f24` |
| `app/Http/Livewire/TenantAgentAuctionEdit.php` | 2 | ⬜ Not frozen by name; **INV-8 scope ambiguous — see Q11** |
| `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php` | 2 | ⬜ Pending |
| `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php` | 1 | ⬜ Pending |
| `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php` | 1 | ⬜ Pending |

*(`ChatController.php`, `OpenAiClientService.php`, and `AgentAiOpenAiOrchestrator.php` also construct bare clients but never call a Google host; they are out of scope for INV-11.)*

**Known exception — the frozen `TenantAgentAuction` Google calls.** `TenantAgentAuction.php:2214` (geocode), `:2330` (geocode), and `:2440` (Places Autocomplete) construct bare Guzzle clients and call Google directly. INV-8 declares the component frozen and this programme does not modify it. **Consequence, recorded explicitly:** until Q11 is resolved, the platform **cannot claim full zero-outbound telemetry coverage**, and INV-11's Phase 0 Definition-of-Done clause — *"zero outbound attempts"* — is satisfiable only by the test-environment guards (`tests/bootstrap.php`, `BlocksGooglePlacesHttpClient`), **not** by production telemetry. This is a known, accepted, and time-boxed gap, not an oversight. It must be closed before **Gate 7 / V1 launch certification**.

---

## Appendix C — Open questions

| # | Question | Blocks | Owner |
|---|---|---|---|
| **Q1** | **Hosting target** for app server + queue worker + Redis + managed PostGIS + Valhalla VM | **Phase 0 — the only true blocker** | **Product owner** |
| Q2 | Row count and on-disk size of an Overture Places extract for US + territories at `confidence ≥ 0.90` | Phase 2 sizing | Engineering — measure |
| Q3 | Valhalla build/serve RAM for a US + PR graph (published figures are planet-scale) | Phase 6 sizing | Engineering — benchmark one state first |
| Q4 | FEMA NFHL national import size and refresh mechanics | Phase 2 sizing | Engineering — measure |
| Q5 | Per-feed GTFS licensing — no blanket commercial-use grant exists | Phase 2 | Engineering + Legal |
| Q6 | Overture per-source NOTICE obligations (the Apache-licensed slice) | Phase 2 | Legal — one-time |
| Q7 | Audience-label reconciliation against Phase A §8 | **Phase 9 (Gate 7)** | **Legal counsel** |
| Q8 | Do we promote *assigned school* to a product claim? If yes, ATTOM is required | Phase 9 | Product + Legal |
| Q9 | Do NAD / OpenAddresses cover Puerto Rico adequately, or does PR need Census-only geocoding? | Phase 4 | Engineering — measure |
| **Q10** | ~~Is the Google credential live?~~ | — | **Withdrawn.** Phase 0 telemetry answers it (SIA-D32). No paid probe. |
| **Q11** | **How do we close the three frozen Google calls in `TenantAgentAuction.php` (INV-8) so INV-11 can be asserted from production telemetry?** Options: (a) grant a narrow, reviewed INV-8 exemption limited to swapping `new Client()` → `app(ClientInterface::class)` with no other change; (b) intercept below the component by making the frozen calls unreachable when `google_places.enabled=false`; (c) accept the gap until Phase 7 deletes the Google code path wholesale. **Sub-question:** does INV-8's freeze on "the `TenantAgentAuction` Livewire component" extend to `TenantAgentAuctionEdit.php` (2 more clients)? | **Gate 7 / V1 launch certification**; full INV-11 telemetry coverage | **Product owner** |

---

## Appendix D — Verification commands

Run at every phase gate. **All must return zero by Phase 7.**

```bash
# 1. Any Google Maps Platform endpoint in application code or views
grep -rn "maps.googleapis.com\|google.com/maps" app/ resources/ config/ | grep -v "fonts.googleapis.com"

# 2. Any Maps JS SDK symbol
grep -rn "google\.maps\." resources/

# 3. The credential
grep -rn "places_key\|GOOGLE_PLACES_API_KEY" app/ config/ resources/

# 4. Persisted Google Maps Content
grep -rn "user_ratings_total\|google_place_id" app/ resources/ database/

# 5. Public Nominatim from the browser
grep -rn "nominatim.openstreetmap.org" resources/

# 6. The kill switch is wired  (Phase 0: must become NON-zero, then zero again at Phase 7)
grep -rn "google_places\." app/

# 7. Runtime proof — no outbound call from the enrichment path
php artisan test --filter=NetworkGuard

# 8. INV-11 — the suite passes with no credential.
#    Since E-37 the env prefix is redundant: tests/bootstrap.php blanks the key
#    unconditionally. Keep the prefix as a belt-and-braces check that it still holds.
GOOGLE_PLACES_API_KEY= php artisan test

# 9. Production proof — outbound_google_requests_total == 0, sustained 7 days

# 10. Bare Guzzle clients that bypass GoogleOutboundTelemetryMiddleware (E-38).
#     Note the \\? — a `new \GuzzleHttp\Client()` is invisible to a `new Client(` grep,
#     which is exactly how the first census undercounted 16 call sites as 4.
#     Expected at Phase 7: no Google-calling bare client remains — only the container
#     binding in AppServiceProvider, plus the three non-Google clients noted below.
#     As of f3dab6f24: 15 Google-calling bare clients remain (3 frozen under INV-8, pending Q11).
grep -rnE 'new\s+\\?(GuzzleHttp\\)?Client\s*\(' app/ --include=*.php
```

**Out-of-scope greps that will correctly still return hits:** `fonts.googleapis.com` (12 files, a static font CDN), and `GOOGLE_CLIENT_ID` / `SocialAuth.php` (OAuth — an identity provider, not Maps Platform). For command 10, `ChatController.php`, `OpenAiClientService.php`, and `AgentAiOpenAiOrchestrator.php` will return hits but call no Google host.

---

## Appendix E — Evidence base

All figures measured **read-only** against the production database and source tree on **2026-07-09**. No code, test, configuration, database, or Git state was modified. **No Google API call was made.**

| Claim | Method |
|---|---|
| 11 of ~1,226 listings have Location DNA | `count()` on `property_location_dna` vs listing tables |
| 667 Bridge rows: **589 FL, 76 PR, 1 VA, 1 "OC"**; 100% carry lat+lng | `GROUP BY state_or_province` |
| 1,090 POI rows across **13** listings = **83.8/listing**; ranks 1–10 | aggregate over `property_location_pois` |
| POI listing types: `seller_agent` 7, `landlord_agent` 3, `seller` 3; **zero `bridge`** | `GROUP BY listing_type` |
| **844** of 1,090 rows carry a rating | `count(rating)` |
| Rating coverage: transit 12/55, school 24/56, hospital 33/55, restaurant 48/55 | `GROUP BY poi_category` |
| `travel_time_minutes` exists (`smallint`), **0 rows populated** | `information_schema.columns` |
| `dna_scores`: **1,001 property-side, 429 demand-side** rows | `GROUP BY side` |
| `CommuteTimeAdapterInterface` = `(originLat, originLng, destinations[], travelModes[])` → matrix | source read |
| `top_rated_dining` is written by `LocationDnaSummaryService` and rendered in `matchmaker-nearby.blade.php` | grep |
| `fitness_center` never appears in `LocationDnaSummaryService` | grep |
| `ImportBridgeProperties.php:161` dispatches `ComputeLocationDna::dispatch('bridge', …)` | grep |
| **8** view files embed the credential; **49** files instantiate browser Autocomplete; **8** Maps JS loaders | grep of `resources/views/` |
| `btree_gist` 1.7, `pg_trgm` 1.6, `h3` 4.2.3, `pgrouting` 3.8.0, `postgis` 3.5.3, `vector` 0.8.0 — **all available, none installed** | `pg_available_extensions` |
| Database size today: **119 MB**; PostgreSQL 16.10 | `pg_database_size` |
| `QUEUE_CONNECTION=sync`, `CACHE_DRIVER=file`, `php artisan serve`, no worker, no scheduler | `.replit`; `.env`; grep |
| Kill switch inert | `grep -rn "google_places\." app/` → **0 hits** |
| `sessiontoken` absent | `grep -rln "sessiontoken" app/ resources/` → **0 files** |
| Both POI callers fall back to bare `new Client()` | `LocationDnaPoiDistanceService:449`, `GooglePlacesPoiAdapter:60` |
| `phpunit.xml` blanks the key **without `force="true"`** | `phpunit.xml:33-36` |
| 38,236 requests / ~$1,223 / 6 days, from the test suite | `docs/investigations/Google-Places-Root-Cause-Analysis.md` |

---

**End of specification.** This document is the single source of truth for geographic intelligence across BidYourAgent and BidYourOffer. Amendments proceed by appending to the Decision Register (Appendix A) and the Errata (Appendix B) — never by silent edit. Phase-level scope changes require an entry in the Decision Register.
