<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Adapters\LocalCoordinateLadder;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\Exceptions\CoordinateProviderUnavailable;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;
use App\Services\Location\Coordinates\PropertyCoordinateResolverInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * G3 adds the first rung that leaves the machine.
 *
 * Every response fixture below is the real shape of the live service, captured
 * on 2026-08-10 — including the two that most often get guessed wrong: a
 * no-match is an HTTP 200 with an empty array rather than a 404, and
 * `coordinates.x` is the longitude.
 *
 * The suite is organised around the distinction the adapter exists to keep:
 * an address that cannot be matched is a final answer worth caching, while a
 * provider that is broken is not an answer at all.
 *
 * NO REAL REQUEST CAN ESCAPE THIS SUITE
 * -------------------------------------
 * Not a convention — {@see \Tests\Support\Http\GuardedPendingRequest} is
 * installed for the whole test run and turns any request that matches no
 * `Http::fake()` stub into a {@see \Tests\Support\Http\StrayHttpRequestException}
 * rather than letting Laravel 8 fall through to the live Guzzle handler. A test
 * here that forgot to fake would fail loudly instead of quietly calling the US
 * Census Bureau. {@see \Tests\Feature\Security\NetworkGuardTest} is what keeps
 * that guard honest.
 */
class CensusGeocoderAdapterTest extends TestCase
{
    private const ENDPOINT = 'geocoding.geo.census.gov/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('census_geocoder.enabled', true);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function adapter(): CensusGeocoderAdapter
    {
        return new CensusGeocoderAdapter();
    }

    private function tampa(string $unit = ''): PropertyAddress
    {
        return new PropertyAddress(
            address:     '315 E Madison St',
            unitAddress: $unit,
            city:        'Tampa',
            county:      'Hillsborough',
            state:       'FL',
            zip:         '33602',
        );
    }

    /** One match, in the exact shape the live service returns. */
    private function matchBody(float $lng = -82.458094358643, float $lat = 27.948434712759): array
    {
        return [
            'result' => [
                'input'          => ['address' => ['address' => '315 e madison st tampa fl 33602']],
                'addressMatches' => [$this->match($lng, $lat)],
            ],
        ];
    }

    private function match(float $lng, float $lat, string $matched = '315 MADISON ST, TAMPA, FL, 33602'): array
    {
        return [
            'tigerLine'         => ['side' => 'R', 'tigerLineId' => '104530163'],
            'coordinates'       => ['x' => $lng, 'y' => $lat],
            'addressComponents' => [
                'zip'         => '33602',
                'streetName'  => 'MADISON',
                'city'        => 'TAMPA',
                'state'       => 'FL',
                'suffixType'  => 'ST',
                'fromAddress' => '301',
                'toAddress'   => '399',
            ],
            'matchedAddress'    => $matched,
        ];
    }

    private function noMatchBody(): array
    {
        return ['result' => ['input' => ['address' => ['address' => 'x']], 'addressMatches' => []]];
    }

    /**
     * A match whose returned state/ZIP can be varied independently of its
     * coordinate — the shape the G4 geography check is about. `null` removes
     * the component entirely; '' returns it empty, which is what the service
     * does for components it has no value for.
     */
    private function geoMatch(?string $state, ?string $zip, float $lng = -82.458094358643, float $lat = 27.948434712759): array
    {
        $components = [
            'streetName'  => 'MADISON',
            'city'        => 'TAMPA',
            'suffixType'  => 'ST',
            'fromAddress' => '301',
            'toAddress'   => '399',
        ];

        if ($state !== null) {
            $components['state'] = $state;
        }

        if ($zip !== null) {
            $components['zip'] = $zip;
        }

        return [
            'tigerLine'         => ['side' => 'R', 'tigerLineId' => '104530163'],
            'coordinates'       => ['x' => $lng, 'y' => $lat],
            'addressComponents' => $components,
            'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
        ];
    }

    /** @param array<int, array<string, mixed>> $matches */
    private function bodyOf(array $matches): array
    {
        return ['result' => ['addressMatches' => $matches]];
    }

    // ── identity ────────────────────────────────────────────────────────────

    public function test_it_identifies_itself_as_the_census_geocoder_rung(): void
    {
        $adapter = $this->adapter();

        $this->assertSame('us_census', $adapter->providerId());
        $this->assertSame(CoordinateSource::Geocoder, $adapter->source());
        $this->assertTrue($adapter->requiresNetwork());
    }

