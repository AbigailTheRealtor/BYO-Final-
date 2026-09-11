<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\Guards\ProviderCircuitBreaker;
use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;
use App\Services\Location\Lookup\AddressLookupService;
use App\Services\Offers\ImportantPlacesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What a failed lookup must NOT do.
 *
 * The stored coordinate is the thing being protected here. A Radius Search
 * centre and an Important Place pin are saved in the listing's own blob and are
 * read back on every reload without any provider being consulted — that is the
 * whole design. So the dangerous failure is not "the lookup did not work
 * today"; it is "the lookup did not work today and took the coordinate the user
 * saved last week with it".
 *
 * Every case below drives a real failure mode — an outage, a spent budget, an
 * open circuit, a no-match — and then asserts that the previously saved value
 * is exactly as it was. The rule the assertions encode is simple and absolute:
 * a stored coordinate is only ever replaced by a SUCCESSFUL resolution, and
 * nothing else may write to it.
 */
class AddressLookupFailurePostureTest extends TestCase
{
    private const ENDPOINT = 'geocoding.geo.census.gov/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('census_geocoder.enabled', true);
    }

    private function service(): AddressLookupService
    {
        return new AddressLookupService();
    }

    /** A radius search that is already stored, in the canonical shape. */
    private function storedRadius(): array
    {
        return [
            'address'      => '315 Madison St Tampa FL 33602',
            'lat'          => 27.948434712759,
            'lng'          => -82.458094358643,
            'radius_miles' => 5,
        ];
    }

    /**
     * The browser's own rule, stated as a function so the test asserts the
     * BEHAVIOUR rather than a copy of the JavaScript: a failed lookup returns
     * the stored entry untouched, and a successful one replaces it.
     */
    private function applyLookupToStoredEntry(array $stored, string $typed): array
    {
        $result = $this->service()->lookup($typed);

        if (! $result->ok) {
            return $stored;
        }

        return [
            'address'      => $result->address,
            'lat'          => $result->latitude,
            'lng'          => $result->longitude,
            'radius_miles' => $stored['radius_miles'],
        ];
    }

    // ── provider failures ───────────────────────────────────────────────────

    public function test_a_provider_outage_leaves_a_stored_radius_untouched(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 503)]);

        $before = $this->storedRadius();
        $after  = $this->applyLookupToStoredEntry($before, '1 New Address Rd, Tampa, FL 33602');

        $this->assertSame($before, $after);
    }

    public function test_a_no_match_leaves_a_stored_radius_untouched(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);

        $before = $this->storedRadius();

        $this->assertSame($before, $this->applyLookupToStoredEntry($before, '999999 Nowhere Rd, Tampa, FL 33602'));
    }

    public function test_a_connection_failure_leaves_a_stored_radius_untouched(): void
    {
        Http::fake([self::ENDPOINT => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $before = $this->storedRadius();

        $this->assertSame($before, $this->applyLookupToStoredEntry($before, '1 New Address Rd, Tampa, FL 33602'));
    }

    public function test_an_important_place_keeps_its_coordinate_when_a_lookup_fails(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 500)]);

        $row = [
            'type'           => 'School',
            'type_other'     => '',
            'address'        => '315 Madison St Tampa FL 33602',
            'lat'            => 27.948434712759,
            'lng'            => -82.458094358643,
            'distance_pref'  => 'miles',
            'distance_value' => 1.0,
            'travel_mode'    => 'driving',
        ];

        $result = $this->service()->lookup('1 Unresolvable Rd, Tampa, FL 33602');
        $this->assertFalse($result->ok);

        // The row is untouched, and still survives normalization unchanged —
        // so a save after a failed lookup persists the good coordinate rather
        // than a null.
        $normalized = (new ImportantPlacesService())->normalize([$row]);

        $this->assertCount(1, $normalized);
        $this->assertSame(27.948434712759, $normalized[0]['lat']);
        $this->assertSame(-82.458094358643, $normalized[0]['lng']);
    }

    // ── the guards, reached through this seam ───────────────────────────────

    public function test_a_spent_budget_is_a_safe_failure_and_sends_nothing(): void
    {
        config()->set('census_geocoder.hourly_cap', 1);
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);

        // Spend the hour's single allowance.
        $this->service()->lookup('315 E Madison St, Tampa, FL 33602');
        Http::assertSentCount(1);

        $before = $this->storedRadius();
        $after  = $this->applyLookupToStoredEntry($before, '400 N Ashley Dr, Tampa, FL 33602');

        $this->assertSame($before, $after);
        // Still one: the second lookup was refused before a request was made.
        Http::assertSentCount(1);
    }

    public function test_an_open_circuit_is_a_safe_failure_and_sends_nothing(): void
    {
        $breaker = new ProviderCircuitBreaker('us_census', 5, 300, 600);

        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFault();
        }
        $this->assertTrue($breaker->isOpen());

        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);

        $before = $this->storedRadius();
        $after  = $this->applyLookupToStoredEntry($before, '400 N Ashley Dr, Tampa, FL 33602');

        $this->assertSame($before, $after);
        Http::assertNothingSent();
    }

    public function test_the_budget_is_shared_with_property_resolution_and_not_a_second_allowance(): void
    {
        // One ceiling for the provider, counted across every caller. A lookup
        // ladder with its own budget would double the traffic the cap was set
        // to bound while both halves reported themselves compliant.
        config()->set('census_geocoder.hourly_cap', 10);

        $budget = new ProviderRequestBudget('us_census', 10, null);
        $before = $budget->spent();

        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);
        $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

        $after = $budget->spent();

        $this->assertGreaterThan($before['hourly'], $after['hourly']);
    }

    // ── no invented location, ever ──────────────────────────────────────────

    public function test_no_failure_mode_produces_a_placeholder_coordinate(): void
    {
        $failures = [
            'outage'      => Http::response('', 500),
            'no_match'    => Http::response(['result' => ['addressMatches' => []]]),
            'malformed'   => Http::response('not json at all'),
            'empty_body'  => Http::response(''),
        ];

        foreach ($failures as $label => $stub) {
            Cache::flush();
            Http::fake([self::ENDPOINT => $stub]);

            $result = $this->service()->lookup('315 E Madison St, Tampa, FL 33602');

            $this->assertFalse($result->ok, "{$label} should not resolve");
            $this->assertNull($result->latitude, "{$label} produced a latitude");
            $this->assertNull($result->longitude, "{$label} produced a longitude");
        }
    }
}
