# Stellar Phase 0 — Data Validation Plan

> Document type: Pre-implementation validation contract
> Date: 2026-06-16
> Scope: Methodology, thresholds, and reporting format only — no code, no migrations, no schema changes
> Downstream document produced by execution: `docs/audits/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md`
> Upstream context:
>   · `docs/audits/STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`
>   · `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`
>   · `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md`
>   · `docs/audits/STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`
>   · `docs/audits/STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md`

---

## Table of Contents

1. [Why Validation Is Needed](#1-why-validation-is-needed)
2. [Required Sample Size and Import Approach](#2-required-sample-size-and-import-approach)
3. [Critical Fields](#3-critical-fields)
4. [Population-Rate Methodology](#4-population-rate-methodology)
5. [Go/No-Go Thresholds](#5-gono-go-thresholds)
6. [Reporting Format](#6-reporting-format)
7. [Escalation Criteria](#7-escalation-criteria)
8. [How Promotion Priorities Are Adjusted](#8-how-promotion-priorities-are-adjusted)

---

## 1. Why Validation Is Needed

### The Problem

The Phase 1 native column migration promotes 20 Stellar MLS fields from `raw_json` into indexed native columns on `bridge_properties`. Every one of those 20 fields was selected based on its population rate in a **25-record audit sample** of the Stellar residential-for-sale feed.

A 25-record sample is sufficient to establish that a field *exists and is used* in the Stellar schema. It is not sufficient to establish that the field is reliably populated across the full live dataset of 1,000+ active Florida residential listings. A field that appears in 25/25 records in a hand-selected sample may be sparse, feed-configuration-dependent, or region-specific when measured across the full population.

### The Consequence of Not Validating

If Phase 1 proceeds based solely on the 25-record sample and a promoted field is actually sparse at scale, the result is:

- **Wasted schema space**: A nullable column that is almost always NULL contributes no query performance benefit and misleads schema readers.
- **Misleading query semantics**: A WHERE clause on a sparsely populated native column silently excludes the majority of listings (NULLs fail equality predicates), producing match results that appear complete but are actually missing most of the inventory.
- **Distorted matching engine logic**: Score weights calibrated against a field assume it is present; when it is absent for 60%+ of listings, every score that uses it is wrong in a systematic, silent way.
- **Wasted migration effort**: Rolling back a migration after data is loaded requires a new migration, a re-backfill, and index rebuilds — all of which are more expensive than not promoting the field in the first place.

### What Validation Gates

Validation is the required gate before any Phase 1 migration file is written or run. The execution task (`STELLAR_PHASE0_DATA_VALIDATION_REPORT.md`) runs the queries defined in Section 4 against a 1,000-record live sample and applies the thresholds defined in Section 5. If the population rates confirm the audit-sample findings, Phase 1 proceeds unchanged. If they do not, the scope of Phase 1 is adjusted per Sections 7 and 8 before any migration runs.

---

## 2. Required Sample Size and Import Approach

### Minimum Record Count

**1,000 records** is the minimum required sample for Phase 0 validation.

### Why 1,000

- The Florida Stellar MLS feed serves thousands of active residential listings. A 25-record sample has a margin of error too wide to detect field sparsity caused by feed configuration, regional variation, or MLS board-specific data entry practices. At 1,000 records, a field with a true population rate of 80% will be observed at 80% ± 2.5% (95% confidence), which is precise enough to make a clear Go/No-Go determination against the thresholds defined in Section 5.
- 1,000 records is achievable in a single paginated import session against the Bridge Data Output API without requiring special dataset access or production data exports.
- The 1,000-record threshold is large enough to detect fields that are sparse by feed configuration (e.g., a field only populated for listings in certain counties or under certain listing types) rather than by chance in a small sample.

### Import Approach

The existing `bridge:import-properties` Artisan command is the correct entry point. No new command is required for validation — the same command that will populate production data will also populate the validation sample.

**Pagination:** The Bridge Data Output API caps page sizes (typically 200 records per request). The import command must be run in a loop until 1,000 records are stored in `bridge_properties`. If the command does not already support `$top` / `$skip` OData pagination, extend it to do so before the validation run. Acceptable implementation: pass `--limit=1000` or run the command in five consecutive 200-record batches using the `$skip` offset.

**Deduplication:** Use `updateOrCreate` on `listing_key` (the Stellar primary key) in `ImportBridgeProperties::handle()`. This is already the production import pattern. Running the command multiple times will not create duplicate rows.

**Record scope:** Import residential-for-sale records (`PropertyType = 'Residential'`, `StandardStatus = 'Active'`). This is the same feed scope used for the 25-record audit sample. Do not mix in rental/for-lease records for this validation run — the rental feed is a separate data source with different field population patterns and is explicitly out of scope for Phase 1.

**Environment:** Run against the development or staging `bridge_properties` table. Do not run validation imports against production until Phase 1 is approved and the migration is ready to execute.

---

## 3. Critical Fields

Five of the 20 Phase 1 candidate columns are **load-bearing** — they are required for the matching engine's foundational query patterns, not merely enhancement signals. A population failure in any one of these five is not a minor scope adjustment; it triggers a full Phase 1 replan.

### The Five Critical Fields

| # | Column Name | Stellar JSON Key | Why It Is Load-Bearing |
|---|---|---|---|
| 1 | `latitude` | `Latitude` | Every radius-based buyer and tenant match query uses Haversine distance computed from native `latitude`/`longitude` columns. Without a reliable native `latitude` column, map-based search and radius matching are impossible using indexed queries. Any listing with a null `latitude` is invisible to every distance-based match, silently excluded with no error. |
| 2 | `longitude` | `Longitude` | Identical load-bearing role to `latitude`. The composite B-tree index on `(latitude, longitude)` — the foundation of the Haversine bounding-box pattern — requires both columns to be populated to be useful. A sparse `longitude` means the index is useless for the majority of the table. |
| 3 | `county_or_parish` | `CountyOrParish` | County-level filtering is the primary geographic grouping above the ZIP code for both buyer and tenant matching. Buyer criteria include county as a selectable location dimension. The matching engine WHERE clause uses `county_or_parish` as a hard filter entry point. A sparse county column means county-filtered match queries silently exclude most listings. |
| 4 | `property_sub_type` | `PropertySubType` | Property subtype separates Single Family Residence from Condominium, Townhouse, and Villa — a critical buyer preference dimension and a key context field for Ask AI answers ("Is this a condo or a house?"). Without reliable subtype data, the matching engine cannot execute the subtype filter and the Phase 1 buyer scoring model's Property Type category (10 points, Section 4.4 of the Buyer Architecture document) cannot score correctly. |
| 5 | `mls_status` | `MlsStatus` | MLS status is the board-specific status vocabulary required for alert trigger diffing. The alert system's status-change alert compares `mls_status` at successive import times to detect transitions (e.g., Active → Sold, Active → Pending). Without a reliable native `mls_status` column, the status-change alert trigger cannot run as an indexed comparison job and must fall back to per-record raw_json extraction — which is acceptable for the MVP alert system but creates a known architectural debt that grows with record volume. Additionally, `mls_status` is the rental-specific status filter for tenant matching (Stellar's rental feed uses board-specific status values not fully captured by `standard_status`). |

### What "Load-Bearing" Means for Thresholds

For all other 15 fields, the three-tier threshold system (Section 5) applies: Go / Caution / Block. For these five critical fields, **only the Go threshold applies**. Any one of the five below 80% population triggers a full Phase 1 replan before any migration file is written. There is no "Caution" tier for critical fields — a 75% population rate on `latitude` means 25% of all listings would be invisible to radius search, which is not an acceptable starting condition.

---

## 4. Population-Rate Methodology

### Definition of "Populated"

A field is counted as populated for a given row if and only if the value in `bridge_properties.raw_json` for that Stellar field key meets all three of the following conditions:

1. The key exists in the JSON object (not absent/missing).
2. The value is not the JSON literal `null`.
3. The value is not an empty string `''`.

The SQL pattern that encodes this definition for all scalar fields (strings, numbers, booleans) is:

```sql
raw_json->>'StellarFieldKey' IS NOT NULL
AND raw_json->>'StellarFieldKey' != ''
AND raw_json->>'StellarFieldKey' != 'null'
```

Note: `raw_json->>'key'` in PostgreSQL returns `NULL` when the key is absent or when the value is the JSON literal null. The `!= 'null'` clause catches the edge case where the JSON value is the string `"null"` rather than the JSON literal null.

### The `PetsAllowed` Special Case

`PetsAllowed` is a **JSON array** in the Stellar API response (e.g., `["Yes"]`, `["Cats OK", "Dogs OK"]`, `[]`). The scalar string extraction pattern above does not apply. An empty array `[]` must not be counted as populated. The correct check for this field uses PostgreSQL's `jsonb_typeof` and `jsonb_array_length` functions:

```sql
jsonb_typeof(raw_json->'PetsAllowed') = 'array'
AND jsonb_array_length(raw_json->'PetsAllowed') > 0
```

This returns true only when the key exists, the value is a JSON array, and the array contains at least one element.

### Population Rate Formula

For each field:

```
population_rate = count_populated / total_rows
```

Where:
- `count_populated` = count of rows where the field passes the populated check above
- `total_rows` = total count of rows in `bridge_properties` at validation time

Both counts use the same WHERE scope (no additional filters — count all rows in the table, not just Active listings).

### Full 20-Field Query Table

The following table defines the exact SQL query pattern for each of the 20 Phase 1 candidate columns. Each query produces `count_populated`. Divide by `SELECT COUNT(*) FROM bridge_properties` to get the population rate.

| # | Column Name | Stellar JSON Key | Query Pattern | Notes |
|---|---|---|---|---|
| 1 | `latitude` | `Latitude` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'Latitude' IS NOT NULL AND raw_json->>'Latitude' != '' AND raw_json->>'Latitude' != 'null'` | Scalar numeric |
| 2 | `longitude` | `Longitude` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'Longitude' IS NOT NULL AND raw_json->>'Longitude' != '' AND raw_json->>'Longitude' != 'null'` | Scalar numeric |
| 3 | `county_or_parish` | `CountyOrParish` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'CountyOrParish' IS NOT NULL AND raw_json->>'CountyOrParish' != '' AND raw_json->>'CountyOrParish' != 'null'` | Scalar string |
| 4 | `property_sub_type` | `PropertySubType` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'PropertySubType' IS NOT NULL AND raw_json->>'PropertySubType' != '' AND raw_json->>'PropertySubType' != 'null'` | Scalar string |
| 5 | `senior_community_yn` | `SeniorCommunityYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'SeniorCommunityYN' IS NOT NULL AND raw_json->>'SeniorCommunityYN' != '' AND raw_json->>'SeniorCommunityYN' != 'null'` | Scalar boolean (stored as string in JSON) |
| 6 | `mls_status` | `MlsStatus` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'MlsStatus' IS NOT NULL AND raw_json->>'MlsStatus' != '' AND raw_json->>'MlsStatus' != 'null'` | Scalar string |
| 7 | `year_built` | `YearBuilt` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'YearBuilt' IS NOT NULL AND raw_json->>'YearBuilt' != '' AND raw_json->>'YearBuilt' != 'null'` | Scalar integer |
| 8 | `association_fee` | `AssociationFee` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'AssociationFee' IS NOT NULL AND raw_json->>'AssociationFee' != '' AND raw_json->>'AssociationFee' != 'null'` | Scalar numeric; 22/25 in audit sample |
| 9 | `pets_allowed` | `PetsAllowed` | `SELECT COUNT(*) FROM bridge_properties WHERE jsonb_typeof(raw_json->'PetsAllowed') = 'array' AND jsonb_array_length(raw_json->'PetsAllowed') > 0` | **Array** — use array check, not string extraction |
| 10 | `furnished` | `Furnished` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'Furnished' IS NOT NULL AND raw_json->>'Furnished' != '' AND raw_json->>'Furnished' != 'null'` | Scalar string; 9/25 in audit sample (sale-context population; rental feed expected higher) |
| 11 | `garage_yn` | `GarageYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'GarageYN' IS NOT NULL AND raw_json->>'GarageYN' != '' AND raw_json->>'GarageYN' != 'null'` | Scalar boolean |
| 12 | `pool_private_yn` | `PoolPrivateYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'PoolPrivateYN' IS NOT NULL AND raw_json->>'PoolPrivateYN' != '' AND raw_json->>'PoolPrivateYN' != 'null'` | Scalar boolean |
| 13 | `waterfront_yn` | `WaterfrontYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'WaterfrontYN' IS NOT NULL AND raw_json->>'WaterfrontYN' != '' AND raw_json->>'WaterfrontYN' != 'null'` | Scalar boolean |
| 14 | `tax_annual_amount` | `TaxAnnualAmount` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'TaxAnnualAmount' IS NOT NULL AND raw_json->>'TaxAnnualAmount' != '' AND raw_json->>'TaxAnnualAmount' != 'null'` | Scalar numeric |
| 15 | `lot_size_sqft` | `LotSizeSquareFeet` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'LotSizeSquareFeet' IS NOT NULL AND raw_json->>'LotSizeSquareFeet' != '' AND raw_json->>'LotSizeSquareFeet' != 'null'` | Scalar integer; note that the string `"0"` is a valid value and must be counted as populated |
| 16 | `association_yn` | `AssociationYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'AssociationYN' IS NOT NULL AND raw_json->>'AssociationYN' != '' AND raw_json->>'AssociationYN' != 'null'` | Scalar boolean |
| 17 | `new_construction_yn` | `NewConstructionYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'NewConstructionYN' IS NOT NULL AND raw_json->>'NewConstructionYN' != '' AND raw_json->>'NewConstructionYN' != 'null'` | Scalar boolean |
| 18 | `view_yn` | `ViewYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'ViewYN' IS NOT NULL AND raw_json->>'ViewYN' != '' AND raw_json->>'ViewYN' != 'null'` | Scalar boolean |
| 19 | `water_view_yn` | `STELLAR_WaterViewYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'STELLAR_WaterViewYN' IS NOT NULL AND raw_json->>'STELLAR_WaterViewYN' != '' AND raw_json->>'STELLAR_WaterViewYN' != 'null'` | Stellar extension field — key name includes prefix |
| 20 | `cdd_yn` | `STELLAR_CDDYN` | `SELECT COUNT(*) FROM bridge_properties WHERE raw_json->>'STELLAR_CDDYN' IS NOT NULL AND raw_json->>'STELLAR_CDDYN' != '' AND raw_json->>'STELLAR_CDDYN' != 'null'` | Stellar extension field — key name includes prefix |

### Audit-Sample Baselines

The 25-record audit sample from `STELLAR_MATCHING_READINESS_AUDIT.md` and `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` provides the following baselines for comparison against the 1,000-record result:

| Column Name | Audit-Sample Baseline |
|---|---|
| `latitude` | 25/25 (100%) |
| `longitude` | 25/25 (100%) |
| `county_or_parish` | 25/25 (100%) |
| `property_sub_type` | 25/25 (100%) |
| `senior_community_yn` | 25/25 (100%) |
| `mls_status` | 25/25 (100%) |
| `year_built` | 25/25 (100%) |
| `association_fee` | 22/25 (88%) |
| `pets_allowed` | 24/25 (96%) |
| `furnished` | 9/25 (36%) |
| `garage_yn` | 25/25 (100%) |
| `pool_private_yn` | 25/25 (100%) |
| `waterfront_yn` | 25/25 (100%) |
| `tax_annual_amount` | 25/25 (100%) |
| `lot_size_sqft` | 25/25 (100%) |
| `association_yn` | 25/25 (100%) |
| `new_construction_yn` | 25/25 (100%) |
| `view_yn` | 25/25 (100%) |
| `water_view_yn` | 25/25 (100%) |
| `cdd_yn` | 25/25 (100%) |

**Note on `furnished`:** The audit-sample baseline of 9/25 (36%) already places `furnished` below the Go threshold based on the for-sale sample alone. This is expected — `Furnished` in a for-sale record describes whether the home is being sold furnished, not its rental condition, and is therefore sparsely populated in a residential-for-sale feed. The 1,000-record validation will measure its actual population rate in the live for-sale feed. If it is confirmed below 50%, it will be demoted from Phase 1 per the Block threshold rules. The rental feed (a future separate import) is expected to populate `furnished` at a much higher rate for rental records.

---

## 5. Go/No-Go Thresholds

### Overview

Every field's measured population rate is classified into one of three tiers. The tier determines whether the field proceeds into Phase 1 as planned, proceeds with a documented caveat, or is removed from Phase 1 scope entirely.

### Tier Definitions

#### Go Threshold — Proceed to Phase 1 as planned

**Condition:** Field is ≥80% populated across the 1,000-record sample.

**Meaning:** The population rate is high enough that the native column will be useful in WHERE clauses and scoring queries. NULLs will be rare exceptions, not the norm. The matching engine can treat a null value as an anomaly and handle it with a null-safe default rather than needing a structural workaround.

**Action:** Include the field in the Phase 1 migration DDL exactly as documented in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`. No modification needed.

---

#### Caution Threshold — Proceed with documented note

**Condition:** Field is 50–79% populated across the 1,000-record sample.

**Meaning:** The field is more common than absent but is not reliably present for the majority of listings. Queries that use this column in a WHERE clause will silently exclude 21–50% of listings where the field is null. This is acceptable for Phase 1 only if the matching engine's use of this field accounts for nulls explicitly — specifically, the scoring model must assign a neutral score (not zero) when the field is null, rather than treating null as "feature absent."

**Action:** Include the field in the Phase 1 migration DDL, but add a note to `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`'s Risk Analysis section (Section 7) documenting the sparse rate and the required null-handling behavior in the matching engine. The migration plan reviewer must acknowledge the sparse rate before the migration runs.

---

#### Block Threshold — Remove from Phase 1 scope

**Condition:** Field is <50% populated across the 1,000-record sample.

**Meaning:** More than half of all records in the live dataset have no value for this field. Promoting it to a native column creates a misleading schema that implies the field is a reliable data dimension when it is not. The matching engine cannot depend on this field for any WHERE clause without excluding the majority of listings.

**Action:** Remove the field from the Phase 1 migration DDL. Move it to Phase 2 in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` per Section 8. Phase 2 promotion is appropriate when either (a) a different data feed (e.g., the rental feed) is expected to populate it, or (b) further investigation reveals a feed configuration change that would increase its population.

---

### Critical Field Override

The five fields designated as critical in Section 3 (`latitude`, `longitude`, `county_or_parish`, `property_sub_type`, `mls_status`) follow a stricter rule: **the Go threshold is the only acceptable outcome**. The Caution and Block tiers do not apply to critical fields. If any one of the five is measured below 80%, the outcome is not "proceed with a note" — it is a full Phase 1 replan as defined in Section 7.

### Threshold Summary Table

| Tier | Population Rate | Applies To | Action |
|---|---|---|---|
| **Go** | ≥80% | All 20 fields | Include in Phase 1 migration as planned |
| **Caution** | 50–79% | Non-critical fields only | Include in Phase 1 with null-handling note in migration plan |
| **Block** | <50% | Non-critical fields only | Remove from Phase 1; move to Phase 2 |
| **Critical Go requirement** | ≥80% | `latitude`, `longitude`, `county_or_parish`, `property_sub_type`, `mls_status` | Any one below 80% → full Phase 1 replan required |

---

## 6. Reporting Format

The execution task produces a single document: `docs/audits/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md`. That document must conform exactly to the structure defined in this section. No open fields, no TBD entries, no conditional sections — every row is completed.

### Required Document Structure

```
# Stellar Phase 0 — Data Validation Report

> Validation date: [YYYY-MM-DD]
> Total records in bridge_properties at validation time: [N]
> Feed scope: Stellar MLS residential-for-sale, StandardStatus=Active
> Executed by: [name or "automated"]
> Verdict: [GO / REPLAN REQUIRED]
```

---

### Section 1 — Population Rate Table

A single table with one row per field. Columns are fixed and must appear in the order below:

| Column Name | Stellar JSON Key | Count Populated | Total Records | Percentage | Audit-Sample Baseline | Threshold Tier | Status |
|---|---|---|---|---|---|---|---|

**Column definitions:**

- **Column Name**: The `bridge_properties` native column name as defined in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`.
- **Stellar JSON Key**: The exact key used in `raw_json` (e.g., `Latitude`, `STELLAR_CDDYN`).
- **Count Populated**: The integer result of the `COUNT(*)` query from Section 4's query table.
- **Total Records**: The total row count of `bridge_properties` at validation time (same value for every row — the denominator).
- **Percentage**: `(Count Populated / Total Records) * 100`, rounded to one decimal place (e.g., `87.4%`).
- **Audit-Sample Baseline**: The baseline from Section 4's audit-sample table (e.g., `25/25 (100%)`, `22/25 (88%)`).
- **Threshold Tier**: One of: `Go`, `Caution`, `Block`, or `Critical Go` (for the five critical fields, this column always shows `Critical Go`).
- **Status**: One of: `✓ Confirmed`, `⚠ Caution — see Priority Adjustments`, `✗ Block — demoted to Phase 2`, or `✗ Critical Failure — Phase 1 replan required`.

The rows must appear in the same order as the 20 fields in Section 4's query table (`latitude` first, `cdd_yn` last).

---

### Section 2 — Confirmed Fields

A bulleted list of all fields that received a `✓ Confirmed` status. Format:

```
- `column_name` (`StellarJsonKey`): [percentage]% — confirmed for Phase 1 as planned.
```

---

### Section 3 — Priority Adjustments

This section is present even if there are no adjustments (in which case it contains the single line "No adjustments required — all fields confirmed at Go threshold or above.").

If there are adjustments, each adjusted field gets its own sub-entry:

```
### [column_name]

- **Measured rate**: [percentage]%
- **Audit-sample baseline**: [baseline]
- **Tier**: [Caution / Block]
- **Action**: [Exact action taken — either "included with null-handling note added to Phase 1 migration plan" or "removed from Phase 1 DDL; moved to Phase 2 with note"]
- **Null-handling requirement** (Caution tier only): [description of how the matching engine must treat null values for this field]
- **Phase 2 rationale** (Block tier only): [explanation of what data source or feed change would populate this field at scale]
```

---

### Section 4 — Go/No-Go Verdict

The final section of the report. It contains exactly three elements:

1. **Verdict line**: One of:
   - `**GO** — All critical fields confirmed. Phase 1 migration may proceed.`
   - `**REPLAN REQUIRED** — [field name(s)] failed the critical Go threshold. Phase 1 migration is blocked until the replan is complete.`

2. **Record count and date**: `Verdict based on [N] records as of [YYYY-MM-DD].`

3. **Next step**: Either "Proceed to Phase 1 migration execution per `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`." or "See Section 8 of `STELLAR_PHASE0_DATA_VALIDATION_PLAN.md` for replan process."

---

## 7. Escalation Criteria

### Outcome 1 — No Adjustment Needed (proceed directly)

**Trigger:** All 20 fields measure at or above the Go threshold (≥80%).

**Action:** No changes to `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`. Proceed to Phase 1 migration execution as documented. Record `✓ Confirmed` for all fields in the report.

---

### Outcome 2 — Minor Adjustment (targeted demotion, Phase 1 continues)

**Trigger:** One to five non-critical fields fall in the Caution (50–79%) or Block (<50%) tier. No critical fields are below 80%.

**Action:** Demote only the specific fields that are in the Caution or Block tier. Keep all other Phase 1 fields in scope. The Phase 1 migration proceeds on its planned timeline with the adjusted column list.

- **For each Caution-tier field**: Keep it in Phase 1. Add a null-handling note to the Risk Analysis section of `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` specifying how the matching engine must treat null values (neutral score, not zero penalty).
- **For each Block-tier field**: Remove it from the Phase 1 migration DDL. Move it to Phase 2 per Section 8. Update the report's Priority Adjustments section.

This is not a replan. It is a scoped correction. The Phase 1 migration timeline is unaffected.

---

### Outcome 3 — Full Replan Required (Phase 1 blocked)

**Trigger (either condition):**

- Any one of the five critical fields (`latitude`, `longitude`, `county_or_parish`, `property_sub_type`, `mls_status`) measures below 80%.
- OR more than five of the 20 fields (counting both critical and non-critical) fall below 50% (Block tier).

**Action:** Phase 1 migration is blocked. Do not write or run any migration file. The replan process in Section 8 must be completed and reviewed before Phase 1 can proceed. The report verdict reads `REPLAN REQUIRED`.

The full replan is a more serious outcome than a minor adjustment because it means the foundational assumptions of the matching engine architecture may need to change — not just the column list. If `latitude` is sparse, the Haversine radius search pattern cannot be the primary matching strategy and an alternative must be designed. If `county_or_parish` is sparse, county-level filtering is unreliable and the location matching model must be reconsidered.

---

## 8. How Promotion Priorities Are Adjusted

This section defines the exact process for adjusting Phase 1 scope when a field is demoted (Block tier) or when a full replan is required (Outcome 3). No migration file is written until this adjustment is complete and the updated plan has been reviewed.

### Step A — Update the Migration DDL in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`

For each **Block-tier field** being demoted:

1. Open `docs/audits/STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`.
2. Remove the field's row from Section 2's "Top 20 Columns to Add" table.
3. Remove the field's index DDL from Section 3 (if it had an index planned).
4. Remove the field's type coercion rule from Section 4's "Type Coercion Rules" table.
5. Remove the field's mapping entry from Section 5's "Mapping Array Changes" code block.
6. Remove the field from Section 5's `$fillable` and `$casts` additions.
7. Renumber the remaining columns in Section 2's table if the column numbering (`#`) is used as a reference elsewhere in the document.
8. Update Section 1's Executive Summary to reflect the revised column count (e.g., "top 18 columns" instead of "top 20 columns") and the revised matching readiness estimate.

### Step B — Move the Demoted Field to Phase 2

1. In `docs/audits/STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`, locate or create a "Phase 2 Deferred Columns" section (add after Section 2 if it does not exist).
2. Add a row for the demoted field including: column name, Stellar JSON key, SQL type, the validation-measured population rate, and a note explaining why it was demoted (e.g., "Measured at 38% in 1,000-record validation run on 2026-06-20 — below 50% Block threshold; expected to populate at higher rate in rental feed").
3. Tag the field with its expected Phase 2 promotion condition (e.g., "Promote after rental feed is active and confirmed to populate this field at ≥80%").

### Step C — Update the Matching Engine Architecture Documents

For each demoted field, identify whether it is referenced in the "Phase 1 behavior" notes of either matching engine architecture document:

- `docs/audits/STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` — check Section 2 (Matching Inputs), Section 3.2 (Hard Exclusion Filters), Section 4 (Scoring Model), and any "Phase 1 behavior" paragraphs.
- `docs/audits/STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md` — check Section 3 (Matching Inputs), Section 4 (Hard Filters), and Section 5 (Scoring Model).

For each reference found:

1. Update the "Native Column?" column from "Phase 1 → `column_name`" to "Phase 2 → `column_name`" (or "raw_json only — deferred to Phase 2").
2. Update any "Phase 1 behavior" note to describe how the engine must handle the absence of this column at Phase 1 launch (e.g., "Phase 1 behavior: `furnished` column not available; the Furnished filter is skipped for Phase 1; no score contribution; displayed as 'data not available' in match explanation").
3. Do not change the scoring weights or the field's planned role in the engine — only update the phase timeline and the Phase 1 interim behavior.

### Step D — Full Replan Process (Outcome 3 only)

If a full replan is triggered (Outcome 3 from Section 7), Steps A through C above still apply for any Block-tier non-critical fields. Additionally:

1. Create a new document `docs/audits/STELLAR_PHASE1_REPLAN_REQUIRED.md` that:
   - Identifies which critical field(s) failed validation and their measured rates.
   - States the architectural impact (e.g., "Without reliable `latitude`/`longitude`, the Haversine radius search pattern cannot be the primary matching approach for Phase 1").
   - Proposes at least one alternative: either (a) investigate why the field is sparse (feed configuration issue? API parameter change needed?) and re-run validation after the fix, or (b) design an alternative matching approach that does not depend on the failed critical field.
2. Phase 1 migration execution is frozen until this document is reviewed and a resolution path is chosen.
3. No migration file is written during the replan period.

---

*End of Stellar Phase 0 — Data Validation Plan.*
*This document is the pre-implementation contract for validation execution. All decisions in Sections 4, 5, 6, 7, and 8 are final — no open options, no TBD entries. The execution task must follow this plan exactly.*
