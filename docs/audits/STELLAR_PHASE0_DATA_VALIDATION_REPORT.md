# Stellar Phase 0 — Data Validation Report

> Validation date: 2026-06-16  
> Total records in bridge_properties at validation time: 1000  
> Feed scope: Stellar MLS residential-for-sale, StandardStatus=Active  
> Executed by: automated (php artisan bridge:validate-phase0)  
> Generated: 2026-06-16 03:14:09 UTC  
> Verdict: GO WITH ADJUSTMENTS

---

## Pagination Audit Note

Before extending the import command, `BridgeApiService::fetchProperties()` was audited in full. Findings: (a) the method accepts an `int $limit` parameter and sends it as OData `$top`, but has **no `$skip` parameter and makes only a single API call**; (b) `ImportBridgeProperties` has no pagination loop — it calls `fetchProperties($limit)` exactly once and stops; (c) no retry or pagination capability existed in any form. A new `fetchPropertiesPaginated(int $top, int $skip)` method was therefore added to `BridgeApiService`, and a `--target` pagination loop was added to `ImportBridgeProperties`. The existing `fetchProperties()` method was left unchanged to preserve backward compatibility.

---

## Section 1 — Population Rate Table

| Column Name | Stellar JSON Key | Count Populated | Total Records | Percentage | Audit-Sample Baseline | Threshold Tier | Status |
|---|---|---|---|---|---|---|---|
| `latitude` | `Latitude` | 1000 | 1000 | 100% | 25/25 (100%) | Critical Go | ✓ Confirmed |
| `longitude` | `Longitude` | 1000 | 1000 | 100% | 25/25 (100%) | Critical Go | ✓ Confirmed |
| `county_or_parish` | `CountyOrParish` | 1000 | 1000 | 100% | 25/25 (100%) | Critical Go | ✓ Confirmed |
| `property_sub_type` | `PropertySubType` | 878 | 1000 | 87.8% | 25/25 (100%) | Critical Go | ✓ Confirmed |
| `senior_community_yn` | `SeniorCommunityYN` | 836 | 1000 | 83.6% | 25/25 (100%) | Go | ✓ Confirmed |
| `mls_status` | `MlsStatus` | 1000 | 1000 | 100% | 25/25 (100%) | Critical Go | ✓ Confirmed |
| `year_built` | `YearBuilt` | 885 | 1000 | 88.5% | 25/25 (100%) | Go | ✓ Confirmed |
| `association_fee` | `AssociationFee` | 722 | 1000 | 72.2% | 22/25 (88%) | Caution | ⚠ Caution — see Priority Adjustments |
| `pets_allowed` | `PetsAllowed` | 853 | 1000 | 85.3% | 24/25 (96%) | Go | ✓ Confirmed |
| `furnished` | `Furnished` | 350 | 1000 | 35% | 9/25 (36%) | Block | ✗ Block — demoted to Phase 2 |
| `garage_yn` | `GarageYN` | 816 | 1000 | 81.6% | 25/25 (100%) | Go | ✓ Confirmed |
| `pool_private_yn` | `PoolPrivateYN` | 893 | 1000 | 89.3% | 25/25 (100%) | Go | ✓ Confirmed |
| `waterfront_yn` | `WaterfrontYN` | 998 | 1000 | 99.8% | 25/25 (100%) | Go | ✓ Confirmed |
| `tax_annual_amount` | `TaxAnnualAmount` | 893 | 1000 | 89.3% | 25/25 (100%) | Go | ✓ Confirmed |
| `lot_size_sqft` | `LotSizeSquareFeet` | 936 | 1000 | 93.6% | 25/25 (100%) | Go | ✓ Confirmed |
| `association_yn` | `AssociationYN` | 979 | 1000 | 97.9% | 25/25 (100%) | Go | ✓ Confirmed |
| `new_construction_yn` | `NewConstructionYN` | 885 | 1000 | 88.5% | 25/25 (100%) | Go | ✓ Confirmed |
| `view_yn` | `ViewYN` | 1000 | 1000 | 100% | 25/25 (100%) | Go | ✓ Confirmed |
| `water_view_yn` | `STELLAR_WaterViewYN` | 995 | 1000 | 99.5% | 25/25 (100%) | Go | ✓ Confirmed |
| `cdd_yn` | `STELLAR_CDDYN` | 762 | 1000 | 76.2% | 25/25 (100%) | Caution | ⚠ Caution — see Priority Adjustments |

---

## Section 2 — Confirmed Fields

