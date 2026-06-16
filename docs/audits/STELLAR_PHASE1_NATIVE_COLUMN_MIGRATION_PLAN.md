# Stellar Phase 1 Native Column Migration Plan

> Document type: Implementation-ready migration plan  
> Date: 2026-06-16  
> Source audits:  
> &nbsp;&nbsp;· `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md`  
> &nbsp;&nbsp;· `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md`  
> &nbsp;&nbsp;· `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`  
> Migration baseline: `database/migrations/2026_06_15_000010_create_bridge_properties_table.php`  
> Scope: **Documentation only — no migrations, no schema changes, no code changes in this document**

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Top 20 Columns to Add](#2-top-20-columns-to-add)
3. [Indexing Strategy](#3-indexing-strategy)
4. [Backfill Strategy](#4-backfill-strategy)
5. [Import Mapping Strategy](#5-import-mapping-strategy)
6. [Rollout Sequence](#6-rollout-sequence)
7. [Risk Analysis](#7-risk-analysis)
8. [Verification Checklist](#8-verification-checklist)

---

## 1. Executive Summary

### Current State

The `bridge_properties` table currently has **13 native columns** that map Stellar MLS fields directly into indexed PostgreSQL columns. Of the **66 Tier 1 Core Matching fields** identified in the readiness audit as essential for buyer and tenant scoring queries, only **9 are covered by existing native columns** (`StandardStatus`, `PropertyType`, `ListPrice`, `City`, `StateOrProvince`, `PostalCode`, `BedroomsTotal`, `BathroomsTotalInteger`, `LivingArea`). The remaining **57 Tier 1 fields exist only inside `raw_json`** and cannot be efficiently filtered, sorted, or range-scanned by PostgreSQL without costly JSON extraction on every row — a full-table sequential scan on any query that touches them.

The most critical gap is geospatial: `Latitude` and `Longitude` are both raw_json only, which means no radius search or map-based matching is possible using native indexed columns. County-level filtering (`CountyOrParish`), property sub-type filtering (`PropertySubType`), and all boolean feature flags (`GarageYN`, `PoolPrivateYN`, `WaterfrontYN`, etc.) are similarly blocked.

### Goal of Phase 1

Promote the **top 20 Stellar MLS fields** from `raw_json` into native PostgreSQL columns on the `bridge_properties` table. This single migration will:

- Take **buyer matching readiness from 21% to approximately 60%** of Tier 1 field coverage.
- Take **tenant matching readiness from 18% to approximately 50%**.
- Unlock map-based radius search using the Haversine bounding-box index pattern.
- Enable core boolean feature filters (garage, pool, waterfront, view, senior community, CDD, new construction).
- Provide native columns for HOA fee range filtering and annual tax cost filtering.
- Lay the foundation for the alert system's status-change and new-construction milestone triggers.

### What This Document Covers

This document provides the implementing developer with everything required to execute Phase 1: the exact DDL types for all 20 new columns, the indexing strategy including partial indexes for low-cardinality boolean columns, the backfill Artisan command specification, the exact import mapping changes for `ImportBridgeProperties.php`, the rollout phase sequence with rationale, a risk analysis with seven risk areas and their mitigations, and a post-implementation verification checklist. No code or schema changes are made as part of producing this document.

---

## 2. Top 20 Columns to Add

All 20 new columns are **nullable**. This is intentional: nullable columns allow the schema migration to run at zero downtime (the import command continues writing to `raw_json` while the new columns exist but are empty), and they allow partial backfill without constraint violations on rows where the source JSON field is absent or null.

| # | Column Name | Stellar Source Field | SQL Type | Nullable | Index | Reason | Feature Unlocked |
|---|---|---|---|---|---|---|---|
| 1 | `latitude` | `Latitude` | `DECIMAL(10,7)` | YES | YES — composite B-tree with `longitude` | Critical for buyer and tenant radius/map search; 25/25 populated | Map-based search, Haversine radius matching for both engines |
| 2 | `longitude` | `Longitude` | `DECIMAL(10,7)` | YES | YES — composite B-tree with `latitude` | Critical for buyer and tenant radius/map search; 25/25 populated | Map-based search, Haversine radius matching for both engines |
| 3 | `county_or_parish` | `CountyOrParish` | `VARCHAR(100)` | YES | YES — individual B-tree | Critical county-level WHERE filter for buyer and tenant; 25/25 populated | County search, market-level filtering |
| 4 | `property_sub_type` | `PropertySubType` | `VARCHAR(100)` | YES | YES — individual B-tree | High priority for buyer and tenant (SFR vs. condo vs. townhouse); 25/25 populated | Property subtype filter plus Ask AI context header |
| 5 | `senior_community_yn` | `SeniorCommunityYN` | `BOOLEAN` | YES | YES — partial (`WHERE senior_community_yn = TRUE`) | Legal compliance filter for both buyer and tenant engines; 25/25 populated | 55+ community gate for matching — legally required to be an indexable query |
| 6 | `mls_status` | `MlsStatus` | `VARCHAR(50)` | YES | YES — individual B-tree | Critical for tenant status filtering; high utility for alert trigger diffing; 25/25 populated | Rental status filter plus price/status alert change detection |
| 7 | `year_built` | `YearBuilt` | `SMALLINT` | YES | NO | High for buyer decade-range filtering; 25/25 populated; Ask AI context | Age-range filter plus "how old is this home?" Ask AI answer |
| 8 | `association_fee` | `AssociationFee` | `DECIMAL(10,2)` | YES | NO | High for buyer HOA cost range queries; 22/25 populated; Ask AI context | HOA fee range filter plus Ask AI ownership cost answers |
| 9 | `pets_allowed` | `PetsAllowed` | `VARCHAR(50)` | YES | NO | Critical for tenant pet policy gate; 24/25 populated (NOTE: source is an array — see Section 7g for normalisation rule) | Pet policy filter for rental matching plus Ask AI |
| 10 | `furnished` | `Furnished` | `VARCHAR(50)` | YES | NO | Critical for tenant furnished/unfurnished toggle; 9/25 in sale sample (higher population expected in rental feed) | Furnished filter for rental matching plus Ask AI |
| 11 | `garage_yn` | `GarageYN` | `BOOLEAN` | YES | YES — partial (`WHERE garage_yn = TRUE`) | High for buyer matching — top-5 national feature filter; 25/25 populated | Garage toggle filter for buyer matching |
| 12 | `pool_private_yn` | `PoolPrivateYN` | `BOOLEAN` | YES | YES — partial (`WHERE pool_private_yn = TRUE`) | High for buyer matching — FL primary feature; 25/25 populated | Pool toggle filter for buyer matching |
| 13 | `waterfront_yn` | `WaterfrontYN` | `BOOLEAN` | YES | YES — partial (`WHERE waterfront_yn = TRUE`) | High for buyer matching — FL premium differentiator; 25/25 populated | Waterfront toggle for buyer matching |
| 14 | `tax_annual_amount` | `TaxAnnualAmount` | `DECIMAL(10,2)` | YES | NO | High for buyer true-ownership-cost range filter; 25/25 populated; Ask AI context | Tax cost filter plus Ask AI cost-of-ownership answers |
| 15 | `lot_size_sqft` | `LotSizeSquareFeet` | `INTEGER` | YES | NO | High for buyer lot size range filter; 25/25 populated; Ask AI context | Lot size range filter plus Ask AI lot dimension answers |
| 16 | `association_yn` | `AssociationYN` | `BOOLEAN` | YES | YES — partial (`WHERE association_yn = TRUE`) | Medium for buyer and tenant — HOA existence gate that guards `association_fee` queries; 25/25 populated | HOA presence filter |
| 17 | `new_construction_yn` | `NewConstructionYN` | `BOOLEAN` | YES | YES — partial (`WHERE new_construction_yn = TRUE`) | Medium for buyer matching; alert trigger for new construction milestone alerts; 25/25 populated | New construction filter plus construction milestone alerts |
| 18 | `view_yn` | `ViewYN` | `BOOLEAN` | YES | YES — partial (`WHERE view_yn = TRUE`) | Medium for buyer and tenant view feature toggle; 25/25 populated | View toggle filter for both engines |
| 19 | `water_view_yn` | `STELLAR_WaterViewYN` | `BOOLEAN` | YES | YES — partial (`WHERE water_view_yn = TRUE`) | Medium for buyer and tenant FL water view toggle; 25/25 populated | Water view toggle for both engines |
| 20 | `cdd_yn` | `STELLAR_CDDYN` | `BOOLEAN` | YES | YES — partial (`WHERE cdd_yn = TRUE`) | Medium for buyer matching — FL CDD cost gate; 25/25 populated; Ask AI context | CDD filter for buyer matching plus Ask AI cost answers |

---

## 3. Indexing Strategy

### 3a — Status, Type, and Price Indexes

Individual B-tree indexes on these columns support the most common WHERE clause entry points for any matching query. These columns all have high cardinality relative to the table size, making standard B-tree indexes efficient.

| Column | Index Type | Rationale |
|---|---|---|
| `standard_status` | B-tree (already exists on existing columns; confirm presence) | Gates every active-listing query |
| `property_type` | B-tree | Primary type filter — Residential, Commercial, etc. |
| `property_sub_type` | B-tree | SFR vs. condo vs. townhouse filter — high selectivity |
| `mls_status` | B-tree | Board-specific status vocab for alert diffing |
| `list_price` | B-tree (already exists) | Price range scan — the most common range filter |

### 3b — City, County, and ZIP Indexes

These columns are the core geography filters. Individual B-tree indexes support both exact-match and range queries on geographic groupings.

| Column | Index Type | Rationale |
|---|---|---|
| `city` | B-tree (already exists) | Core city equality filter |
| `county_or_parish` | B-tree | County-level WHERE clause — new column |
| `postal_code` | B-tree (already exists) | ZIP-code equality filter |
| `state_or_province` | B-tree (already exists) | State-level filter |

### 3c — Latitude/Longitude Composite Index

After promoting `latitude` and `longitude` as `DECIMAL(10,7)` columns, add a **composite B-tree index on `(latitude, longitude)`**. This index is the foundational enabler for Haversine-based radius search.

**Composite index DDL:**

```sql
CREATE INDEX CONCURRENTLY bridge_properties_lat_lng_idx
ON bridge_properties (latitude, longitude);
```

**How it is used — the Haversine bounding-box pattern** (from `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 7):

```sql
SELECT *, (
    3959 * ACOS(
        COS(RADIANS(?)) * COS(RADIANS(latitude))
        * COS(RADIANS(longitude) - RADIANS(?))
        + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
    )
) AS distance_miles
FROM bridge_properties
WHERE
    standard_status = 'Active'
    AND latitude BETWEEN (? - ?/69.0) AND (? + ?/69.0)
    AND longitude BETWEEN (? - ?/53.0) AND (? + ?/53.0)
HAVING distance_miles <= ?
ORDER BY distance_miles ASC;
```

The `BETWEEN` clauses in the WHERE block hit the composite B-tree index and reduce the candidate set to a geographic bounding box. The Haversine expression in the HAVING clause then filters the reduced set to the precise radius. This pattern is accurate to within ~0.5% and requires no PostGIS extension. When query volume or precision requirements grow, the PostGIS migration path (adding a `GEOGRAPHY(POINT, 4326)` column with a GIST index) does not require removing `latitude` or `longitude` — the decimal columns remain for display and non-radius filters.

### 3d — Boolean Feature Indexes

The nine boolean columns added in Phase 1 (`garage_yn`, `pool_private_yn`, `waterfront_yn`, `association_yn`, `senior_community_yn`, `new_construction_yn`, `view_yn`, `water_view_yn`, `cdd_yn`) present a **low-cardinality indexing trade-off**.

**The problem:** Boolean columns contain only two meaningful values (true/false) plus NULL. On a large table, a standard B-tree index on a boolean column has very poor selectivity — PostgreSQL's query planner frequently determines that a full sequential scan is cheaper than an index scan when a large fraction of rows match the predicate (e.g. `WHERE garage_yn = FALSE` on a table where 90% of homes have no garage). Standard boolean indexes are often ignored by the planner.

**The solution — partial indexes:** A partial index covers only the rows where the boolean is TRUE, which is typically a small, high-selectivity subset (pool homes, waterfront properties, senior communities). PostgreSQL will use a partial index efficiently because its statistics show a small row count under that index.

| Column | Partial Index DDL |
|---|---|
| `garage_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_garage_yn_idx ON bridge_properties (garage_yn) WHERE garage_yn = TRUE;` |
| `pool_private_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_pool_yn_idx ON bridge_properties (pool_private_yn) WHERE pool_private_yn = TRUE;` |
| `waterfront_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_waterfront_yn_idx ON bridge_properties (waterfront_yn) WHERE waterfront_yn = TRUE;` |
| `association_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_association_yn_idx ON bridge_properties (association_yn) WHERE association_yn = TRUE;` |
| `senior_community_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_senior_yn_idx ON bridge_properties (senior_community_yn) WHERE senior_community_yn = TRUE;` |
| `new_construction_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_new_construction_yn_idx ON bridge_properties (new_construction_yn) WHERE new_construction_yn = TRUE;` |
| `view_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_view_yn_idx ON bridge_properties (view_yn) WHERE view_yn = TRUE;` |
| `water_view_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_water_view_yn_idx ON bridge_properties (water_view_yn) WHERE water_view_yn = TRUE;` |
| `cdd_yn` | `CREATE INDEX CONCURRENTLY bridge_properties_cdd_yn_idx ON bridge_properties (cdd_yn) WHERE cdd_yn = TRUE;` |

**Timing note:** All indexes — both composite and partial — should be created **after** the backfill is complete (see Section 6, Phase D → Phase E ordering). Creating indexes after the data is loaded avoids per-row index maintenance overhead during the bulk backfill and is significantly faster than creating them before the backfill. Use `CREATE INDEX CONCURRENTLY` in all cases to avoid locking the table against reads during index construction.

---

## 4. Backfill Strategy

### Overview

A dedicated Artisan command — `bridge:backfill-native-columns` — will be created to iterate all existing rows in `bridge_properties`, extract the 20 field values from `raw_json`, cast them to the correct native types, and write them into the new columns.

**Why a dedicated command rather than a migration `DB::statement`:** The backfill involves PHP-layer type coercion, error detection per row, and progress reporting. A migration `DB::statement` cannot log individual row failures or handle type anomalies gracefully. The command is also idempotent and can be re-run safely (see idempotency below).

### Batch Size

Process rows in batches of **1,000** using `BridgeProperty::whereNull('latitude')->chunk(1000, ...)` (or `chunkById` for large tables). Chunking avoids loading all rows into memory simultaneously and keeps each database transaction short.

For a forced re-run (`--force` flag), use `BridgeProperty::chunk(1000, ...)` without the NULL filter.

### Extraction Approach

Each row's `raw_json` column contains the full Stellar API record as a JSON string. Extract values using PHP `json_decode($record->raw_json, true)`, which returns actual PHP types (booleans as `bool`, numbers as `int`/`float`, arrays as `array`, null as `null`). Do not use PostgreSQL's `raw_json::json->>'FieldName'` SQL extraction in the backfill command — PHP `json_decode` returns proper native types rather than the string representations that SQL JSON extraction returns (e.g. SQL returns the string `"true"` where PHP returns `true`).

### Type Coercion Rules

| Column | Source Key | Coercion Rule |
|---|---|---|
| `latitude` | `Latitude` | `isset($data['Latitude']) ? (float) $data['Latitude'] : null` |
| `longitude` | `Longitude` | `isset($data['Longitude']) ? (float) $data['Longitude'] : null` |
| `county_or_parish` | `CountyOrParish` | `$data['CountyOrParish'] ?? null` (string, already correct type) |
| `property_sub_type` | `PropertySubType` | `$data['PropertySubType'] ?? null` (string) |
| `senior_community_yn` | `SeniorCommunityYN` | `isset($data['SeniorCommunityYN']) ? (bool) $data['SeniorCommunityYN'] : null` |
| `mls_status` | `MlsStatus` | `$data['MlsStatus'] ?? null` (string) |
| `year_built` | `YearBuilt` | `isset($data['YearBuilt']) ? (int) $data['YearBuilt'] : null` |
| `association_fee` | `AssociationFee` | `isset($data['AssociationFee']) ? (float) $data['AssociationFee'] : null` |
| `pets_allowed` | `PetsAllowed` | See note below — array source requires normalisation |
| `furnished` | `Furnished` | `$data['Furnished'] ?? null` (string) |
| `garage_yn` | `GarageYN` | `isset($data['GarageYN']) ? (bool) $data['GarageYN'] : null` |
| `pool_private_yn` | `PoolPrivateYN` | `isset($data['PoolPrivateYN']) ? (bool) $data['PoolPrivateYN'] : null` |
| `waterfront_yn` | `WaterfrontYN` | `isset($data['WaterfrontYN']) ? (bool) $data['WaterfrontYN'] : null` |
| `tax_annual_amount` | `TaxAnnualAmount` | `isset($data['TaxAnnualAmount']) ? (float) $data['TaxAnnualAmount'] : null` |
| `lot_size_sqft` | `LotSizeSquareFeet` | `isset($data['LotSizeSquareFeet']) ? (int) $data['LotSizeSquareFeet'] : null` — **NOTE:** string `"0"` is falsy in PHP; use `!== null` guard, not a truthy check |
| `association_yn` | `AssociationYN` | `isset($data['AssociationYN']) ? (bool) $data['AssociationYN'] : null` |
| `new_construction_yn` | `NewConstructionYN` | `isset($data['NewConstructionYN']) ? (bool) $data['NewConstructionYN'] : null` |
| `view_yn` | `ViewYN` | `isset($data['ViewYN']) ? (bool) $data['ViewYN'] : null` |
| `water_view_yn` | `STELLAR_WaterViewYN` | `isset($data['STELLAR_WaterViewYN']) ? (bool) $data['STELLAR_WaterViewYN'] : null` |
| `cdd_yn` | `STELLAR_CDDYN` | `isset($data['STELLAR_CDDYN']) ? (bool) $data['STELLAR_CDDYN'] : null` |

**`PetsAllowed` normalisation note:** In the Stellar API, `PetsAllowed` is an **array**, not a scalar (e.g. `["Yes"]`, `["Cats", "Dogs"]`). The `VARCHAR(50)` column stores a normalised string. Extraction rule: `$raw = $data['PetsAllowed'] ?? null; $value = is_array($raw) ? ($raw[0] ?? null) : $raw;`. Store the first element as the canonical value. If the first element is null or the array is empty, store null. See Section 7g for full normalisation details.

### Idempotency

The command is idempotent by default. On a normal run it uses a `WHERE <col> IS NULL` filter (via chunking only rows where `latitude` is null, as a proxy for "not yet backfilled") and skips rows that already have native column data. Passing `--force` disables the NULL filter and overwrites all rows — use this when re-running after a code fix that corrected an extraction bug.

### Progress Output and Logging

The command outputs a progress line every 1,000 rows: `Processed 1000 / 45230 rows...`. On completion it prints totals: `Backfill complete. Updated: 44980. Skipped (already filled): 200. Errors: 50.`

For any row where `json_decode` fails or an extracted value is an unexpected type (e.g. `AssociationFee` returns an array instead of a number), the command logs a warning via `Log::warning('bridge:backfill-native-columns: unexpected type', ['listing_key' => $record->listing_key, 'field' => $fieldName, 'type' => gettype($value)])` and sets that field to null for that row, continuing to the next field and row. It does not abort the entire backfill on a single row error.

---

## 5. Import Mapping Strategy

### No API Layer Changes Required

`BridgeApiService::fetchProperties()` already returns the full record array from the Stellar OData API, including all 20 source fields. No changes to `BridgeApiService` are needed. Only the `updateOrCreate` mapping array inside `ImportBridgeProperties::handle()` needs to be extended.

### Mapping Array Changes

Add the following entries to the second argument of the `BridgeProperty::updateOrCreate(...)` call in `ImportBridgeProperties::handle()`:

```php
// --- Phase 1 native column promotions ---

// Geospatial
'latitude'             => isset($record['Latitude']) ? (float) $record['Latitude'] : null,
'longitude'            => isset($record['Longitude']) ? (float) $record['Longitude'] : null,

// Location
'county_or_parish'     => $record['CountyOrParish'] ?? null,

// Property classification
'property_sub_type'    => $record['PropertySubType'] ?? null,
'mls_status'           => $record['MlsStatus'] ?? null,

// Age
'year_built'           => isset($record['YearBuilt']) ? (int) $record['YearBuilt'] : null,

// Financial
'association_fee'      => isset($record['AssociationFee']) ? (float) $record['AssociationFee'] : null,
'tax_annual_amount'    => isset($record['TaxAnnualAmount']) ? (float) $record['TaxAnnualAmount'] : null,

// Size
'lot_size_sqft'        => ($v = $record['LotSizeSquareFeet'] ?? null) !== null ? (int) $v : null,

// Rental qualifiers
'pets_allowed'         => (function () use ($record) {
                               $raw = $record['PetsAllowed'] ?? null;
                               if (is_array($raw)) { return $raw[0] ?? null; }
                               return $raw;
                           })(),
'furnished'            => $record['Furnished'] ?? null,

// Boolean feature flags
'garage_yn'            => filter_var($record['GarageYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'pool_private_yn'      => filter_var($record['PoolPrivateYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'waterfront_yn'        => filter_var($record['WaterfrontYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'association_yn'       => filter_var($record['AssociationYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'senior_community_yn'  => filter_var($record['SeniorCommunityYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'new_construction_yn'  => filter_var($record['NewConstructionYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'view_yn'              => filter_var($record['ViewYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'water_view_yn'        => filter_var($record['STELLAR_WaterViewYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
'cdd_yn'               => filter_var($record['STELLAR_CDDYN'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
```

### `filter_var` vs. `(bool)` Cast for Boolean Fields

`filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)` is preferred over a bare `(bool)` cast for import-time boolean normalisation because:

1. The API returns actual PHP `true`/`false` values after JSON decoding — `filter_var` handles both these and the string representations `"true"`/`"false"` that may appear in edge cases.
2. `FILTER_NULL_ON_FAILURE` returns `null` when the value is neither a recognisable boolean nor `null`, rather than silently casting an unexpected value to `false`. This preserves the column's nullable semantics for records where the field is genuinely absent.

If the field key is not present in `$record` at all, the `?? false` fallback causes `filter_var` to return `false` — which is correct for a boolean flag (absent = no feature). If strict nullable semantics are desired (absent key = null, not false), replace `?? false` with `?? null` and `filter_var` will return `null` via `FILTER_NULL_ON_FAILURE`.

### `BridgeProperty::$fillable` and `$casts` Updates

When Phase B of the rollout is executed, add the 20 new columns to `BridgeProperty::$fillable` and the appropriate casts to `BridgeProperty::$casts`:

**`$fillable` additions:**
```php
'latitude', 'longitude', 'county_or_parish', 'property_sub_type', 'mls_status',
'year_built', 'association_fee', 'tax_annual_amount', 'lot_size_sqft',
'pets_allowed', 'furnished', 'garage_yn', 'pool_private_yn', 'waterfront_yn',
'association_yn', 'senior_community_yn', 'new_construction_yn', 'view_yn',
'water_view_yn', 'cdd_yn',
```

**`$casts` additions:**
```php
'latitude'            => 'decimal:7',
'longitude'           => 'decimal:7',
'year_built'          => 'integer',
'association_fee'     => 'decimal:2',
'tax_annual_amount'   => 'decimal:2',
'lot_size_sqft'       => 'integer',
'garage_yn'           => 'boolean',
'pool_private_yn'     => 'boolean',
'waterfront_yn'       => 'boolean',
'association_yn'      => 'boolean',
'senior_community_yn' => 'boolean',
'new_construction_yn' => 'boolean',
'view_yn'             => 'boolean',
'water_view_yn'       => 'boolean',
'cdd_yn'              => 'boolean',
```

String columns (`county_or_parish`, `property_sub_type`, `mls_status`, `pets_allowed`, `furnished`) do not require explicit casts — Laravel returns them as strings by default.

---

## 6. Rollout Sequence

Execute the phases in strict order. Do not skip ahead. Each phase has a defined rationale and a clear safe-to-proceed criterion before starting the next phase.

### Phase A — Run the Laravel Migration

**What:** Create and run a new Laravel migration that adds the 20 nullable columns to `bridge_properties`. No indexes are added yet (indexes are deferred to Phase E — after backfill — for performance).

**DDL summary for the migration's `up()` method:**
```php
Schema::table('bridge_properties', function (Blueprint $table) {
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('county_or_parish', 100)->nullable();
    $table->string('property_sub_type', 100)->nullable();
    $table->string('mls_status', 50)->nullable();
    $table->smallInteger('year_built')->nullable();
    $table->decimal('association_fee', 10, 2)->nullable();
    $table->decimal('tax_annual_amount', 10, 2)->nullable();
    $table->integer('lot_size_sqft')->nullable();
    $table->string('pets_allowed', 50)->nullable();
    $table->string('furnished', 50)->nullable();
    $table->boolean('garage_yn')->nullable();
    $table->boolean('pool_private_yn')->nullable();
    $table->boolean('waterfront_yn')->nullable();
    $table->boolean('association_yn')->nullable();
    $table->boolean('senior_community_yn')->nullable();
    $table->boolean('new_construction_yn')->nullable();
    $table->boolean('view_yn')->nullable();
    $table->boolean('water_view_yn')->nullable();
    $table->boolean('cdd_yn')->nullable();
});
```

**Why it is safe:** All columns are nullable. PostgreSQL's `ALTER TABLE ... ADD COLUMN` with a nullable column (no default) is a metadata-only operation — it does not rewrite the table and does not lock rows. The existing import continues running and writing to `raw_json` without any disruption.

**Rollout criterion:** `php artisan migrate` exits successfully. Verify with `\d bridge_properties` in psql.

---

### Phase B — Update `BridgeProperty::$fillable` and `$casts`

**What:** Add the 20 new column names to `$fillable` and the appropriate type casts to `$casts` in `app/Models/BridgeProperty.php` (see Section 5 for the exact additions).

**Why:** Without `$fillable` entries, Laravel's mass-assignment protection will silently discard the new column values from `updateOrCreate` calls. Without `$casts`, Eloquent returns boolean columns as the integers `0`/`1` and decimal columns as strings — which causes type errors downstream.

**Why at Phase B (not Phase C):** Updating `$fillable` before updating the import command ensures that when the import command is updated in Phase C, the model is already ready to accept the new fields. If Phase C happened before Phase B, the import would attempt to mass-assign fields that the model would silently reject.

**Behavioural change:** None yet. The import command does not yet write to the new columns, so `$fillable` additions have no observable effect until Phase C.

**Rollout criterion:** `grep -n 'latitude' app/Models/BridgeProperty.php` finds the entry in both `$fillable` and `$casts`.

---

### Phase C — Update `ImportBridgeProperties::handle()` Mapping Array

**What:** Add the 20 mapping entries (Section 5) to the `updateOrCreate` second-argument array in `ImportBridgeProperties::handle()`.

**Why at Phase C:** At this point the columns exist (Phase A) and the model accepts them (Phase B). New imports will now write to both native columns and `raw_json` simultaneously. Historical rows remain with null native columns until the backfill runs in Phase D.

**Dual-write period:** Between Phase C and Phase D completion, the table is in a mixed state — new records have native columns populated, old records have null native columns. This is acceptable because the matching engine is not yet reading from native columns (Phase F). All queries during this period must still fall back to `raw_json` if they need these fields.

**Rollout criterion:** Run `php artisan bridge:import-properties --limit=1`. Verify the imported row has non-null values in `latitude`, `longitude`, and at least two other new columns using the spot-check query from Section 8 item (3).

---

### Phase D — Run `bridge:backfill-native-columns`

**What:** Execute the backfill command to populate native columns for all historical rows that were imported before Phase C.

```bash
php artisan bridge:backfill-native-columns
```

**Expected duration:** Proportional to the number of existing rows. At 1,000-row batches with PHP-layer JSON decoding, expect approximately 500–2,000 rows per second depending on server resources. Monitor progress output.

**Idempotency:** The command can be interrupted and re-run. By default it skips rows where `latitude` is already non-null (proxy for "already backfilled"). Re-running after completion is safe and will output `Skipped: N` for all rows.

**Rollout criterion:** Command exits with `Errors: 0` (or a small number of error rows that have been inspected and confirmed as genuinely malformed JSON in `raw_json`). Check null coverage queries from Section 8 item (2).

---

### Phase E — Create Indexes

**What:** Run all 14 new indexes: 4 individual B-tree indexes (`county_or_parish`, `property_sub_type`, `mls_status`, and confirming `standard_status`/`list_price`/`city`/`postal_code` already exist), 1 composite B-tree index on `(latitude, longitude)`, and 9 partial boolean indexes.

Use `CREATE INDEX CONCURRENTLY` for all indexes to avoid table locks during index construction. These DDL statements can be run directly in psql or wrapped in a migration with `DB::statement(...)`.

**Why after backfill:** Creating indexes after the data is loaded is significantly faster than creating them before. An index created before a bulk insert must be updated on every inserted row. An index created after a bulk insert is built in a single sorted pass over the full dataset. For a table with tens of thousands of rows, this difference is substantial.

**Rollout criterion:** `SELECT indexname FROM pg_indexes WHERE tablename='bridge_properties'` shows all 14 new index names plus the pre-existing indexes.

---

### Phase F — Matching Engine Can Now Be Built Against Native Columns

**What:** With all 20 native columns populated and indexed, the buyer and tenant matching engines can be built to query native columns directly. The `raw_json` column remains the source of truth for fields not yet promoted — matching queries that need Tier 2 or display fields can extract them from `raw_json` on the already-filtered result set (which is now small enough that per-row JSON extraction is acceptable).

**Not a deployment step:** Phase F is the development gate — it signals to the engineering team that the schema is ready for matching engine implementation. No schema changes occur in Phase F.

---

## 7. Risk Analysis

### 7a — Nullable Fields and NULL Handling in Matching Queries

**Risk:** All 20 new columns are nullable. A matching WHERE clause that does not account for NULL values may accidentally exclude valid listings where the field is present in `raw_json` but was not yet backfilled (during the dual-write period), or where the Stellar API genuinely does not populate the field for that listing.

**Mitigation:** All matching WHERE clauses that filter on Phase 1 columns must use explicit `IS NOT NULL` guards or handle NULL as a non-matching value deliberately. For boolean flags, prefer the pattern `AND (garage_yn = TRUE OR garage_yn IS NULL)` when the intent is "include listings where garage status is unknown" — versus `AND garage_yn = TRUE` when only confirmed-garage listings should match. Document the NULL handling convention in the matching engine's query builder layer before building matching queries.

---

### 7b — Bad JSON Values in `raw_json`

**Risk:** Some rows in `bridge_properties` may have malformed JSON in `raw_json` (truncated during import, encoding errors, API edge cases), or the extracted field may be an unexpected type (e.g. `AssociationFee` returns the string `"N/A"` instead of a number, or `YearBuilt` returns `0` for a listing where the year is genuinely unknown).

**Mitigation:** The backfill command wraps `json_decode` in error checking: if `json_decode` returns `null` and `json_last_error() !== JSON_ERROR_NONE`, the row is logged via `Log::warning` with its `listing_key` and skipped (all 20 columns remain null for that row). For type mismatches, each extraction checks `is_numeric` before casting numeric fields and `is_bool` or `is_string` before using string fields — unexpected types are logged and the column is left null rather than storing a corrupted value. After backfill, review the Laravel log for warning entries and investigate the flagged `listing_key` values directly in the database.

---

### 7c — Boolean Normalisation: SQL Layer vs. PHP Layer

**Risk:** Developers writing SQL-layer backfill queries using PostgreSQL JSON extraction (`raw_json::json->>'GarageYN'`) will receive the **string** `"true"` or `"false"`, not the SQL boolean `TRUE`/`FALSE`. Inserting the string `"true"` into a `BOOLEAN` column via a raw SQL `UPDATE` will fail or silently produce an unexpected value depending on PostgreSQL's coercion rules for that context.

**Mitigation:** The backfill command exclusively uses **PHP-layer `json_decode`**, which returns actual PHP `bool` values. PDO's parameter binding correctly maps PHP `true`/`false` to PostgreSQL `TRUE`/`FALSE`. If a developer ever writes a supplemental SQL-layer script for debugging or patching, they must use the `::boolean` cast: `(raw_json::json->>'GarageYN')::boolean`. Document this distinction in any code comments near the backfill command.

At import time (`ImportBridgeProperties`), the API returns JSON-decoded PHP booleans from `BridgeApiService::fetchProperties()` (which calls `json_decode` internally), so the `filter_var` mappings in Section 5 operate on PHP `true`/`false` values — this is safe and correct.

---

### 7d — Decimal Precision

**Risk:** Precision loss when storing float values from `raw_json` into `DECIMAL` columns, or when comparing stored values against user input in matching queries.

**Analysis:**
- `DECIMAL(10,7)` for `latitude`/`longitude` provides 7 decimal places of precision, which is approximately 1.1 cm at the equator. GPS coordinates in the Stellar API are float values with 6 decimal places of meaningful precision (approximately 11 cm). No precision loss occurs.
- `DECIMAL(10,2)` for `association_fee` and `tax_annual_amount` matches the existing `list_price` convention in the table and provides two decimal places — sufficient for dollar-and-cents monetary values. PHP `(float)` cast of an integer field (e.g. `AssociationFee: 60`) produces `60.0`, which PostgreSQL stores as `60.00` in `DECIMAL(10,2)` with no loss.
- Float storage in `raw_json` (IEEE 754 double) can introduce sub-cent rounding artifacts when the source value has many decimal places. However, MLS fee and tax values in practice are whole-dollar or two-decimal-place amounts. The rounding artifact, if any, is at the 15th significant digit — negligible for matching and display purposes.

**Mitigation:** No additional mitigation is required. Use `DECIMAL` (exact) columns for monetary fields, not `FLOAT` or `REAL`, to avoid accumulating rounding errors in future calculations.

---

### 7e — Index Bloat and Write Overhead

**Risk:** Adding 14 new indexes (4 individual B-tree + 1 composite B-tree + 9 partial boolean) to a table that is updated on every import cycle introduces index maintenance overhead. Every `INSERT` or `UPDATE` to `bridge_properties` must update all applicable indexes.

**Estimated overhead:** PostgreSQL documentation and community benchmarks estimate approximately 10–20% write slowdown per additional index on a table of this column count and size, depending on index type. The 9 partial boolean indexes are the least costly — they only update when the indexed condition changes (i.e. when a property gains or loses its `garage_yn = TRUE` state). The composite lat/lng index and the individual B-tree indexes are updated on every import regardless.

**Mitigation:**
1. Defer all index creation until after the backfill (Phase E) to avoid per-row index maintenance during the bulk backfill.
2. Use `CREATE INDEX CONCURRENTLY` to avoid write-blocking locks during index creation.
3. Monitor import job duration after Phase E. If import time increases by more than 30%, audit which indexes the planner is using via `EXPLAIN ANALYZE` on representative import update queries and drop any that are unused in practice.
4. The 9 partial boolean indexes carry very low write overhead because PostgreSQL only maintains a partial index for rows that satisfy the `WHERE` clause — rows where the flag is `FALSE` or `NULL` are invisible to those indexes.

---

### 7f — Rollback

**Risk:** If the migration or backfill introduces a bug that requires reverting the schema change.

**Rollback path:** Because all 20 columns are nullable and the existing `raw_json` column remains the source of truth throughout this entire process, rolling back is clean and non-destructive:

1. Run the migration's `down()` method, which calls `Schema::table('bridge_properties', function (Blueprint $table) { $table->dropColumn([...]) })`.
2. No data loss occurs — `raw_json` retains all original field values.
3. Revert the `BridgeProperty.php` `$fillable` and `$casts` changes.
4. Revert the `ImportBridgeProperties.php` mapping array changes.

**`Schema::dropColumn` list for the `down()` method:**
```php
$table->dropColumn([
    'latitude', 'longitude', 'county_or_parish', 'property_sub_type', 'mls_status',
    'year_built', 'association_fee', 'tax_annual_amount', 'lot_size_sqft',
    'pets_allowed', 'furnished', 'garage_yn', 'pool_private_yn', 'waterfront_yn',
    'association_yn', 'senior_community_yn', 'new_construction_yn', 'view_yn',
    'water_view_yn', 'cdd_yn',
]);
```

Note: PostgreSQL does not support dropping multiple columns in a single `ALTER TABLE ... DROP COLUMN` statement in all versions. Laravel's `dropColumn` handles this by generating individual `DROP COLUMN` statements per column. The indexes associated with the dropped columns are automatically dropped by PostgreSQL when the column is dropped.

---

### 7g — `PetsAllowed` Array Normalisation

**Risk:** `PetsAllowed` in the Stellar API is an **array**, not a scalar string. Example values from the field audit: `["Yes"]`, `["Cats"]`, `["Dogs", "Cats"]`, `["No"]`. The `VARCHAR(50)` column cannot store the full array. A naive cast `(string) $record['PetsAllowed']` would produce `"Array"` — an invalid and misleading stored value.

**Normalisation rule:**
```php
$raw = $data['PetsAllowed'] ?? null;
if (is_array($raw)) {
    $value = $raw[0] ?? null;
} elseif (is_string($raw)) {
    $value = $raw;
} else {
    $value = null;
}
```

Store the first element as the canonical value. This captures the most common cases:
- `["Yes"]` → stored as `"Yes"` (all pets allowed)
- `["No"]` → stored as `"No"` (no pets)
- `["Cats"]` → stored as `"Cats"` (cats only — tenant matching engine checks for substring or equality)
- `["Dogs", "Cats"]` → stored as `"Dogs"` (first element only — partial information, but the most common case; a future `pets_allowed_full` TEXT column can store the full JSON array if needed)
- `null` or absent → stored as `null`

**Matching engine implication:** Tenant matching queries on `pets_allowed` should use `LIKE '%Yes%'` or equality checks against the normalised values, not a strict array containment query. Document this convention in the matching engine. If the full array is needed for matching (e.g. "tenant has a cat — does this property allow cats?"), add a `pets_allowed_raw` TEXT column in Phase 2 to store the full JSON array and use PostgreSQL's `@>` operator against it.

---

## 8. Verification Checklist

Run each of the following checks after Phase E (index creation) is complete and before Phase F (matching engine development) begins.

### Check 1 — Column Presence

Verify all 20 new columns appear in the table schema:

```sql
SELECT column_name, data_type, is_nullable, character_maximum_length, numeric_precision, numeric_scale
FROM information_schema.columns
WHERE table_name = 'bridge_properties'
ORDER BY ordinal_position;
```

Expected: The result set includes all 20 new column names with the correct data types (`numeric` for decimals, `boolean` for flags, `character varying` for varchar, `smallint` for year_built, `integer` for lot_size_sqft).

Alternatively in psql: `\d bridge_properties`

---

### Check 2 — Null Coverage After Backfill

For each of the 20 columns, verify null counts match expected values after backfill. For 25-record sample tables, all reliably-populated source fields (25/25 in the audit) should show zero NULLs:

```sql
SELECT
    COUNT(*) FILTER (WHERE latitude IS NULL)           AS latitude_null,
    COUNT(*) FILTER (WHERE longitude IS NULL)          AS longitude_null,
    COUNT(*) FILTER (WHERE county_or_parish IS NULL)   AS county_null,
    COUNT(*) FILTER (WHERE property_sub_type IS NULL)  AS sub_type_null,
    COUNT(*) FILTER (WHERE mls_status IS NULL)         AS mls_status_null,
    COUNT(*) FILTER (WHERE year_built IS NULL)         AS year_built_null,
    COUNT(*) FILTER (WHERE garage_yn IS NULL)          AS garage_yn_null,
    COUNT(*) FILTER (WHERE pool_private_yn IS NULL)    AS pool_yn_null,
    COUNT(*) FILTER (WHERE waterfront_yn IS NULL)      AS waterfront_yn_null,
    COUNT(*) FILTER (WHERE association_yn IS NULL)     AS association_yn_null,
    COUNT(*) FILTER (WHERE new_construction_yn IS NULL) AS new_construction_yn_null,
    COUNT(*) FILTER (WHERE view_yn IS NULL)            AS view_yn_null,
    COUNT(*) FILTER (WHERE water_view_yn IS NULL)      AS water_view_yn_null,
    COUNT(*) FILTER (WHERE cdd_yn IS NULL)             AS cdd_yn_null,
    COUNT(*) FILTER (WHERE association_fee IS NULL)    AS association_fee_null,
    COUNT(*) FILTER (WHERE tax_annual_amount IS NULL)  AS tax_null,
    COUNT(*) FILTER (WHERE lot_size_sqft IS NULL)      AS lot_size_null,
    COUNT(*) FILTER (WHERE senior_community_yn IS NULL) AS senior_yn_null,
    COUNT(*) FILTER (WHERE pets_allowed IS NULL)       AS pets_null,
    COUNT(*) FILTER (WHERE furnished IS NULL)          AS furnished_null,
    COUNT(*) TOTAL
FROM bridge_properties;
```

Expected for 25/25 source fields: null count = 0. Expected for 22/25 (`association_fee`): null count ≤ 3. Expected for 9/25 (`furnished`): null count ≤ 16 in a sale-only sample. Expected for `pets_allowed` (24/25): null count ≤ 1.

---

### Check 3 — Value Spot-Check Against `raw_json`

Verify that the native column values match the source `raw_json` for the same rows:

```sql
SELECT
    listing_key,
    latitude,
    (raw_json::json->>'Latitude')::decimal   AS raw_latitude,
    longitude,
    (raw_json::json->>'Longitude')::decimal  AS raw_longitude,
    county_or_parish,
    raw_json::json->>'CountyOrParish'        AS raw_county,
    property_sub_type,
    raw_json::json->>'PropertySubType'       AS raw_sub_type,
    mls_status,
    raw_json::json->>'MlsStatus'             AS raw_mls_status
FROM bridge_properties
LIMIT 5;
```

Expected: Each native column value matches its corresponding `raw_json` extracted value. Flag any row where they differ for manual investigation.

---

### Check 4 — Index Presence

Verify all 14 new indexes were created successfully:

```sql
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'bridge_properties'
ORDER BY indexname;
```

Expected output includes (among pre-existing indexes):
- `bridge_properties_lat_lng_idx` — composite B-tree on `(latitude, longitude)`
- `bridge_properties_county_or_parish_idx` (or equivalent name) — B-tree on `county_or_parish`
- `bridge_properties_property_sub_type_idx` — B-tree on `property_sub_type`
- `bridge_properties_mls_status_idx` — B-tree on `mls_status`
- Nine partial boolean indexes with their respective `WHERE` clauses visible in `indexdef`

---

### Check 5 — Boolean Sanity

Verify boolean columns contain only true/false/null — never strings like `"true"` or integers like `1`:

```sql
SELECT garage_yn, COUNT(*) FROM bridge_properties GROUP BY garage_yn;
SELECT pool_private_yn, COUNT(*) FROM bridge_properties GROUP BY pool_private_yn;
SELECT association_yn, COUNT(*) FROM bridge_properties GROUP BY association_yn;
SELECT new_construction_yn, COUNT(*) FROM bridge_properties GROUP BY new_construction_yn;
SELECT cdd_yn, COUNT(*) FROM bridge_properties GROUP BY cdd_yn;
```

Expected: Each query returns at most three rows with `garage_yn` values of `t` (PostgreSQL displays boolean TRUE as `t`), `f`, and `<null>`. No string values, no integers, no unexpected enum values.

---

### Check 6 — Import Round-Trip After Phase C

Run a single-record import and confirm new native columns populate alongside `raw_json`:

```bash
php artisan bridge:import-properties --limit=1
```

Then query the most recently imported record:

```sql
SELECT listing_key, latitude, longitude, county_or_parish, property_sub_type,
       garage_yn, pool_private_yn, association_fee, year_built, pets_allowed
FROM bridge_properties
ORDER BY imported_at DESC
LIMIT 1;
```

Expected: All queried native columns are non-null for the freshly imported record (assuming the Stellar API returns the corresponding source fields, which are 25/25 populated for geo and boolean fields).

---

### Check 7 — Backfill Idempotency

Run the backfill command twice and verify the second run produces identical results to the first:

```bash
# First run — capture totals from output
php artisan bridge:backfill-native-columns

# Second run — verify "Skipped (already filled)" equals total row count
php artisan bridge:backfill-native-columns
```

Expected on the second run: `Updated: 0. Skipped (already filled): N. Errors: 0.` where N equals the total row count. If `Updated` is non-zero on the second run, there is a bug in the null-filter logic of the backfill command — investigate before running on production data.

Confirm value stability across both runs:

```sql
SELECT COUNT(DISTINCT latitude) FROM bridge_properties;
SELECT MIN(latitude), MAX(latitude), MIN(longitude), MAX(longitude) FROM bridge_properties;
```

Both queries should return identical results before and after the second backfill run.
