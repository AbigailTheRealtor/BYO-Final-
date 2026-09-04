<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use RuntimeException;
use Tests\TestCase;

/**
 * OvertureCorpusPoiAdapter — behaviour, hermetically.
 *
 * NO SPATIAL CLUSTER IS TOUCHED. `tests/bootstrap.php` blanks every SPATIAL_*
 * variable precisely so the suite cannot reach one, and this test relies on that
 * rather than working around it: the fixture subclass below overrides the single
 * method that talks to a database, so every other behaviour — the region gate, the
 * category gate, the clamps, the row mapping, both exception contracts — is the real
 * implementation running on real inputs.
 *
 * The SQL those inputs would produce is pinned separately in
 * {@see OvertureCorpusPoiSqlManifestTest}, and executed for real only by the
 * read-only live smoke verification.
 */
class OvertureCorpusPoiAdapterTest extends TestCase
{
    /** 6817 Stones Throw Circle N, St. Petersburg FL 33710 — a real Pinellas listing. */
    private const PINELLAS_LAT = 27.788945;
    private const PINELLAS_LNG = -82.735144;

    protected function setUp(): void
    {
        parent::setUp();

        // The adapter's own gates open; the fixture subclass stands in for the corpus.
        config([
            'overture_corpus_poi.enabled'              => true,
            'overture_corpus_poi.corpus_version'       => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.connection'           => 'pgsql_spatial',
            'overture_corpus_poi.table'                => 'places',
            'overture_corpus_poi.category_column'      => 'category_key',
            'overture_corpus_poi.geom_column'          => 'geom',
            'overture_corpus_poi.centroid_column'      => 'centroid',
            'overture_corpus_poi.regions'              => ['US-FL'],
            'overture_corpus_poi.region_bounds'        => [
                'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
            ],
            'overture_corpus_poi.overfetch_floor'      => 20,
            'overture_corpus_poi.overfetch_factor'     => 2,
            'overture_corpus_poi.max_results'          => 20,
            'overture_corpus_poi.default_radius_miles' => 25,
            'overture_corpus_poi.max_radius_miles'     => 25,
        ]);
    }

    // ── contracts ───────────────────────────────────────────────────────────

    public function test_it_implements_both_poi_interfaces(): void
    {
        $adapter = new OvertureCorpusPoiAdapter();

        $this->assertInstanceOf(PoiLookupAdapterInterface::class, $adapter);
        $this->assertInstanceOf(NearbyPoiFetcherInterface::class, $adapter);
    }

    public function test_the_provider_id_matches_the_registry_key(): void
    {
        $this->assertSame('overture_corpus', OvertureCorpusPoiAdapter::PROVIDER_ID);
        $this->assertArrayHasKey(
            'overture_corpus',
            (array) config('location_providers.providers'),
            'The adapter constant and the registry key must name the same provider.'
        );
    }

    // ── availability ────────────────────────────────────────────────────────

    public function test_it_is_unavailable_when_the_flag_is_off(): void
    {
        config(['overture_corpus_poi.enabled' => false]);

        $this->assertFalse((new OvertureCorpusPoiAdapter())->isAvailable());
    }

    /** An enabled adapter with no version pinned must not guess which import to serve. */
    public function test_it_is_unavailable_when_no_corpus_version_is_pinned(): void
    {
        config(['overture_corpus_poi.corpus_version' => null]);

        $this->assertFalse((new OvertureCorpusPoiAdapter())->isAvailable());
    }

    /**
     * The CI-realistic case: flags set, but the spatial connection is inert because the
     * bootstrap blanked SPATIAL_*. A schema probe against it must report unavailable, not
     * escape as an exception.
     */
    public function test_an_unreachable_spatial_connection_reports_unavailable_without_throwing(): void
    {
        $this->assertFalse(
            (new OvertureCorpusPoiAdapter())->isAvailable(),
            'An inert/unreachable spatial connection must make the adapter unavailable.'
        );
    }

    // ── the two exception contracts, which are opposite on purpose ───────────

