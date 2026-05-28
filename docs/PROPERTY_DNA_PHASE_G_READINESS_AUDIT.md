# Property DNA Phase G — Post-Phase-F Readiness Audit

**Audit Date:** 2026-05-28
**Auditor:** Task Agent (read-only; no implementation changes made)
**Scope:** Phases A–F complete. This document confirms alignment, validates no public exposure, verifies append-only semantics and observer chain safety, and records all unresolved risks for the next phase.

---

## 1. Files Reviewed

| File | Role |
|------|------|
| `app/Models/PropertyDnaProfile.php` | Supply-side DNA model |
| `app/Models/BuyerTenantDnaProfile.php` | Demand-side DNA model |
| `app/Models/ListingCompatibilityScore.php` | Compatibility score model |
| `app/Models/DnaMarketingOutput.php` | Marketing output model (stub) |
| `app/Services/Dna/PropertyDnaGenerator.php` | Supply-side DNA generation service |
| `app/Services/Dna/BuyerTenantDnaGenerator.php` | Demand-side DNA generation service |
| `app/Services/Dna/Compatibility/CompatibilityEngine.php` | 14-dimension compatibility engine |
| `app/Jobs/ComputePropertyDnaProfile.php` | Queue job — supply DNA |
| `app/Jobs/ComputeBuyerTenantDnaProfile.php` | Queue job — demand DNA |
| `app/Jobs/ComputeCompatibilityScore.php` | Queue job — compatibility persistence |
| `app/Observers/Dna/PropertyAuctionDnaObserver.php` | Observer: PropertyAuction → supply DNA job |
| `app/Observers/Dna/LandlordAuctionDnaObserver.php` | Observer: LandlordAuction → supply DNA job |
| `app/Observers/Dna/BuyerCriteriaAuctionDnaObserver.php` | Observer: BuyerCriteriaAuction → demand DNA job |
| `app/Observers/Dna/TenantCriteriaAuctionDnaObserver.php` | Observer: TenantCriteriaAuction → demand DNA job |
| `app/Observers/Dna/PropertyDnaProfileCompatibilityObserver.php` | Observer: PropertyDnaProfile → compat jobs |
| `app/Observers/Dna/BuyerTenantDnaProfileCompatibilityObserver.php` | Observer: BuyerTenantDnaProfile → compat jobs |
| `app/Providers/AppServiceProvider.php` | Observer registration |
| `database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php` | Migration |
| `database/migrations/2026_05_27_000002_create_buyer_tenant_dna_profiles_table.php` | Migration |
| `database/migrations/2026_05_27_000003_create_listing_compatibility_scores_table.php` | Migration |
| `database/migrations/2026_05_27_000005_create_dna_marketing_outputs_table.php` | Migration |
| `docs/PROPERTY_DNA_PHASE_E_INTERNAL_GENERATION_RULES.md` | Governance doc |
| `docs/PROPERTY_DNA_PHASE_F_COMPATIBILITY_RULES.md` | Governance doc |
| `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` | Governance doc |

---

## 2. Command Output — Verbatim

### 2a. `php artisan migrate:status` (DNA/compat migrations only)

```
| Yes  | 2026_05_27_000001_create_property_dna_profiles_table        | 12 |
| Yes  | 2026_05_27_000002_create_buyer_tenant_dna_profiles_table    | 12 |
| Yes  | 2026_05_27_000003_create_listing_compatibility_scores_table | 12 |
| Yes  | 2026_05_27_000005_create_dna_marketing_outputs_table        | 12 |
```

**Result:** All four DNA/compat migrations are applied (batch 12). No pending or failed migrations.

Additional context migrations in the same batch group:

```
| Yes  | 2026_05_27_000004_create_ai_faq_answers_table          | 12 |
| Yes  | 2026_05_27_000006_add_landlord_phase_b_eav_meta_keys   | 13 |
| Yes  | 2026_05_27_000007_add_tenant_phase_b_eav_meta_keys     | 13 |
```

