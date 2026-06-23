# LDNA POI Tile Precision Benchmark Guide

**Service:** `LocationDnaPoiDistanceService` — Phase C (v3 cost optimisation)
**Task:** Location DNA POI Cost Optimization (tile cache + category grouping + precision benchmarking)

> **Scope notice:** This document covers API-cost optimisation only (tile cache + category
> grouping). It does **not** address the stale-POI-row / exclusion-filter bypass issue
> affecting listings whose `property_location_pois` rows were populated before the current
> exclusion rules were introduced. That correctness issue is tracked separately in #3205.

---

## Purpose

Before enabling the spatial tile cache in production, run this benchmark against a live listing
dataset to determine the optimal `LOCATION_DNA_POI_TILE_PRECISION` value. The benchmark measures
the tradeoff between cache hit rate (cost savings) and top-3 POI output stability (quality
retention).

```bash
php artisan ldna:benchmark-tile-precision --listing-ids=seller_agent_auction:1,seller_agent_auction:2,...
# or random sample:
php artisan ldna:benchmark-tile-precision --sample=50
```

---

## How the benchmark works

1. A **baseline** run executes `calculateForListing()` with tile cache **disabled** (precision=null).
   The array cache store is flushed before every precision run to prevent cross-precision
   contamination.
2. For each of the four candidate precisions, the same listings are re-run with the tile cache
   enabled at that precision.
3. After the first listing at a given precision populates the tile cache, subsequent nearby
   listings read from cache instead of calling the API.
4. **Cache hit rate** = tile cache hits ÷ (tile cache hits + API calls) × 100.
5. **Avg calls/listing** = total API calls ÷ number of listings.
6. **Top-3 POI stability** = percentage of (listing, category, rank ≤ 3) triples where the
   POI name matches the baseline. 100% = zero output difference.

---

## Candidate precisions

| Precision (°) | Approx tile size | Notes |
|--------------|-----------------|-------|
| 0.001        | ~100 m          | Finest grain; maximises accuracy, lowest hit rate |
| 0.0025       | ~250 m          | Quarter-block precision |
| 0.005        | ~500 m          | Half-block to block precision |
| 0.01         | ~1 km           | Coarsest grain; highest hit rate, highest drift risk |

---

## Benchmark results

> **These values must be measured before a production recommendation can be made.**
> Fill in the table below by running the command against your production listing dataset
> with a live Google Places API key. Do not rely on placeholder or estimated figures.

| Precision | Cache Hit Rate | Avg Calls/Listing | Top-3 POI Stability |
|-----------|---------------|------------------|---------------------|
| 0.001°    | TBD           | TBD              | TBD                 |
| 0.0025°   | TBD           | TBD              | TBD                 |
| 0.005°    | TBD           | TBD              | TBD                 |
| 0.01°     | TBD           | TBD              | TBD                 |

*Stability % is the share of top-3 POI names that match the uncached baseline.*

**Decision criteria:**
- Prefer the coarsest precision whose stability remains ≥ 97%.
- If stability drops below 95% at 0.01°, step back to 0.005°.
- If listings are predominantly high-density (condos, urban blocks), 0.001°–0.0025° may be
  sufficient to achieve meaningful hit rates without noticeable quality drift.

---

## Category grouping savings (always-on, no config required)

The v3 release also introduces permanent category grouping that reduces API calls from 19 to 16
per full tile miss, regardless of tile cache settings:

| Group           | Primary call params     | Secondary reuses        |
|-----------------|------------------------|------------------------|
| park / waterfront_park   | google_type=park       | park's raw candidates  |
| gym / fitness_center     | google_type=gym        | gym's raw candidates   |
| beach / beach_access     | keyword=beach          | beach's raw candidates |

This saves **3 calls per listing** before the tile cache is even considered.

At 1,000 listings × $0.032/call × 3 saved = **$96 saved from grouping alone**.

---

## Enabling in production

Set the precision value chosen from your measured benchmark results:

```env
LOCATION_DNA_POI_TILE_PRECISION=<measured value>
LOCATION_DNA_POI_TILE_CACHE_TTL=604800   # 7 days (default)
```

Leave `LOCATION_DNA_POI_TILE_PRECISION` unset (null) to keep the tile cache disabled until the
benchmark has been run.

---

## Monitoring after enablement

```bash
php artisan ldna:poi-cost-report
php artisan ldna:poi-cost-report --since="7 days ago"
```

Expected output fields:
- `calls_made` — fresh Google Places API calls
- `calls_avoided_tile` — calls saved by tile cache
- `calls_avoided_grouping` — calls saved by category grouping (always 3× listings)
- `cache_hit_rate_pct` — tile cache efficiency
- `estimated_saving_usd` — dollar value at $0.032/call
