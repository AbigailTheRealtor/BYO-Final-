<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use InvalidArgumentException;
use Tests\Support\Spatial\ExplainPlanShape;
use Tests\TestCase;

/**
 * The SQL manifest for the corpus KNN read — the house pattern
 * (`OvertureImportSqlManifestTest`, `Gate2CoverageSqlManifestTest`,
 * `TigerBoundaryImportSqlManifestTest`): pin the statement's SHAPE without a cluster.
 *
 * WHY A MANIFEST AND NOT AN INTEGRATION TEST
 * ------------------------------------------
 * The suite runs on SQLite and cannot execute PostGIS, and `tests/bootstrap.php`
 * deliberately blanks every SPATIAL_* variable so no test can reach the real cluster.
 * That leaves two honest ways to be sure of this query, and both are used: this file
 * pins what the adapter emits, and the read-only live smoke verification runs it for
 * real and feeds the resulting EXPLAIN through {@see ExplainPlanShape}.
 *
 * WHAT THE SHAPE HAS TO BE, AND WHY
 * ---------------------------------
 * The corpus is 29,434 rows behind a composite `gist (category_key, geom)` index. A
 * query that filters on category and then sorts by distance in a second step would
 * return the right answer via a sequential scan — correct, and a defect. The index only
 * does its job when category equality is an Index Cond and `<->` supplies the ordering
 * inside the same scan, which is what the assertions below fix in place.
 *
 * The `<->` operator measures on a SPHERE, so near-equidistant neighbours can swap
 * (erratum E-50). The statement therefore over-fetches under the index and re-ranks the
 * over-fetched rows on the exact spheroidal `ST_Distance(..., true)`. Both halves are
 * asserted: dropping the over-fetch would silently degrade accuracy, and dropping the
 * re-rank would silently reorder results.
 */
