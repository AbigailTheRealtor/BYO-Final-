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
| `LOCATION_DNA_FLOOD_ZONE_MAX_AREA` | FEMA API bounding-box threshold in sq-degrees |
| `OFFER_PLAYOFF_ALLOWED_IDS` | Comma-separated user IDs or `*` for all |

`.env` is not tracked in git — back it up separately.
