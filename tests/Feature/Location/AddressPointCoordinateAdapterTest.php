<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\Adapters\AddressPointCoordinateAdapter;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\StandardCoordinateLadder;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressPointCoordinateAdapterTest extends TestCase
{
    private AddressPointCoordinateAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new AddressPointCoordinateAdapter();
    }

    private function tampa(string $unit = ''): PropertyAddress
    {
        return new PropertyAddress(
            address:     '123 North Main Street',
            unitAddress: $unit,
            city:        'Tampa',
            state:       'FL',
            zip:         '33602',
        );
    }

    // ── identity ────────────────────────────────────────────────────────────

    public function test_it_answers_for_the_address_point_tier(): void
    {
        $this->assertSame('address_point', $this->adapter->providerId());
        $this->assertSame(CoordinateSource::AddressPoint, $this->adapter->source());
    }

    public function test_it_is_a_local_rung(): void
    {
        $this->assertFalse($this->adapter->requiresNetwork());
        $this->assertTrue($this->adapter->source()->isLocal());
    }

    // ── inert by default ────────────────────────────────────────────────────

    public function test_the_shipped_default_is_off(): void
    {
        // Read from the config file rather than from an override, so this fails
        // if the default is ever flipped in the file itself.
        $this->assertFalse((bool) config('address_point_corpus.enabled'));
        $this->assertNull(config('address_point_corpus.corpus_version'));
    }

    public function test_it_is_unavailable_while_the_flag_is_off(): void
    {
        config(['address_point_corpus.enabled' => false]);

        $this->assertFalse($this->adapter->isAvailable());
    }

    public function test_resolving_while_disabled_says_so_and_queries_nothing(): void
    {
        config(['address_point_corpus.enabled' => false]);

        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        $result = $this->adapter->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('address_point_corpus_disabled', $result->reason);
        $this->assertSame(0, $queries, 'A disabled rung must not cost a query.');
    }

    public function test_an_enabled_flag_without_a_pinned_version_stays_inert(): void
    {
        // The dangerous half-configured state: somebody enabled the rung and did
        // not say which import to read. It must not guess.
        config([
            'address_point_corpus.enabled'        => true,
            'address_point_corpus.corpus_version' => null,
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        $this->assertFalse($this->adapter->isAvailable());

        $result = $this->adapter->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('address_point_corpus_not_pinned', $result->reason);
        $this->assertSame(0, $queries);
    }

    public function test_a_blank_pinned_version_counts_as_none(): void
    {
        config([
            'address_point_corpus.enabled'        => true,
            'address_point_corpus.corpus_version' => '   ',
        ]);

        $this->assertFalse($this->adapter->isAvailable());
    }

    public function test_an_unreachable_corpus_makes_the_rung_unavailable_not_fatal(): void
    {
        // A missing or misconfigured spatial connection must degrade to "this
        // rung cannot answer", never to an exception inside a listing save.
        config([
            'address_point_corpus.enabled'        => true,
            'address_point_corpus.corpus_version' => 'nad-2026-fl',
            'address_point_corpus.connection'     => 'no_such_connection',
        ]);

        $this->assertFalse($this->adapter->isAvailable());
    }

    public function test_an_address_too_thin_to_look_up_is_declined_before_any_query(): void
    {
        config([
            'address_point_corpus.enabled'        => true,
            'address_point_corpus.corpus_version' => 'nad-2026-fl',
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        $result = $this->adapter->resolve(new PropertyAddress());

        $this->assertFalse($result->isResolved());
        $this->assertSame('insufficient_address', $result->reason);
        $this->assertSame(0, $queries);
    }

    public function test_the_rung_never_reaches_the_network(): void
    {
        Http::fake();

        config([
            'address_point_corpus.enabled'        => true,
            'address_point_corpus.corpus_version' => 'nad-2026-fl',
            'address_point_corpus.connection'     => 'no_such_connection',
        ]);

        $this->adapter->isAvailable();
        $this->adapter->resolve(new PropertyAddress());

        Http::assertNothingSent();
    }

    // ── precision rules ─────────────────────────────────────────────────────

    public function test_a_unit_lookup_that_only_matched_the_building_cannot_claim_rooftop(): void
    {
        $this->assertSame(
            CoordinatePrecision::Parcel,
            AddressPointCoordinateAdapter::precisionForLookup(
                CoordinatePrecision::Rooftop,
                lookupDroppedUnit: true
            ),
            'Rooftop for a unit we did not match would claim we located that unit.'
        );
    }

    public function test_a_matched_unit_keeps_the_corpus_precision(): void
    {
        $this->assertSame(
            CoordinatePrecision::Rooftop,
            AddressPointCoordinateAdapter::precisionForLookup(
                CoordinatePrecision::Rooftop,
                lookupDroppedUnit: false
            )
        );
    }

    /** @dataProvider coarseAndLesserTiers */
    public function test_the_unit_cap_never_promotes_a_tier(CoordinatePrecision $corpus): void
    {
        // The reason PropertyCoordinateResult::forBuilding() is not used here:
        // it *sets* Parcel, so a `street` row would be promoted from coarse to
        // exact and become eligible for flood-boundary work.
        $capped = AddressPointCoordinateAdapter::precisionForLookup($corpus, lookupDroppedUnit: true);

        $this->assertSame($corpus, $capped);
        $this->assertSame(
            $corpus->isExact(),
            $capped->isExact(),
            'Capping may lower a tier; it may never make a coarse row measurable.'
        );
    }

    public static function coarseAndLesserTiers(): array
    {
        return [
            'street'        => [CoordinatePrecision::Street],
            'zip_centroid'  => [CoordinatePrecision::ZipCentroid],
            'city_centroid' => [CoordinatePrecision::CityCentroid],
            'unknown'       => [CoordinatePrecision::Unknown],
            'interpolated'  => [CoordinatePrecision::Interpolated],
            'entrance'      => [CoordinatePrecision::Entrance],
            'parcel'        => [CoordinatePrecision::Parcel],
        ];
    }

    // ── ladder placement ────────────────────────────────────────────────────

    public function test_the_standard_ladder_places_the_corpus_below_bridge_and_above_census(): void
    {
        $classes = array_map('get_class', StandardCoordinateLadder::adapters());

        $this->assertSame([
            ExistingCoordinatesAdapter::class,
            BridgeMlsCoordinatesAdapter::class,
            AddressPointCoordinateAdapter::class,
            CensusGeocoderAdapter::class,
        ], $classes);
    }

    public function test_every_rung_before_the_network_rung_is_local(): void
    {
        $adapters = StandardCoordinateLadder::adapters();

        foreach ($adapters as $index => $adapter) {
            if ($adapter->requiresNetwork()) {
                $this->assertSame(
                    count($adapters) - 1,
                    $index,
                    'The only network rung must be last; a free rung after a paid one would never be reached cheaply.'
                );
            }
        }
    }

    public function test_the_local_ladder_is_untouched(): void
    {
        // LocalCoordinateLadder's guarantee is that a resolver built from it
        // cannot leave this machine. The corpus rung is local, but adding rungs
        // to that ladder is a separate decision — it is not made by this change.
        $classes = array_map(
            'get_class',
            \App\Services\Location\Coordinates\Adapters\LocalCoordinateLadder::adapters()
        );

        $this->assertSame([
            ExistingCoordinatesAdapter::class,
            BridgeMlsCoordinatesAdapter::class,
        ], $classes);
    }

    // ── it matches exactly, never nearly ────────────────────────────────────

    public function test_the_rung_uses_no_fuzzy_matching(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Location/Coordinates/Adapters/AddressPointCoordinateAdapter.php')
        );

        $this->assertIsString($source);

        // Trigram similarity is what the typeahead index is for. Here it would
        // return the *nearest* address rather than *this* address — another
        // property's coordinate, reported as complete success.
        foreach (['similarity(', '->orWhere', 'ILIKE', 'LIKE ', "'%'"] as $needle) {
            $this->assertStringNotContainsString($needle, $source, "Matching must be exact; found {$needle}");
        }
    }

    public function test_no_outbound_client_or_google_symbol_appears_in_the_rung(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Location/Coordinates/Adapters/AddressPointCoordinateAdapter.php')
        );

        $this->assertIsString($source);

        foreach (['Http::', 'GuzzleHttp', 'curl_init', 'googleapis', 'GOOGLE_PLACES'] as $needle) {
            $this->assertStringNotContainsString($needle, $source, $needle);
        }
    }
}