### 2b. `php artisan route:list` (grep: dna / compat / score)

```
No dna/compat/score routes found
```

**Result:** Zero routes matching `dna`, `compat`, or `score` are registered. The DNA system has no public-facing HTTP surface.

### 2c. `git diff --name-only`

```
(no output — clean working tree)
```

**Result:** Working tree is clean. No uncommitted changes.

---

## 3. Public Exposure Grep Checks

Grep was run across all routes (`routes/`), controllers (`app/Http/Controllers/`), Livewire components (`app/Livewire/`), Blade views (`resources/views/`), mail classes (`app/Mail/`), and PDF/API files for each required symbol. No Livewire DNA directory exists (`app/Livewire/Dna/` is absent), confirming no Livewire component exposes DNA data.

| Symbol | Files Searched | Result |
|--------|---------------|--------|
| `PropertyDnaProfile` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `BuyerTenantDnaProfile` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `ListingCompatibilityScore` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `CompatibilityEngine` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `ComputeCompatibilityScore` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `score_explanation` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `overall_score` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |
| `deal_breaker_triggered` | routes, controllers, Livewire, views, mail | **PASS — no public matches** |

**Summary:** All eight symbols are fully confined to the DNA system's internal layers (models, services, jobs, observers). None appear in any public-facing controller, Blade view, Livewire component, mail class, PDF template, or API file.

---

## 4. Queue / Observer Chain Summary

### 4a. Full Event Chain

```
[1] PropertyAuction::saved
      └── PropertyAuctionDnaObserver::saved()
            └── ComputePropertyDnaProfile::dispatch('seller', $id)
                  └── PropertyDnaGenerator::generate('seller', $id)
                        └── PropertyDnaProfile::create(...)  ← triggers [3]

[2] LandlordAuction::saved
      └── LandlordAuctionDnaObserver::saved()
            └── ComputePropertyDnaProfile::dispatch('landlord', $id)
                  └── PropertyDnaGenerator::generate('landlord', $id)
                        └── PropertyDnaProfile::create(...)  ← triggers [3]

[3] PropertyDnaProfile::saved
      └── PropertyDnaProfileCompatibilityObserver::saved()
            └── BuyerTenantDnaProfile (active, counterpart type) → foreach (up to FANOUT_CAP=500)
                  └── ComputeCompatibilityScore::dispatch(...)
                        └── ListingCompatibilityScore::create(...)  ← NO observer; chain terminates

[4] BuyerCriteriaAuction::saved
      └── BuyerCriteriaAuctionDnaObserver::saved()
            └── ComputeBuyerTenantDnaProfile::dispatch('buyer', $id)
                  └── BuyerTenantDnaGenerator::generate('buyer', $id)
                        └── BuyerTenantDnaProfile::create(...)  ← triggers [5]

[5] TenantCriteriaAuction::saved
      └── TenantCriteriaAuctionDnaObserver::saved()
            └── ComputeBuyerTenantDnaProfile::dispatch('tenant', $id)
                  └── BuyerTenantDnaGenerator::generate('tenant', $id)
                        └── BuyerTenantDnaProfile::create(...)  ← triggers [5]

[5] BuyerTenantDnaProfile::saved
      └── BuyerTenantDnaProfileCompatibilityObserver::saved()
            └── PropertyDnaProfile (active, counterpart type) → foreach (up to FANOUT_CAP=500)
                  └── ComputeCompatibilityScore::dispatch(...)
                        └── ListingCompatibilityScore::create(...)  ← NO observer; chain terminates
```

### 4b. ListingCompatibilityScore Observer Status

**Confirmed: `ListingCompatibilityScore` has no registered observer.**

Grep of `app/Observers/` and `app/Providers/AppServiceProvider.php` for `ListingCompatibilityScore` returns only inline comments in the two compatibility observers stating they "never run on ListingCompatibilityScore saves." No `ListingCompatibilityScore::observe(...)` call exists anywhere in the codebase. The chain terminates cleanly at `ListingCompatibilityScore::create(...)` with no recursive enqueuing.

