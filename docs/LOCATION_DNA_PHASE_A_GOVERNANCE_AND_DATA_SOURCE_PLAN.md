# Location DNA — Phase A: Governance & Data Source Plan

**Document Status:** Planning / Governance Only
**Phase:** A (Documentation)
**Date:** 2026-05-31
**Author:** Platform Architecture

---

## Section 1 — Purpose of Location DNA

Location DNA is a **deterministic computed layer** that enriches a property profile with objective, nearby-location and distance-based context.

Its purpose is to answer factual questions such as:

- How far is the nearest grocery store?
- How many parks are within 2 miles?
- What is the nearest public transit stop?
- How far is the nearest hospital or urgent care?
- How close is the property to a beach?

Location DNA is **not** AI-generated. It is not predictive, probabilistic, or model-based. Every output is a direct calculation from publicly available Point-of-Interest (POI) data and geometric distance computation.

Location DNA is **not** demographic in nature. It does not incorporate, reference, or infer information about the population characteristics of any neighborhood, census tract, ZIP code, or geographic area. It describes the physical and civic landscape surrounding a property — not the people who live there.

Location DNA is designed to give buyers, tenants, sellers, and landlords an objective, factual snapshot of a property's location convenience — comparable to what a buyer would observe by exploring the neighborhood themselves.

---

## Section 2 — Phase Boundaries

### Phase A Scope: Documentation Only

Phase A produces **this document and nothing else**. The following are explicitly out of scope for Phase A:

| Category | Status |
|---|---|
| PHP service, model, or class files | Not created |
| Database migrations | Not created |
| Routes (web or API) | Not created |
| UI components or Blade views | Not created |
| OpenAI prompt changes | Not made |
| AiMarketingReportGeneratorService changes | Not made |
| AiMarketingReportPersistenceService changes | Not made |
| `marketing_reports` table changes | Not made |
| Integration with Property DNA pipeline | Not made |
| Integration with Marketing Intelligence pipeline | Not made |
| Any live API calls to external services | Not made |

Phase A exists to establish shared understanding, define the data contract, enumerate the Fair Housing safeguards, and plan the phased implementation roadmap so that all future phases can proceed with a clear governance foundation.

---

## Section 3 — Allowed Inputs

The following inputs are permitted for Location DNA computation:

| Input | Description |
|---|---|
| Property address | Street address as stored on the listing |
| City | City as stored on the listing |
| County | County as stored on the listing |
| State | State as stored on the listing |
| ZIP code | Postal code as stored on the listing |
| Latitude / Longitude | Resolved coordinates (when available from geocoding) |
| Property type | Residential, commercial, land, etc. |
| Public POI/location API results | Name, category, coordinates, and distance of nearby points of interest returned by approved APIs |
| Cached distance results | Previously computed distances stored in the `property_location_dna_pois` table (Phase E onward) |

All inputs must be directly tied to the physical location of the property or to factual public POI data. No input may be derived from or correlated with protected-class characteristics.

---

## Section 4 — Prohibited Inputs

The following inputs are **strictly prohibited** from being used in any Location DNA computation, storage, or output:

| Prohibited Input | Reason |
|---|---|
| Race, color, national origin, religion | Protected class under the Fair Housing Act |
| Sex / gender | Protected class under the Fair Housing Act |
| Familial status (presence of children) | Protected class under the Fair Housing Act |
| Disability / handicap status | Protected class under the Fair Housing Act |
| Ancestry or ethnicity | Protected class under the Fair Housing Act |
| Neighborhood demographic composition | Proxy for protected-class data |
| Census demographic statistics by tract or ZIP | Proxy for protected-class data |
| School "quality" ratings tied to demographic proxies | Indirect proxy steering |
| Crime statistics by neighborhood | Known proxy for race/ethnicity in FHA compliance |
| "Safety" ratings of any area | Known steering vector under FHA |
| Any data set that correlates with protected traits | Must be reviewed before use |
| Any data used to recommend or discourage a property based on who lives nearby | Core FHA violation |

Any proposed data source that is not on the Allowed Inputs list must be reviewed against this Prohibited Inputs list before use. If there is ambiguity, the answer is no.

---

## Section 5 — Initial Location Categories

The following POI categories are approved for the initial Location DNA feature. Each category produces factual, objective distance-based outputs.

