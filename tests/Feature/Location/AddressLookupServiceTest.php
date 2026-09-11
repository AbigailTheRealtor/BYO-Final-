<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\Adapters\AddressPointCoordinateAdapter;
use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Adapters\LookupCoordinateLadder;
use App\Services\Location\Coordinates\Adapters\StandardCoordinateLadder;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResolverInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use App\Services\Location\Lookup\AddressLookupQuery;
use App\Services\Location\Lookup\AddressLookupResult;
use App\Services\Location\Lookup\AddressLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The free-text lookup seam: what it resolves, what it refuses, and what it
 * refuses to invent.
 *
 * NO REAL REQUEST CAN ESCAPE THIS SUITE. `Tests\Support\Http\GuardedPendingRequest`
 * is installed for the whole run and turns any unfaked request into a
 * StrayHttpRequestException rather than letting it reach the US Census Bureau,
 * so a test here that forgot to fake fails loudly instead of quietly spending a
 * live provider's budget. Every response fixture below is the shape captured
 * from the live service on 2026-08-10 and already pinned by
 * {@see CensusGeocoderAdapterTest}.
 */
class AddressLookupServiceTest extends TestCase
{
    private const ENDPOINT = 'geocoding.geo.census.gov/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('census_geocoder.enabled', true);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function service(): AddressLookupService
    {
        return new AddressLookupService();
    }

    /** One match, in the exact shape the live service returns. */
    private function matchBody(
        float $lng = -82.458094358643,
        float $lat = 27.948434712759,
        string $matched = '315 MADISON ST, TAMPA, FL, 33602'
    ): array {
        return [
            'result' => [
                'addressMatches' => [[
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
                ]],
            ],
        ];
    }

    private function noMatchBody(): array
    {
        return ['result' => ['addressMatches' => []]];
    }

    // ── the splitter ────────────────────────────────────────────────────────

    public function test_it_splits_a_full_address_into_its_parts(): void
    {
        $address = AddressLookupQuery::parse('315 E Madison St, Tampa, FL 33602');

        $this->assertSame('315 E Madison St', $address->address);
        $this->assertSame('Tampa', $address->city);
        $this->assertSame('fl', $address->normalizedState());
        $this->assertSame('33602', $address->normalizedZip5());
    }

    public function test_it_splits_an_address_with_no_commas_in_the_locality_tail(): void
    {
        $address = AddressLookupQuery::parse('315 E Madison St, Tampa FL 33602');

        $this->assertSame('315 E Madison St', $address->address);
        $this->assertSame('Tampa', $address->city);
        $this->assertSame('fl', $address->normalizedState());
        $this->assertSame('33602', $address->normalizedZip5());
    }

    public function test_it_reads_a_spelled_out_state(): void
    {
        $address = AddressLookupQuery::parse('11687 Oxford Street North, Largo, Florida 33778');

        $this->assertSame('11687 Oxford Street North', $address->address);
        $this->assertSame('Largo', $address->city);
        $this->assertSame('fl', $address->normalizedState());
        $this->assertSame('33778', $address->normalizedZip5());
    }

    public function test_it_reads_a_two_word_state(): void
    {
        // A single-word test would read this as the city "New" and the state
        // "York", which is not a state at all.
        $address = AddressLookupQuery::parse('1 Broadway, Albany, New York 12207');

        $this->assertSame('Albany', $address->city);
        $this->assertSame('ny', $address->normalizedState());
    }

    public function test_a_word_that_merely_looks_like_a_state_is_not_taken_as_one(): void
    {
        // "North" ends the street name. Folding it into a state would leave the
        // street truncated and the lookup pointed at the wrong place entirely.
        $address = AddressLookupQuery::parse('11687 Oxford Street North');

        $this->assertSame('11687 Oxford Street North', $address->address);
        $this->assertSame('', $address->city);
        $this->assertSame('', $address->normalizedState());
    }

    public function test_a_zip_plus_four_is_read_as_its_zip5(): void
    {
        $address = AddressLookupQuery::parse('315 E Madison St, Tampa, FL 33602-1234');

        $this->assertSame('33602', $address->normalizedZip5());
    }

    public function test_it_never_invents_a_locality(): void
    {
        $address = AddressLookupQuery::parse('123 Main Street');

        $this->assertSame('', $address->city);
        $this->assertSame('', $address->normalizedState());
        $this->assertSame('', $address->normalizedZip5());
        $this->assertFalse($address->hasMinimumForLookup());
    }

    // ── resolution ──────────────────────────────────────────────────────────

    public function test_a_valid_address_resolves_to_coordinates(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertTrue($result->ok);
        $this->assertSame(27.948434712759, $result->latitude);
        $this->assertSame(-82.458094358643, $result->longitude);
    }

    public function test_x_is_longitude_and_y_is_latitude_through_this_seam_too(): void
    {
        // The transposition this guards against is silent: both numbers stay in
        // range and the property simply moves to the far side of the planet.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertGreaterThan(20.0, $result->latitude);
        $this->assertLessThan(50.0, $result->latitude);
        $this->assertLessThan(0.0, $result->longitude);
    }

    public function test_the_matched_address_is_returned_for_display(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->service()->lookup('315 e madison st, tampa, fl 33602');

        // The provider's matched line, presented — not the raw text typed, and
        // not the lowercased normalized form the ladder compares on.
        $this->assertSame('315 Madison St Tampa FL 33602', $result->address);
    }

