# Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN Feasibility Spike — RESULTS

**Determination: PASS (composite strategy).** The composite `gist(category_key, geom)`
index over a `geography(Point,4326)` column satisfies every Stage 0a success
criterion for all four sparse categories, on both the unpartitioned table and the
LIST-partitioned-by-`corpus_version` table. The partial-index fallback also passes
and is retained as a documented contingency. KNN results are byte-for-byte
identical to brute-force `ST_Distance` ordering.

This is a throwaway feasibility spike. It creates **no** Laravel migrations and
modifies **no** application runtime code. All artifacts live under
`spikes/phase-2-batch-0a-postgis-knn/`. All database objects live in the
disposable `spike` database inside the `byo-batch0-spike` container.

---

## 1. Environment

| Component | Value |
|-----------|-------|
| PostgreSQL | 16.9 (Debian 16.9-1.pgdg110+1) |
| PostGIS | 3.5.2 (GEOS 3.9.0, PROJ 7.2.1) |
| `btree_gist` | 1.7 (enables scalar `category_key` in the GiST index) |
| `pg_trgm` | 1.6 |
| Column type under test | `geography(Point, 4326)` — **not** geometry |
| Container | `byo-batch0-spike` (image `postgis/postgis:16-3.5`) |
| Planner settings for plans | `jit = off`, `max_parallel_workers_per_gather = 0` (readable, deterministic plans) |

## 2. Dataset

**Total: 176,560 rows.** Continental-US bounding box. Deterministic
(`setseed(0.20260715)`).

| category_key | rows | class | v1 / v2 |
|--------------|-----:|-------|---------|
| restaurant | 80,000 | dense | 31,922 / 48,078 |
| retail_store | 60,000 | dense | 23,942 / 36,058 |
| school | 20,000 | dense | 8,175 / 11,825 |
| park | 15,000 | dense | 6,039 / 8,961 |
| urgent_care | 900 | **sparse** | 185 / 715 |
| marina | 300 | **sparse** | 57 / 243 |
| boat_ramp | 220 | **sparse** | 51 / 169 |
| airport | 140 | **sparse** | 24 / 116 |

Partitioned table parity: `places_spike` (176,560) = `places_spike_part` (176,560),
split v1 = 70,395 / v2 = 106,165. The dense filler exists specifically so that a
distance-first / category-after plan (Strategy B) must walk thousands of
wrong-category rows — making the filter-walk failure measurable.

## 3. Strategy A — composite `gist(category_key, geom)` — **PASS**

Query shape (per sparse category):

```sql
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = '<cat>'
ORDER BY geom <-> :ref
LIMIT 10;
```

Every plan is `Limit → Index Scan using places_cat_geom`, with:

- **`Index Cond: (category_key = '<cat>')`** — category handled inside the index
- **`Order By: (geom <-> ref)`** — KNN ordering inside the same index scan
- **no `Filter` / zero `Rows Removed by Filter`**
- **no top-level `Sort`**, **no `Seq Scan`**

| category | plan | Index Cond | KNN in-scan | rows removed | buffers |
|----------|------|-----------|-------------|-------------:|--------:|
| boat_ramp | Index Scan `places_cat_geom` | ✓ | ✓ | 0 | 173 |
| airport | Index Scan `places_cat_geom` | ✓ | ✓ | 0 | 8 |
| marina | Index Scan `places_cat_geom` | ✓ | ✓ | 0 | 13 |
| urgent_care | Index Scan `places_cat_geom` | ✓ | ✓ | 0 | 17 |

**Partitioned** (`... AND corpus_version = 'v2'`): the planner prunes to
`places_spike_part_v2` and uses that partition's local composite GiST index
(`places_spike_part_v2_category_key_geom_idx`) with the same Index Cond + in-scan
KNN ordering. `corpus_version` appears as a cheap partition-level `Filter` that
removes **0** rows (the partition is entirely v2). boat_ramp@v2: 9 buffers;
urgent_care@v2: 13 buffers.

> Note on timing: the *first* spatial query in a fresh session (boat_ramp: ~18 ms)
> reflects one-time warmup of the geography operator machinery; every subsequent
> category runs in < 0.11 ms. Buffer counts, not first-call wall-time, are the
> reliable signal — and they are tiny (8–173 pages).

## 4. Strategy B — geography-only `gist(geom)` control — **fails as designed**

Same queries, index is `gist(geom)` only; `category_key` becomes a post-filter.
Every plan is `Index Scan using places_geom_only` with `Order By: geom <-> ref`
**and** `Filter: (category_key = '<cat>')` — i.e. it walks the nearest rows of
*any* category and discards the wrong ones:

| category | Rows Removed by Filter | buffers | exec (ms) |
|----------|-----------------------:|--------:|----------:|
| boat_ramp | 8,826 | 9,147 | 28.5 |
| airport | 14,347 | 14,582 | 18.2 |
| marina | 9,243 | 9,418 | 10.4 |
| urgent_care | 1,280 | 1,352 | 1.9 |