| Category | POI Examples |
|---|---|
| Beaches | Public ocean, lake, or river beaches |
| Parks | City parks, state parks, national parks, recreation areas |
| Schools | Public elementary, middle, and high schools |
| Grocery Stores | Supermarkets, grocery chains, food co-ops |
| Restaurants | Dining establishments (nearest, count nearby) |
| Hospitals / Urgent Care | Hospitals, urgent care clinics, ERs |
| Airports | Commercial and regional airports |
| Downtown / City Center | Central business districts, main commercial corridors |
| Public Transit Stops | Bus stops, light rail, subway, commuter rail |
| Major Highways | On-ramps to interstate or state highways |

### Category Expansion

Additional POI categories may be added in future phases after review against the Prohibited Inputs list. A category must:

1. Be factual and physically observable.
2. Not serve as a proxy for demographic data.
3. Be computable from approved API sources.

---

## Section 6 — Data Source Plan

The following sources are evaluated for use in Location DNA. All sources must be used only for factual POI and distance data. No demographic data from these sources is permitted.

---

### 6.1 OpenStreetMap / Nominatim

| Attribute | Detail |
|---|---|
| **Purpose** | Geocoding (address → lat/lng), reverse geocoding, POI search |
| **Cost** | Free and open source (OSM data); Nominatim public API is free with rate limits |
| **API Limits** | Public Nominatim: 1 request/second, no bulk queries. Self-hosted: unlimited |
| **Pros** | Globally available, no API key required, no vendor lock-in, data is open |
| **Cons** | Public rate limits are strict; POI coverage is inconsistent in rural areas; data quality varies by region |
| **Recommended MVP Use** | Self-hosted Nominatim for geocoding. OSM Overpass API for POI queries. Best fit for MVP given zero cost and no key dependency |

---

### 6.2 Geoapify

| Attribute | Detail |
|---|---|
| **Purpose** | Geocoding, POI search (Places API), isochrones, routing |
| **Cost** | Free tier: 3,000 API credits/day. Paid tiers from ~$49/month |
| **API Limits** | Free tier is sufficient for low-volume MVP; rate limits apply per plan |
| **Pros** | Clean REST API, good POI coverage in the US, supports category filtering, no per-result charge on free tier |
| **Cons** | Free tier credits deplete quickly at scale; less globally established than Google |
| **Recommended MVP Use** | Good candidate for POI search in the MVP. Evaluate against OSM Overpass for coverage quality in target markets |

---

### 6.3 Google Places API / Geocoding API

| Attribute | Detail |
|---|---|
| **Purpose** | Geocoding (already in use), POI nearby search, place details |
| **Cost** | Pay-per-use: Geocoding ~$5/1,000 requests; Nearby Search ~$32/1,000 requests |
| **API Limits** | No hard daily limit; billed per call |
| **Pros** | Already integrated for address validation; best-in-class coverage and accuracy; well-documented |
| **Cons** | Significant cost at scale for POI queries; vendor lock-in; requires billing account |
| **Recommended MVP Use** | Geocoding is already used — this integration path is established. POI search (Nearby Search) viable if budget allows. Consider limiting to high-value categories only (hospitals, grocery) to control cost |

---

### 6.4 Mapbox

| Attribute | Detail |
|---|---|
| **Purpose** | Geocoding, POI search (Mapbox Search API), routing |
| **Cost** | Free tier: 100,000 geocode requests/month; Mapbox Search: ~$0.75–$5/1,000 requests |
| **API Limits** | Generous free tier for geocoding; POI search billed separately |
| **Pros** | Strong US coverage, developer-friendly SDK, flexible pricing |
| **Cons** | POI Search API (formerly Search Box) is newer and has evolving documentation; less established than Google for POI data |
| **Recommended MVP Use** | Viable backup or alternative to Geoapify. Evaluate for POI coverage quality against target markets |

---

### 6.5 OpenRouteService

| Attribute | Detail |
|---|---|
| **Purpose** | Route-based distance and travel time calculation (drive time, walk time) |
| **Cost** | Free public API: 2,000 requests/day. Self-hosted: unlimited |
| **API Limits** | 2,000 route requests/day on free tier; stricter than OSM Nominatim |
| **Pros** | Open source (ORS), supports driving, walking, and cycling; returns actual travel time not just straight-line distance |
| **Cons** | Free public API limits may be too low at scale; self-hosting requires infrastructure |
| **Recommended MVP Use** | Use for drive/walk time enrichment (Phase C). Self-hosted instance is the recommended production path |