### 4c. FANOUT_CAP Enforcement

`FANOUT_CAP = 500` is defined as a public constant on `CompatibilityEngine` (`CompatibilityEngine::FANOUT_CAP`). Both compatibility observers import and bind to this constant via their own `private const FANOUT_CAP = CompatibilityEngine::FANOUT_CAP`. Enforcement is applied in two places per observer:

1. **Count check first:** queries the total active counterpart count; if it exceeds the cap, a `Log::warning` is emitted with full identifiers.
2. **Limit enforced on fetch:** `->limit(self::FANOUT_CAP)` is applied to the actual dispatch loop query — never unbounded.

This is confirmed in both `PropertyDnaProfileCompatibilityObserver` (lines 60–73) and `BuyerTenantDnaProfileCompatibilityObserver` (lines 60–73).

### 4d. Recursion Risk

**No recursion risk detected.** The observer chain is acyclic:

- Listing observers dispatch DNA generation jobs only (never compatibility jobs).
- Compatibility observers dispatch compatibility jobs only (never DNA generation jobs).
- `ListingCompatibilityScore` has no observer — the chain terminates there.
- `ComputeCompatibilityScore::handle()` explicitly states it "never dispatches additional compatibility jobs from within handle()."

Type-routing further enforces channel separation: seller → buyer, landlord → tenant (and vice versa). A PropertyDnaProfile save for `seller` will never trigger a compatibility dispatch that reaches another PropertyDnaProfile.

---

## 5. Append-Only Persistence Confirmation

All three `persist()` methods follow the same structural pattern. Each is confirmed below.

### 5a. `PropertyDnaGenerator::persist()` → `property_dna_profiles`

| Step | Confirmed | Details |
|------|-----------|---------|
| Advisory lock acquisition | ✓ | `pg_advisory_xact_lock(crc32('pdna:' . $type . ':' . $id))` — PostgreSQL only; no-op on other drivers |
| Prior-row archive | ✓ | `orderByDesc('version')->first()` on all rows regardless of `archived_at`; sets `archived_at = now()` if null |
| Version increment | ✓ | `$newVersion = $prior->version + 1`; starts at 1 if no prior row |
| New-row create | ✓ | `PropertyDnaProfile::create(...)` with `archived_at = null` |
| Transaction isolation | ✓ | Entire lock + archive + create wrapped in `DB::transaction()` |

### 5b. `BuyerTenantDnaGenerator::persist()` → `buyer_tenant_dna_profiles`

| Step | Confirmed | Details |
|------|-----------|---------|
| Advisory lock acquisition | ✓ | `pg_advisory_xact_lock(crc32('btdna:' . $type . ':' . $id))` — PostgreSQL only |
| Prior-row archive | ✓ | `orderByDesc('version')->first()`; sets `archived_at = now()` if null |
| Version increment | ✓ | `$newVersion = $prior->version + 1`; starts at 1 if no prior row |
| New-row create | ✓ | `BuyerTenantDnaProfile::create(...)` with `archived_at = null` |
| Transaction isolation | ✓ | Entire lock + archive + create wrapped in `DB::transaction()` |

### 5c. `ComputeCompatibilityScore::persist()` → `listing_compatibility_scores`

| Step | Confirmed | Details |
|------|-----------|---------|
| Advisory lock acquisition | ✓ | `pg_advisory_xact_lock(crc32('compat:' . $demandType . ':' . $demandId . ':' . $supplyType . ':' . $supplyId))` — PostgreSQL only |
| Prior-row archive | ✓ | `orderByDesc('version')->first()` on all four key columns; sets `archived_at = now()` if null |
| Version increment | ✓ | `$newVersion = $prior->version + 1`; starts at 1 if no prior row |
| New-row create | ✓ | `ListingCompatibilityScore::create(...)` with `archived_at = null` |
| Transaction isolation | ✓ | Entire lock + archive + create wrapped in `DB::transaction()`; explicitly isolated from listing and DNA profile transactions |

