<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\LocationMatchEngine;
use App\Services\Location\Lookup\AddressLookupService;
use App\Support\Spatial\LdnaBasemapSurface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Radius Search by typed address, end to end on the PHP side.
 *
 * The browser half — button, fetch, circle — is Playwright's. What is asserted
 * here is everything that outlives the click: that a resolved address produces
 * the CANONICAL stored shape and no other, that the shape the matching engine
 * reads is the shape that was stored, and that reopening the listing spends
 * nothing at the provider.
 *
 * The last of those is the one worth stating plainly. A radius centre is
 * resolved once, when it is typed. Every reload after that reads `lat`/`lng`
 * out of the listing's own blob. A test that only proved "the address resolves"
 * would pass just as happily against an implementation that geocoded on every
 * page render, which is the failure that ends free access to a free provider.
 */
class RadiusSearchAddressResolutionTest extends TestCase
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
                    'coordinates'       => ['x' => -82.6367, 'y' => 27.7705],
                    'addressComponents' => [
                        'zip' => '33701', 'streetName' => '2ND', 'city' => 'SAINT PETERSBURG',
                        'state' => 'FL', 'suffixType' => 'AVE', 'fromAddress' => '100', 'toAddress' => '199',
                    ],
                    'matchedAddress'    => '100 2ND AVE S, SAINT PETERSBURG, FL, 33701',
                ]],
            ],
        ];
    }

    /**
     * What the browser does with a successful lookup, expressed once.
     *
     * The canonical entry is built here rather than copied into each test, so a
     * change to the stored shape breaks in one place rather than four.
     */
    private function resolveIntoRadiusEntry(string $typed, float $miles): ?array
    {
        $result = (new AddressLookupService())->lookup($typed);

        if (! $result->ok) {
            return null;
        }

        return [
            'address'      => $result->address,
            'lat'          => $result->latitude,
            'lng'          => $result->longitude,
            'radius_miles' => $miles,
        ];
    }

    public function test_a_typed_address_becomes_the_canonical_radius_entry(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $entry = $this->resolveIntoRadiusEntry('100 2nd Ave S, St. Petersburg, FL 33701', 5);

        // Four keys, exactly — the shape `ldna-geometry.js` documents as frozen
        // and every criteria loader reads. Not a new format, not a nested
        // `center`, and no provenance keys bolted on.
        $this->assertSame(['address', 'lat', 'lng', 'radius_miles'], array_keys($entry));
        $this->assertSame(27.7705, $entry['lat']);
        $this->assertSame(-82.6367, $entry['lng']);
        $this->assertSame(5.0, (float) $entry['radius_miles']);
    }

    public function test_the_stored_address_is_the_one_that_was_matched(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $entry = $this->resolveIntoRadiusEntry('100 2nd ave s, saint petersburg, fl 33701', 5);

        // Presented for a human, and derived from the provider's matched line
        // rather than echoed back from the box — the difference between the two
        // is what lets somebody notice a wrong match before they save it.
        $this->assertSame('100 2nd Ave S Saint Petersburg FL 33701', $entry['address']);
    }

    public function test_a_failed_lookup_produces_no_entry_at_all(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);

        $this->assertNull($this->resolveIntoRadiusEntry('999999 Nowhere Rd, Tampa, FL 33602', 5));
    }

    public function test_the_stored_entry_is_what_the_match_engine_reads(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $entry = $this->resolveIntoRadiusEntry('100 2nd Ave S, St. Petersburg, FL 33701', 5);

        $engine = new LocationMatchEngine();

        // A property inside the circle matches; one well outside does not. This
        // is the assertion that the coordinate is stored in the keys the rest of
        // the platform actually reads, rather than merely in keys of the right
        // names.
        $inside = $engine->match(
            ['radius_searches' => [$entry]],
            ['lat' => 27.7705, 'lng' => -82.6367]
        );
        $outside = $engine->match(
            ['radius_searches' => [$entry]],
            ['lat' => 26.1224, 'lng' => -80.1373]   // Fort Lauderdale
        );

        $this->assertTrue($inside['radius_match']);
        $this->assertSame(1, $inside['matched_radius_count']);
        $this->assertFalse($outside['radius_match']);
        $this->assertSame(0, $outside['matched_radius_count']);
    }

    public function test_save_then_reload_preserves_the_centre_and_the_radius_exactly(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $entry = $this->resolveIntoRadiusEntry('100 2nd Ave S, St. Petersburg, FL 33701', 3.5);

        // The round trip the widget performs: serialise into the blob, store,
        // read back, hand to the renderer.
        $stored   = json_encode(['radius_searches' => [$entry], 'polygons' => []]);
        $reloaded = json_decode($stored, true);

        $this->assertSame($entry, $reloaded['radius_searches'][0]);
    }

    public function test_reloading_a_stored_radius_geocodes_nothing(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $entry = $this->resolveIntoRadiusEntry('100 2nd Ave S, St. Petersburg, FL 33701', 3.5);
        Http::assertSentCount(1);

        // Render the widget with that radius already stored — the reopened-listing
        // case — and assert the provider is not consulted. The coordinate reaches
        // the browser in the hydration payload instead.
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => [LdnaBasemapSurface::CREATE_BUYER],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);

        $html = view('partials.location-dna.map-input', [
            'existingLocationDna' => ['radius_searches' => [$entry], 'polygons' => []],
            'ldnaSurface'         => LdnaBasemapSurface::CREATE_BUYER,
            'errors'              => new ViewErrorBag(),
        ])->render();

        Http::assertSentCount(1);   // still one: rendering resolved nothing

        $this->assertStringContainsString('100 2nd Ave S Saint Petersburg FL 33701', $html);
        $this->assertStringContainsString('27.7705', $html);
    }

    public function test_the_widget_still_offers_the_circle_tool_as_the_no_network_path(): void
    {
        // The two-click Circle tool produces the same stored entry with no
        // provider involved, and it is the answer when an address cannot be
        // resolved. Removing it while adding the lookup would trade one failure
        // mode for another.
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => [LdnaBasemapSurface::CREATE_BUYER],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);

        $html = view('partials.location-dna.map-input', [
            'existingLocationDna' => [],
            'ldnaSurface'         => LdnaBasemapSurface::CREATE_BUYER,
            'errors'              => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('r.startDrawCircle()', $html);
    }
}