- `latitude` (`Latitude`): 100% — confirmed for Phase 1 as planned.
- `longitude` (`Longitude`): 100% — confirmed for Phase 1 as planned.
- `county_or_parish` (`CountyOrParish`): 100% — confirmed for Phase 1 as planned.
- `property_sub_type` (`PropertySubType`): 87.8% — confirmed for Phase 1 as planned.
- `senior_community_yn` (`SeniorCommunityYN`): 83.6% — confirmed for Phase 1 as planned.
- `mls_status` (`MlsStatus`): 100% — confirmed for Phase 1 as planned.
- `year_built` (`YearBuilt`): 88.5% — confirmed for Phase 1 as planned.
- `pets_allowed` (`PetsAllowed`): 85.3% — confirmed for Phase 1 as planned.
- `garage_yn` (`GarageYN`): 81.6% — confirmed for Phase 1 as planned.
- `pool_private_yn` (`PoolPrivateYN`): 89.3% — confirmed for Phase 1 as planned.
- `waterfront_yn` (`WaterfrontYN`): 99.8% — confirmed for Phase 1 as planned.
- `tax_annual_amount` (`TaxAnnualAmount`): 89.3% — confirmed for Phase 1 as planned.
- `lot_size_sqft` (`LotSizeSquareFeet`): 93.6% — confirmed for Phase 1 as planned.
- `association_yn` (`AssociationYN`): 97.9% — confirmed for Phase 1 as planned.
- `new_construction_yn` (`NewConstructionYN`): 88.5% — confirmed for Phase 1 as planned.
- `view_yn` (`ViewYN`): 100% — confirmed for Phase 1 as planned.
- `water_view_yn` (`STELLAR_WaterViewYN`): 99.5% — confirmed for Phase 1 as planned.

---

## Section 3 — Priority Adjustments

### association_fee

- **Measured rate**: 72.2%
- **Audit-sample baseline**: 22/25 (88%)
- **Tier**: Caution
- **Action**: Included with null-handling note added to Phase 1 migration plan.
- **Null-handling requirement**: The matching engine must assign a neutral score (not zero) when `association_fee` is null for a listing, rather than treating null as 'feature absent.' Query filters on this column must use IS NULL safety rather than equality-only predicates.

### furnished

- **Measured rate**: 35%
- **Audit-sample baseline**: 9/25 (36%)
- **Tier**: Block
- **Action**: Removed from Phase 1 DDL; moved to Phase 2 with note.
- **Phase 2 rationale**: This field's population rate of 35% falls below the 50% Block threshold in the current residential-for-sale feed. Promotion to a native column would create a misleading schema dimension where the majority of rows are NULL. Phase 2 promotion is appropriate once the Stellar For Lease feed is enabled — `Furnished` is expected to populate at a much higher rate in rental records than in residential-for-sale records.

### cdd_yn

- **Measured rate**: 76.2%
- **Audit-sample baseline**: 25/25 (100%)
- **Tier**: Caution
- **Action**: Included with null-handling note added to Phase 1 migration plan.
- **Null-handling requirement**: The matching engine must assign a neutral score (not zero) when `cdd_yn` is null for a listing, rather than treating null as 'feature absent.' Query filters on this column must use IS NULL safety rather than equality-only predicates.

---

## Section 4 — Go/No-Go Verdict

**GO WITH ADJUSTMENTS** — All critical fields confirmed. Non-critical field(s) blocked and moved to Phase 2. Phase 1 migration may proceed with the adjusted column list documented in Section 3.

**Summary:**

- Confirmed (Go): 17 fields
- Caution (50–79%): 2 fields
- Blocked (<50%, demoted to Phase 2): 1 fields
- Critical failures (Phase 1 blocked): 0 fields

**Next steps:**

1. Update `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` to reflect the adjusted Phase 1 column list (blocked fields moved to Phase 2).
2. Proceed with Phase 1 migration using the adjusted column list.
3. Add null-handling notes for any Caution-tier fields before the matching engine consumes them.

---

## Proof Block

| Item | Value |
|---|---|
| Command run | `php artisan bridge:validate-phase0` |
| Validation date | 2026-06-16 03:14:09 UTC |
| Total bridge_properties rows | 1000 |
| Fields evaluated | 20 |
| Confirmed (Go) | 17 |
| Caution | 2 |
| Blocked (Phase 2) | 1 |
| Critical failures | 0 |
| Schema changes made | **None** |
| Migrations created | **None** |