**Note:** Advisory lock keys use different prefixes per table (`pdna:`, `btdna:`, `compat:`) preventing any cross-table lock collision.

---

## 6. Queue Job Bounds Check

| Job | `$tries` | `$timeout` | Status |
|-----|----------|------------|--------|
| `ComputeCompatibilityScore` | `3` (explicit) | `60` (explicit) | **PASS** |
| `ComputePropertyDnaProfile` | None | None | **RISK — see below** |
| `ComputeBuyerTenantDnaProfile` | None | None | **RISK — see below** |

### Risk: Missing `$tries` / `$timeout` on DNA Generation Jobs

`ComputePropertyDnaProfile` and `ComputeBuyerTenantDnaProfile` do not declare explicit `$tries` or `$timeout` properties. Both jobs catch all `\Throwable` internally and call `Log::error(...)` without rethrowing, meaning they will not produce a failed queue entry in practice. However:

- **Without explicit `$tries`:** The queue driver's default retry count applies (typically 3 but configurable). On some queue driver configurations this may be 0 or unlimited.
- **Without explicit `$timeout`:** A hung database call (advisory lock wait on a busy PostgreSQL instance, or a very large meta load) can stall a queue worker indefinitely until the worker's global timeout fires.

The task specification confirms this is a known risk to document, not fix in this phase.

---

## 7. Reserved Column and Future-Phase Signal Review

### 7a. Reserved Columns — `property_dna_profiles`

The following columns are defined in the migration with explicit `Reserved / Future Use Only` comments and are never populated by `PropertyDnaGenerator`:

| Column | Migration Comment | Generator Behavior |
|--------|------------------|--------------------|
| `walk_score` | Reserved / Future Use Only — Not Implemented (F-01) | Excluded from `$fillable` generator call; not passed to `persist()` |
| `transit_score` | Reserved / Future Use Only — Not Implemented (F-01) | Same |
| `bike_score` | Reserved / Future Use Only — Not Implemented (F-01) | Same |
| `school_rating` | Reserved / Future Use Only — Not Implemented (F-02) | Same |
| `flood_zone_verified` | Reserved / Future Use Only — Not Implemented (F-03) | Same |
| `estimated_monthly_utilities` | Reserved / Future Use Only — Not Implemented (F-05) | Same |

All six columns are nullable in the schema. They are included in the model's `$fillable` and `$casts` arrays for future readiness but are never written to by any current job or service. Location scores (`location_score`, `condition_score`, `legal_score`) are explicitly passed as `null` in `PropertyDnaGenerator::generate()`.

### 7b. Reserved Column — `buyer_tenant_dna_profiles`

| Column | Migration Comment | Generator Behavior |
|--------|------------------|--------------------|
| `commute_polygon_cache` | Reserved / Future Use Only — Not Implemented (F-03). Must not be populated, read, or exposed in any Phase 3 phase. | Explicitly passed as `null` in `BuyerTenantDnaGenerator::generate()` |

### 7c. `deal_breaker_flags` on `listing_compatibility_scores` — Always Null

`ComputeCompatibilityScore::persist()` hardcodes `'deal_breaker_flags' => null` in every row it creates (line 185 of the job). The `deal_breaker_triggered` boolean is populated (true when `count($result['conflicting_dimensions']) > 0`), but the accompanying flags detail field is never written. This is a documented intentional choice — conflict-presence detection is operational, but the per-flag detail breakdown in `listing_compatibility_scores` is deferred to a future phase.

### 7d. `hoa_preference_specified` — Always Null on Demand Side

`BuyerTenantDnaGenerator` hardcodes `$d['hoa_preference_specified'] = null` (unconditionally) with an explicit governance comment explaining the skip:

