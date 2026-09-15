# Phase 2 · Batch 0 · Stage 0a — Local PostGIS / KNN Feasibility Spike

Throwaway feasibility spike answering one question: **can a single composite GiST
index over a `geography` column serve category-filtered K-nearest-neighbour
queries with the category as an index condition and the KNN ordering inside the
index scan — for sparse categories, at corpus scale?**

**Answer: yes.** See [`RESULTS.md`](./RESULTS.md) for the full PASS determination,
EXPLAIN evidence, and the recommended indexing architecture.

## Scope

This is a spike, not an implementation. It deliberately does **not**:

- create Laravel production migrations,
- modify any application runtime code,
- touch Phase 1 files,
- begin Stage 0b (external provider testing) or any later Phase 2 batch,
- provision or purchase any infrastructure.

Everything here is self-contained under this directory, against a disposable
`spike` database in the local `byo-batch0-spike` PostGIS container.

## Layout

```
spikes/phase-2-batch-0a-postgis-knn/
├── README.md                       this file
├── RESULTS.md                      findings + PASS/FAIL + recommendation
├── run_spike.sh                    runs all SQL, captures results/
├── sql/
│   ├── 00_setup.sql                extensions + SSOT-shaped tables
│   ├── 10_generate_data.sql        deterministic 176,560-row synthetic corpus
│   ├── 20_strategy_a_composite.sql STRATEGY A: gist(category_key, geom)
│   ├── 30_strategy_b_geography_only.sql  STRATEGY B (control): gist(geom)
│   ├── 40_strategy_c_partial.sql   STRATEGY C (fallback): partial gist per cat
│   ├── 50_knn_correctness.sql      <-> vs ST_Distance top-10 comparison
│   └── 60_distribution.sql         dataset size + category distribution
└── results/                        captured EXPLAIN / query output (evidence)
```

## Run

```bash
docker start byo-batch0-spike
SPIKE_PGHOST=172.17.0.2 SPIKE_PGDATABASE=spike \
  bash spikes/phase-2-batch-0a-postgis-knn/run_spike.sh
```

The target must be named in `SPIKE_PGHOST` and `SPIKE_PGDATABASE`
(`SPIKE_PGPORT/SPIKE_PGUSER/SPIKE_PGPASSWORD/PSQL_BIN` are optional; see the header of
`run_spike.sh`). The ambient `PG*` variables are never used: in the Replit workspace they point
at the production database, so the runner refuses `helium`, `heliumdb`, an unnamed target and a
Replit deployment before any `psql` runs (`lib/require-isolated-target.sh`). It connects over TCP
because `docker exec` is unavailable in some sandbox environments. On a standard
Docker host you can instead pipe each file through
`docker exec -i byo-batch0-spike psql -U postgres -d spike -f -`.

## Teardown / revert

```bash
# database objects (disposable spike DB only)
psql ... -c 'DROP TABLE IF EXISTS places_spike, places_spike_part CASCADE;'
# or dispose the whole environment
docker rm -f byo-batch0-spike

# repository (all artifacts are new, untracked-then-committed files here)
git rm -r spikes/phase-2-batch-0a-postgis-knn
```

No application migration, config, or runtime code is involved.