class OvertureCorpusPoiSqlManifestTest extends TestCase
{
    private const PINELLAS_LAT = 27.788945;
    private const PINELLAS_LNG = -82.735144;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'overture_corpus_poi.corpus_version'   => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.table'            => 'places',
            'overture_corpus_poi.category_column'  => 'category_key',
            'overture_corpus_poi.geom_column'      => 'geom',
            'overture_corpus_poi.centroid_column'  => 'centroid',
            'overture_corpus_poi.overfetch_floor'  => 20,
            'overture_corpus_poi.overfetch_factor' => 2,
            'overture_corpus_poi.max_results'      => 20,
        ]);
    }

    private function query(int $limit = 10, int $radiusMiles = 25): array
    {
        return (new OvertureCorpusPoiAdapter())->buildKnnQuery(
            'grocery_store',
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            $radiusMiles,
            $limit,
        );
    }

    // ── the indexed inner scan ──────────────────────────────────────────────

    /** Category equality must be a bare `=` on the indexed column — an Index Cond. */
    public function test_the_inner_scan_filters_category_by_equality_on_the_indexed_column(): void
    {
        [$sql] = $this->query();

        $this->assertStringContainsString('p.category_key = ?', $sql);
    }

    /** The corpus version is pinned in the same WHERE, scoping the read to one import. */
    public function test_the_inner_scan_scopes_to_the_pinned_corpus_version(): void
    {
        [$sql, $bindings] = $this->query();

        $this->assertStringContainsString('p.corpus_version = ?', $sql);
        $this->assertContains('overture-2026-06-17.0-fl', $bindings);
    }

    /**
     * The KNN operator supplies the ordering. This is the assertion that stops the query
     * from quietly becoming a filter-then-sort.
     */
    public function test_the_inner_scan_orders_by_the_knn_operator_on_the_indexed_geometry(): void
    {
        [$sql] = $this->query();

        $this->assertMatchesRegularExpression(
            '/ORDER BY p\.geom <-> ST_SetSRID\(ST_MakePoint\(\?, \?\), 4326\)::geography/',
            $sql,
            'The inner scan must order by `<->` on the indexed geom column, so the '
            . 'composite GiST index provides the ordering rather than a Sort node.'
        );
    }

    /** Ordering uses `geom` (true geometry), not the centroid — a park is not a pin. */
    public function test_ordering_and_distance_use_geom_while_the_reported_point_is_the_centroid(): void
    {
        [$sql] = $this->query();

        $this->assertStringContainsString('p.geom <-> ', $sql);
        $this->assertStringContainsString('p.geom, true) AS meters', $sql);
        $this->assertStringContainsString('ST_Y(p.centroid::geometry) AS poi_lat', $sql);
        $this->assertStringContainsString('ST_X(p.centroid::geometry) AS poi_lng', $sql);
    }

    // ── over-fetch + spheroidal re-rank (SIA-D40 / E-50) ────────────────────

    public function test_the_inner_scan_overfetches_to_the_floor_for_a_small_limit(): void
    {
        [$sql] = $this->query(limit: 5);

        $this->assertStringContainsString('LIMIT 20', $sql, 'over-fetch floor is 20');
        $this->assertStringContainsString('LIMIT 5', $sql, 'outer truncation is the caller limit');
    }

    public function test_the_inner_scan_overfetches_by_the_factor_above_the_floor(): void
    {
        [$sql] = $this->query(limit: 15);

        $this->assertStringContainsString('LIMIT 30', $sql, '2 × 15');
    }

    /** Distance is measured on the spheroid — the `true` argument is load-bearing. */
    public function test_distance_is_measured_on_the_spheroid(): void
    {
        [$sql] = $this->query();

        $this->assertStringContainsString(
            'ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, p.geom, true)',
            $sql,
            'The third argument `true` selects the spheroidal measure; without it the '
            . 're-rank would repeat the sphere approximation it exists to correct.'
        );
    }

    public function test_the_outer_query_reranks_on_the_measured_metres(): void
    {
        [$sql] = $this->query();

        $this->assertMatchesRegularExpression('/FROM nearest\s+WHERE meters <= \?\s+ORDER BY meters/s', $sql);
    }

    // ── bindings ────────────────────────────────────────────────────────────

    /**
     * Every VALUE is bound. PostGIS takes X (longitude) before Y (latitude) — a swap here
     * would put every Florida property in the Indian Ocean, so the order is pinned.
     */
    public function test_all_values_are_bound_in_the_documented_order(): void
    {
        [$sql, $bindings] = $this->query(limit: 10, radiusMiles: 25);

        $this->assertSame(
            substr_count($sql, '?'),
            count($bindings),
            'Every placeholder must have exactly one binding.'
        );

        $this->assertSame([
            self::PINELLAS_LNG,             // ST_MakePoint X for ST_Distance
            self::PINELLAS_LAT,             // ST_MakePoint Y for ST_Distance
            'grocery_store',                // category_key
            'overture-2026-06-17.0-fl',     // corpus_version
            self::PINELLAS_LNG,             // ST_MakePoint X for the <-> ordering
            self::PINELLAS_LAT,             // ST_MakePoint Y for the <-> ordering
            25 * 1609.344,                  // radius ceiling in metres
        ], $bindings);
    }

    /** Neither the category nor the version is ever interpolated into the SQL text. */
    public function test_no_value_is_interpolated_into_the_sql_text(): void
    {
        [$sql] = $this->query();

        $this->assertStringNotContainsString('grocery_store', $sql);
        $this->assertStringNotContainsString('overture-2026-06-17.0-fl', $sql);
        $this->assertStringNotContainsString((string) self::PINELLAS_LAT, $sql);
    }

    /** Identifiers cannot be bound, so they are whitelisted instead of trusted. */
    public function test_an_unsafe_identifier_in_config_is_refused(): void
    {
        config(['overture_corpus_poi.table' => 'places; DROP TABLE places']);

        $this->expectException(InvalidArgumentException::class);

        $this->query();
    }

    // ── read-only ───────────────────────────────────────────────────────────

    public function test_the_statement_is_read_only(): void
    {
        [$sql] = $this->query();

        $this->assertMatchesRegularExpression('/^\s*WITH nearest AS \(\s*SELECT/i', $sql);

        foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT'] as $write) {
            $this->assertStringNotContainsString($write, strtoupper($sql));
        }
    }

    // ── plan-shape gate ─────────────────────────────────────────────────────

    /**
     * The acceptance shape the live EXPLAIN must satisfy, exercised here against the plan
     * a correctly-indexed partition produces. This proves the ASSERTION ENGINE is wired to
     * this query's expectations; the live smoke verification supplies the real plan.
     *
     * The node name is the partition-local child index — PostgreSQL synthesises
     * `<partition>_category_key_geom_idx` for a partition's copy of the composite index,
     * and the parent `places_cat_geom` never appears as a scan node.
     */
    public function test_the_expected_plan_shape_passes_the_acceptance_gate(): void
    {
        $result = (new ExplainPlanShape())->evaluate(
            $this->explainJson(indexName: 'places_p_overture_2026_06_17_0_fl_category_key_geom_idx'),
            'grocery_store',
        );

        $this->assertTrue($result['pass'], 'Expected plan shape rejected: ' . implode('; ', $result['failures']));
    }

    /** And the gate must actually reject a sequential scan, or it proves nothing. */
    public function test_a_sequential_scan_fails_the_acceptance_gate(): void
    {
        $seqScan = [[
            'Plan' => [
                'Node Type'     => 'Seq Scan',
                'Relation Name' => 'places',
                'Actual Rows'   => 29434,
            ],
        ]];

        $result = (new ExplainPlanShape())->evaluate($seqScan, 'grocery_store');

        $this->assertFalse(
            $result['pass'],
            'A sequential scan across the whole corpus must fail the gate, not pass it slowly.'
        );
    }

    /**
     * An EXPLAIN plan of the shape a correctly-indexed corpus produces for the inner
     * candidate retrieval. Structure mirrors `spikes/…batch-1b/validate/knn_explain.sql`.
     */
    private function explainJson(string $indexName): array
    {
        return [[
            'Plan' => [
                'Node Type'      => 'Limit',
                'Actual Rows'    => 20,
                'Plans'          => [[
                    'Node Type'      => 'Append',
                    'Actual Rows'    => 20,
                    'Plans'          => [[
                        'Node Type'              => 'Index Scan',
                        'Scan Direction'         => 'NoMovement',
                        'Index Name'             => $indexName,
                        'Relation Name'          => 'places_p_overture_2026_06_17_0_fl',
                        'Index Cond'             => "(category_key = 'grocery_store'::text)",
                        'Order By'               => '(geom <-> \'...\'::geography)',
                        'Rows Removed by Filter' => 0,
                        'Actual Rows'            => 20,
                    ]],
                ]],
            ],
        ]];
    }
}
