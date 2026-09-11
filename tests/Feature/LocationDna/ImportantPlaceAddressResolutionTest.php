<?php

namespace Tests\Feature\LocationDna;

use App\Services\Location\Lookup\AddressLookupService;
use App\Services\Offers\ImportantPlacesService;
use App\Support\Spatial\LdnaBasemapSurface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Important Places: a typed address becomes a coordinate, and nothing else about
 * the row changes.
 *
 * `ImportantPlacesService::normalize()` rebuilds every row from eight named keys
 * and drops the rest, so "the coordinate was added" and "the row survived" are
 * genuinely separate questions — a lookup that wrote its result under a key the
 * service does not know would vanish silently on the very next save. Both are
 * asserted, on the same row.
 *
 * The miles/minutes distinction is asserted here too, at the storage level. The
 * rendering half is Playwright's, but what a ring is drawn FROM is a stored
 * value, and a minutes row that came out of a save carrying `miles` would draw a
 * circle no matter how careful the renderer was.
 */
class ImportantPlaceAddressResolutionTest extends TestCase
{
    private const ENDPOINT = 'geocoding.geo.census.gov/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('census_geocoder.enabled', true);
    }

    private function matchBody(): array
    {
        return [
            'result' => [
                'addressMatches' => [[
                    'coordinates'       => ['x' => -82.6390, 'y' => 27.7712],
                    'addressComponents' => [
                        'zip' => '33701', 'streetName' => 'CENTRAL', 'city' => 'SAINT PETERSBURG',
                        'state' => 'FL', 'suffixType' => 'AVE', 'fromAddress' => '200', 'toAddress' => '299',
                    ],
                    'matchedAddress'    => '200 CENTRAL AVE, SAINT PETERSBURG, FL, 33701',
                ]],
            ],
        ];
    }

    /** A started row, before its address has been located. */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'type'           => 'School',
            'type_other'     => '',
            'address'        => '200 Central Ave, St. Petersburg, FL 33701',
            'lat'            => null,
            'lng'            => null,
            'distance_pref'  => 'miles',
            'distance_value' => 1,
            'travel_mode'    => 'driving',
        ], $overrides);
    }

    /** What the browser does to a row when a lookup succeeds. */
    private function locate(array $row): array
    {
        $result = (new AddressLookupService())->lookup($row['address']);

        if (! $result->ok) {
            return $row;
        }

        $row['address'] = $result->address;
        $row['lat']     = $result->latitude;
        $row['lng']     = $result->longitude;

        return $row;
    }

    public function test_a_located_place_gains_a_coordinate(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $located = $this->locate($this->row());

        $this->assertSame(27.7712, $located['lat']);
        $this->assertSame(-82.6390, $located['lng']);
    }

    public function test_every_other_canonical_field_survives_the_lookup(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $before = $this->row(['type' => 'Daycare', 'distance_value' => 2, 'travel_mode' => 'walking']);
        $after  = $this->locate($before);

        foreach (['type', 'type_other', 'distance_pref', 'distance_value', 'travel_mode'] as $key) {
            $this->assertSame($before[$key], $after[$key], "lookup altered {$key}");
        }
    }

    public function test_the_coordinate_survives_normalization(): void
    {
        // The real hazard: a value written under a key `normalize()` does not
        // know is dropped on the next save, silently and completely.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $normalized = (new ImportantPlacesService())->normalize([$this->locate($this->row())]);

        $this->assertCount(1, $normalized);
        $this->assertSame(27.7712, $normalized[0]['lat']);
        $this->assertSame(-82.6390, $normalized[0]['lng']);
        $this->assertSame('School', $normalized[0]['type']);
        $this->assertSame('miles', $normalized[0]['distance_pref']);
        $this->assertSame(1.0, $normalized[0]['distance_value']);
        $this->assertSame('driving', $normalized[0]['travel_mode']);
    }

    public function test_the_stored_address_is_the_matched_one(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $located = $this->locate($this->row());

        $this->assertSame('200 Central Ave Saint Petersburg FL 33701', $located['address']);
    }

    public function test_a_failed_lookup_leaves_the_row_exactly_as_it_was(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 500)]);

        $before = $this->row(['lat' => 27.9, 'lng' => -82.4]);

        $this->assertSame($before, $this->locate($before));
    }

    // ── miles vs minutes, at the storage level ──────────────────────────────

    public function test_a_minutes_row_keeps_minutes_through_a_lookup_and_a_save(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $row        = $this->row(['distance_pref' => 'minutes', 'distance_value' => 10]);
        $normalized = (new ImportantPlacesService())->normalize([$this->locate($row)]);

        // Locating a place says where it is. It says nothing about whether the
        // user asked for a distance or a travel time, and must not quietly
        // convert one into the other — ten minutes is not a number of miles.
        $this->assertSame('minutes', $normalized[0]['distance_pref']);
        $this->assertSame(10.0, $normalized[0]['distance_value']);
        $this->assertSame(27.7712, $normalized[0]['lat']);
    }

    public function test_the_two_preferences_are_the_only_ones_that_survive(): void
    {
        $service = new ImportantPlacesService();

        // Anything else falls back to miles at the service, which is why the
        // renderer decides on the normalized value rather than on raw input.
        $this->assertSame('miles', $service->normalize([$this->row(['distance_pref' => 'kilometres'])])[0]['distance_pref']);
        $this->assertSame('minutes', $service->normalize([$this->row(['distance_pref' => 'minutes'])])[0]['distance_pref']);
    }

    // ── the surface ─────────────────────────────────────────────────────────

    public function test_the_maplibre_surface_offers_a_lookup_rather_than_an_apology(): void
    {
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => [LdnaBasemapSurface::CREATE_BUYER],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);

        $html = view('partials.location-dna.map-input', [
            'existingLocationDna'   => [],
            'ldnaSurface'           => LdnaBasemapSurface::CREATE_BUYER,
            'enableImportantPlaces' => true,
            'errors'                => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('ldnaLookupAddress(address)', $html);
        $this->assertStringNotContainsString('this place will not show a pin', $html);

        // The on-screen promise is miles only, and it is true — every miles row gets a
        // ring. No "within minutes" is offered, because nothing here can measure one.
        $this->assertStringContainsString('with a ring at the number of miles you choose', $html);
        $this->assertStringNotContainsString('Within minutes', $html);
    }

    public function test_a_stored_row_is_marked_resolved_so_a_reload_looks_nothing_up(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => [LdnaBasemapSurface::CREATE_BUYER],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);

        $html = view('partials.location-dna.map-input', [
            'existingLocationDna'    => [],
            'ldnaSurface'            => LdnaBasemapSurface::CREATE_BUYER,
            'enableImportantPlaces'  => true,
            'existingImportantPlaces' => [$this->row(['lat' => 27.7712, 'lng' => -82.6390])],
            'errors'                 => new ViewErrorBag(),
        ])->render();

        // Rendering resolves nothing.
        Http::assertNothingSent();

        // And the row is built already marked as resolved for its stored address,
        // so a blur on an untouched field — which happens on every reopened
        // listing — does not become a provider request.
        $this->assertStringContainsString("row.dataset.ldnaResolvedFor = prefill.address || ''", $html);
    }
}