> "No dedicated HOA-specific source field exists for buyer or tenant listings in the current schema. Proxy fields (leasing_55_plus, non_negotiable_amenities) were evaluated but rejected: they do not reliably represent HOA preference and would produce systematic false positives. Per governance rule: skip this dimension rather than bend the mapping."

As a consequence, `hoa_alignment` in `CompatibilityEngine` is structurally eligible in Phase E (it appears in `STRUCTURALLY_ELIGIBLE_DIMENSIONS`) but will always return `'unresolved'` because the demand-side tag (`preference:hoa-community-aware`) can never be emitted while `hoa_preference_specified` is null. This is a known gap in the current eligible-dimension set.

### 7e. `DnaMarketingOutput` — Model and Table Exist; No Generator or Job

`DnaMarketingOutput` (`app/Models/DnaMarketingOutput.php`) and its migration (`2026_05_27_000005_create_dna_marketing_outputs_table.php`) are fully defined and applied. The model has `$fillable`, `$casts`, and timestamp configuration in place. However:

- No service class (`DnaMarketingGenerator` or equivalent) exists.
- No queue job (`ComputeDnaMarketingOutput` or equivalent) exists.
- No observer dispatches any job targeting `dna_marketing_outputs`.
- The table is empty and inert.

This is an intentional scaffolding stub for a future phase.

---

## 8. Unresolved Risks and Open Items

| # | Risk / Open Item | Severity | Resolution Path |
|---|-----------------|----------|----------------|
| R-01 | `ComputePropertyDnaProfile` has no explicit `$tries` or `$timeout` | Medium | Add `public int $tries = 3; public int $timeout = 120;` in a future phase; low urgency because the job's internal try/catch prevents failed-job entries |
| R-02 | `ComputeBuyerTenantDnaProfile` has no explicit `$tries` or `$timeout` | Medium | Same as R-01 |
| R-03 | `hoa_alignment` is in `STRUCTURALLY_ELIGIBLE_DIMENSIONS` but always resolves to `'unresolved'` because demand-side HOA field does not exist | Low | Either add a dedicated `hoa_preference` field to buyer/tenant forms and emit the lifestyle tag, or move `hoa_alignment` to `STRUCTURALLY_INELIGIBLE_DIMENSIONS` and bump `SCORING_FRAMEWORK_VERSION` |
| R-04 | `deal_breaker_flags` on `listing_compatibility_scores` is always null; conflict dimension detail is not persisted | Low | Populate `deal_breaker_flags` from `$result['conflicting_dimensions']` in `ComputeCompatibilityScore::persist()` when per-flag detail is needed |
| R-05 | FANOUT_CAP (500) is prototype-scale only; no chunked/cursor pagination or batch dispatch pattern exists | Medium | Phase-specific scalability architecture required before production scale; acknowledged in observer docblocks |
| R-06 | `DnaMarketingOutput` table is created and inert — no generator, job, or observer | Low | Intentional scaffolding; no action required until the marketing output phase begins |
| R-07 | Six reserved columns on `property_dna_profiles` (`walk_score`, `transit_score`, `bike_score`, `school_rating`, `flood_zone_verified`, `estimated_monthly_utilities`) are schema-present but never populated | Low | Requires external data sources (Walk Score API, school rating API, flood zone API); implement in a dedicated future phase |
| R-08 | `commute_polygon_cache` on `buyer_tenant_dna_profiles` is reserved and migration comment prohibits it from being populated in any Phase 3 phase | Low | Requires geospatial commute radius computation architecture; implement in a dedicated future phase |
| R-09 | `location_score`, `condition_score`, `legal_score`, `compatibility_score` on `property_dna_profiles` are always persisted as null by `PropertyDnaGenerator` | Low | Documented intentional omissions; implement when corresponding data sources and rules are defined |
| R-10 | Advisory locks use `crc32()` which has a 32-bit output space; collision probability is negligible at prototype scale but non-zero at high listing volume | Low | Replace with a larger hash function (e.g. a composite integer from `listing_type` + `listing_id` modular arithmetic) if volume warrants it |
| R-11 | Non-PostgreSQL drivers receive no advisory lock; `acquireListingLock()` is a no-op on other drivers | Informational | Project uses PostgreSQL exclusively; document as a deployment constraint |

