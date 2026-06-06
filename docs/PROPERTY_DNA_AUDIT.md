# Property DNA Audit Document

**Date:** 2026-06-06  
**Scope:** Storage, generation, triggering, display, and gaps — no production code changes.  
**Purpose:** Single authoritative reference for the Property DNA system, code-verified.

---

## Q1 — What table stores Property DNA?

**Table: `property_dna_profiles`**

Defined in two migrations:

- **`database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php`** — creates the full table with all score columns, JSON columns, reserved enrichment columns, indexes, and timestamps.
- **`database/migrations/2026_06_01_000001_add_location_intelligence_context_to_property_dna_profiles.php`** — adds one column: `location_intelligence_context` (JSON, nullable) after `ai_marketing_hooks`.

---

## Q2 — What model represents it?

**`App\Models\PropertyDnaProfile`** (`app/Models/PropertyDnaProfile.php`)

- Uses `$table = 'property_dna_profiles'`
- All columns declared in `$fillable` and `$casts`
- No relationships defined on the model itself

---

## Q3 — What service generates it?

**`App\Services\Dna\PropertyDnaGenerator`** (`app/Services/Dna/PropertyDnaGenerator.php`)

- Entry point: `generate(string $listingType, int $listingId): void`
- Loads `PropertyAuction` (seller) or `LandlordAuction` (landlord) with eager-loaded `->meta`
- Calls `mapDimensions()` → `buildArchetypeTags()` + `buildMarketingHooks()` + `computeCompleteness()` + `computeScores()`
- Calls `persist()` which uses `DB::transaction()` + PostgreSQL advisory lock (`pg_advisory_xact_lock`)
- **Append-only:** archives the previous version (sets `archived_at = now()`) then inserts a new row with `version + 1`

A downstream read/write service also exists:

**`App\Services\Dna\PropertyIntelligenceProfileService`** (`app/Services/Dna/PropertyIntelligenceProfileService.php`)

- Not a generator — it reads a persisted profile and assembles a "Property Intelligence Profile" output array
- Its `generate()` method makes one bounded write: persists `location_intelligence_context` back onto the `PropertyDnaProfile` row
- Its `buildPayloadReadOnly()` method is fully side-effect-free

---

## Q4 — When does it run when a Seller Listing is created or updated?

**Trigger chain (seller):**

1. `PropertyAuction::observe(PropertyAuctionDnaObserver::class)` registered in `AppServiceProvider::boot()`
2. `PropertyAuctionDnaObserver::saved(PropertyAuction $listing)` fires on every `saved` event (create OR update)  
   Source: `app/Observers/Dna/PropertyAuctionDnaObserver.php`
3. Dispatches `ComputePropertyDnaProfile::dispatch('seller', $listing->id)`
4. `ComputePropertyDnaProfile` (job, `app/Jobs/ComputePropertyDnaProfile.php`) calls `PropertyDnaGenerator->generate('seller', $listingId)`

**Queue driver:** `QUEUE_CONNECTION=sync` in `.env` and `.env.example` — jobs run **synchronously inline**, not on a background worker.

**Job config:** `$tries = 3`, `$timeout = 120`

---

## Q5 — Does it run for Landlord listings too?

**Yes.**

`LandlordAuction::observe(LandlordAuctionDnaObserver::class)` registered in `AppServiceProvider::boot()`  
`LandlordAuctionDnaObserver::saved(LandlordAuction $listing)` dispatches `ComputePropertyDnaProfile::dispatch('landlord', $listing->id)`

`PropertyDnaGenerator::generate()` explicitly handles `'landlord'` as a separate branch (loads `LandlordAuction` instead of `PropertyAuction`).

**Does NOT run for:** `BuyerCriteriaAuction` or `TenantCriteriaAuction`. Those have their own separate observers (`BuyerCriteriaAuctionDnaObserver`, `TenantCriteriaAuctionDnaObserver`) and write to a **different table** (`buyer_tenant_dna_profiles` via `BuyerTenantDnaProfile` model) — that is a separate "Demand DNA" system, not Property DNA.

---

## Q6 — What fields/JSON keys are saved?

