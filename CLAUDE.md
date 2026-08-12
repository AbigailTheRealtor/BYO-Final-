# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Asset compilation (Laravel Mix / Webpack — NOT Vite, despite vite.config.js existing)
npm run dev          # one-shot dev build
npm run watch        # watch mode (use in Replit)
npm run production   # minified production build

# Tests — SQLite in-memory, NOT PostgreSQL
php artisan test                                              # all suites
php artisan test tests/Unit/BidMatchScoreHelperAuditTest.php  # single file
php artisan test --filter=CompatibilityScoreServiceTest       # by class name

# Migrations
php artisan migrate:status    # check what has run before touching anything
php artisan migrate --pretend # dry-run SQL before executing
php artisan migrate           # run pending

# Key artisan commands
php artisan ldna:generate {listing_id}   # run Location DNA pipeline for one listing
php artisan ldna:refresh-all             # re-run pipeline for all listings
php artisan ldna:audit-listing {id}      # inspect pipeline state for a listing
```

## Architecture

### Role symmetry (Seller / Buyer / Landlord / Tenant)

Almost everything in this codebase is quadruplicated by role. Each role has its own controller, Livewire component, model, bid model, meta model, routes, and Blade views. When fixing a bug or adding a field, check whether all four role variants need the same change.

**Schema asymmetry**: `seller_agent_auctions` and `buyer_agent_auctions` store data in **native columns**. `landlord_agent_auctions` and `tenant_agent_auctions` store data via **EAV meta** (`meta_key` / `meta_value` in `*_metas` tables). This is not an accident — it was architectural and must be respected.

### EAV meta pattern

Many extended fields are stored as key-value rows in `*_metas` tables (e.g. `buyer_agent_auction_metas`, `agent_service_auction_metas`). The meta model is a plain Eloquent model with `meta_key` and `meta_value` columns. Livewire components read/write these via `saveMeta()` / `getMeta()` calls rather than native Eloquent attributes.

### Livewire bid wizards

Bid forms are multi-tab Livewire components located in `app/Http/Livewire/` subdirected by role (`Buyer/`, `Seller/`, `Landlord/`, `Tenant/`) and by flow (`HireBuyerAgent/`, `HireSellerAgent/`, `HireLandLordAgent/`, `OfferListing/`). Tab navigation uses a **delegation pattern** — tabs emit events to the parent component rather than navigating directly. Validation runs twice: partial on "Save Draft" and full on "Save Edit / Submit".

`HasListingLifecycle` (`app/Http/Livewire/Concerns/HasListingLifecycle.php`) is the shared trait for listing state (`isDraft`, `isApproved`, `isSold`, `listing_status`, flash helpers). **`TenantAgentAuction` does not use this trait** — it predates it and is too large to refactor safely.

### Agent default profiles & auto-bid

`AgentDefaultProfile` stores a `profile_data` JSON blob per agent per role. `AgentBidMapperService::mapFromProfile()` is a pure, side-effect-free transformer from that blob to the normalized bid-field array consumed by all four role bid components. The "Hire Me" direct-entry flow calls this mapper to auto-populate a bid when a client hires an agent.

### Match scoring

`*BidMatchScoreHelper` classes (one per role, in `app/Helpers/`) compare listing criteria against bid fields. Dimension weights and activation flags live in `config/match_scoring.php` — **all enabled weights must sum to 100**. The helpers read config; scoring logic never lives in config.

### Location DNA pipeline

`LocationDnaPipelineRunner` (in `app/Services/LocationDna/`) orchestrates async enrichment for a property: POI lookup (Google Places via `GooglePlacesPoiAdapter`), flood zone (FEMA API), school districts (Census TIGER), and commute times. Results are cached via `LocationDnaPoiTileCache`. The pipeline runs as a queued job (`app/Jobs/ComputeLocationDna.php`). FEMA bounding-box size limits are configured in `config/location_dna.php`.

### Property coordinate ladder (separate from the Location DNA pipeline)

`app/Services/Location/Coordinates/` resolves one property address to one coordinate, provider-neutrally. `PropertyCoordinateResolver` walks a list of `CoordinateProviderAdapterInterface` rungs in precedence order and returns the first resolved answer — local sources before any paid or rate-limited one.

Three types carry the design and are worth reading before touching anything here:

- **`PropertyAddress`** normalizes twice on purpose — `coordinateLookupLine()` drops the unit (what a geocoder is asked, and the cache key), `propertyIdentityLine()` keeps it (what distinguishes two condos).
- **`CoordinatePrecision`** decides via `isExact()` whether a point may drive distance, commute and flood-boundary work. A ZIP centroid and a rooftop are both "a latitude and a longitude"; this enum is what stops the first being measured from.
- **`PropertyCoordinateResult`** is the single immutable return type. Consumers should reach for `exactCoordinates()`, not `->latitude` — the accessor enforces the gate, the property bypasses it.

Built in phases: G1 the contracts, G2 the two local rungs (`ExistingCoordinatesAdapter`, `BridgeMlsCoordinatesAdapter`, assembled by `LocalCoordinateLadder`), G3 the first network rung (`CensusGeocoderAdapter`), G4 operational safety. **Nothing here is wired into a listing flow yet** — `PropertyCoordinateResolverInterface` is deliberately bound to nothing, so no component can inject a resolver by accident. Integration is G5, and Seller/Landlord Location DNA dispatch stays separately gated regardless.

A rung that is *broken* raises `CoordinateProviderUnavailable`; a rung that simply *cannot match the address* returns an unresolved result. Keep that distinction when adding a rung — the first must never be cached, the second should be.

**The address-point rung and the suggestion contracts (exact-address foundation).** `AddressPointCoordinateAdapter` answers from our own imported corpus in `pgsql_spatial.addresses`, and sits on `StandardCoordinateLadder` **below `BridgeMlsCoordinatesAdapter` and above `CensusGeocoderAdapter`** — below Bridge because a coordinate carried by the listing record outranks one matched by address line, above Census because a published address point beats interpolating a house number along a street range, for free. It is flag-inert (`ADDRESS_POINT_CORPUS_ENABLED`, plus a pinned `ADDRESS_POINT_CORPUS_VERSION`) and the corpus is empty; **no importer exists and no address dataset has been downloaded**.

Rows are matched on `normalized` = `PropertyAddress::coordinateLookupLine()` by **equality only**. The `addresses_trgm` GIN index is for typeahead and is deliberately unused here — trigram similarity returns the *nearest* address, which is another property's coordinate reported as success. When matched rows disagree on a point, the rung returns unresolved and the next rung tries.

Address suggestions live in `app/Services/Location/Suggestions/` (`AddressSuggestionProviderInterface`, `AddressCandidate`) and are **deliberately not** part of the coordinate contract. A candidate offers `toPropertyAddress()` and no conversion to a `PropertyCoordinateResult`: a pick re-enters through the ladder like any other address. That separation is the fix for the old path, where an autocomplete pick's coordinate became the listing's coordinate with no provider and no precision recorded. Nothing implements the interface yet.

**G4 added four things worth knowing before touching this code:**

- **Geography agreement.** A returned coordinate is checked against the requested state and ZIP5. A geocoder will happily resolve a street name that exists in two states to the wrong one and return a perfectly valid coordinate; nothing about the numbers reveals it. Exact equality on normalized values only — no fuzzy fallback.
- **Provenance is stored.** `property_location_dna` carries `geocode_precision`, `geocode_provider` and `normalized_address`. Read precision back through `CoordinateProvenance::storedIsUsableForLocationDna()`, never by comparing strings — an unrecognised value must read as coarse, and that rule lives in one place.
- **Guards, in `Coordinates/Guards/`.** `ProviderRequestBudget` (hourly/daily caps) and `ProviderCircuitBreaker` refuse *before* a request by raising, so the resolver skips the rung exactly as it would any other unavailable one. Both are provider-neutral so a future commercial adapter wraps identically. Neither can affect a local rung.
- **Telemetry.** `CoordinateProviderTelemetry` writes one structured `coordinate_provider` log line per attempt. **It must never carry an address** — only `CoordinateProviderTelemetry::addressHash()`. A test asserts no address fragment reaches the log.

`php artisan location:probe-census-address` sends one live request on demand. Dry-run only, refuses to run without `--force-probe` while the flag is off, never scheduled, never called from application code.

### AI DNA profiles (separate from Location DNA)

`PropertyDnaGenerator` and `BuyerTenantDnaGenerator` (in `app/Services/Dna/`) produce AI-generated personality/marketing profiles via the OpenAI client. These are unrelated to the geospatial Location DNA system despite the similar naming.

### Bridge API (MLS data)

`BridgeApiService` (`app/Services/Bridge/BridgeApiService.php`) fetches external MLS listings from the Bridge Data Output OData API. Credentials are `BRIDGE_DATASET` and `BRIDGE_SERVER_TOKEN` in `.env` (see `config/bridge.php`). `BuyerCriteriaODataFilterBuilder` and `TenantCriteriaODataFilterBuilder` (in `app/Services/Bridge/OData/`) translate search criteria objects into OData `$filter` strings.

### Accepted Bid Summary & PDF

When a bid is accepted, an `AcceptedBidSummary` row is created with `summary_html` containing `{{placeholder}}` tokens for signatures. `AcceptedBidSummaryService` performs placeholder replacement at render time. PDFs are generated on demand via `barryvdh/laravel-dompdf`. **Invalidate the cached PDF whenever bid terms change** — the service tracks this; do not bypass it.

### Display logic in config

Service order, compensation fields, and UI display decisions are driven by config files rather than hardcoded in views: `config/buyer_services_order.php`, `config/seller_services_order.php`, `config/landlord_services_order.php`, `config/tenant_services_order.php`, `config/agent_preset_compensation.php`. The `ListingDisplayHelper` and `OfferListingViewHelper` read these at render time.

### Feature flags

`config/bya_compatibility.php` has a **kill switch** (`BYA_COMPATIBILITY_KILL_SWITCH`, defaults `true` = all consumer-facing compatibility blocked) and a GA flag (`BYA_COMPATIBILITY_GA_ENABLED`, defaults `false`). Do not enable GA without coordinating with the owner.

### Deployment & migrations

**`deploy/start-production.sh` is the only thing that runs migrations.** The Replit `[deployment] run` command invokes it; it reports via `deploy:preflight`, then runs `php artisan migrate --force`, then serves — and a failed migration stops the deploy rather than serving against an old schema.

Nothing else may migrate: not `deploy/scheduler.sh`, not the build phase, not a second web process. This app is on **Laravel 8, which has no `migrate --isolated`**, so there is no migration lock and concurrency safety rests entirely on single ownership. `DeploymentMigrationReadinessTest` asserts all of it.

`scripts/post-merge.sh` also migrates, but it is the Replit **workspace** `[postMerge]` hook — it does not fire on deploy or on a GitHub merge. Do not treat it as the deployment's migration step; that assumption is exactly how G4's migration reached `main` and never reached a schema.

Two CI gates: `migration-tests.yml` (`migrate:fresh`, empty DB) and `incremental-migration-tests.yml` (previous-release schema, populated, migrated forward — the operation a deploy actually performs).

`ProvenanceSchemaReadiness` is the runtime backstop: when the provenance columns are absent, coordinate writes proceed and provenance is skipped with `schema_not_ready` rather than raising `SQLSTATE[42703]` inside a listing save. See `deploy/DEPLOYMENT.md`, which also documents an **open question about `APP_DEBUG` in deployments**.

## Frozen / legacy code

**`initializeLimitedService()`** — present in all four Create Offer Listing Blade files (seller, buyer, landlord, tenant). This function is **frozen legacy code for the Limited Service flow**. Never modify, test, or clean up anything inside it. All validation cleanup applies only to the Full Service scope, never inside this function.

**`TenantAgentAuction` Livewire component** — predates the `HasListingLifecycle` engine and is intentionally excluded from the shared trait. Do not attempt to refactor it to use the trait.

## Key `.env` variables

Beyond standard Laravel keys, this app requires:

| Key | Purpose |
|-----|---------|
| `BRIDGE_DATASET` | Bridge Data Output dataset ID |
| `BRIDGE_SERVER_TOKEN` | Bridge API access token |
| `GOOGLE_PLACES_API_KEY` | Address validation + POI lookup |
| `OPENAI_API_KEY` | DNA profile generation |
| `BYA_COMPATIBILITY_KILL_SWITCH` | Consumer compatibility gate (default `true` = blocked) |
| `BYA_COMPATIBILITY_GA_ENABLED` | GA rollout flag (default `false`) |
| `DNA_SCORES_GENERATION_ENABLED` | Master gate for production `dna_scores` generation via the lifecycle (observers + `ComputeLocationDna` chain + `dna:generate-scores`). Default `false` = inert. Independent of Matching V2. |
| `MATCHING_V2_PERSISTENCE_ENABLED` | Matching V2 C7 persistence gate (materialize ranked results into `matching_v2_*`). Default `false`. A write also requires `MATCHING_V2_ENABLED` and a non-production environment — `MatchResultPersister` hard-refuses in production. |
| `MATCHING_V2_PERSISTENCE_VERSION` | Materialization version tag stamped on persisted runs; the reader trusts only rows at the current value (read-time re-gate). Default `c7-v1`. |
| `HIRE_AGENT_HERO_REDESIGN_ENABLED` | Master gate for the redesigned Hire Agent hero (M4). Default `false` = inert; the legacy hero and the sidebar identity block render unchanged for all four roles. Read only via `HireAgentHeroData::redesignEnabledFor()`. **Manual visual verification is a required prerequisite before enabling this in any shared environment** — there is no automated browser coverage for the hero, so layout and CSS regressions are not caught by the suite. |
| `HIRE_AGENT_HERO_REDESIGN_ROLES` | Comma-separated roles the redesign applies to while enabled. Default `landlord` (the pilot). Independent of the master switch — both must agree. Widening this is a rollout decision, not a code change. |
| `HIRE_AGENT_DETAIL_REDESIGN_ENABLED` | Master gate for the redesigned Hire Agent listing **detail page** (M5) — section navigation, quick actions, sidebar, cards, photo gallery. Default `false` = inert. **Independent of the hero flag on purpose**: the hero is being visually verified while the detail rebuild is still in progress, so one must not move the other. Gating is read only via `HireAgentDetailRedesign::enabledFor($role)` — no view may gate on the master switch, because the page body and the shared shell disagreeing is what once let the body render redesign markup without the stylesheet that lays it out. `enabled()` still answers the master switch alone and is not a gate. Pairs with `HIRE_AGENT_DETAIL_REDESIGN_ROLES` below; both must agree. |
| `HIRE_AGENT_DETAIL_REDESIGN_ROLES` | Comma-separated roles the detail redesign applies to while enabled. Default `landlord` (the pilot). Independent of the master switch — both must agree, and this list is the only thing that grants a role the redesign. Widening it is a rollout decision, not a code change. Mirrors `HIRE_AGENT_HERO_REDESIGN_ROLES`; added in M7.1 when page layout moved into the shared shell all four roles render, so "which files exist" stopped being able to scope the pilot. |
| `CENSUS_GEOCODER_ENABLED` | Master gate for `CensusGeocoderAdapter` (G3) — the first non-Google coordinate provider. Default `false` = the adapter reports itself unavailable and is skipped without being called. **This flag carries more weight than the other gates in this table**: the US Census Geocoder needs no API key, so the missing credential that normally keeps an unfinished integration quiet does not exist here. Nothing else stands between the adapter and an outbound request. As of G3 the adapter is on no ladder, bound in no container and referenced by no flow, so enabling it changes nothing — assembling a ladder that includes it is G4/G5. |
| `CENSUS_GEOCODER_BENCHMARK` | Which vintage of the Census address-range corpus to match against. Default `Public_AR_Current`. Pinned explicitly rather than relying on the service default so a change on the Census side arrives as a config diff instead of as coordinates that quietly moved. Valid values come from `/geocoder/benchmarks?format=json`; an unrecognised one is rejected with HTTP 400 and surfaces as a provider fault, not as "this address does not exist". |
| `CENSUS_GEOCODER_TIMEOUT` / `CENSUS_GEOCODER_CACHE_TTL` / `CENSUS_GEOCODER_MAX_ADDRESS_LENGTH` | Request ceiling (default 10s), cache lifetime (default 30 days, keyed on the unit-free lookup line so every unit in a building shares one call), and the service's own 100-character address limit mirrored locally so an over-long address is declined before a request is spent on it. |
| `CENSUS_GEOCODER_HOURLY_CAP` / `CENSUS_GEOCODER_DAILY_CAP` | Request ceilings (G4), defaults 500/hour and 5,000/day. **Deliberately independent of price.** Census is free and publishes no rate limit, which is exactly why these exist: an observer firing per save or a page resolving per render turns one user action into thousands of requests, and against a free provider that produces no bill and no signal until the Bureau stops answering us. These are a backstop against a bug, not a capacity plan — raise them with evidence from telemetry. `null` disables a ceiling; prefer a high number, since a ceiling you can see in config beats one that is absent. Note `(int) null` is `0`, which would block everything — `config/census_geocoder.php` guards that explicitly. |
| `CENSUS_GEOCODER_BREAKER_THRESHOLD` / `_COOLDOWN` / `_WINDOW` | Circuit breaker (G4): 5 faults inside 600s opens the circuit for 300s, during which nothing is sent. Only genuine provider faults count — a no-match is the provider working correctly, and a rate-limit block is our own decision (counting it would let the breaker trip on our own rationing and stay open blaming Census). **Local rungs are never affected**: an open circuit must not stop a coordinate we already hold from being returned. |
| `CENSUS_GEOCODER_AMBIGUOUS_CACHE_TTL` | How long an ambiguous match is remembered (default 1 day, vs 30 for a clean hit or miss). Ambiguity is deterministic, so re-asking every render wastes budget — but it is usually the symptom of a thin address rather than a property of the world, so it expires sooner and a corrected ZIP is picked up the next day without anyone needing to know a cache exists. |
| `MLS_DIRECT_IMPORT_PREFILL_ENABLED` | Master gate for the Seller/Landlord **"Import by MLS #"** entry point on Create Offer Listing — the Bridge OData lookup that turns an MLS number into a facts-only prefill. Default `false` = inert: the input is not rendered and `HasMlsImport::importListingByMlsNumber()` returns early, so a hidden input or a hand-crafted Livewire call lands on the same answer as the UI. Read via `mlsNumberImportAvailable()`, which requires both this flag **and** a role in `mls_direct_import.prefill_roles` (`seller`, `landlord`). That role list is not a rollout dial — Buyer/Tenant listings describe search criteria across many areas rather than one property, so there is nothing to prefill. **Does not gate the pre-existing URL / raw-text importer** (`MlsListingImportService`), which is not a Bridge feature and keeps working in every environment regardless of this value. **Not the Match Check flag either**: `mls_match_check.enabled` gates a Buyer/Tenant scoring page that never writes a form, while this gates a Seller/Landlord write path into a listing — one switch governing both would mean enabling a read-only report also enabled form population. Enabling requires Bridge credentials (see `config/bridge.php`); with the flag on and credentials absent the lookup reports "MLS data service unavailable" rather than "listing not found". |
| `ADDRESS_POINT_CORPUS_ENABLED` | Master gate for `AddressPointCoordinateAdapter`, the ladder rung that reads our own address-point corpus. Default `false`. Off is not a placeholder: the corpus holds **zero rows** and no importer exists, so an enabled rung would spend a query per resolution to return `address_point_not_found` forever. Turn it on only after an import has been loaded and verified. Unlike the Census flag, an enabled rung here cannot reach the network — the worst case is a wasted local query. |
| `ADDRESS_POINT_CORPUS_VERSION` | Which `corpus_version` the rung reads. **Both this and the flag must be set** — an enabled rung with no version pinned reports itself unavailable rather than guessing which import to serve. Deliberately not "whatever the ledger says is active": two corpus versions coexisting is what makes a new import verifiable before it is trusted, and a rung that followed activation would start serving new coordinates the instant a ledger row flipped, with no deploy and no diff. |
| `ADDRESS_POINT_CORPUS_MAX_MATCHES` | How many corpus rows one lookup line may pull back (default 25). Rows sharing a normalized line are units of one building; a handful settles whether they agree on a point. A zero or negative value falls back to the default rather than silencing the rung. |
| `LOCATION_DNA_FLOOD_ZONE_MAX_AREA` | FEMA API bounding-box threshold in sq-degrees |
| `OFFER_PLAYOFF_ALLOWED_IDS` | Comma-separated user IDs or `*` for all |

`.env` is not tracked in git — back it up separately.