---

## 9. Recommended Next Phase Actions

Based on the audit findings, the following actions are recommended for Phase H and beyond, in priority order:

### High Priority

1. **Add `$tries` and `$timeout` to DNA generation jobs (R-01, R-02)**
   Add `public int $tries = 3;` and `public int $timeout = 120;` to both `ComputePropertyDnaProfile` and `ComputeBuyerTenantDnaProfile`. This is a two-line change per file and closes the retry/stall risk completely.

2. **Resolve `hoa_alignment` eligible/ineligible discrepancy (R-03)**
   Either add a `hoa_preference` field to buyer/tenant listing forms (enabling demand-side HOA signal), or move `hoa_alignment` from `STRUCTURALLY_ELIGIBLE_DIMENSIONS` to `STRUCTURALLY_INELIGIBLE_DIMENSIONS` and bump `SCORING_FRAMEWORK_VERSION` to `phase-h-v1`. Leaving it in the eligible set while it always returns `'unresolved'` silently deflates the `compatibility_coverage_metric` denominator accuracy.

### Medium Priority

3. **Scalability architecture for FANOUT_CAP (R-05)**
   Before production launch, replace the current single-batch query + dispatch loop with a chunked cursor or queue-chained batch pattern. The current 500-item hard cap is acknowledged as prototype-scale only.

4. **Populate `deal_breaker_flags` on `listing_compatibility_scores` (R-04)**
   Emit the conflicting dimension names into `deal_breaker_flags` from `$result['conflicting_dimensions']` to make conflict detail queryable per-row without parsing `score_explanation`.

### Future Phases

5. **`DnaMarketingOutput` generator and job (R-06)**
   Design and implement the marketing output generation service, queue job, and observer hook. The table and model are scaffolded and ready.

6. **External data integrations for reserved columns (R-07, R-08)**
   Implement Walk Score, transit score, bike score, school rating, and flood zone data integrations to populate the reserved columns on `property_dna_profiles`. Implement commute polygon computation for `buyer_tenant_dna_profiles`.

7. **`location_score`, `condition_score`, `legal_score`, `compatibility_score` computation rules (R-09)**
   Define scoring rules and data sources for the four always-null score columns on `property_dna_profiles`, then implement in `PropertyDnaGenerator::computeScores()`.

---

## 10. Overall Readiness Assessment

| Area | Status |
|------|--------|
| Schema migrations applied | All four DNA/compat migrations: Yes, batch 12 |
| Public route exposure | None — PASS |
| Controller/view/mail exposure | None for all 8 symbols — PASS |
| Append-only persistence | Confirmed for all three tables |
| Advisory lock correctness | Confirmed; keys namespaced per table |
| Observer chain safety | Confirmed; no recursion; chain terminates at `ListingCompatibilityScore` |
| `ListingCompatibilityScore` observer | None registered — PASS |
| FANOUT_CAP enforcement | Confirmed in both compatibility observers (500, fail-closed) |
| `ComputeCompatibilityScore` job bounds | `$tries=3`, `$timeout=60` — PASS |
| DNA generation job bounds | No explicit `$tries` / `$timeout` — RISK documented |
| Reserved columns | All null, never populated, correctly commented — PASS |
| `deal_breaker_flags` on scores | Always null — documented gap |
| `hoa_preference_specified` | Always null — documented gap / eligible-set discrepancy |
| `DnaMarketingOutput` | Scaffolded, inert, no generator or job — PASS for current phase |

**The system is internally consistent and ready to proceed to the next implementation phase.** The two highest-priority items before any production rollout are the missing job bounds on DNA generation jobs (R-01, R-02) and the `hoa_alignment` eligible-set discrepancy (R-03).