    /**
     * `fetchNearby()` must let an unavailable corpus PROPAGATE, so
     * LocationDnaPoiDistanceService persists status='error' rather than
     * status='not_found'. Swallowing here would record "nothing is near this home".
     */
    public function test_fetch_nearby_propagates_when_the_corpus_is_unreadable(): void
    {
        config(['overture_corpus_poi.enabled' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(OvertureCorpusPoiAdapter::UNAVAILABLE_MESSAGE);

        (new OvertureCorpusPoiAdapter())->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['grocery_store'],
        );
    }

    /** `search()` must swallow the same failure and degrade gracefully to []. */
    public function test_search_swallows_the_same_failure_and_returns_empty(): void
    {
        config(['overture_corpus_poi.enabled' => false]);

        $result = (new OvertureCorpusPoiAdapter())->search(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            'gyms',
            5,
            5,
        );

        $this->assertSame([], $result);
    }

    // ── category gate ───────────────────────────────────────────────────────

    /**
     * An uningested category is `not_found`, not an error — so `fetchNearby()` returns []
     * WITHOUT raising, even though the corpus itself is perfectly readable.
     */
    public function test_an_unsupported_category_returns_empty_without_raising(): void
    {
        $adapter = $this->adapterReturning($this->corpusRows());

        foreach (['beach', 'school', 'park', 'hospital', 'transit_station'] as $category) {
            $this->assertSame(
                [],
                $adapter->fetchNearby(
                    self::PINELLAS_LAT,
                    self::PINELLAS_LNG,
                    LocationDnaPoiDistanceService::CATEGORIES[$category],
                ),
                "'{$category}' has no corpus rows and must yield no candidates"
            );
        }

        $this->assertSame(
            [],
            $adapter->queriesIssued,
            'An unsupported category must not reach the database at all.'
        );
    }

    public function test_unservable_buyer_tenant_slugs_return_empty(): void
    {
        $adapter = $this->adapterReturning($this->corpusRows());

        foreach (['schools', 'parks', 'hospitals', 'airports', 'downtown'] as $slug) {
            $this->assertSame([], $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, $slug, 5, 5));
        }

        $this->assertSame([], $adapter->queriesIssued);
    }

    // ── Florida-only safety ─────────────────────────────────────────────────

    public function test_a_pinellas_coordinate_is_inside_the_supported_region(): void
    {
        $this->assertTrue(
            (new OvertureCorpusPoiAdapter())->withinSupportedRegion(self::PINELLAS_LAT, self::PINELLAS_LNG)
        );
    }

    /**
     * The safety property: a Florida corpus must never answer for an out-of-state
     * property. A returned row would be a real business at a real distance and utterly
     * wrong — nothing downstream could detect it.
     *
     * @dataProvider outOfRegionCoordinates
     */
    public function test_an_out_of_region_coordinate_is_declined_before_any_query(
        string $label,
        float $lat,
        float $lng,
    ): void {
        $adapter = $this->adapterReturning($this->corpusRows());

        $this->assertFalse($adapter->withinSupportedRegion($lat, $lng), "{$label} must be out of region");

        $fetched = $adapter->fetchNearby($lat, $lng, LocationDnaPoiDistanceService::CATEGORIES['grocery_store']);
        $found   = $adapter->search($lat, $lng, 'gyms', 5, 5);

        $this->assertSame([], $fetched, "{$label} must receive no corpus candidates");
        $this->assertSame([], $found, "{$label} must receive no corpus candidates");
        $this->assertSame(
            [],
            $adapter->queriesIssued,
            "{$label} must be declined BEFORE a query is issued, not filtered afterwards."
        );
    }

    public static function outOfRegionCoordinates(): array
    {
        return [
            'Atlanta GA'      => ['Atlanta GA', 33.7490, -84.3880],
            'Austin TX'       => ['Austin TX', 30.2672, -97.7431],
            'Mobile AL'       => ['Mobile AL', 30.6954, -88.0399],
            'San Juan PR'     => ['San Juan PR', 18.4655, -66.1057],
            'New York NY'     => ['New York NY', 40.7128, -74.0060],
            'Null Island'     => ['Null Island', 0.0, 0.0],
        ];
    }