---

### 6.6 TransitLand

| Attribute | Detail |
|---|---|
| **Purpose** | Public transit stop locations, route coverage, GTFS feed aggregation |
| **Cost** | Free API with rate limits (requires API key). Bulk/commercial use requires agreement |
| **API Limits** | Standard tier: 1,000 API calls/day |
| **Pros** | Aggregates GTFS data from transit agencies nationwide; open ecosystem |
| **Cons** | Coverage depends on agency participation; data freshness varies by agency; not real-time |
| **Recommended MVP Use** | Use for transit stop proximity in the "Public Transit" category. Phase C or later |

---

### 6.7 SchoolDigger (Future)

| Attribute | Detail |
|---|---|
| **Purpose** | School location data by address/ZIP; school name and type |
| **Cost** | API pricing varies by plan; contact required for commercial use |
| **API Limits** | Not publicly documented; varies by plan |
| **Pros** | Structured US school data with location; well-maintained |
| **Cons** | Must use strictly for location/proximity only — SchoolDigger also provides ranking data that must never be used (proxy for demographics/protected-class steering) |
| **Recommended MVP Use** | Deferred to a future phase. When used, only school name, category (elementary/middle/high), and distance are permitted outputs. School rankings, test scores, or ratings must never be included |

---

### Recommended MVP Source Stack

| Function | Recommended Source |
|---|---|
| Geocoding (address → lat/lng) | Google Geocoding API (already integrated) or OSM Nominatim |
| POI search | Geoapify or OSM Overpass API |
| Drive/walk time | OpenRouteService (self-hosted) |
| Transit stops | TransitLand |
| Schools (future) | SchoolDigger (location only, no ratings) |

---

## Section 7 — Allowed Outputs

All Location DNA outputs must be factual, objective, and non-steering. Allowed outputs include:

| Output Type | Example |
|---|---|
| Distance in miles | "Nearest grocery store: 1.4 miles" |
| Estimated drive time | "~4 min drive to nearest hospital" |
| Estimated walk time | "~12 min walk to nearest park" |
| Count of nearby POIs by category | "3 parks within 2 miles" |
| Nearest POI name and category | "Nearest beach: Clearwater Beach (2.1 mi)" |
| Location convenience summary | Factual bullet list of nearest POIs per category |
| Missing-data notice | "Transit stop data not available for this area" |

All numeric values must be computed from real API data or geometry — never estimated or fabricated.

---

## Section 8 — Prohibited Outputs

The following outputs are **never permitted** in any Location DNA summary, UI display, or AI prompt:

| Prohibited Output | Why |
|---|---|
| "Best neighborhood" or "best area" | Steering language; subjective and legally risky |
| "Safe neighborhood" or "low crime area" | Crime stats are a known FHA proxy violation |
| "Good schools" (without neutral sourcing) | School quality as a selling point is a FHA steering vector |
| "Ideal for families" | Familial status is a protected class |
| "Perfect for young professionals" | Age discrimination proxy |
| "Best for retirees" | Age discrimination proxy |
| Any demographic claim about area residents | Direct FHA violation |
| Protected-class or proxy-based recommendations | Core FHA violation |
| Steering language of any kind | Encouraging or discouraging based on protected traits |
| Rankings of people or groups | Not factual location data |
| "Up and coming neighborhood" | Demographic proxy / gentrification language |
| "Quiet neighborhood" (implying demographic composition) | Steering proxy |

When in doubt, ask: "Does this output describe the physical location, or does it describe the people who live there?" Only physical-location outputs are permitted.

---

## Section 9 — Fair Housing Safeguards

### Core Principle

**Location DNA describes the property's location features — not who should live there.**

The Fair Housing Act (42 U.S.C. §§ 3601–3619) prohibits discrimination in the sale, rental, and financing of housing based on race, color, national origin, religion, sex, familial status, and disability. Steering — directing buyers or renters toward or away from neighborhoods based on protected characteristics — is a specific prohibited act.

Location DNA is designed to provide the factual proximity information a buyer or tenant would discover by walking the neighborhood themselves. It must never be used to influence where someone should live based on who they are.

### Allowed vs. Not-Allowed Phrasing Examples