    // ── inert by default ────────────────────────────────────────────────────

    public function test_it_is_disabled_by_default(): void
    {
        // Read the config file itself rather than the runtime value: setUp()
        // turns the adapter on for the rest of this suite, so asking the
        // container here would only prove that setUp() ran.
        $shipped = require config_path('census_geocoder.php');

        // The service needs no API key, so a missing credential cannot keep this
        // quiet. The flag is the only thing that does, and it defaults off.
        $this->assertFalse(
            $shipped['enabled'],
            'The shipped default must be off — see config/census_geocoder.php'
        );
    }

    public function test_a_disabled_adapter_reports_itself_unavailable(): void
    {
        config()->set('census_geocoder.enabled', false);

        $this->assertFalse($this->adapter()->isAvailable());
    }

    public function test_the_resolver_skips_a_disabled_adapter_without_calling_it(): void
    {
        Http::fake();
        config()->set('census_geocoder.enabled', false);

        $result = (new PropertyCoordinateResolver([$this->adapter()]))->resolve($this->tampa());

        Http::assertNothingSent();
        $this->assertSame('no_adapter_resolved', $result->reason);
    }

    public function test_g3_binds_the_resolver_interface_to_nothing(): void
    {
        $this->assertFalse(
            $this->app->bound(PropertyCoordinateResolverInterface::class),
            'G3 ships an adapter, not an integration — nothing may inject a resolver yet'
        );
    }

    public function test_the_census_rung_is_not_on_the_local_ladder(): void
    {
        $this->assertSame(
            ['existing_coordinates', 'bridge_mls'],
            LocalCoordinateLadder::resolver()->providerIds()
        );
    }

    public function test_no_listing_flow_references_the_census_adapter(): void
    {
        $components = [
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        ];

        foreach ($components as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertIsString($source);
            $this->assertStringNotContainsString('CensusGeocoderAdapter', $source, $path);
        }
    }

    public function test_the_adapter_dispatches_no_location_dna_work(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Location/Coordinates/Adapters/CensusGeocoderAdapter.php')
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString('ComputeLocationDna', $source);
        $this->assertStringNotContainsString('dispatch(', $source);
    }

    public function test_the_adapter_names_no_google_or_commercial_provider(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Location/Coordinates/Adapters/CensusGeocoderAdapter.php')
        );

        $this->assertIsString($source);

