<?php

namespace App\Services\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\Providers\CorpusPoiCategoryMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * OvertureCorpusPoiAdapter — nearby places answered from the corpus we already host.
 *
 * This is the `CorpusPoiAdapter` that {@see NearbyPoiFetcherInterface}'s docblock has
 * been describing since Phase 1 Batch 1: "returning corpus-native rows, with no change
 * to the service". It reads `places` on the `pgsql_spatial` cluster — one indexed KNN
 * query per category — and issues no HTTP request, holds no credential, and costs
 * nothing per call.
 *
 * WHY ONE CLASS IMPLEMENTS BOTH INTERFACES
 * ----------------------------------------
 * {@see GooglePlacesPoiAdapter} already does exactly this, and for the same reason: the
 * two interfaces are two SHAPES over one lookup, not two lookups. `search()` returns the
 * normalised 9-key envelope the buyer/tenant search-area path consumes; `fetchNearby()`
 * returns provider-native rows the production Location DNA path's exclusion, ranking and
 * persistence read directly. Splitting them would duplicate the query, the region gate,
 * the availability check and the row mapping across two classes that must agree forever.
 *
 * Their EXCEPTION CONTRACTS genuinely differ, and that difference is preserved here
 * rather than smoothed over. `search()` swallows and returns `[]` — its caller degrades
 * gracefully and a buyer's map should not fail because a cluster blinked. `fetchNearby()`
 * lets provider faults PROPAGATE — its caller distinguishes a thrown error (persists
 * `status = 'error'`) from an empty result (persists `status = 'not_found'`), and
 * swallowing would silently reclassify "the corpus was unreachable" as "there is nothing
 * near this home". The two methods therefore share `candidatesFor()` and differ only in
 * how they wrap it.
 *
 * THE QUERY IS THE SSOT §7.5 OVER-FETCH, NOT A CONVENIENT APPROXIMATION
 * ---------------------------------------------------------------------
 * The composite `places_cat_geom` GiST index — `gist (category_key, geom)`, which needs
 * btree_gist to put a scalar ahead of a geography — makes category equality an Index Cond
 * and performs the `<->` ordering INSIDE the same index scan. That is the whole point of
 * the index and the reason Strategy A was chosen in the Batch 0a spike.
 *
 * But `<->` on a geography is a SPHERE distance, and near-equidistant neighbours can swap
 * under it (erratum E-50). So the read is two-stage, exactly as
 * `spikes/…batch-1b/validate/knn_explain.sql` specifies:
 *
 *   inner   WHERE category_key = ? AND corpus_version = ? ORDER BY geom <-> ref LIMIT n
 *           — index-provided ordering, over-fetching n = max(20, 2 × limit)
 *   outer   ORDER BY ST_Distance(ref, geom, true) — exact spheroidal re-rank over only
 *           those n rows, then truncate to the caller's limit
 *
 * The outer sort is over at most 20 rows and is not part of the plan-shape gate; the
 * inner scan is, and {@see \Tests\Support\Spatial\ExplainPlanShape} is the engine that
 * checks it. A sequential scan across the corpus is a defect, not a slow success.
 *
 * DISTANCE IS MEASURED ONCE, IN THE DATABASE, ON THE SPHEROID
 * -----------------------------------------------------------
 * `ST_Distance(geography, geography, true)` returns metres on the WGS-84 spheroid; the
 * adapter converts to miles for the envelope. It does NOT recompute a haversine in PHP.
 * Downstream, {@see LocationDnaPoiDistanceService} and {@see LocationDnaRankingEngine}
 * still compute their own haversine from the returned coordinate exactly as they do for
 * any provider — this adapter changes no downstream distance or ranking semantics. The
 * `distance_miles` it reports in the `search()` envelope is the same quantity Google's
 * adapter reports there, measured more precisely.
 *
 * `geom` may be a Point, a LineString or a Polygon (SSOT §7.4) — a park boundary is not a
 * pin. Distance and KNN ordering therefore use `geom`, the true geometry, so "0.2 miles
 * from the park" means from its edge. The COORDINATE reported back is `centroid`, because
 * a marker needs one point and the centroid is the point the schema stores for exactly
 * that purpose.
 *
 * FLORIDA ONLY, AND IT SAYS SO BEFORE IT QUERIES
 * -----------------------------------------------
 * The loaded corpus is `overture-2026-06-17.0-fl`. A coordinate outside the configured
 * region envelope is declined WITHOUT a query — not because a query would be slow, but
 * because the nearest Florida grocery store to a Georgia address is a real row, a real
 * distance, and a completely wrong answer that nothing downstream could detect. Fail
 * closed is the only safe posture for a regional corpus behind a nationwide interface.
 *
 * WHAT IT REFUSES TO CLAIM
 * ------------------------
 * Seven categories are loaded. {@see CorpusPoiCategoryMap} holds that claim and derives
 * it from the corpus taxonomy. Beach, school, park, hospital and transit resolve to null
 * there and this adapter returns no candidates for them — the caller records
 * `not_found`, which is true, rather than an error, which would be misleading, and
 * certainly rather than a substitute row.
 *
 * READ-ONLY BY CONSTRUCTION
 * -------------------------
 * Every statement is a `SELECT`. The adapter never writes to the spatial cluster, never
 * writes to the application database, and never copies corpus rows into it.
 *
 * @see \App\Services\LocationDna\Providers\CorpusPoiCategoryMap
 * @see \Tests\Unit\Services\LocationDna\OvertureCorpusPoiAdapterTest
 * @see \Tests\Unit\Services\LocationDna\OvertureCorpusPoiSqlManifestTest
 */