| Category | Allowed | Not Allowed |
|---|---|---|
| Schools | "Nearest elementary school: 0.8 miles" | "Great school district — ideal for families" |
| Parks | "2 parks within 1 mile" | "Family-friendly area with lots of green space" |
| Transit | "Bus stop: 0.3 miles. Light rail: 1.2 miles" | "Perfect for young professionals commuting downtown" |
| Safety | (No safety output at all) | "Safe, quiet neighborhood" |
| Demographics | (No demographic output at all) | "Diverse neighborhood" or "predominantly X community" |
| Restaurants | "14 restaurants within 1 mile" | "Vibrant dining scene — great for foodies" |
| General area | "Located 2.1 miles from downtown Tampa" | "Up and coming neighborhood with great energy" |

### Review Requirement

Before any new POI category or output format is introduced in Phases B–H, it must be reviewed against this Fair Housing safeguards section. The review should ask:

1. Does this output describe a physical or civic fact?
2. Could this output be interpreted as recommending or discouraging based on a protected class?
3. Would a Fair Housing compliance officer flag this language?

If the answer to questions 2 or 3 is "yes," the output is prohibited.

---

## Section 10 — Storage Plan

The following tables are proposed for future phases. **They are not created in Phase A.** Schema decisions are deferred to Phase B/C after approval.

### Proposed Table: `property_location_dna`

Stores the computed Location DNA summary for a property.

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint PK | Primary key |
| `listing_type` | varchar | Polymorphic: 'seller', 'buyer', 'landlord', 'tenant' |
| `listing_id` | bigint | FK to the relevant listing |
| `geocoded_lat` | decimal(10,7) | Resolved latitude |
| `geocoded_lng` | decimal(10,7) | Resolved longitude |
| `geocode_source` | varchar | Which API resolved the coordinates |
| `geocoded_at` | timestamp | When geocoding was last performed |
| `summary_json` | jsonb | Full computed Location DNA summary |
| `generated_at` | timestamp | When the summary was last generated |
| `created_at` | timestamp | Record creation |
| `updated_at` | timestamp | Record last update |

### Proposed Table: `property_location_dna_pois`

Stores individual resolved POI records, cached per listing.

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint PK | Primary key |
| `location_dna_id` | bigint FK | FK to `property_location_dna` |
| `category` | varchar | POI category (beach, park, school, etc.) |
| `poi_name` | varchar | Name of the POI |
| `poi_lat` | decimal(10,7) | POI latitude |
| `poi_lng` | decimal(10,7) | POI longitude |
| `distance_miles` | decimal(8,2) | Straight-line or routed distance in miles |
| `drive_time_minutes` | integer | Estimated drive time (nullable) |
| `walk_time_minutes` | integer | Estimated walk time (nullable) |
| `data_source` | varchar | Which API returned this POI |
| `fetched_at` | timestamp | When this POI was fetched/cached |

### Proposed Table: `property_location_dna_audits`

Append-only audit trail for Location DNA generation events (for compliance review).

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint PK | Primary key |
| `location_dna_id` | bigint FK | FK to `property_location_dna` |
| `event_type` | varchar | 'geocode', 'poi_fetch', 'summary_generated', 'cache_invalidated' |
| `api_source` | varchar | Which API was called |
| `input_snapshot` | jsonb | Snapshot of inputs at time of generation |
| `output_snapshot` | jsonb | Snapshot of outputs at time of generation |
| `created_at` | timestamp | When this audit event was recorded |

Phase B or Phase C will propose the actual migration files for review before any schema changes are applied.

---

## Section 11 — Future Architecture

The recommended data flow for Location DNA, to be implemented across Phases B–H:

```
Property Address
        │
        ▼
┌─────────────────────┐
│  Geocode Service    │  (Phase B)
│  Address → Lat/Lng  │
└────────┬────────────┘
         │
         ▼
┌─────────────────────┐
│  POI Search Service │  (Phase C)
│  Lat/Lng → Nearby   │
│  POI results by     │
│  category           │
└────────┬────────────┘
         │
         ▼
┌─────────────────────────┐
│  Distance Calculation   │  (Phase C)
│  Service                │
│  POI + Property →       │
│  Drive/Walk time, miles │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────────┐
│  Location DNA Summary       │  (Phase D)
│  Service                    │
│  All results → Structured   │
│  JSON summary per property  │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│  Computed Storage +         │  (Phase E)
│  Audit Trail                │
│  property_location_dna      │
│  property_location_dna_pois │
│  property_location_dna_     │
│  audits                     │
└────────┬────────────────────┘
         │
         ├──────────────────────────────────────┐
         ▼                                      ▼
┌──────────────────────┐           ┌─────────────────────────────┐
│  Property DNA        │  (Ph. F)  │  Marketing Intelligence      │  (Ph. G)
│  Integration         │           │  Integration                 │
│  (future)            │           │  (future — after XM→XP done)│
└──────────────────────┘           └─────────────────────────────┘
                                              │
                                              ▼
                                   ┌─────────────────────────────┐
                                   │  Ask AI / Listing Chatbot   │  (Ph. H)
                                   │  Integration (future)       │
                                   └─────────────────────────────┘
```