### Native columns on `property_dna_profiles`

Source: `database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php` and `database/migrations/2026_06_01_000001_add_location_intelligence_context_to_property_dna_profiles.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `listing_type` | string | `'seller'` or `'landlord'` |
| `listing_id` | unsignedBigInteger | FK to source listing |
| `version` | integer | Monotonically increasing per listing |
| `source_listing_updated_at` | timestamp | Snapshot of `updated_at` from source listing |
| `physical_score` | decimal(5,2) | Coverage metric (not quality score) |
| `financial_score` | decimal(5,2) | Coverage metric |
| `location_score` | decimal(5,2) | **Always null** — generator sets it to `null` explicitly |
| `condition_score` | decimal(5,2) | **Always null** — generator sets it to `null` explicitly |
| `legal_score` | decimal(5,2) | **Always null** — generator sets it to `null` explicitly |
| `flexibility_score` | decimal(5,2) | Coverage metric |
| `occupant_qualification_score` | decimal(5,2) | Coverage metric (occupant policy completeness; NOT a screening score) |
| `marketing_score` | decimal(5,2) | Coverage metric |
| `compatibility_score` | decimal(5,2) | **Always null** — generator sets it to `null` explicitly |
| `commercial_score` | decimal(5,2) | Coverage metric |
| `overall_dna_completeness` | decimal(5,2) | % of 29 dimension slots populated |
| `ai_buyer_archetype_tags` | JSON array | e.g. `["type:single-family","amenity:pool","parking:garage"]` |
| `ai_marketing_hooks` | JSON array of objects | e.g. `[{"trait":"bedrooms","value":"3"}]` |
| `location_intelligence_context` | JSON (added in second migration) | Populated by `PropertyIntelligenceProfileService`, not the generator |
| `walk_score` | integer | **Reserved / Not Implemented (F-01)** — always null |
| `transit_score` | integer | **Reserved / Not Implemented (F-01)** — always null |
| `bike_score` | integer | **Reserved / Not Implemented (F-01)** — always null |
| `school_rating` | decimal(5,2) | **Reserved / Not Implemented (F-02)** — always null |
| `flood_zone_verified` | string | **Reserved / Not Implemented (F-03)** — always null |
| `estimated_monthly_utilities` | decimal(10,2) | **Reserved / Not Implemented (F-05)** — always null |
| `computed_at` | timestamp | When this version was computed |
| `archived_at` | timestamp, nullable | Set when superseded by a newer version |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 29 dimension slots mapped by the generator

Source: `DIMENSION_SLOTS` constant in `app/Services/Dna/PropertyDnaGenerator.php`

`property_type`, `property_style`, `property_condition`, `bedrooms`, `bathrooms`, `minimum_sqft`, `total_acreage`, `has_pool`, `has_garage`, `has_carport`, `has_storage`, `pets_allowed`, `is_55_plus`, `is_commercial`, `smoking_policy_specified`, `has_hoa`, `furnishing_indicator`, `move_in_timing`, `occupant_status`, `lease_length_flexibility`, `has_lease_option`, `has_lease_purchase`, `has_seller_financing`, `has_assumable_loan`, `sale_provision_type`, `offered_financing_types`, `interested_in_selling`, `has_video_tour`, `view_preference`

### Scores actually populated by the generator (6 of 10)

Source: `computeScores()` in `app/Services/Dna/PropertyDnaGenerator.php`

- **`physical_score`** — fields: `property_type`, `property_style`, `property_condition`, `bedrooms`, `bathrooms`, `minimum_sqft`, `total_acreage`, `has_pool`, `has_garage`, `has_carport`, `has_storage`
- **`financial_score`** — fields: `offered_financing_types`, `has_seller_financing`, `has_assumable_loan`, `has_lease_option`, `has_lease_purchase`, `sale_provision_type`
- **`flexibility_score`** — fields: `has_lease_option`, `has_lease_purchase`, `has_seller_financing`, `lease_length_flexibility`, `sale_provision_type`
- **`occupant_qualification_score`** — fields: `is_55_plus`, `pets_allowed`, `occupant_status`, `smoking_policy_specified`, `furnishing_indicator`
- **`marketing_score`** — fields: `has_video_tour`, `has_pool`, `view_preference`, `has_garage`
- **`commercial_score`** — fields: `is_commercial`

### Scores hardcoded null by the generator (4 of 10)

Source: `generate()` method in `app/Services/Dna/PropertyDnaGenerator.php` (explicit `null` assignments in the `persist()` call)

- `location_score`, `condition_score`, `legal_score`, `compatibility_score`

---

## Q7 — Are records actually being created?

**Architecturally: yes** — the pipeline is complete end-to-end. Observer is registered, job is queued synchronously, generator handles both seller and landlord. However:

**Runtime caveat:** Since `QUEUE_CONNECTION=sync`, the DNA generation runs inline on every `PropertyAuction::saved()` and `LandlordAuction::saved()` event. If a listing save occurs and the observer fires, a row will be created. Whether records exist in the actual database cannot be verified without a live DB query — no seed or factory was found in the audit. The `DnaInspectorController::propertyIndex` view will show "No records found" if none have been computed yet.

**No seeder or factory for `PropertyDnaProfile` was found** in the audit.

Source: `app/Observers/Dna/PropertyAuctionDnaObserver.php`, `app/Jobs/ComputePropertyDnaProfile.php`, `app/Services/Dna/PropertyDnaGenerator.php`

---

## Q8 — Is there any UI where admin/agent/seller can see it?

### Admin views (full DNA inspector access)

Source: `app/Http/Controllers/Admin/DnaInspectorController.php`, `app/Http/Controllers/Admin/DnaProfileController.php`, `app/Http/Controllers/Admin/AiMarketingReportAdminController.php`, `routes/web.php`

| Route | Controller | View | What it shows |
|---|---|---|---|
| `GET /admin/dna/property` | `DnaInspectorController::propertyIndex` | `admin.dna.property.index` | Paginated list of all property DNA profiles with key scores, filterable by listing_type, listing_id, version, status, date |
| `GET /admin/dna/property/{id}` | `DnaInspectorController::propertyShow` | `admin.dna.property.show` | All versions for a listing; current vs archived |
| `GET /admin/dna/profiles/seller/{listingId}` | `DnaProfileController::seller` | `admin.dna.seller` | Full DNA profile detail: scores, archetype tags with explanations, marketing hooks, property personality |
| `GET /admin/dna/profiles/landlord/{listingId}` | `DnaProfileController::landlord` | `admin.dna.landlord` | Same as above for landlord |
| `GET /admin/property-dna/{profile}/marketing-brief-preview` | `DnaInspectorController::marketingBriefPreview` | `admin.dna.marketing-brief-preview` | Marketing brief preview + button to trigger AI marketing report generation |
| `GET /admin/property-dna/marketing-reports/{report}` | `AiMarketingReportAdminController::show` | `admin.dna.marketing-report-show` | Full AI marketing report review + publish action |

**Admin sidebar** has a "DNA Inspector" dropdown with: Property DNA, Demand DNA, Coverage Scores.  
Source: `resources/views/layouts/partials/admin_sidebar.blade.php`

### Agent views (marketing reports only)

Source: `app/Http/Controllers/Agent/PropertyMarketingBriefReviewController.php`, `app/Http/Controllers/Agent/AiMarketingReportAgentController.php`, `routes/web.php`

- `GET /agent/property-dna/{profile}/marketing-brief-review` → `PropertyMarketingBriefReviewController`
- `GET /agent/property-dna/marketing-reports/{report}` → `AiMarketingReportAgentController::show` → `agent.dna.marketing-report-show`
- `POST /agent/property-dna/marketing-reports/{report}/sections/{section}` — agent edits report sections

### Owner/seller views (marketing reports only)

Source: `app/Http/Controllers/Owner/AiMarketingReportOwnerApprovalController.php`, `routes/web.php`

- `GET /owner/property-dna/marketing-reports/{report}/approval` → `AiMarketingReportOwnerApprovalController::show` → `owner.dna.marketing-report-approval`
- `POST /owner/property-dna/marketing-reports/{report}/approve`
- `POST /owner/property-dna/marketing-reports/{report}/reject`

**No seller or agent view exposes the raw DNA scores or archetype tags — those are admin-only.**

---

## Q9 — Is there a regenerate action?

**No dedicated "regenerate DNA" route or admin action exists.**

Generation is triggered exclusively by model observer events (`PropertyAuction::saved`, `LandlordAuction::saved`). The only way to regenerate a DNA profile today is to resave the source listing (which fires the observer and dispatches the job).

The `marketing-brief-preview` admin page has a "Generate Marketing Report" button — but that generates an **AI marketing report from an existing DNA profile**, not a new DNA profile itself.

No artisan command, no admin form, and no route was found that dispatches `ComputePropertyDnaProfile` directly.

Source: `routes/web.php`, `app/Http/Controllers/Admin/DnaInspectorController.php`

---

## Q10 — What is missing?

The gaps below are confirmed from code inspection. They are distinct from the "Reserved / Not Implemented" columns, which are intentionally placeholder schema entries documented as such in the migrations.

### Confirmed gaps (from code)

**G-01 — Four score columns are always null.**  
`location_score`, `condition_score`, `legal_score`, and `compatibility_score` are hardcoded to `null` in `PropertyDnaGenerator::generate()` (`app/Services/Dna/PropertyDnaGenerator.php`). No future service writes them — only `location_intelligence_context` is written downstream by `PropertyIntelligenceProfileService`. These four score columns are schema dead weight until a future phase populates them.

**G-02 — Reserved enrichment fields are always null.**  
`walk_score`, `transit_score`, `bike_score`, `school_rating`, `flood_zone_verified`, `estimated_monthly_utilities` — all documented as "Reserved / Future Use Only — Not Implemented" in the migration (`database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php`) and surfaced as always-null in the admin view.

**G-03 — No admin "regenerate" button.**  
To force a re-compute, an admin must resave the source listing through the application UI or directly in the DB. There is no artisan command or admin form that dispatches `ComputePropertyDnaProfile` directly. Source: `routes/web.php`, `app/Http/Controllers/Admin/DnaInspectorController.php`.

**G-04 — No seller/agent view of raw DNA scores or archetype tags.**  
Agents only see AI marketing reports (downstream output, via `agent.dna.marketing-report-show`), not the underlying dimension completeness or archetype tags that produced them. Source: `routes/web.php`, `app/Http/Controllers/Agent/AiMarketingReportAgentController.php`.

**G-05 — No seeder or test factory for `property_dna_profiles`.**  
Tests for `SellerDnaReportService`, `LandlordDnaReportService`, `PropertyPersonalityService`, and `AiMarketingReportPersistenceService` build in-memory stubs only — there is no DB-backed test verifying that the observer → job → generator → persist chain actually creates rows. Source: `tests/Unit/Services/Dna/SellerDnaReportServiceTest.php`, `tests/Unit/Services/Dna/LandlordDnaReportServiceTest.php`, `tests/Unit/Services/Dna/AiMarketingReportPersistenceServiceTest.php`.

**G-06 — `interested_in_selling` dimension is semantically irrelevant for landlord listings.**  
The `interested_in_selling` dimension is mapped from the `interested_in_selling` meta key for both seller and landlord in `mapDimensions()` (`app/Services/Dna/PropertyDnaGenerator.php`). This field has no semantic meaning for a landlord listing. The mapping silently returns null for landlord (since the meta key won't exist), which is harmless but undocumented.

**G-07 — `lifestyle_amenity_indicator_pool_detail` dimension skipped.**  
The generator comment explicitly documents that `pool_type` meta is not reliably structured enough to map beyond `has_pool`. This is an acknowledged limitation, not a bug. Source: `DIMENSION_SLOTS` docblock in `app/Services/Dna/PropertyDnaGenerator.php`.

**G-08 — No end-to-end feature test for the full observer → job → generator → DB chain.**  
`ComputeCompatibilityScorePersistenceTest.php` covers the compatibility score side; no equivalent exists for DNA generation. No feature test verifies that saving a `PropertyAuction` or `LandlordAuction` results in a row being written to `property_dna_profiles`. Source: `tests/Feature/ComputeCompatibilityScorePersistenceTest.php`.

---

## Relevant Files

| File | Role |
|---|---|
| `database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php` | Creates `property_dna_profiles` table |
| `database/migrations/2026_06_01_000001_add_location_intelligence_context_to_property_dna_profiles.php` | Adds `location_intelligence_context` column |
| `database/migrations/2026_05_31_000002_create_marketing_reports_table.php` | Creates `marketing_reports` table (downstream of DNA) |
| `app/Models/PropertyDnaProfile.php` | Eloquent model |
| `app/Services/Dna/PropertyDnaGenerator.php` | Core generation service |
| `app/Services/Dna/PropertyIntelligenceProfileService.php` | Downstream intelligence assembly; writes `location_intelligence_context` |
| `app/Services/Dna/SellerDnaReportService.php` | Read-only interpretation layer for seller profiles |
| `app/Services/Dna/LandlordDnaReportService.php` | Read-only interpretation layer for landlord profiles |
| `app/Jobs/ComputePropertyDnaProfile.php` | Queued job wrapping the generator |
| `app/Observers/Dna/PropertyAuctionDnaObserver.php` | Observer firing on seller listing save |
| `app/Observers/Dna/LandlordAuctionDnaObserver.php` | Observer firing on landlord listing save |
| `app/Observers/Dna/PropertyDnaProfileCompatibilityObserver.php` | Compatibility observer (separate system) |
| `app/Observers/Dna/BuyerTenantDnaProfileCompatibilityObserver.php` | Demand DNA compatibility observer (separate system) |
| `app/Providers/AppServiceProvider.php` | Observer registration in `boot()` |
| `app/Http/Controllers/Admin/DnaProfileController.php` | Admin DNA detail views (seller/landlord) |
| `app/Http/Controllers/Admin/DnaInspectorController.php` | Admin DNA index/show/marketing brief preview |
| `app/Http/Controllers/Admin/AiMarketingReportAdminController.php` | Admin marketing report review |
| `app/Http/Controllers/Admin/AiMarketingReportPublicationController.php` | Admin marketing report publish action |
| `app/Http/Controllers/Agent/AiMarketingReportAgentController.php` | Agent marketing report view and section editing |
| `app/Http/Controllers/Agent/PropertyMarketingBriefReviewController.php` | Agent marketing brief review |
| `app/Http/Controllers/Owner/AiMarketingReportOwnerApprovalController.php` | Owner/seller approval/rejection |
| `resources/views/admin/dna/seller.blade.php` | Admin seller DNA detail view |
| `resources/views/admin/dna/landlord.blade.php` | Admin landlord DNA detail view |
| `resources/views/admin/dna/property/index.blade.php` | Admin DNA profile list |
| `resources/views/admin/dna/property/show.blade.php` | Admin DNA profile version history |
| `resources/views/admin/dna/marketing-brief-preview.blade.php` | Admin marketing brief preview |
| `resources/views/admin/dna/marketing-report-show.blade.php` | Admin marketing report review |
| `resources/views/agent/dna/marketing-report-show.blade.php` | Agent marketing report view |
| `resources/views/owner/dna/marketing-report-approval.blade.php` | Owner approval view |
| `resources/views/layouts/partials/admin_sidebar.blade.php` | Admin sidebar with DNA Inspector nav |
| `routes/web.php` | All DNA-related routes |
| `tests/Unit/Services/Dna/SellerDnaReportServiceTest.php` | Unit tests for seller report service |
| `tests/Unit/Services/Dna/LandlordDnaReportServiceTest.php` | Unit tests for landlord report service |
| `tests/Unit/Services/Dna/AiMarketingReportPersistenceServiceTest.php` | Unit tests for report persistence |
| `tests/Unit/Services/Dna/AiMarketingReportGeneratorServiceTest.php` | Unit tests for report generation |
| `tests/Feature/AiMarketingReportGenerateAdminTest.php` | Feature test for admin report generation |
| `tests/Feature/ComputeCompatibilityScorePersistenceTest.php` | Feature test for compatibility score (no DNA equivalent exists) |