        foreach (['googleapis', 'GOOGLE_PLACES', 'geoapify.com', 'opencage'] as $needle) {
            $this->assertStringNotContainsString($needle, $source);
        }
    }

    // ── the happy path ──────────────────────────────────────────────────────

    public function test_a_match_resolves_to_the_provider_coordinate(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertTrue($result->isResolved());
        $this->assertSame(27.948434712759, $result->latitude);
        $this->assertSame(-82.458094358643, $result->longitude);
        $this->assertSame(CoordinateSource::Geocoder, $result->source);
        $this->assertSame('us_census', $result->provider);
    }

    public function test_x_is_read_as_longitude_and_y_as_latitude(): void
    {
        // The transposition test. Florida is near 28N, -82E; reading the axes
        // the other way round would put this property in the Indian Ocean.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertGreaterThan(0, $result->latitude,  'US latitudes are positive');
        $this->assertLessThan(0, $result->longitude,    'US longitudes are negative');
    }

    public function test_a_match_is_always_graded_interpolated(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertSame(CoordinatePrecision::Interpolated, $result->precision);
    }

    public function test_an_interpolated_match_is_usable_for_location_dna(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertTrue($result->isUsableForLocationDna());
        $this->assertNotNull($result->exactCoordinates());
    }

    public function test_no_confidence_is_invented(): void
    {
        // The service reports no quality field of any kind. A fabricated 1.0
        // would read as certainty nobody asserted.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->assertNull($this->adapter()->resolve($this->tampa())->confidence);
    }

    public function test_the_matched_address_is_recorded_normalized(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertSame('315 madison st tampa fl 33602', $result->normalizedAddress);
    }

    // ── the request ─────────────────────────────────────────────────────────

    public function test_it_calls_the_documented_endpoint_with_the_documented_parameters(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->adapter()->resolve($this->tampa());

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress')
                && $query['address']   === '315 e madison st tampa fl 33602'
                && $query['benchmark'] === 'Public_AR_Current'
                && $query['format']    === 'json';
        });
    }

    public function test_the_unit_is_stripped_from_the_query(): void
    {
        // No US geocoder resolves individual units, and asking lowers the match
        // rate. PropertyAddress already knows this; the adapter must use the
        // lookup line rather than the identity line.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->adapter()->resolve($this->tampa(unit: 'Unit 4A'));

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ! str_contains($query['address'], '4a');
        });
    }

    public function test_a_unit_address_is_still_interpolated_and_not_upgraded_to_parcel(): void
    {
        // forBuilding() caps an over-claiming provider at Parcel. Applying it to
        // an interpolated point would RAISE the tier — a fabricated upgrade.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->adapter()->resolve($this->tampa(unit: 'Unit 4A'));

        $this->assertSame(CoordinatePrecision::Interpolated, $result->precision);
    }

    // ── answers that are not coordinates ────────────────────────────────────

    public function test_an_empty_match_list_is_a_miss_and_not_an_error(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_no_match', $result->reason);
    }

    public function test_an_insufficient_address_is_declined_without_a_request(): void
    {
        Http::fake();

        $result = $this->adapter()->resolve(new PropertyAddress('123 Main St'));

        Http::assertNothingSent();
        $this->assertSame('insufficient_address', $result->reason);
    }

    public function test_an_over_long_address_is_declined_without_a_request(): void
    {
        Http::fake();

        $result = $this->adapter()->resolve(new PropertyAddress(
            address: str_repeat('a', 120),
            city:    'Tampa',
            state:   'FL',
            zip:     '33602',
        ));

        // The service enforces 100 characters itself and answers with a 400
        // that is byte-identical to the one a bad benchmark produces. Declining
        // first keeps a misconfiguration distinguishable from a long address.
        Http::assertNothingSent();
        $this->assertSame('address_exceeds_provider_limit', $result->reason);
    }

    public function test_a_rejected_request_is_unresolved_rather_than_a_fault(): void
    {
        Http::fake([self::ENDPOINT => Http::response(
            ['errors' => ['Invalid benchmark in request'], 'status' => '400'],
            400
        )]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_request_rejected', $result->reason);
    }

    public function test_an_unusable_coordinate_is_rejected(): void
    {
        // Null Island, the most common corrupt value in geospatial data.
        Http::fake([self::ENDPOINT => Http::response([
            'result' => ['addressMatches' => [$this->match(0.0, 0.0)]],
        ])]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_coordinates_invalid', $result->reason);
    }

    public function test_a_transposed_coordinate_cannot_reach_a_caller(): void
    {
        // x and y swapped. Note that range validation alone does NOT catch this:
        // Tampa's longitude is -82.46, and -82.46 is a perfectly legal latitude
        // in the Southern Ocean. Both numbers are individually in range, and the
        // property would be placed near Antarctica. The service-area check is
        // what makes the transposition detectable.
        Http::fake([self::ENDPOINT => Http::response([
            'result' => ['addressMatches' => [$this->match(27.948434712759, -82.458094358643)]],
        ])]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_coordinates_invalid', $result->reason);
    }

    public function test_the_transposed_pair_would_have_passed_plain_range_validation(): void
    {
        // Pins the reason the check above has to exist. If this ever starts
        // failing, CoordinateValidator has grown a guard of its own and the
        // service-area check can be reconsidered.
        $this->assertTrue(
            \App\Services\Location\Coordinates\Adapters\CoordinateValidator::isValidPair(
                -82.458094358643,  // Tampa's longitude, read as a latitude
                27.948434712759,   // Tampa's latitude, read as a longitude
            ),
            'Range validation alone cannot detect this transposition'
        );
    }

    /**
     * @dataProvider realUnitedStatesPoints
     */
    public function test_the_service_area_check_admits_every_place_census_covers(
        float $lat,
        float $lng,
        string $where
    ): void {
        // Guards the envelope from the opposite failure. A check drawn too
        // tightly around the lower 48 would silently drop real properties, which
        // is worse than the transposition it was added to catch.
        Http::fake([self::ENDPOINT => Http::response([
            'result' => ['addressMatches' => [$this->match($lng, $lat)]],
        ])]);

        $this->assertTrue(
            $this->adapter()->resolve($this->tampa())->isResolved(),
            "{$where} is inside the Census service area and must be accepted"
        );
    }

    public static function realUnitedStatesPoints(): array
    {
        return [
            'Florida'             => [27.9506, -82.4572,  'Tampa, FL'],
            'Maine (east edge)'   => [44.8101, -66.9500,  'Lubec, ME'],
            'Alaska (north edge)' => [71.2906, -156.7886, 'Utqiagvik, AK'],
            'Aleutians'           => [52.9400, -173.1600, 'Adak, AK'],
            'Hawaii'              => [21.3069, -157.8583, 'Honolulu, HI'],
            'Puerto Rico'         => [18.4655, -66.1057,  'San Juan, PR'],
            'US Virgin Islands'   => [18.3358, -64.8963,  'Charlotte Amalie, VI'],
            'Guam'                => [13.4443, 144.7937,  'Hagatna, GU'],
            'American Samoa'      => [-14.2756, -170.7020, 'Pago Pago, AS'],
        ];
    }

    // ── geography agreement (G4) ────────────────────────────────────────────

    public function test_a_match_in_the_requested_state_and_zip_is_accepted(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch('FL', '33602')]))]);

        $this->assertTrue($this->adapter()->resolve($this->tampa())->isResolved());
    }

    public function test_a_match_in_a_different_state_is_rejected(): void
    {
        // The failure this check exists for: a street name that also exists in
        // another state resolves to a valid, in-range, entirely plausible
        // coordinate several hundred miles from the listing.
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch('GA', '33602')]))]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_state_mismatch', $result->reason);
    }

    public function test_a_match_in_a_different_zip_is_rejected(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch('FL', '33607')]))]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_zip_mismatch', $result->reason);
    }

    public function test_a_state_name_and_its_code_are_the_same_state(): void
    {
        // Both sides normalize through PropertyAddress, so this must not be a
        // mismatch. A check that rejected "Florida" against "FL" would be worse
        // than no check at all — it would fail on correct data.
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch('FL', '33602')]))]);

        $address = new PropertyAddress(
            address: '315 E Madison St', city: 'Tampa', state: 'Florida', zip: '33602'
        );

        $this->assertTrue($this->adapter()->resolve($address)->isResolved());
    }

    public function test_a_requested_zip_plus_four_matches_a_returned_zip5(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch('FL', '33602')]))]);

        $address = new PropertyAddress(
            address: '315 E Madison St', city: 'Tampa', state: 'FL', zip: '33602-1234'
        );

        $this->assertTrue(
            $this->adapter()->resolve($address)->isResolved(),
            'ZIP+4 identifies a delivery segment, not a different place'
        );
    }

    public function test_a_component_the_caller_did_not_supply_is_not_checked(): void
    {
        // No ZIP was claimed, so the provider cannot contradict one. The state
        // still has to agree.
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch('FL', '33607')]))]);

        $address = new PropertyAddress(address: '315 E Madison St', city: 'Tampa', state: 'FL');

        $this->assertTrue($this->adapter()->resolve($address)->isResolved());
    }

    /**
     * @dataProvider omittedComponents
     */
    public function test_a_missing_requested_component_is_rejected_with_its_own_reason(
        ?string $state,
        ?string $zip,
        string $case
    ): void {
        // Policy pinned deliberately (G4). Ten live matches were sampled and
        // every one populated both fields, while other components routinely
        // come back as ''. A missing component is therefore the provider
        // behaving unobserved, not the normal case degrading — and it gets its
        // own reason so a shape change shows up in telemetry as an anomaly
        // rather than as an epidemic of apparently wrong addresses.
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([$this->geoMatch($state, $zip)]))]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved(), $case);
        $this->assertSame('census_match_components_missing', $result->reason, $case);
    }

    public static function omittedComponents(): array
    {
        return [
            'state absent'  => [null, '33602', 'state key missing entirely'],
            'state empty'   => ['',   '33602', 'state returned as an empty string'],
            'zip absent'    => ['FL', null,    'zip key missing entirely'],
            'zip empty'     => ['FL', '',      'zip returned as an empty string'],
        ];
    }

    public function test_the_requested_zip_disambiguates_rather_than_taking_the_first_match(): void
    {
        // Two candidates, one in the requested ZIP. Dropping the other is not
        // "taking the first result" — it is using information the caller
        // supplied instead of guessing.
        Http::fake([self::ENDPOINT => Http::response($this->bodyOf([
            $this->geoMatch('FL', '33607', -82.500000, 27.960000),
            $this->geoMatch('FL', '33602', -82.458094358643, 27.948434712759),
        ]))]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertTrue($result->isResolved());
        $this->assertSame(27.948434712759, $result->latitude);
    }

    // ── ambiguity ───────────────────────────────────────────────────────────

    public function test_two_materially_different_matches_are_refused(): void
    {
        // The live 1-Broadway case: two matches, two ZIPs, ~130 m apart.
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => [
            $this->match(-74.014179491274, 40.704749136067, '1 BROADWAY, NEW YORK, NY, 10004'),
            $this->match(-74.013277270642, 40.705945753181, '1 BROADWAY, NEW YORK, NY, 10006'),
        ]]])]);

        $result = $this->adapter()->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('census_ambiguous_match', $result->reason);
    }

    public function test_duplicate_matches_for_one_place_still_resolve(): void
    {
        // Same place listed twice — a metre apart, far inside the tolerance.
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => [
            $this->match(-82.458094358643, 27.948434712759),
            $this->match(-82.458095358643, 27.948435712759),
        ]]])]);

        $this->assertTrue($this->adapter()->resolve($this->tampa())->isResolved());
    }

    // ── faults ──────────────────────────────────────────────────────────────

    public function test_a_server_error_is_a_fault(): void
    {
        Http::fake([self::ENDPOINT => Http::response('gateway blew up', 502)]);

        $this->expectException(CoordinateProviderUnavailable::class);

        $this->adapter()->resolve($this->tampa());
    }

    public function test_rate_limiting_is_a_fault(): void
    {
        Http::fake([self::ENDPOINT => Http::response('slow down', 429)]);

        $this->expectException(CoordinateProviderUnavailable::class);

        $this->adapter()->resolve($this->tampa());
    }

    public function test_a_body_without_the_documented_shape_is_a_fault(): void
    {
        // An error page served with a 200 is not an answer, and must not be
        // cached for a month as though the address did not exist.
        Http::fake([self::ENDPOINT => Http::response('<html>maintenance</html>', 200)]);

        $this->expectException(CoordinateProviderUnavailable::class);

        $this->adapter()->resolve($this->tampa());
    }

    public function test_a_transport_failure_is_a_fault(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(CoordinateProviderUnavailable::class);

        $this->adapter()->resolve($this->tampa());
    }

    public function test_a_fault_carries_the_provider_id(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 503)]);

        try {
            $this->adapter()->resolve($this->tampa());
            $this->fail('Expected a fault');
        } catch (CoordinateProviderUnavailable $e) {
            $this->assertSame('us_census', $e->providerId);
        }
    }

    // ── caching ─────────────────────────────────────────────────────────────

    public function test_a_match_is_cached_rather_than_re_requested(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->adapter()->resolve($this->tampa());
        $second = $this->adapter()->resolve($this->tampa());

        Http::assertSentCount(1);
        $this->assertTrue($second->isResolved());
        $this->assertSame(27.948434712759, $second->latitude);
    }

    public function test_every_unit_in_a_building_shares_one_provider_call(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->adapter()->resolve($this->tampa(unit: 'Unit 4A'));
        $this->adapter()->resolve($this->tampa(unit: 'Unit 4B'));
        $this->adapter()->resolve($this->tampa());

        Http::assertSentCount(1);
    }

    public function test_a_definitive_miss_is_cached(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        $this->adapter()->resolve($this->tampa());
        $second = $this->adapter()->resolve($this->tampa());

        Http::assertSentCount(1);
        $this->assertSame('census_no_match', $second->reason);
    }

    public function test_a_fault_is_never_cached(): void
    {
        // The property that matters most: one bad minute must not teach the
        // cache that a real address does not exist.
        //
        // One sequenced stub rather than two Http::fake() calls — a second
        // fake() merges into the first rather than replacing it, so the 502
        // would answer both requests and the test would pass for the wrong
        // reason (or, as it did first time round, fail for the wrong one).
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push('', 502)
                ->push($this->matchBody(), 200),
        ]);

        try {
            $this->adapter()->resolve($this->tampa());
            $this->fail('The first call must fault');
        } catch (CoordinateProviderUnavailable) {
            // expected
        }

        $this->assertTrue(
            $this->adapter()->resolve($this->tampa())->isResolved(),
            'The recovered service must be asked again'
        );

        Http::assertSentCount(2);
    }

    public function test_a_cached_result_is_stamped_when_it_is_returned(): void
    {
        // The cache holds the provider's answer, not a result object — replaying
        // a stored resolvedAt would claim a month-old lookup just happened.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->adapter()->resolve($this->tampa());
        $second = $this->adapter()->resolve($this->tampa());

        $this->assertNotNull($second->resolvedAt);
        $this->assertLessThanOrEqual(2, abs(time() - $second->resolvedAt->getTimestamp()));
    }

    // ── the resolver keeps its never-throw promise ──────────────────────────

    public function test_a_faulting_rung_does_not_escape_the_resolver(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 502)]);

        $result = (new PropertyCoordinateResolver([$this->adapter()]))->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('provider_unavailable', $result->reason);
    }

    public function test_a_fault_is_distinguishable_from_a_miss_at_the_resolver(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        $missed = (new PropertyCoordinateResolver([$this->adapter()]))->resolve($this->tampa());

        $this->assertSame('no_adapter_resolved', $missed->reason);
    }

    public function test_a_faulting_rung_does_not_stop_a_later_rung_answering(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 502)]);

        $resolver = new PropertyCoordinateResolver([
            $this->adapter(),
            new class () implements \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface {
                public function providerId(): string { return 'later_rung'; }
                public function source(): CoordinateSource { return CoordinateSource::Centroid; }
                public function requiresNetwork(): bool { return false; }
                public function isAvailable(): bool { return true; }

                public function resolve(PropertyAddress $address): \App\Services\Location\Coordinates\PropertyCoordinateResult
                {
                    return \App\Services\Location\Coordinates\PropertyCoordinateResult::resolved(
                        27.9506, -82.4572, CoordinatePrecision::ZipCentroid, CoordinateSource::Centroid, 'later_rung'
                    );
                }
            },
        ]);

        $this->assertSame('later_rung', $resolver->resolve($this->tampa())->provider);
    }

    public function test_a_local_rung_short_circuits_before_census_is_asked(): void
    {
        // The whole point of putting local sources ahead of the network: a
        // coordinate we already have must cost nothing. Asserted on the socket,
        // not on the returned provider id — a resolver that called Census and
        // then discarded the answer would satisfy the latter.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $resolver = new PropertyCoordinateResolver([
            new class () implements \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface {
                public function providerId(): string { return 'existing_coordinates'; }
                public function source(): CoordinateSource { return CoordinateSource::Existing; }
                public function requiresNetwork(): bool { return false; }
                public function isAvailable(): bool { return true; }

                public function resolve(PropertyAddress $address): \App\Services\Location\Coordinates\PropertyCoordinateResult
                {
                    return \App\Services\Location\Coordinates\PropertyCoordinateResult::resolved(
                        27.9506, -82.4572, CoordinatePrecision::Rooftop,
                        CoordinateSource::Existing, 'existing_coordinates'
                    );
                }
            },
            $this->adapter(),
        ]);

        $result = $resolver->resolve($this->tampa());

        Http::assertNothingSent();
        $this->assertSame('existing_coordinates', $result->provider);
    }

    public function test_the_catch_covers_adapter_resolution_and_not_availability(): void
    {
        // The fault catch must not widen into a blanket try/catch around the
        // loop. An adapter whose isAvailable() throws is a programming error —
        // the interface requires that method to be answerable without a network
        // call — and swallowing it would hide the bug rather than degrade a
        // provider outage.
        $resolver = new PropertyCoordinateResolver([
            new class () implements \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface {
                public function providerId(): string { return 'broken_availability'; }
                public function source(): CoordinateSource { return CoordinateSource::Geocoder; }
                public function requiresNetwork(): bool { return true; }
                public function isAvailable(): bool { throw new \LogicException('isAvailable must not throw'); }

                public function resolve(PropertyAddress $address): \App\Services\Location\Coordinates\PropertyCoordinateResult
                {
                    return \App\Services\Location\Coordinates\PropertyCoordinateResult::unresolved('unused');
                }
            },
        ]);

        $this->expectException(\LogicException::class);

        $resolver->resolve($this->tampa());
    }

    public function test_a_resolver_carrying_the_census_rung_is_not_local_only(): void
    {
        $this->assertFalse(
            (new PropertyCoordinateResolver([$this->adapter()]))->isLocalOnly()
        );
    }
}