class OvertureCorpusPoiAdapter implements PoiLookupAdapterInterface, NearbyPoiFetcherInterface
{
    /** Provider identity. Matches the key in config/location_providers.php. */
    public const PROVIDER_ID = 'overture_corpus';

    /** Metres in one statute mile — the unit ST_Distance returns for a geography. */
    private const METERS_PER_MILE = 1609.344;

    /**
     * Raised when the corpus cannot be read. Distinct from "the corpus holds nothing
     * nearby", which is an empty array. `fetchNearby()` lets this propagate so the
     * caller persists `status = 'error'`; `search()` catches it and returns `[]`.
     */
    public const UNAVAILABLE_MESSAGE = 'Overture corpus is unavailable';

    // ── PoiLookupAdapterInterface (buyer / tenant search areas) ──────────────

    /**
     * {@inheritDoc}
     *
     * Category here is a buyer/tenant VIEW SLUG (`gyms`, `shopping`, …), not a canonical
     * key. Only the two slugs the corpus holds rows for resolve; the other five return
     * `[]` — a buyer asking for nearby hospitals gets nothing from a corpus with no
     * hospitals in it.
     *
     * Swallows every failure and returns `[]`, per the interface contract.
     */
    public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array
    {
        try {
            $corpusCategory = CorpusPoiCategoryMap::corpusCategoryForViewSlug($category);

            if ($corpusCategory === null) {
                return [];
            }

            $rows = $this->candidatesFor($lat, $lng, $corpusCategory, $radiusMiles, $limit);

            $fetchedAt = now()->toIso8601String();

            return array_map(fn (array $row): array => [
                'category'       => $category,
                'name'           => (string) $row['name'],
                'address'        => (string) ($row['vicinity'] ?? ''),
                'latitude'       => (float) $row['geometry']['location']['lat'],
                'longitude'      => (float) $row['geometry']['location']['lng'],
                'distance_miles' => (float) $row['_corpus_distance_miles'],
                'source'         => self::PROVIDER_ID,
                // The corpus states its own existence confidence per row (Overture
                // `confidence`, floored at 0.90 by the extract). Reported as given —
                // it is a real measurement, not a rating-derived proxy.
                'confidence'     => $row['_corpus_confidence'],
                // The corpus row's own last_seen, when it has one: the honest freshness
                // of this datum. Falls back to the read time only when the row carries
                // none, matching how the provider adapters timestamp a fetch.
                'last_refreshed' => $row['_corpus_last_seen'] ?? $fetchedAt,
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    // ── NearbyPoiFetcherInterface (production Location DNA path) ─────────────

    /**
     * {@inheritDoc}
     *
     * Returns provider-native rows in the shape the production path reads:
     * `name`, `geometry.location.lat/lng`, `types`, `rating`, `user_ratings_total`,
     * `vicinity`, `place_id`. Those are the seven keys
     * {@see LocationDnaPoiDistanceService} and {@see PoiCandidate} actually consume.
     *
     * On `types` — this is the one field where filling the shape takes a real decision.
     * The corpus has no Google type tags, so the naive answer is `[]`. That would be
     * wrong: `passesExclusionFilter()` treats an EMPTY types array as "the provider sent
     * a sparse response" and activates name-pattern fallbacks written for that case. The
     * grocery fallback would then discard a legitimate corpus grocery store whose name
     * happens to contain "Shell" or "Wawa". So the adapter emits the Google type token
     * the category's own descriptor names — recovered from the pipeline's category table,
     * not invented — which is a true statement about the row (it came from the
     * corpus partition for that category) and lets the authoritative type-based rules
     * arbitrate as designed.
     *
     * `rating` and `user_ratings_total` are absent, because the corpus has no reviews and
     * a fabricated rating would flow into `review_confidence_score` and change ranking.
     * `PoiCandidate` reads a missing `rating` as null and a missing `user_ratings_total`
     * as 0, which is precisely "no review signal".
     *
     * Exceptions PROPAGATE — see the class docblock.
     */
    public function fetchNearby(float $lat, float $lng, array $meta): array
    {
        $corpusCategory = CorpusPoiCategoryMap::corpusCategoryForDescriptor($meta);

        if ($corpusCategory === null) {
            // Not an error: the corpus genuinely holds no rows for this category, and
            // the caller records `not_found`. Raising would report a broken provider.
            return [];
        }

        $rows = $this->candidatesFor(
            $lat,
            $lng,
            $corpusCategory,
            $this->defaultRadiusMiles(),
            $this->maxResults(),
        );

        $types = $this->typeTokensFor($meta);

        return array_map(static function (array $row) use ($types): array {
            $native = [
                'name'     => $row['name'],
                'geometry' => $row['geometry'],
                'types'    => $types,
                'vicinity' => $row['vicinity'],
                'place_id' => $row['place_id'],
            ];

            // Carried through so the caller's persistence and the buyer/tenant envelope
            // can read what the corpus actually asserted. Underscore-prefixed for the
            // same reason `_row_id` is: non-provider keys that ride the raw payload.
            $native['_corpus_distance_miles'] = $row['_corpus_distance_miles'];
            $native['_corpus_confidence']     = $row['_corpus_confidence'];
            $native['_corpus_last_seen']      = $row['_corpus_last_seen'];

            return $native;
        }, $rows);
    }

    // ── availability ────────────────────────────────────────────────────────

    /**
     * Flag on, a corpus version pinned, and a corpus that is actually there.
     *
     * Mirrors {@see \App\Services\Location\Coordinates\Adapters\AddressPointCoordinateAdapter::isAvailable()}
     * deliberately — the two are the same kind of object (a local corpus behind a
     * provider seam) and should fail the same way. Every check is cheap and local, and
     * the whole thing is wrapped: an unconfigured or unreachable spatial connection makes
     * this adapter unavailable, never an exception escaping from a schema probe.
     *
     * Not consulted by `search()`/`fetchNearby()` directly — they call `assertReadable()`,
     * which raises. This is the boolean form, for a factory or a readiness check that
     * wants to ask before constructing.
     */
    public function isAvailable(): bool
    {
        if (! $this->enabled() || $this->corpusVersion() === null) {
            return false;
        }

        try {
            $schema = Schema::connection($this->connection());
            $table  = $this->table();

            return $schema->hasTable($table)
                && $schema->hasColumn($table, 'corpus_version')
                && $schema->hasColumn($table, $this->categoryColumn());
        } catch (Throwable) {
            return false;
        }
    }

    // ── the read ────────────────────────────────────────────────────────────

    /**
     * The shared lookup both interfaces wrap.
     *
     * @param  string $corpusCategory a `category_key` the corpus is known to hold
     * @return list<array<string, mixed>> rows in a neutral intermediate shape, nearest
     *                                    first, already truncated to $limit
     *
     * @throws \RuntimeException when the corpus cannot be read (never for "no results")
     */
    private function candidatesFor(
        float  $lat,
        float  $lng,
        string $corpusCategory,
        int    $radiusMiles,
        int    $limit,
    ): array {
        $this->assertReadable();

        if (! $this->withinSupportedRegion($lat, $lng)) {
            // Outside the corpus's coverage. No query — see FLORIDA ONLY above.
            return [];
        }

        $limit  = $this->clampLimit($limit);
        $radius = $this->clampRadiusMiles($radiusMiles);

        [$sql, $bindings] = $this->buildKnnQuery($corpusCategory, $lat, $lng, $radius, $limit);

        $rows = $this->selectRows($sql, $bindings);

        return array_values(array_filter(array_map(
            fn (object $row): ?array => $this->mapRow($row),
            $rows,
        )));
    }

    /**
     * Execute one read against the corpus connection.
     *
     * The single line in this class that touches a database, isolated so that the row
     * mapping, the region gate, the clamps and the two exception contracts can all be
     * exercised without a PostGIS cluster — which the test suite deliberately cannot
     * reach (`tests/bootstrap.php` blanks every SPATIAL_* variable, so the connection is
     * inert in CI by design). A test subclass overrides this and returns fixture rows.
     *
     * The SQL itself is therefore NOT proven by those tests, and is not pretended to be.
     * It is proven in two other places: `OvertureCorpusPoiSqlManifestTest` pins the
     * emitted statement's shape and bindings, and the live read-only smoke verification
     * runs it against the real corpus and feeds the EXPLAIN through
     * {@see \Tests\Support\Spatial\ExplainPlanShape}.
     *
     * @param  list<mixed> $bindings
     * @return list<object>
     */
    protected function selectRows(string $sql, array $bindings): array
    {
        return DB::connection($this->connection())->select($sql, $bindings);
    }

    /**
     * The SSOT §7.5 over-fetch KNN, parameterised. Pure — no connection, no execution.
     *
     * Public so a manifest test can assert the emitted SQL's shape (the house pattern:
     * see `OvertureImportSqlManifestTest`, `Gate2CoverageSqlManifestTest`) without a
     * PostGIS cluster, and so the live EXPLAIN harness runs the exact statement the
     * adapter runs rather than a hand-written lookalike.
     *
     * Every VALUE is bound. The only interpolated text is table/column identifiers, each
     * whitelisted through {@see self::identifier()} — defence in depth against a
     * hand-edited config, since these never come from a request.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function buildKnnQuery(
        string $corpusCategory,
        float  $lat,
        float  $lng,
        int    $radiusMiles,
        int    $limit,
    ): array {
        $table    = $this->identifier($this->table());
        $catCol   = $this->identifier($this->categoryColumn());
        $geomCol  = $this->identifier($this->geomColumn());
        $centCol  = $this->identifier($this->centroidColumn());

        $overfetch    = $this->overfetchFor($limit);
        $radiusMeters = $radiusMiles * self::METERS_PER_MILE;

        // ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography — note the argument order:
        // PostGIS takes X (longitude) first. Bound, never interpolated.
        $reference = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        $sql = <<<SQL
            WITH nearest AS (
                SELECT p.name,
                       p.brand,
                       p.confidence,
                       p.source_ref,
                       p.last_seen,
                       p.attrs,
                       ST_Y(p.{$centCol}::geometry) AS poi_lat,
                       ST_X(p.{$centCol}::geometry) AS poi_lng,
                       ST_Distance({$reference}, p.{$geomCol}, true) AS meters
                FROM {$table} p
                WHERE p.{$catCol} = ?
                  AND p.corpus_version = ?
                ORDER BY p.{$geomCol} <-> {$reference}
                LIMIT {$overfetch}
            )
            SELECT name, brand, confidence, source_ref, last_seen, attrs, poi_lat, poi_lng, meters
            FROM nearest
            WHERE meters <= ?
            ORDER BY meters
            LIMIT {$limit}
            SQL;

        return [
            $sql,
            [
                // inner SELECT's ST_Distance reference
                $lng, $lat,
                $corpusCategory,
                $this->corpusVersion(),
                // ORDER BY <-> reference
                $lng, $lat,
                // outer radius ceiling
                $radiusMeters,
            ],
        ];
    }

    /**
     * One corpus row into the neutral intermediate shape, or null when the row cannot
     * supply a usable coordinate.
     *
     * A row without a centroid is skipped rather than reported at (0, 0). Null Island is
     * a real place to a haversine, and a POI silently placed there would rank as
     * thousands of miles away — visible as a bizarre distance, not as an error.
     */
    private function mapRow(object $row): ?array
    {
        $poiLat = $this->toFloat($row->poi_lat ?? null);
        $poiLng = $this->toFloat($row->poi_lng ?? null);

        if ($poiLat === null || $poiLng === null) {
            return null;
        }

        $meters = $this->toFloat($row->meters ?? null);

        return [
            'name'     => (string) ($row->name ?? ''),
            'geometry' => ['location' => ['lat' => $poiLat, 'lng' => $poiLng]],
            // The corpus carries no formatted street address of its own. Rather than
            // fabricate one, `vicinity` reports the brand when the row names one and null
            // otherwise — null is what `PoiCandidate::address()` and the persistence layer
            // already handle for "the provider supplied none".
            'vicinity' => $this->nonEmptyString($row->brand ?? null),
            // The corpus's stable upstream identifier (Overture GERS id via source_ref).
            // Persisted as `provenance_json.raw_ref`, exactly as a place_id would be:
            // an opaque reference, never content.
            'place_id' => $this->nonEmptyString($row->source_ref ?? null),

            '_corpus_distance_miles' => $meters === null ? null : $meters / self::METERS_PER_MILE,
            '_corpus_confidence'     => $this->toFloat($row->confidence ?? null),
            '_corpus_last_seen'      => $this->toIso8601($row->last_seen ?? null),
        ];
    }

    /**
     * The Google type token(s) for a fetch descriptor — see the `types` note on
     * {@see self::fetchNearby()}.
     *
     * Read straight off the descriptor the caller handed us, so the token can only ever
     * be the one the pipeline itself associates with this category. A keyword-only
     * category (no `google_type`) yields `[]`, which is honest: there is no type to
     * assert, and the name-pattern fallbacks are then the correct arbiter. In practice no
     * keyword-only category is corpus-supported today.
     *
     * @param  array<string, mixed> $meta
     * @return list<string>
     */
    private function typeTokensFor(array $meta): array
    {
        $type = $meta['google_type'] ?? null;

        return (is_string($type) && $type !== '') ? [$type] : [];
    }

    // ── region gate ─────────────────────────────────────────────────────────

    /**
     * Is this coordinate inside any configured region envelope?
     *
     * Public so a readiness check or a test can ask the question without issuing a
     * lookup, and so the Florida-only guarantee is directly assertable.
     */
    public function withinSupportedRegion(float $lat, float $lng): bool
    {
        foreach ($this->regions() as $region) {
            $box = $this->regionBounds()[$region] ?? null;

            if (! is_array($box)) {
                // A region named without an envelope cannot be checked, and an
                // uncheckable region must not admit a coordinate.
                continue;
            }

            $within = $lat >= (float) $box['south']
                && $lat <= (float) $box['north']
                && $lng >= (float) $box['west']
                && $lng <= (float) $box['east'];

            if ($within) {
                return true;
            }
        }

        return false;
    }

    // ── guards & helpers ────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException when the adapter must not or cannot read the corpus.
     */
    private function assertReadable(): void
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException(self::UNAVAILABLE_MESSAGE);
        }
    }

    /** Over-fetch depth for a requested limit (SIA-D40: floor 20, factor 2). */
    public function overfetchFor(int $limit): int
    {
        $floor  = max(1, (int) config('overture_corpus_poi.overfetch_floor', 20));
        $factor = max(1, (int) config('overture_corpus_poi.overfetch_factor', 2));

        return max($floor, $this->clampLimit($limit) * $factor);
    }

    private function clampLimit(int $limit): int
    {
        $max = max(1, (int) config('overture_corpus_poi.max_results', 20));

        if ($limit < 1) {
            return $max;
        }

        return min($limit, $max);
    }

    private function clampRadiusMiles(int $radiusMiles): int
    {
        $max = max(1, (int) config('overture_corpus_poi.max_radius_miles', 25));

        if ($radiusMiles < 1) {
            return $this->defaultRadiusMiles();
        }

        return min($radiusMiles, $max);
    }

    private function defaultRadiusMiles(): int
    {
        $default = (int) config('overture_corpus_poi.default_radius_miles', 25);
        $max     = max(1, (int) config('overture_corpus_poi.max_radius_miles', 25));

        return min(max(1, $default), $max);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function toIso8601(mixed $value): ?string
    {
        $string = $this->nonEmptyString($value);

        if ($string === null) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($string)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whitelist a table/column identifier to a strict `[a-z_][a-z0-9_]*` token.
     *
     * Identical in intent to {@see \App\Services\Spatial\Gate2\CoverageQueryCatalog::identifier()}.
     * These values only ever come from committed config — no request reaches them — but
     * an identifier is the one thing in this query that cannot be a bound parameter, so
     * it is checked rather than trusted.
     */
    private function identifier(string $name): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("OvertureCorpusPoiAdapter: unsafe identifier '{$name}'.");
        }

        return $name;
    }

    // ── config ──────────────────────────────────────────────────────────────

    private function enabled(): bool
    {
        return (bool) config('overture_corpus_poi.enabled', false);
    }

    public function corpusVersion(): ?string
    {
        $version = config('overture_corpus_poi.corpus_version');

        if (! is_string($version) || trim($version) === '') {
            return null;
        }

        return trim($version);
    }

    private function connection(): string
    {
        return (string) config('overture_corpus_poi.connection', 'pgsql_spatial');
    }

    private function table(): string
    {
        return (string) config('overture_corpus_poi.table', 'places');
    }

    private function categoryColumn(): string
    {
        return (string) config('overture_corpus_poi.category_column', 'category_key');
    }

    private function geomColumn(): string
    {
        return (string) config('overture_corpus_poi.geom_column', 'geom');
    }

    private function centroidColumn(): string
    {
        return (string) config('overture_corpus_poi.centroid_column', 'centroid');
    }

    private function maxResults(): int
    {
        return max(1, (int) config('overture_corpus_poi.max_results', 20));
    }

    /** @return list<string> */
    private function regions(): array
    {
        return array_values(array_map('strval', (array) config('overture_corpus_poi.regions', [])));
    }

    /** @return array<string, array<string, float|int>> */
    private function regionBounds(): array
    {
        return (array) config('overture_corpus_poi.region_bounds', []);
    }
}
