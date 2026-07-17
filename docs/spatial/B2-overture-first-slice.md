# B2 — Overture First Slice (OFFLINE extract)

**Phase 2 · Batch 2A · Spatial Intelligence Platform**
Branch: `phase-2-batch-2a-overture-first-slice-offline-extract`
Status: **cluster-free authoring scope (B1–B4) complete. No PostGIS, no DuckDB, no data download, no infrastructure. Live Q2 measurements PENDING.**

This batch authors the taxonomy, the offline extractor/normalizer, the offline
test suite, and the Q2 sizing harness for the Overture Places first slice. It is
deliberately **inert against infrastructure**: nothing here opens a PostGIS
connection, reads a `SPATIAL_*` secret, installs DuckDB, or downloads Overture.

## Scope guardrails honored

- No PostGIS cluster · no `SPATIAL_*` secrets · no production data.
- No Gate 1A / 1B, no corpus load, no materialization, no object storage.
- No new migrations; the 11 B1.2 spatial migrations are untouched.
- No changes to `app/Services/LocationDna/**` or `config/location_dna.php`, and
  no existing Location DNA consumer is touched.
- PostGIS-only staging / COPY / partition flip / rollback / live acceptance are
  **deferred to the later Class-2 phase**.

## Pinned release

`config/overture_places.php` → `release = 2026-06-17.0` — the latest stable
monthly Overture release available at implementation time (2026-07-17), resolved
but **not downloaded**.

> **Schema deprecation (roadmap).** The June-2026 release deprecated the
> `categories` property (`categories.primary` / `categories.alternate`); it is
> scheduled for removal in the September-2026 release, replaced by
> `basic_category` + `taxonomy`. All three coexist in `2026-06-17.0`, so reading
> `categories.primary` is correct for this slice. **The successor batch MUST
> migrate the primary-category read to `basic_category` before adopting a
> release `>= 2026-09`.**

## B1 — Taxonomy & category mapping

`App\Services\Spatial\OvertureCategoryMap` is the pure SSOT: Overture **primary**
category → canonical category_key. **Primary category only — alternate matches
are ignored.**

| Overture primary | Canonical `category_key` | Thematic block |
|---|---|---|
| grocery_store | grocery_store | daily_convenience |
| restaurant | restaurant | daily_convenience |
| pharmacy | pharmacy | daily_convenience |
| shopping_center | shopping_center | daily_convenience |
| coffee_shop | coffee_shop | daily_convenience |
| gym | gym | daily_convenience |
| fitness_center | **gym** | daily_convenience |
| gas_station | gas_station | transportation |

8 source categories → 7 canonical keys (`fitness_center` + `gym` → `gym`).
Every canonical row seeds `rank_strategy = confidence` and `exclusion_rules =
NULL` (owner decision); `base_source = overture`.

The PostGIS rows are authored as **guarded seeders** but **not applied**:

- `Database\Seeders\SpatialFirstSliceCategorySeeder` → `place_categories`
- `Database\Seeders\SpatialOvertureCategoryMappingSeeder` → `place_category_mappings`

Both target the `pgsql_spatial` connection ONLY and **fail closed** off the
cluster (same guard as the B1.2 migrations). Neither is registered in
`DatabaseSeeder`, so `db:seed` and the SQLite suite never invoke them. Run them
explicitly, in FK order, once a cluster exists:

```bash
php artisan db:seed --class=Database\\Seeders\\SpatialFirstSliceCategorySeeder      --database=pgsql_spatial
php artisan db:seed --class=Database\\Seeders\\SpatialOvertureCategoryMappingSeeder --database=pgsql_spatial
```

## B2 — Offline extractor & normalizer

Canonical normalized extract format: **NDJSON** (owner decision). Pipeline:

```
raw Overture rows  →  OverturePlaceNormalizer  →  NormalizedPlaceRecord[]  →  NormalizedExtractIo  →  *.ndjson
```

| Class (`app/Services/Spatial/`) | Responsibility |
|---|---|
| `OvertureCategoryMap` | primary→canonical map + registry reconciliation |
| `NormalizedPlaceRecord` | immutable DTO; stable NDJSON key order; round-trips |
| `OverturePlaceNormalizer` | primary-only map, `>=0.90` floor, source_count, full accounting |
| `NormalizationResult` | kept + rejected buckets + unmapped tally (nothing lost) |
| `NormalizedExtractIo` | deterministic + idempotent NDJSON read/write |
| `CorpusSizingProjector` | ×450 / ×94 planning-proxy projection |

**Filtering.** A row is kept iff its **primary** category is one of the 8 (via
the map) **and** `confidence >= 0.90`. Rejections are counted in three buckets
(`unmapped`, `low_confidence`, `invalid`); unmapped primary tokens are tallied,
never silently dropped. `source_count` = distinct contributing datasets in
`sources[]` (fallback 1).

**Command** `corpus:extract-overture` (`app/Console/Commands/`):

- **Refuses production** (no override flag).
- Opens **no** DB connection — touches no PostGIS.
- **Works against the committed fixture with DuckDB absent** (fixture/NDJSON code
  path); the DuckDB GeoParquet recipe is authored in the spike for the live run.

```bash
php artisan corpus:extract-overture --region=pinellas \
  --output=storage/app/spatial/overture/pinellas_normalized_places.ndjson
```

## B3 — Offline tests & Pinellas smoke fixture

`tests/fixtures/spatial/overture/pinellas_raw_places.ndjson` — 15 Pinellas-shaped
raw rows: 9 keepable (covering all 8 primary categories, incl. a case/whitespace
variant), 2 sub-floor (`0.75`, `0.89`), 4 unmapped (`bar`×2, `school`, `hotel`
whose *alternate* is mapped — proving alternate is ignored).

Tests (`tests/Unit/Spatial/`, `tests/Feature/Spatial/`): category mapping ·
registry reconciliation · confidence filtering · primary-only behavior ·
source_count derivation · DTO round-trip · deterministic/idempotent output ·
unmapped-counted-not-lost · production guard · SQLite/default-DB isolation ·
sizing projection · seeder data shape · SQL manifest drift guard.

## B4 — Q2 measurement harness

`spikes/phase-2-batch-2a-overture-first-slice/` — DuckDB **count-only** SQL for
Pinellas / Florida / CONUS, per-category counts, and a pre-floor confidence
histogram; plus `q2/run_measurements.php` (detects missing DuckDB → reports all
**PENDING**) and `q2/RESULTS_TEMPLATE.md`.

Accepted planning proxies: **total ≈ 450 B/row**, **composite GiST ≈ 94 B/row**.
DuckDB is not installed, so live Q2 numbers are PENDING by design — the harness
is authored now; measurements run in the Class-2 phase.

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Live Q2 row counts (Pinellas/FL/CONUS) | DuckDB not installed; no download | Class-2 |
| Applying the guarded seeders | No PostGIS cluster / `SPATIAL_*` | Class-2 |
| `basic_category` migration | Only needed for release `>= 2026-09` | successor batch |

## Touch surface

`config/overture_places.php` · `app/Services/Spatial/**` ·
`app/Console/Commands/CorpusExtractOverture.php` ·
`database/seeders/Spatial*Seeder.php` ·
`spikes/phase-2-batch-2a-overture-first-slice/**` · `tests/Unit/Spatial/**` ·
`tests/Feature/Spatial/**` · `tests/fixtures/spatial/overture/**` · this doc.