    /** A region named without an envelope cannot be checked, so it must not admit anything. */
    public function test_a_region_without_bounds_admits_nothing(): void
    {
        config([
            'overture_corpus_poi.regions'       => ['US-FL', 'US-GA'],
            'overture_corpus_poi.region_bounds' => [
                'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
                // US-GA deliberately absent
            ],
        ]);

        $adapter = new OvertureCorpusPoiAdapter();

        $this->assertTrue($adapter->withinSupportedRegion(self::PINELLAS_LAT, self::PINELLAS_LNG));
        $this->assertFalse($adapter->withinSupportedRegion(33.7490, -84.3880));
    }

    // ── row mapping ─────────────────────────────────────────────────────────

    public function test_fetch_nearby_returns_the_provider_native_shape_the_pipeline_reads(): void
    {
        $adapter = $this->adapterReturning($this->corpusRows());

        $rows = $adapter->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['grocery_store'],
        );

        $this->assertCount(3, $rows);

        $first = $rows[0];

        // The seven keys LocationDnaPoiDistanceService and PoiCandidate actually consume.
        $this->assertSame('Publix', $first['name']);
        $this->assertSame(27.7930, $first['geometry']['location']['lat']);
        $this->assertSame(-82.7401, $first['geometry']['location']['lng']);
        $this->assertSame('Publix', $first['vicinity']);
        $this->assertSame('overture:gers:aaa111', $first['place_id']);

        // types carries the category's own Google token — never [] — so the authoritative
        // type-based exclusion rules arbitrate instead of the types-empty name fallback.
        $this->assertSame(['grocery_or_supermarket'], $first['types']);

