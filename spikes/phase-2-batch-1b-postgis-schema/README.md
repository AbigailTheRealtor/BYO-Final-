# Spike artifacts — B1.2 mixed-geometry KNN validation

**RUN LATER**, on the temporary Crunchy Bridge verification cluster (SIA-D38 deferral). Nothing here executes during B1.2 authoring — it all requires the live `pgsql_spatial` connection.

These artifacts drive the **E-50 acceptance gate** (`docs/spatial/B1.2-postgis-schema-migrations.md`, §"Acceptance gate"): confirm the composite-GiST KNN plan holds on the mixed `geography(Geometry,4326)` type, not just the `geography(Point)` the Stage 0b spike proved.

## Contents

```
fixtures/generate_tier2_fixture.sql   5,000,200-row Point+Polygon+LineString corpus, exact Stage 0b distribution
validate/knn_explain.sql              per-category candidate-retrieval EXPLAIN (plan shape) + set-exactness check
validate/run_validation.php           boots the framework, runs the EXPLAINs, feeds ExplainPlanShape, exits 0/1
```

The counts in `generate_tier2_fixture.sql` are kept in lockstep with `Tests\Support\Spatial\FixtureCorpusPlan` — `FixtureCorpusPlanTest` fails if they drift. The plan-shape assertions in `run_validation.php` are `Tests\Support\Spatial\ExplainPlanShape`, unit-tested by `ExplainPlanShapeTest`.

## Procedure (once the cluster exists)

```bash
# 0. Point the app at the cluster (dev/staging only)
export SPATIAL_DATABASE_URL='postgres://…crunchy…/spatial?sslmode=require'

# 1. Apply the schema
php artisan migrate --path=database/migrations/spatial --database=pgsql_spatial

# 2. Load the mixed-geometry fixture (+ ANALYZE) — this inserts ~5M rows, give it time
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -f spikes/phase-2-batch-1b-postgis-schema/fixtures/generate_tier2_fixture.sql

# 3a. Run the plan-shape gate via the PHP harness
php spikes/phase-2-batch-1b-postgis-schema/validate/run_validation.php
#    …or capture raw EXPLAIN JSON directly:
psql "$SPATIAL_DATABASE_URL" -f spikes/phase-2-batch-1b-postgis-schema/validate/knn_explain.sql

# 4. Tear down the throwaway fixture partition
psql "$SPATIAL_DATABASE_URL" -c 'DROP TABLE IF EXISTS places_fixture_tier2;'
```

**Pass:** every sparse category (+ park) shows `Index Scan using places_cat_geom` · `Index Cond` on `category_key` · in-scan `<->` `Order By` · 0 rows removed · 0 Sort · 0 Seq Scan; and set-exactness holds after the spheroidal re-rank.

**If the mixed type degrades the plan:** SSOT-sanctioned fallback is to run KNN against `centroid` (`geography(Point)`). Record which path was taken in a `RESULT.md` mirroring the Stage 0b format, then reconcile the SSOT E-50 caveat.

## Cleanup

The verification cluster is ephemeral. After capturing results: drop the fixture partition (above), then destroy the cluster and shred any `.pgpass`/DSN — as with Stage 0b, no live credential belongs in the workspace.