This is the filter-walk anti-pattern: the sparser the target category, the more
dense-category rows are scanned and thrown away. Composite (Strategy A) reads
**~53× fewer buffers** for boat_ramp (173 vs 9,147). The control confirms the
composite index is doing real work, not coincidentally cheap.

## 5. Strategy C — partial `gist(geom)` per sparse category — **PASS (fallback)**

One partial index per sparse category:
`CREATE INDEX ... USING gist (geom) WHERE category_key = '<cat>'`.

Every plan is `Index Scan using places_geom_<cat>`, KNN `Order By` in-scan, **no
Filter, zero rows removed, no sort, no seq scan** — because the partial index's
predicate already restricts it to that one category.

| category | plan | rows removed | buffers |
|----------|------|-------------:|--------:|
| boat_ramp | Index Scan `places_geom_boat_ramp` | 0 | 165 |
| airport | Index Scan `places_geom_airport` | 0 | 6 |
| marina | Index Scan `places_geom_marina` | 0 | 11 |
| urgent_care | Index Scan `places_geom_urgent_care` | 0 | 14 |

Trade-off: N indexes to build and maintain (one per sparse category) versus one
composite index that covers *every* category, dense and sparse, with a single
object. Both are correct; the composite is operationally simpler.

## 6. KNN vs brute-force correctness — **exact match**

For each sparse category, top-10 nearest ids by `geom <-> ref` (index operator)
vs `ST_Distance(geom, ref)` (exhaustive geodesic):

| category | exact_order_match | same_set |
|----------|-------------------|----------|
| airport | ✓ | ✓ |
| boat_ramp | ✓ | ✓ |
| marina | ✓ | ✓ |
| urgent_care | ✓ | ✓ |

Identical ids in identical order. The `<->` operator on `geography` and
`ST_Distance` on `geography` are the same geodesic metre metric, so index-assisted
KNN returns the exact nearest neighbours.

## 7. Success-criteria checklist (composite)

| Criterion | boat_ramp | airport | marina | urgent_care |
|-----------|:---------:|:-------:|:------:|:-----------:|
| `Index Scan using places_cat_geom` | ✓ | ✓ | ✓ | ✓ |
| category as `Index Cond` | ✓ | ✓ | ✓ | ✓ |
| KNN `Order By` inside index scan | ✓ | ✓ | ✓ | ✓ |
| negligible rows removed by filter (0) | ✓ | ✓ | ✓ | ✓ |
| no top-level sort | ✓ | ✓ | ✓ | ✓ |
| no sequential scan | ✓ | ✓ | ✓ | ✓ |
| KNN == brute-force | ✓ | ✓ | ✓ | ✓ |

**All criteria met on both physical layouts.**

## 8. Recommended indexing architecture

**Primary: single composite GiST index per spatial corpus table.**

```sql
CREATE INDEX places_cat_geom ON places USING gist (category_key, geom);
```

- Requires `btree_gist` (available: 1.7). Column is `geography(Point,4326)`.
- One index covers all categories (dense and sparse); category equality is an
  Index Cond, KNN `<->` ordering is in-scan, zero post-filter walk.
- **If the corpus is partitioned by `corpus_version`** (LIST), create the composite
  index on the parent; PostgreSQL materializes a local composite index on each
  partition and prunes + in-scan-orders correctly. Pin queries to a single
  `corpus_version` so pruning leaves exactly one partition.

**Fallback (validated, hold in reserve): partial GiST per sparse category** — only
if a future workload shows the composite index's category-key selectivity
degrading for specific categories. Same correctness, more indexes to maintain.

**Reject: geography-only `gist(geom)` with category as a post-filter.** Proven to
filter-walk thousands of rows for sparse categories.

## 9. Revertibility

- **Repository:** every artifact is a *new* file under
  `spikes/phase-2-batch-0a-postgis-knn/`. No existing tracked file is modified
  (only unrelated `.claude/settings.local.json`, out of scope). Reverting =
  `git rm -r spikes/phase-2-batch-0a-postgis-knn` (or dropping the commit). No
  migration, no app code, no config touched.
- **Database:** all objects are confined to the disposable `spike` database:
  `DROP TABLE places_spike, places_spike_part CASCADE;` removes everything, or
  `docker rm -f byo-batch0-spike` plus its anonymous volume disposes of the entire
  environment. Nothing persists into the application's Postgres/SQLite.

## 10. Reproduce

```bash
docker start byo-batch0-spike
bash spikes/phase-2-batch-0a-postgis-knn/run_spike.sh   # outputs under results/
```

Connection defaults target the container over TCP (see `run_spike.sh` header for
env overrides; `docker exec` is unavailable in some sandboxes). Captured evidence
for this run is committed under `results/`.