        // No fabricated review signal. PoiCandidate reads these as null / 0.
        $this->assertArrayNotHasKey('rating', $first);
        $this->assertArrayNotHasKey('user_ratings_total', $first);
    }

    /**
     * The types decision, stated as a test because getting it wrong is invisible: an empty
     * types array activates `exclude_if_name_matches_when_types_empty`, whose grocery
     * pattern would discard a corpus grocery store named for a fuel brand.
     */
    public function test_a_corpus_grocery_row_survives_the_grocery_exclusion_filter(): void
    {
        $adapter = $this->adapterReturning([
            $this->corpusRow(name: 'Wawa Market', brand: 'Wawa', sourceRef: 'overture:gers:w1'),
        ]);

        $rows = $adapter->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['grocery_store'],
        );

        $this->assertTrue(
            (new LocationDnaPoiDistanceService())->passesExclusionFilter('grocery_store', $rows[0]),
            'A corpus grocery row must be arbitrated by its type, not by the types-empty '
            . 'brand-name fallback that exists for sparse Google responses.'
        );
    }

    /** And the authoritative type rule still bites when the type genuinely says so. */
    public function test_the_authoritative_type_rule_still_excludes_a_gas_station(): void
    {
        $place = ['name' => 'Speedway', 'types' => ['gas_station'], 'vicinity' => null];

        $this->assertFalse(
            (new LocationDnaPoiDistanceService())->passesExclusionFilter('grocery_store', $place)
        );
    }

    public function test_search_returns_the_nine_key_normalised_envelope(): void
    {
        $adapter = $this->adapterReturning($this->corpusRows());

        $items = $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, 'gyms', 10, 5);

        $this->assertCount(3, $items);

        $this->assertSame([
            'category', 'name', 'address', 'latitude', 'longitude',
            'distance_miles', 'source', 'confidence', 'last_refreshed',
        ], array_keys($items[0]));

        $this->assertSame('gyms', $items[0]['category']);
        $this->assertSame('Publix', $items[0]['name']);
        $this->assertSame(27.7930, $items[0]['latitude']);
        $this->assertSame(-82.7401, $items[0]['longitude']);
        $this->assertSame('overture_corpus', $items[0]['source']);
        $this->assertSame(1.0, $items[0]['confidence']);

        // 627.9 m → 0.39 miles, matching the audit's measured Publix distance.
        $this->assertEqualsWithDelta(0.39, $items[0]['distance_miles'], 0.005);
    }

    /** Distance comes from the database's spheroidal measure, converted, not recomputed. */
    public function test_distance_is_converted_from_the_metres_the_corpus_returned(): void
    {
        $adapter = $this->adapterReturning([
            $this->corpusRow(meters: 1609.344), // exactly one mile
        ]);

        $items = $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, 'gyms', 10, 5);

        $this->assertSame(1.0, $items[0]['distance_miles']);
    }

    /** The corpus's own last_seen is the honest freshness, when it has one. */
    public function test_the_corpus_last_seen_is_reported_as_last_refreshed(): void
    {
        $adapter = $this->adapterReturning([
            $this->corpusRow(lastSeen: '2026-06-17 00:00:00'),
        ]);

        $items = $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, 'gyms', 10, 5);

        $this->assertStringStartsWith('2026-06-17', $items[0]['last_refreshed']);
    }

    /** A row with no usable centroid is dropped, never reported at Null Island. */
    public function test_a_row_without_a_centroid_is_dropped(): void
    {
        $adapter = $this->adapterReturning([
            $this->corpusRow(name: 'Good', lat: 27.79, lng: -82.74),
            $this->corpusRow(name: 'Broken', lat: null, lng: null),
        ]);

        $rows = $adapter->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['grocery_store'],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Good', $rows[0]['name']);
    }

    /** A brandless row reports a null address rather than an invented one. */
    public function test_a_row_with_no_brand_reports_a_null_address(): void
    {
        $adapter = $this->adapterReturning([$this->corpusRow(brand: null)]);

        $rows = $adapter->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['grocery_store'],
        );

        $this->assertNull($rows[0]['vicinity']);
    }

    // ── limits & clamps ─────────────────────────────────────────────────────

    /** SIA-D40: over-fetch is max(floor 20, 2 × limit) so the spheroid re-rank has room. */
    public function test_overfetch_honours_the_floor_and_the_factor(): void
    {
        $adapter = new OvertureCorpusPoiAdapter();

        $this->assertSame(20, $adapter->overfetchFor(1), 'floor applies for a small limit');
        $this->assertSame(20, $adapter->overfetchFor(10), '2 × 10 == the floor');
        $this->assertSame(30, $adapter->overfetchFor(15), 'factor applies above the floor');
        $this->assertSame(40, $adapter->overfetchFor(20));
    }

    public function test_a_caller_limit_above_the_ceiling_is_clamped(): void
    {
        config(['overture_corpus_poi.max_results' => 5]);

        $adapter = $this->adapterReturning($this->corpusRows());
        $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, 'gyms', 10, 9999);

        $this->assertStringContainsString('LIMIT 5', $adapter->queriesIssued[0]['sql']);
    }

    public function test_a_caller_radius_above_the_ceiling_is_clamped(): void
    {
        config(['overture_corpus_poi.max_radius_miles' => 25]);

        $adapter = $this->adapterReturning($this->corpusRows());
        $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, 'gyms', 500, 5);

        // Last binding is the radius ceiling in metres.
        $bindings = $adapter->queriesIssued[0]['bindings'];

        $this->assertEqualsWithDelta(25 * 1609.344, end($bindings), 0.001);
    }

    /** The production path supplies no radius; the configured default bounds it. */
    public function test_fetch_nearby_bounds_itself_with_the_default_radius(): void
    {
        $adapter = $this->adapterReturning($this->corpusRows());
        $adapter->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['grocery_store'],
        );

        $bindings = $adapter->queriesIssued[0]['bindings'];

        $this->assertEqualsWithDelta(25 * 1609.344, end($bindings), 0.001);
    }

    // ── no Google, anywhere ─────────────────────────────────────────────────

    /**
     * Structural, not behavioural: no Google symbol appears in the adapter's EXECUTABLE
     * code. A corpus provider that could reach Google under any condition would defeat the
     * point of having one.
     *
     * Comments are stripped before the check rather than included in it. The class
     * docblock legitimately compares this adapter to `GooglePlacesPoiAdapter` — explaining
     * why one class implements both interfaces — and a test that failed on prose would
     * push that explanation out of the file to satisfy itself. What must be absent is a
     * code path, so the assertion is made against code.
     */
    public function test_the_adapter_has_no_google_code_path(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(OvertureCorpusPoiAdapter::class))->getFileName()
        );

        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
                continue;
            }
            $code .= $token;
        }

        $this->assertStringNotContainsString('Publix', $code, 'sanity: fixture data must not be in the class');

        foreach ([
            'GooglePlacesPoiAdapter',
            'googleapis',
            'services.google',
            'google_places.enabled',
            'places_key',
            'GuzzleHttp',
            'ClientInterface',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "OvertureCorpusPoiAdapter's executable code must not reference '{$forbidden}'."
            );
        }
    }

    public function test_the_adapter_issues_only_select_statements(): void
    {
        $adapter = $this->adapterReturning($this->corpusRows());

        $adapter->search(self::PINELLAS_LAT, self::PINELLAS_LNG, 'gyms', 10, 5);
        $adapter->fetchNearby(
            self::PINELLAS_LAT,
            self::PINELLAS_LNG,
            LocationDnaPoiDistanceService::CATEGORIES['pharmacy'],
        );

        $this->assertNotEmpty($adapter->queriesIssued);

        foreach ($adapter->queriesIssued as $issued) {
            $this->assertMatchesRegularExpression(
                '/^\s*WITH nearest AS \(\s*SELECT/i',
                $issued['sql'],
                'Every corpus read must be a SELECT.'
            );

            foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE'] as $write) {
                $this->assertStringNotContainsString($write, strtoupper($issued['sql']));
            }
        }
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /** @param list<object> $rows */
    private function adapterReturning(array $rows): OvertureCorpusPoiAdapter
    {
        return new class ($rows) extends OvertureCorpusPoiAdapter {
            /** @var list<array{sql: string, bindings: list<mixed>}> */
            public array $queriesIssued = [];

            /** @param list<object> $rows */
            public function __construct(private readonly array $rows)
            {
            }

            /**
             * The corpus schema probe cannot run against an inert connection, so the
             * fixture reports the availability its config already describes. Everything
             * the probe would have checked is asserted directly by the availability tests
             * above, against the real implementation.
             */
            public function isAvailable(): bool
            {
                return (bool) config('overture_corpus_poi.enabled', false)
                    && $this->corpusVersion() !== null;
            }

            protected function selectRows(string $sql, array $bindings): array
            {
                $this->queriesIssued[] = ['sql' => $sql, 'bindings' => $bindings];

                return $this->rows;
            }
        };
    }

    /** Three rows shaped like the corpus's own SELECT output, nearest first. */
    private function corpusRows(): array
    {
        return [
            $this->corpusRow(name: 'Publix', brand: 'Publix', lat: 27.7930, lng: -82.7401, meters: 627.9, confidence: 1.0, sourceRef: 'overture:gers:aaa111'),
            $this->corpusRow(name: 'Brick Road Olive Oil Company', brand: null, lat: 27.8010, lng: -82.7500, meters: 2076.0, confidence: 0.972, sourceRef: 'overture:gers:bbb222'),
            $this->corpusRow(name: 'VGP Produce', brand: null, lat: 27.8090, lng: -82.7300, meters: 2382.0, confidence: 0.920, sourceRef: 'overture:gers:ccc333'),
        ];
    }

    private function corpusRow(
        string  $name = 'Publix',
        ?string $brand = 'Publix',
        ?float  $lat = 27.7930,
        ?float  $lng = -82.7401,
        float   $meters = 627.9,
        float   $confidence = 1.0,
        ?string $lastSeen = null,
        string  $sourceRef = 'overture:gers:aaa111',
    ): object {
        return (object) [
            'name'       => $name,
            'brand'      => $brand,
            'confidence' => $confidence,
            'source_ref' => $sourceRef,
            'last_seen'  => $lastSeen,
            'attrs'      => null,
            'poi_lat'    => $lat,
            'poi_lng'    => $lng,
            'meters'     => $meters,
        ];
    }
}
