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

### Hire Agent compatibility preferences — the allowlist boundary

`compatibility_preferences` is one EAV meta blob per listing, keyed `{role}_specific`. **Every write
goes through `CompatibilityPreferencePolicy::project()`** (`app/Support/HireAgent/`), which rebuilds
the sub-array from the allowlist in `config/hire_agent_compatibility_keys.php` — that config has one
reader and a test asserts it.

**Validation cannot do this job, and assuming it could is what made the policy necessary.**
`$compatibility_preferences` is a public Livewire property, so a client can set any nested path;
`validate()` checks the keys named in `rules()` and leaves the rest on the property; the persist then
wrote the sub-array verbatim. A `prohibited` rule narrows only the paths that reach full validation,
and a draft save does not. So the gate is at the write, it is an **intersection** (a key survives by
being named, never by escaping a deny-list), and it covers Create, Save Draft, Save Edit and the
three still-routed legacy per-role create components.

**Landlord keys are scoped by property type.** `preferred_business_use` /
`preferred_business_use_other` are commercial-only. Anything that is not exactly
`Commercial Property` — null, `''`, a legacy spelling — is treated as residential, because
`property_type` is EAV and can be absent on an older row. **On Edit the STORED property type governs**
(`propertyTypeForProjection()`): one request can otherwise flip the listing commercial and supply the
commercial-only key in the same message.

**Retired for Fair Housing reasons, and not to be reintroduced:**
`tenant_type_preference` / `tenant_type_preference_other` (mixed occupant categories — Individual /
Family, Young Professionals, Students — with business ones, rendered on residential and commercial
listings alike, and published on a route with no auth middleware). Residential has **no** replacement
occupant question. Commercial answers **Preferred Business Use** instead; its options live in
`config/landlord_business_use_options.php`. Landlord `risk_tolerance` became
`applicant_screening_approach` (method, not tolerance) and is `informational_context` only — never a
trait slot, because a slot is what a future scorer reads. Buyer `risk_tolerance` is unrelated and
stays. `HireAgentFairHousingWordingTest` guards the wording and the keys at source.

`php artisan hireagent:retire-tenant-type` remediates stored values. It is a command rather than a
migration because nothing schema-shaped changes and `deploy/start-production.sh` is the single
migration owner. **Not yet run against any database, and running `--write` against one requires
separate explicit approval.**

It runs in **two phases, and the order is the safety property**. *Phase A* plans: it scans every
candidate row and computes each affected listing's exact original and remediated blob, writing
nothing in either mode — the default invocation is Phase A plus a report, and creates no backup row,
no rollback record and no timestamp change. *Phase B* is reached only under `--write`: every affected
listing's original is persisted, **then every backup is read back out of the database and
checksum-verified, and only then does the first compatibility blob change.** Any failure in backup or
verification aborts with `FAILURE` having performed zero remediation writes.

