# Phase 2 · Batch 2C — Overture Import Framework (AUTHORING)

Branch: `phase-2-batch-2c-overture-import-framework-authoring`
Status: **cluster-free authoring complete; live import DEFERRED to Class-2 (no cluster / no download / nothing executed).**

This spike holds the **live-run SQL recipes** for loading a normalized Overture
extract into the partitioned `places` corpus. It is authored, not run:

- **No PostGIS cluster** and **no `SPATIAL_*` secrets** are used.
- **No live import**, **no Overture download**, **no DuckDB execution**, **no
  seeder run**.
- The runnable PHP framework lives in the app and is exercised entirely offline
  by the test suite against a committed normalized fixture.

## The framework (app — `App\Services\Spatial`, offline & pure)

| Class | Responsibility |
|---|---|
| `CorpusPartitionManager` | Author the `places` LIST-partition DDL for a corpus_version (create / stage / check / attach / detach / drop). |
| `PlaceRowMaterializer` | Normalized record → one `places` row (COPY column order; EWKT geography). |
| `CorpusCopyLoader` | Serialize rows into PostgreSQL COPY text; author the `COPY … FROM STDIN` / `\copy` statements. |
| `CorpusImportLedger` | Build the `corpus_imports` provenance row + INSERT/activate/supersede SQL. |
| `CorpusActivationService` | Ordered, transactional activation plan (attach + ledger flip) and retirement plan. |
| `CorpusImportAcceptance` | Pre-load acceptance gate (source/category/confidence/coords/reconciliation). |
| `corpus:import-overture` (command) | OFFLINE dry-run: reads an extract, gates, and writes all load artifacts. Refuses production; opens no cluster. |

## The load flow (what the recipes do, in order)

```
sql/
  create_partition.sql    # 1. CREATE staging table LIKE places  (or direct PARTITION OF)
  load_copy.sql           # 2. \copy the offline COPY payload into staging
  acceptance_checks.sql   # 3. READ-ONLY gate: counts / source / category / floor / coords
  attach_activate.sql     # 4. ATTACH PARTITION + ledger staging→active (one txn)
  ledger_insert.sql       # 5. one corpus_imports provenance row
RESULTS_TEMPLATE.md       # copy to RESULTS.md and fill when the Class-2 load runs
```

Zero-downtime model (owner decision): load into a **detached** staging table, add
a `CHECK (corpus_version = …)` so `ATTACH PARTITION` is an **O(1)** metadata flip,
then flip the ledger status in the same transaction. The previous version's
partition is **left attached** for instant rollback; retirement (detach + drop)
is a separate, explicit run.

## Pins (SSOT: `config/overture_places.php`)

| Pin | Value |
|---|---|
| Overture release | `2026-06-17.0` |
| Confidence floor | `>= 0.90` |
| Total storage proxy | ~450 B/row |
| Composite GiST proxy | ~94 B/row |
| corpus_version | `overture-{release}-{region}` (e.g. `overture-2026-06-17.0-pinellas`) |
| Partition name | `places_p_<sanitized corpus_version>` |

## Try the offline dry-run (no cluster needed)

```bash
# 1) produce a normalized extract (Batch 2A), then author the import plan:
php artisan corpus:extract-overture --region=pinellas --output=/tmp/pinellas.ndjson
php artisan corpus:import-overture  --region=pinellas --input=/tmp/pinellas.ndjson --out-dir=/tmp/import

# writes: partition_load.sql · copy_payload.txt · ledger.json · activate.sql
# executes NOTHING against a database.
```

## Later (Class-2 phase, when a cluster exists)

1. Run `create_partition.sql` → `load_copy.sql` (with the generated payload).
2. Run `acceptance_checks.sql`; every "must be 0" query must return 0.
3. Run `attach_activate.sql` and `ledger_insert.sql`.
4. Fill `RESULTS.md` from `RESULTS_TEMPLATE.md`.

Schema note: release `2026-06-17.0` still ships the deprecated `categories`
struct; any release `>= 2026-09` removes it — migrate the primary-category read
to `basic_category` before adopting a newer release (see Batch 2A).