Each layer is independently deployable and testable. Services communicate via well-defined contracts. No layer should be skipped or combined without explicit review.

---

## Section 12 — Future Phases

| Phase | Name | Description |
|---|---|---|
| **A** | Governance | This document. Establishes data sources, safeguards, storage plan, and roadmap. No code changes. |
| **B** | Geocode Service | Implement a `LocationDnaGeocodeService` that resolves a property address to lat/lng. Uses the approved geocoding source (Google or OSM). Writes to `property_location_dna`. Includes caching and error handling. |
| **C** | Distance-to-POI Engine | Implement `LocationDnaPoiSearchService` and `LocationDnaDistanceService`. Queries approved POI APIs per category. Computes distance in miles and drive/walk time. Writes to `property_location_dna_pois`. |
| **D** | Location DNA Summary Service | Implement `LocationDnaSummaryService`. Aggregates Phase C outputs into a structured JSON summary per property. Writes `summary_json` to `property_location_dna`. Enforces all Fair Housing output restrictions. |
| **E** | Computed Storage + Audit Trail | Implement migrations for `property_location_dna`, `property_location_dna_pois`, and `property_location_dna_audits`. Implement cache invalidation strategy (e.g., on address change). |
| **F** | Property DNA Integration | Integrate Location DNA summary into the Property DNA pipeline. Location context is surfaced as a factual data block alongside other property attributes. |
| **G** | Marketing Intelligence Integration | Feed Location DNA factual summary into the AI Marketing Report context builder. Location DNA outputs become grounding facts for the AI prompt. **Must not begin until main agent completes phases XM → XN → XO → XP.** |
| **H** | Ask AI / Listing Chatbot Integration | Surface Location DNA summary in any AI-assisted buyer/tenant chatbot or Q&A feature. Factual location data is used as context, not as steering. |

---

## Section 13 — Integration Timing

### Marketing Intelligence Integration is Gated

Location DNA **must not** be connected to the AI Marketing Report pipeline (`AiMarketingReportGeneratorService`, `AiMarketingReportPersistenceService`, OpenAI prompts, or `marketing_reports` tables) until the main agent has completed all of the following phases in order:

- **Phase XM** — Final Compatibility Governance Audit
- **Phase XN** — Compatibility Explanation Engine
- **Phase XO** — Property DNA Explanation Engine
- **Phase XP** — Deterministic Marketing Context Builder

Until all four phases are complete and verified, Location DNA Phase G (Marketing Intelligence Integration) and Phase H (Ask AI Integration) must remain unstarted.

This gate exists to prevent Location DNA from being injected into an AI prompt pipeline that has not yet been hardened for Fair Housing compliance, output determinism, and context grounding.

### Integration with Property DNA

Location DNA integration with Property DNA (Phase F) may begin independently of the XM–XP gate, since it is a factual data layer addition and does not touch AI generation logic.

---

## Section 14 — Verification Checklist

The following checklist confirms that Phase A has been implemented correctly:

| Item | Status |
|---|---|
| `docs/LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` created | ✅ |
| Zero PHP files changed | ✅ |
| Zero routes changed | ✅ |
| Zero migrations created or modified | ✅ |
| Zero AI / OpenAI files changed | ✅ |
| `AiMarketingReportGeneratorService` not touched | ✅ |
| `AiMarketingReportPersistenceService` not touched | ✅ |
| `marketing_reports` table not touched | ✅ |
| No integration into existing report pipeline | ✅ |
| No live API calls made | ✅ |
| No UI components created | ✅ |
| No Livewire components created | ✅ |
| No database schema changes | ✅ |

Phase A is complete. All subsequent implementation work begins at Phase B.