**The rollback record lives in the database, not the filesystem** — one row per listing in
`landlord_agent_auction_metas` under `fair_housing_backup_compatibility_preferences`, holding the
original bytes, a SHA-256 over them and the run id. `storage/app` was the wrong home: the Replit
container is rebuilt from the image on deploy and on restart, `storage/` has no persistent mount and
the file is not in git, so the undo evaporated while the deletion stayed. **Nothing at runtime
resolves that key** — every meta consumer reads a named key, `$auction->get->namedKey`, or an
explicit field whitelist (`LandlordFieldMap::sections()`, Ask AI's `CANONICAL_SOURCE_MAP`), so the
row is inert to the application. It is **written once per listing and never overwritten**, so the
path back to the value the landlord actually submitted survives any re-dirty / re-remediate cycle.

`--list-backups` (read-only) lists the restorable runs; `--restore=RUN_ID` undoes one **named** run —
no default, no "latest", and one unreadable envelope anywhere refuses the whole restore, because an
envelope that cannot be decoded is one whose run cannot be ruled out. Restore is idempotent and
leaves the backup rows in place.

**Malformed rows are skipped, never repaired, and now counted.** A blob that is not valid JSON and a
`landlord_specific` that is present but not an object are reported separately, and the run ends with
an explicit `REMEDIATION INCOMPLETE` warning — those listings may still hold retired values. A
`landlord_specific` that is simply *absent* is normal and is not counted.

**Detail-page visibility.** Representation rows are built in two buckets: `$repAdd()` is public,
`$repAddOwn()` is owner-only (free text, screening posture, and the seller's own motivation and price
firmness). The gate is `$hlaViewerIsOwner`, resolved in the four controllers from
`HireAgentProposalAccess::isListingOwner()` — **the ownership relationship, not the audience tier**,
because `audienceFor()` resolves widest-match-first and would otherwise hide an owner's own answers
from them whenever that owner also holds an agent account.

### Landlord applicant screening — the second write boundary (Fair Housing Phase 2)

The landlord **Applicant Requirements** tab has its own allowlist, separate from the Hire Agent
one and for the same reason. Every screening key is a public Livewire property that `saveMeta()`
wrote verbatim, and the audit found **no validation rule referencing any of them** — so deleting
an `<option>` changed the form and nothing else.

`config/landlord_screening_options.php` is the SSOT. It has exactly two readers: the Blade
partial that renders the options and `LandlordScreeningPolicy` (`app/Support/OfferListing/`)
that enforces them on the write; a test asserts both. Every governed write on Create **and**
Edit goes through `LandlordScreeningPolicy::project()`, which is an **intersection** — a value
survives by being named in the allowlist, never by escaping a deny-list.

**One partial serves Create and Edit** (`applicant-requirements.blade.php`), so option lists
cannot drift between the two wizards. That is structural, and a test pins it.

**The policy must not depend on a booted container.** It is called from Blade, from both Livewire
components, and from the Ask AI landlord extractor — and that extractor's unit test extends
PHPUnit's `TestCase` directly with no application. A `config()` call there raises,
`AskAiContextBuilderService::buildForListing()` catches every `Throwable`, and the symptom is not
a missing screening key but an **entirely empty listing context**, with the real error swallowed
several frames away. `LandlordScreeningPolicy::conf()` uses the container when one is bound and
reads the file when it is not; a test runs the policy in a separate process to prove it.

**Retired for Fair Housing reasons, and not to be reintroduced:** `employment_requirement`,
`custom_employment_requirement`, `employment_verification_requirement`. They required an
employment *status* ("Employed", "Retired allowed", "Student allowed"), which gates tenancy on
how income is earned rather than whether rent can be paid. **There is no replacement control** —
`income_qualification_method`, `min_income_requirement` / `min_monthly_income_fixed` and
`income_verification_requirement` (relabelled *Income documentation required*) already ask the
objective question, and the income block carries fixed copy stating that all lawful verifiable
income counts. Do not add an "accepted income sources" checklist: a landlord who ticks everything
except benefit letters has rebuilt source-of-income exclusion inside a field that looks neutral.

**Stale values are suppressed or normalized, and the difference is deliberate.** A blanket
`No criminal background` is **suppressed** — rendering it as `Individualized review of
convictions` would credit a listing with a process it never had. `Case-by-case review` is
**normalized** forward, because the meaning survives the rename. `Compensating factors
considered` normalizes to the generic documented-criteria wording, **not** to the new deposit /
co-signer option, which would assert a remedy the landlord never chose. No rows are deleted;
remediation is a later backup-first operation.

**The rental qualification page is a second publication of these values** on a route with **no
auth middleware** (`offer.listing.landlord.qualification.check`). It reads landlord policy to
show applicants what is required — the safe direction — but it must resolve through
`LandlordScreeningPolicy::displayValue()`, or a retired requirement stays published there after
the field that set it is gone.

**`criminal_background` is not `criminal_background_requirement`.** The first is what an
*applicant* discloses about themselves on the legacy qualification and offer-terms forms; the
second is what a *landlord* requires. They share the option string `No criminal background` and
are one word apart. `LegacyApplicantDisclosureContainmentTest` fails if anything ever aliases one
onto the other. The legacy applicant *controls* are deferred work and were not changed.

### Deployment & migrations

**`deploy/start-production.sh` is the only thing that runs migrations.** The Replit `[deployment] run` command invokes it; it reports via `deploy:preflight`, then runs `php artisan migrate --force`, then serves — and a failed migration stops the deploy rather than serving against an old schema.

Nothing else may migrate: not `deploy/scheduler.sh`, not the build phase, not a second web process. This app is on **Laravel 8, which has no `migrate --isolated`**, so there is no migration lock and concurrency safety rests entirely on single ownership. `DeploymentMigrationReadinessTest` asserts all of it.

`scripts/post-merge.sh` also migrates, but it is the Replit **workspace** `[postMerge]` hook — it does not fire on deploy or on a GitHub merge. Do not treat it as the deployment's migration step; that assumption is exactly how G4's migration reached `main` and never reached a schema.

Two CI gates: `migration-tests.yml` (`migrate:fresh`, empty DB) and `incremental-migration-tests.yml` (previous-release schema, populated, migrated forward — the operation a deploy actually performs).

**`deploy/start-production.sh` also gates on the required product flags, and that gate can fail a deploy.** `php artisan deploy:require-flags` runs after `deploy:preflight` and **before `migrate`** — with no `|| true`, so `set -euo pipefail` stops the deploy before any schema change and long before a port is bound. The contract is `config/required_production_flags.php`: the Hire Agent hero and detail redesigns enabled for all four roles, and both MLS direct-import surfaces enabled.

**The defaults and the gate solve different halves of the same problem.** The shipped config defaults (now `true` / all four roles) cover the **absent** variable — an environment that supplies nothing serves the modern platform. The gate covers the variable that is **present and wrong**: a stale secret, a typo, a value left behind by a finished pilot. That failure is the invisible one — the app boots, answers 200, passes its health check, and serves the superseded surface until somebody notices. It is exactly what happened when these six values lived only in a machine-local `.env` that a container rebuild discarded.

**The contract may never name a safety switch.** BYA compatibility (kill switch and GA), every Location DNA gate, the Census geocoder, the address-point corpus, MLS Match Check, DNA score generation, Matching V2 persistence and the Bridge credentials are all excluded, and `RequiredProductionFlagsTest` asserts the exclusion rather than leaving it to reviewer memory — otherwise the gate would become a deploy-time mechanism for enabling a consumer-facing or spend-incurring feature, decided in a file nobody reads during a rollout conversation. The command is **read-only in both directions**: it compares and reports, so a wrong value stops a deploy and is never silently corrected.

Role lists are compared as a **subset**, so adding a fifth role later cannot fail a deployment for being extra. An **empty or unreadable contract fails closed** — a config file that did not load is indistinguishable from one that requires nothing, and that ambiguity is not safe to resolve as "pass". `REQUIRED_PRODUCTION_FLAGS_ENFORCED=false` downgrades it to a loud warning.

`deploy/start-serving.sh` (the Replit **workspace** workflow) calls the same command as `deploy:require-flags --report || true` — **informational only, and it can refuse nothing**. `--report` exits 0 whatever it finds and `|| true` catches even an unexpected fault, because turning a flag off locally to test the legacy path is legitimate work and a visibility line must never cost somebody their local server.

Version-controlled defaults are proven by `RequiredProductionDefaultsTest`, which evaluates the config files in a child process with all six variables **removed** — the container-rebuild scenario reproduced rather than described.

`ProvenanceSchemaReadiness` is the runtime backstop: when the provenance columns are absent, coordinate writes proceed and provenance is skipped with `schema_not_ready` rather than raising `SQLSTATE[42703]` inside a listing save. See `deploy/DEPLOYMENT.md`, which also documents an **open question about `APP_DEBUG` in deployments**.

**`PHP_INI_SCAN_DIR` must be added to, never assigned.** All three entrypoints (`start-production.sh`, `start-serving.sh`, `scheduler.sh`) apply `deploy/php/uploads.ini` through `configure_php_ini_scan_dir` in `deploy/lib/php-runtime.sh`, which resolves the interpreter's own scan directory at run time and prepends it. A bare `export PHP_INI_SCAN_DIR="$PWD/deploy/php"` **replaces** that directory — on this Nix build it is where every extension is declared, so the assignment delivered the upload limits and silently unloaded PDO, pdo_pgsql and tokenizer, taking production from 54 extensions to 12. The symptom was `deploy:migrations-pending` reporting `error=repository_unreadable` and the restart correctly refusing to serve, against a database that was entirely healthy. The documented `":$dir"` shorthand does **not** work here (`PHP_CONFIG_FILE_SCAN_DIR` is defined but empty on this build), and the resolved path must never be hardcoded — it is a store path whose hash moves with the Nix channel. `PhpIniScanDirTest` runs the real scripts and proves the resulting runtime keeps its extensions *and* its raised limits.

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
| `HIRE_AGENT_HERO_REDESIGN_ENABLED` | Master gate for the redesigned Hire Agent hero (M4). **Default `true` as of the required-modern-platform-defaults change** — it shipped `false` so the merge was inert, and that pilot is over. A `false` default now describes a regression rather than an inert merge: it is what let a container rebuild, which discarded the machine-local `.env` these values lived in, silently restore the superseded hero. Read only via `HireAgentHeroData::redesignEnabledFor()`. Set to `false` to roll back — still an environment change, not a revert. Required to be `true` in production by `config/required_production_flags.php`. Manual visual verification remains the prerequisite before changing the hero itself; there is no automated browser coverage. |
| `HIRE_AGENT_HERO_REDESIGN_ROLES` | Comma-separated roles the redesign applies to while enabled. **Default `seller,buyer,landlord,tenant`** (was `landlord`, the pilot). Independent of the master switch — both must agree. All four is the default because a partial list produces a visibly mixed platform, which reads as a rendering bug rather than as a missing variable. Narrowing it is a rollout decision, not a code change. The production contract requires all four to be present, as a **subset** test, so adding a fifth role later does not fail a deploy. |
| `HIRE_AGENT_DETAIL_REDESIGN_ENABLED` | Master gate for the redesigned Hire Agent listing **detail page** (M5) — section navigation, quick actions, sidebar, cards, photo gallery. **Default `true`**, for the same reason as the hero flag above. **Still independent of the hero flag on purpose**: the two moved together once, which is a fact about today's values, not a merger of the switches. Gating is read only via `HireAgentDetailRedesign::enabledFor($role)` — no view may gate on the master switch, because the page body and the shared shell disagreeing is what once let the body render redesign markup without the stylesheet that lays it out. `enabled()` still answers the master switch alone and is not a gate. The reader keeps its own `false` fallback for a **missing** key — a config that failed to load must still read as off, which is a different question from the default. Pairs with `HIRE_AGENT_DETAIL_REDESIGN_ROLES`; both must agree. Required `true` in production. |
| `HIRE_AGENT_DETAIL_REDESIGN_ROLES` | Comma-separated roles the detail redesign applies to while enabled. **Default `seller,buyer,landlord,tenant`** (was `landlord`, the pilot). Independent of the master switch — both must agree, and this list is the only thing that grants a role the redesign. Mirrors `HIRE_AGENT_HERO_REDESIGN_ROLES`; added in M7.1 when page layout moved into the shared shell all four roles render, so "which files exist" stopped being able to scope the pilot. Required in production as a subset. |
| `CENSUS_GEOCODER_ENABLED` | Master gate for `CensusGeocoderAdapter` (G3) — the first non-Google coordinate provider. Default `false` = the adapter reports itself unavailable and is skipped without being called. **This flag carries more weight than the other gates in this table**: the US Census Geocoder needs no API key, so the missing credential that normally keeps an unfinished integration quiet does not exist here. Nothing else stands between the adapter and an outbound request. As of G3 the adapter is on no ladder, bound in no container and referenced by no flow, so enabling it changes nothing — assembling a ladder that includes it is G4/G5. |
| `CENSUS_GEOCODER_BENCHMARK` | Which vintage of the Census address-range corpus to match against. Default `Public_AR_Current`. Pinned explicitly rather than relying on the service default so a change on the Census side arrives as a config diff instead of as coordinates that quietly moved. Valid values come from `/geocoder/benchmarks?format=json`; an unrecognised one is rejected with HTTP 400 and surfaces as a provider fault, not as "this address does not exist". |
| `CENSUS_GEOCODER_TIMEOUT` / `CENSUS_GEOCODER_CACHE_TTL` / `CENSUS_GEOCODER_MAX_ADDRESS_LENGTH` | Request ceiling (default 10s), cache lifetime (default 30 days, keyed on the unit-free lookup line so every unit in a building shares one call), and the service's own 100-character address limit mirrored locally so an over-long address is declined before a request is spent on it. |
| `CENSUS_GEOCODER_HOURLY_CAP` / `CENSUS_GEOCODER_DAILY_CAP` | Request ceilings (G4), defaults 500/hour and 5,000/day. **Deliberately independent of price.** Census is free and publishes no rate limit, which is exactly why these exist: an observer firing per save or a page resolving per render turns one user action into thousands of requests, and against a free provider that produces no bill and no signal until the Bureau stops answering us. These are a backstop against a bug, not a capacity plan — raise them with evidence from telemetry. `null` disables a ceiling; prefer a high number, since a ceiling you can see in config beats one that is absent. Note `(int) null` is `0`, which would block everything — `config/census_geocoder.php` guards that explicitly. |
| `CENSUS_GEOCODER_BREAKER_THRESHOLD` / `_COOLDOWN` / `_WINDOW` | Circuit breaker (G4): 5 faults inside 600s opens the circuit for 300s, during which nothing is sent. Only genuine provider faults count — a no-match is the provider working correctly, and a rate-limit block is our own decision (counting it would let the breaker trip on our own rationing and stay open blaming Census). **Local rungs are never affected**: an open circuit must not stop a coordinate we already hold from being returned. |
| `CENSUS_GEOCODER_AMBIGUOUS_CACHE_TTL` | How long an ambiguous match is remembered (default 1 day, vs 30 for a clean hit or miss). Ambiguity is deterministic, so re-asking every render wastes budget — but it is usually the symptom of a thin address rather than a property of the world, so it expires sooner and a corrected ZIP is picked up the next day without anyone needing to know a cache exists. |
| `MLS_DIRECT_IMPORT_PREFILL_ENABLED` | Master gate for the Seller/Landlord **"Import by MLS #"** entry point on Create Offer Listing — the Bridge OData lookup that turns an MLS number into a facts-only prefill. **Default `true`** (was `false`): the owner has enabled it and it is verified in production, and the off default has since done the only harm it can do — removing a working entry point from the form with no error anywhere, indistinguishable from the feature having been withdrawn. `false` = inert: the input is not rendered and `HasMlsImport::importListingByMlsNumber()` returns early, so a hidden input or a hand-crafted Livewire call lands on the same answer as the UI. Read via `mlsNumberImportAvailable()`, which requires both this flag **and** a role in `mls_direct_import.prefill_roles` (`seller`, `landlord`). That role list is not a rollout dial — Buyer/Tenant listings describe search criteria across many areas rather than one property, so there is nothing to prefill. **Does not gate the pre-existing URL / raw-text importer** (`MlsListingImportService`), which is not a Bridge feature and keeps working regardless. **Not the Match Check flag either**: `mls_match_check.enabled` gates a Buyer/Tenant scoring page that never writes a form, while this gates a Seller/Landlord write path into a listing. **This default does not supply credentials** — Bridge credentials are still required (see `config/bridge.php`); with the flag on and credentials absent the lookup reports "MLS data service unavailable" rather than "listing not found". Required `true` in production. |
| `MLS_DIRECT_IMPORT_QUICK_IMPORT_ENABLED` | Master gate for the shortened Seller/Landlord MLS quick-import path: enter an MLS #, have the property portion of the listing built for you, answer only the BidYourOffer transaction questions, review, publish. **Default `true`** (was `false`). **Separate from `MLS_DIRECT_IMPORT_PREFILL_ENABLED` and still an additional gate, never a replacement one** — both must be on for the flow to be reachable, because prefill adds an input to a form the user is already filling in while this adds a whole creation path that writes a draft listing. Role scope is `mls_direct_import.prefill_roles`, one list, so the two surfaces cannot drift about which roles the feature exists for. Deliberately **not** tied to `config/mls_media.php`: the flow's promise is delivered by the facts alone, which is what lets the media licence be settled on its own timetable. Required `true` in production. |
| `REQUIRED_PRODUCTION_FLAGS_ENFORCED` | Whether `deploy:require-flags` **gates** a production start or merely warns. Default `true`. See the Deployment & migrations section above — this is the escape hatch, not a rollout dial, and the command announces in capitals when it is taken. Setting it `false` does not change any product flag; it only stops the gate refusing. |
| `ADDRESS_POINT_CORPUS_ENABLED` | Master gate for `AddressPointCoordinateAdapter`, the ladder rung that reads our own address-point corpus. Default `false`. Off is not a placeholder: the corpus holds **zero rows** and no importer exists, so an enabled rung would spend a query per resolution to return `address_point_not_found` forever. Turn it on only after an import has been loaded and verified. Unlike the Census flag, an enabled rung here cannot reach the network — the worst case is a wasted local query. |
| `ADDRESS_POINT_CORPUS_VERSION` | Which `corpus_version` the rung reads. **Both this and the flag must be set** — an enabled rung with no version pinned reports itself unavailable rather than guessing which import to serve. Deliberately not "whatever the ledger says is active": two corpus versions coexisting is what makes a new import verifiable before it is trusted, and a rung that followed activation would start serving new coordinates the instant a ledger row flipped, with no deploy and no diff. |
| `ADDRESS_POINT_CORPUS_MAX_MATCHES` | How many corpus rows one lookup line may pull back (default 25). Rows sharing a normalized line are units of one building; a handful settles whether they agree on a point. A zero or negative value falls back to the default rather than silencing the rung. |
| `LOCATION_DNA_FLOOD_ZONE_MAX_AREA` | FEMA API bounding-box threshold in sq-degrees |
| `CRITERIA_LDNA_GEOGRAPHY_SOURCE` | Which `CriteriaGeographyRepository` backs the geography cascade. **Exactly three values are accepted** — `eloquent` (default; the `us_*` reference tables), `census` (the `census_*` corpus from `census:import-geography`), `fake` (in-memory fixture, local/demo only). **Anything else throws at container resolution.** That is deliberate: the binding used to fall through to `eloquent`, so a typo silently served legacy data and looked exactly like success. Selecting `census` requires the corpus to be present — run `php artisan census:verify-geography` first, and in the deploy sequence of any environment using it, or every tier enumerates empty with no error. |
| `CRITERIA_LDNA_PREVIEW_ENABLED` | Geography preview surface. Default `false`. |
| `OFFER_PLAYOFF_ALLOWED_IDS` | Comma-separated user IDs or `*` for all |

`.env` is not tracked in git — back it up separately.