    public function test_the_precision_tier_is_carried_through_honestly(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        // Census interpolates along a street segment and reports no quality
        // field. Anything better than 'interpolated' here would be invented.
        $this->assertSame(CoordinatePrecision::Interpolated->value, $result->precision);
    }

    // ── refusals ────────────────────────────────────────────────────────────

    public function test_a_thin_address_is_refused_before_any_request_is_sent(): void
    {
        Http::fake();

        $result = $this->service()->lookup('11687 Oxford Street North');

        $this->assertFalse($result->ok);
        $this->assertSame('insufficient_address', $result->reason);
        Http::assertNothingSent();
    }

    public function test_empty_input_is_refused_before_any_request_is_sent(): void
    {
        Http::fake();

        $this->assertFalse($this->service()->lookup('   ')->ok);
        Http::assertNothingSent();
    }

    public function test_a_no_match_is_a_safe_failure(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        $result = $this->service()->lookup('999999 Nowhere Rd, Tampa, FL 33602');

        $this->assertFalse($result->ok);
        $this->assertNull($result->latitude);
        $this->assertNull($result->longitude);
    }

    public function test_a_provider_outage_is_a_safe_failure(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 500)]);

        $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertFalse($result->ok);
        $this->assertNull($result->latitude);
    }

    public function test_a_faulting_rung_is_a_safe_failure_and_never_an_exception(): void
    {
        $exploding = new class implements PropertyCoordinateResolverInterface {
            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                throw new RuntimeException('provider exploded');
            }
        };

        $result = (new AddressLookupService($exploding))->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertFalse($result->ok);
        $this->assertSame('lookup_error', $result->reason);
    }

    public function test_no_failure_ever_carries_a_coordinate(): void
    {
        // Zero, the centre of Florida and the map's current view are all
        // indistinguishable from a real answer once they are saved.
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        foreach (['11687 Oxford Street North', '999999 Nowhere Rd, Tampa, FL 33602', ''] as $input) {
            $result = $this->service()->lookup($input);

            $this->assertFalse($result->ok);
            $this->assertNull($result->latitude, "coordinate leaked for: {$input}");
            $this->assertNull($result->longitude, "coordinate leaked for: {$input}");
            $this->assertNull($result->address);
        }
    }

    public function test_every_failure_shows_the_same_actionable_sentence(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 500)]);

        $response = $this->service()->lookup('315 E Madison St, Tampa, FL 33602')->toResponse();

        $this->assertFalse($response['ok']);
        $this->assertSame(AddressLookupResult::FAILURE_MESSAGE, $response['message']);
        $this->assertStringContainsString('city, state, or ZIP', $response['message']);
    }

    // ── the ladder this seam is allowed to use ──────────────────────────────

    public function test_the_lookup_ladder_carries_no_property_record_rungs(): void
    {
        // The whole point of a separate ladder. ExistingCoordinatesAdapter and
        // BridgeMlsCoordinatesAdapter answer "where is THIS LISTING?" from a
        // record handle. A typed workplace address that arrived alongside a
        // listing id would otherwise resolve to the listing's own coordinate
        // and report complete success.
        foreach (LookupCoordinateLadder::adapters() as $adapter) {
            $this->assertNotSame(CoordinateSource::Existing, $adapter->source());
            $this->assertNotSame(CoordinateSource::Mls, $adapter->source());
        }
    }

    public function test_the_lookup_ladder_is_address_point_then_census(): void
    {
        $adapters = LookupCoordinateLadder::adapters();

        $this->assertCount(2, $adapters);
        $this->assertInstanceOf(AddressPointCoordinateAdapter::class, $adapters[0]);
        $this->assertInstanceOf(CensusGeocoderAdapter::class, $adapters[1]);
    }

    public function test_the_standard_listing_ladder_is_left_alone(): void
    {
        // This work adds a ladder; it does not re-order the one that resolves a
        // listing's own coordinate.
        $sources = array_map(
            static fn ($adapter) => $adapter->source(),
            StandardCoordinateLadder::adapters()
        );

        $this->assertSame(
            [
                CoordinateSource::Existing,
                CoordinateSource::Mls,
                CoordinateSource::AddressPoint,
                CoordinateSource::Geocoder,
            ],
            $sources
        );
    }

    // ── independence from Google ────────────────────────────────────────────

    public function test_lookup_works_with_no_google_credential_and_google_disabled(): void
    {
        config()->set('google_places.enabled', false);
        config()->set('services.google.places_key', null);

        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertTrue($result->ok);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_the_census_switch_is_respected(): void
    {
        // The provider gate is a live-traffic safety switch and this seam does
        // not get to override it. With it off the rung reports itself
        // unavailable and is skipped without being called.
        config()->set('census_geocoder.enabled', false);
        Http::fake();

        $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertFalse($result->ok);
        Http::assertNothingSent();
    }

    // ── caching, from the caller's side ─────────────────────────────────────

    public function test_a_repeated_lookup_costs_no_second_request(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $first  = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');
        $second = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $this->assertTrue($first->ok);
        $this->assertTrue($second->ok);
        $this->assertSame($first->latitude, $second->latitude);
        Http::assertSentCount(1);
    }

    public function test_two_spellings_of_one_address_share_one_request(): void
    {
        // The adapter caches on the normalized, unit-free line, so "North Main
        // Street" and "N Main St" are one address and one provider call.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->service()->lookup('315 East Madison Street, Tampa, FL 33602');
        $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        Http::assertSentCount(1);
    }
}
