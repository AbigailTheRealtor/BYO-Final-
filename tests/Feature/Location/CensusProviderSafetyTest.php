<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\Exceptions\CoordinateProviderUnavailable;
use App\Services\Location\Coordinates\Guards\CoordinateProviderTelemetry;
use App\Services\Location\Coordinates\Guards\ProviderCircuitBreaker;
use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * G4 operational safety: caps, breaker, telemetry.
 *
 * The claim that matters most here is not that the guards work in isolation but
 * that they only ever cost us the network rung. A Census outage, or our own
 * rationing of it, must never stop a coordinate we already hold from being
 * returned — which is the entire reason the network rung sits below the local
 * ones. Several tests below assert exactly that, because it is the property
 * most easily broken by a guard placed one level too high.
 */
class CensusProviderSafetyTest extends TestCase
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

    private function tampa(string $street = '315 E Madison St'): PropertyAddress
    {
        return new PropertyAddress(
            address: $street, city: 'Tampa', county: 'Hillsborough', state: 'FL', zip: '33602'
        );
    }

    private function matchBody(): array
    {
        return ['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
            'addressComponents' => ['state' => 'FL', 'zip' => '33602'],
            'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
        ]]]];
    }

    private function noMatchBody(): array
    {
        return ['result' => ['addressMatches' => []]];
    }

    /** A local rung that always answers, standing in for Existing/Bridge. */
    private function localRung(): CoordinateProviderAdapterInterface
    {
        return new class () implements CoordinateProviderAdapterInterface {
            public function providerId(): string { return 'existing_coordinates'; }
            public function source(): CoordinateSource { return CoordinateSource::Existing; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return true; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                return PropertyCoordinateResult::resolved(
                    27.9506, -82.4572, CoordinatePrecision::Rooftop,
                    CoordinateSource::Existing, 'existing_coordinates'
                );
            }
        };
    }

    // ── rate limiting ───────────────────────────────────────────────────────

    public function test_the_hourly_cap_blocks_the_outbound_call(): void
    {
        config()->set('census_geocoder.hourly_cap', 2);
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        // Distinct addresses so the cache cannot mask the cap.
        $this->adapter()->resolve($this->tampa('1 First St'));
        $this->adapter()->resolve($this->tampa('2 Second St'));

        try {
            $this->adapter()->resolve($this->tampa('3 Third St'));
            $this->fail('The third call must be refused');
        } catch (CoordinateProviderUnavailable $e) {
            $this->assertSame('provider_hourly_cap_reached', $e->reason);
            $this->assertSame(CoordinateProviderUnavailable::KIND_RATE_LIMITED, $e->kind);
        }

        Http::assertSentCount(2);
    }

    public function test_the_daily_cap_blocks_the_outbound_call(): void
    {
        config()->set('census_geocoder.hourly_cap', 1000);
        config()->set('census_geocoder.daily_cap', 1);
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        $this->adapter()->resolve($this->tampa('1 First St'));

        try {
            $this->adapter()->resolve($this->tampa('2 Second St'));
            $this->fail('The second call must be refused');
        } catch (CoordinateProviderUnavailable $e) {
            $this->assertSame('provider_daily_cap_reached', $e->reason);
        }

        Http::assertSentCount(1);
    }

    public function test_a_rate_limit_block_is_not_a_provider_fault(): void
    {
        // Rationing is our decision. Counting it against the breaker would let
        // the breaker trip on our own policy and then stay open blaming Census.
        config()->set('census_geocoder.hourly_cap', 0);
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        for ($i = 0; $i < 10; $i++) {
            try {
                $this->adapter()->resolve($this->tampa("{$i} Some St"));
            } catch (CoordinateProviderUnavailable $e) {
                $this->assertFalse($e->isProviderFault());
            }
        }

        $breaker = new ProviderCircuitBreaker('us_census', 5, 300, 600);
        $this->assertFalse($breaker->isOpen(), 'Our own rationing must not open the circuit');
    }

    public function test_a_cache_hit_is_not_counted_against_the_budget(): void
    {
        // A budget that counted cache hits would ration our own memory.
        config()->set('census_geocoder.hourly_cap', 1);
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->adapter()->resolve($this->tampa());

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->adapter()->resolve($this->tampa())->isResolved());
        }

        Http::assertSentCount(1);
    }

    public function test_local_rungs_still_resolve_when_the_cap_is_reached(): void
    {
        config()->set('census_geocoder.hourly_cap', 0);
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $resolver = new PropertyCoordinateResolver([$this->localRung(), $this->adapter()]);
        $result   = $resolver->resolve($this->tampa());

        $this->assertTrue($result->isResolved());
        $this->assertSame('existing_coordinates', $result->provider);
        Http::assertNothingSent();
    }

    public function test_a_capped_census_rung_does_not_crash_the_resolver(): void
    {
        config()->set('census_geocoder.hourly_cap', 0);
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $result = (new PropertyCoordinateResolver([$this->adapter()]))->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('provider_unavailable', $result->reason);
    }

    public function test_a_null_cap_means_no_ceiling(): void
    {
        // `(int) null` is 0, and a cap of 0 blocks everything — the inverse of
        // "unset". config/census_geocoder.php guards that; this pins it.
        config()->set('census_geocoder.hourly_cap', null);
        config()->set('census_geocoder.daily_cap', null);
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        for ($i = 0; $i < 5; $i++) {
            $this->adapter()->resolve($this->tampa("{$i} Some St"));
        }

        Http::assertSentCount(5);
    }

    // ── circuit breaker ─────────────────────────────────────────────────────

    public function test_repeated_faults_open_the_circuit(): void
    {
        config()->set('census_geocoder.breaker.failure_threshold', 3);
        Http::fake([self::ENDPOINT => Http::response('', 502)]);

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->adapter()->resolve($this->tampa("{$i} Some St"));
            } catch (CoordinateProviderUnavailable) {
                // expected
            }
        }

        $this->assertTrue((new ProviderCircuitBreaker('us_census', 3, 300, 600))->isOpen());
    }

    public function test_an_open_circuit_sends_nothing(): void
    {
        config()->set('census_geocoder.breaker.failure_threshold', 2);
        Http::fake([self::ENDPOINT => Http::response('', 502)]);

        foreach (['a', 'b'] as $street) {
            try {
                $this->adapter()->resolve($this->tampa("1 {$street} St"));
            } catch (CoordinateProviderUnavailable) {
                // expected
            }
        }

        Http::assertSentCount(2);

        try {
            $this->adapter()->resolve($this->tampa('1 c St'));
            $this->fail('An open circuit must refuse');
        } catch (CoordinateProviderUnavailable $e) {
            $this->assertSame(CoordinateProviderUnavailable::KIND_CIRCUIT_OPEN, $e->kind);
            $this->assertSame('provider_circuit_open', $e->reason);
        }

        // Still two: the third attempt never reached the network.
        Http::assertSentCount(2);
    }

    public function test_local_rungs_are_unaffected_by_an_open_circuit(): void
    {
        // The property the whole ladder ordering exists to guarantee.
        (new ProviderCircuitBreaker('us_census', 1, 300, 600))->recordFault();

        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $resolver = new PropertyCoordinateResolver([$this->localRung(), $this->adapter()]);
        $result   = $resolver->resolve($this->tampa());

        $this->assertTrue($result->isResolved());
        $this->assertSame('existing_coordinates', $result->provider);
        Http::assertNothingSent();
    }

    public function test_a_success_clears_accumulated_faults(): void
    {
        // A provider that fails once in a while and succeeds the rest of the
        // time is not broken, and must not accumulate its way to an outage.
        $breaker = new ProviderCircuitBreaker('us_census', 3, 300, 600);

        $breaker->recordFault();
        $breaker->recordFault();
        $this->assertSame(2, $breaker->faultCount());

        $breaker->recordSuccess();
        $this->assertSame(0, $breaker->faultCount());

        $breaker->recordFault();
        $this->assertFalse($breaker->isOpen(), 'One fault after a success must not open a 3-fault breaker');
    }

    public function test_the_circuit_closes_after_its_cooldown(): void
    {
        $breaker = new ProviderCircuitBreaker('us_census', 1, 300, 600);
        $breaker->recordFault();

        $this->assertTrue($breaker->isOpen());

        // The open state is a cache entry with the cooldown as its TTL, so
        // expiry is what closes it. Travelling past the TTL is the honest way
        // to test that rather than reaching in and deleting the key.
        $this->travel(301)->seconds();

        $this->assertFalse($breaker->isOpen(), 'The circuit must close when the cooldown expires');
    }

    public function test_a_closed_circuit_does_not_reopen_on_a_single_fault(): void
    {
        // The fault counter is cleared when the circuit opens. Without that it
        // would still sit at the threshold on reopening, and a fixed cooldown
        // would become a permanent outage.
        $breaker = new ProviderCircuitBreaker('us_census', 2, 300, 600);
        $breaker->recordFault();
        $breaker->recordFault();
        $this->assertTrue($breaker->isOpen());

        $this->travel(301)->seconds();
        $this->assertFalse($breaker->isOpen());

        $breaker->recordFault();
        $this->assertFalse($breaker->isOpen(), 'One fault must not immediately reopen the circuit');
    }

    public function test_a_no_match_is_not_a_fault(): void
    {
        // The provider working correctly and saying no is not evidence of ill
        // health, and must never contribute to an outage.
        config()->set('census_geocoder.breaker.failure_threshold', 2);
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        foreach (range(1, 5) as $i) {
            $this->adapter()->resolve($this->tampa("{$i} Some St"));
        }

        $this->assertFalse((new ProviderCircuitBreaker('us_census', 2, 300, 600))->isOpen());
    }

    // ── budget unit behaviour ───────────────────────────────────────────────

    public function test_the_budget_reports_which_ceiling_stopped_it(): void
    {
        $budget = new ProviderRequestBudget('test_provider', 2, 10);

        $this->assertNull($budget->blockedReason());
        $budget->recordRequest();
        $budget->recordRequest();

        $this->assertSame('provider_hourly_cap_reached', $budget->blockedReason());
        $this->assertSame(['hourly' => 2, 'daily' => 2], $budget->spent());
    }

    public function test_budgets_are_tracked_per_provider(): void
    {
        // The guards are provider-neutral so a future commercial adapter wraps
        // identically. Sharing a counter would make one provider's traffic
        // silently ration another's.
        $census = new ProviderRequestBudget('us_census', 1, 100);
        $other  = new ProviderRequestBudget('some_other_provider', 1, 100);

        $census->recordRequest();

        $this->assertSame('provider_hourly_cap_reached', $census->blockedReason());
        $this->assertNull($other->blockedReason());
    }

    // ── telemetry ───────────────────────────────────────────────────────────

    public function test_a_success_is_recorded(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $events = $this->captureTelemetry(fn () => $this->adapter()->resolve($this->tampa()));

        $event = $this->eventWithOutcome($events, CoordinateProviderTelemetry::OUTCOME_SUCCESS);

        $this->assertSame('us_census', $event['provider']);
        $this->assertSame('interpolated', $event['precision']);
        $this->assertSame('miss', $event['cache']);
        $this->assertSame('closed', $event['circuit_state']);
        $this->assertIsInt($event['latency_ms']);
    }

    public function test_a_no_match_is_recorded(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->noMatchBody())]);

        $events = $this->captureTelemetry(fn () => $this->adapter()->resolve($this->tampa()));
        $event  = $this->eventWithOutcome($events, CoordinateProviderTelemetry::OUTCOME_NO_MATCH);

        $this->assertSame('census_no_match', $event['reason']);
        $this->assertNull($event['precision']);
    }

    public function test_a_provider_failure_is_recorded(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 503)]);

        $events = $this->captureTelemetry(function () {
            try {
                $this->adapter()->resolve($this->tampa());
            } catch (CoordinateProviderUnavailable) {
                // expected
            }
        });

        $event = $this->eventWithOutcome($events, CoordinateProviderTelemetry::OUTCOME_PROVIDER_FAILURE);

        $this->assertSame('provider_http_503', $event['reason']);
        $this->assertSame(CoordinateProviderUnavailable::KIND_FAULT, $event['kind']);
    }

    public function test_a_cache_hit_is_recorded(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);
        $this->adapter()->resolve($this->tampa());

        $events = $this->captureTelemetry(fn () => $this->adapter()->resolve($this->tampa()));

        $this->assertNotNull(
            $this->eventWithOutcome($events, CoordinateProviderTelemetry::OUTCOME_CACHE_HIT)
        );
    }

    public function test_a_rate_block_is_recorded(): void
    {
        config()->set('census_geocoder.hourly_cap', 0);
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $events = $this->captureTelemetry(function () {
            try {
                $this->adapter()->resolve($this->tampa());
            } catch (CoordinateProviderUnavailable) {
                // expected
            }
        });

        $event = $this->eventWithOutcome($events, CoordinateProviderTelemetry::OUTCOME_BLOCKED);

        $this->assertSame('provider_hourly_cap_reached', $event['reason']);
    }

    public function test_a_geography_rejection_is_recorded(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
            'addressComponents' => ['state' => 'GA', 'zip' => '33602'],
            'matchedAddress'    => 'SOMEWHERE ELSE',
        ]]]])]);

        $events = $this->captureTelemetry(fn () => $this->adapter()->resolve($this->tampa()));
        $event  = $this->eventWithOutcome($events, CoordinateProviderTelemetry::OUTCOME_REJECTED);

        $this->assertSame('census_state_mismatch', $event['reason']);
    }

    public function test_the_address_is_never_logged(): void
    {
        // A property address identifies a real place a real person is connected
        // to, and logs are shipped and retained far more casually than the
        // database is. Only an opaque digest may leave this class.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $events = $this->captureTelemetry(fn () => $this->adapter()->resolve($this->tampa()));

        $serialized = json_encode($events);

        foreach (['315', 'madison', 'Madison', 'Tampa', 'tampa', '33602'] as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $serialized,
                "Telemetry must not carry the address fragment '{$fragment}'"
            );
        }
    }

    public function test_the_address_hash_is_stable_and_one_way(): void
    {
        $hash = CoordinateProviderTelemetry::addressHash('315 madison st tampa fl 33602');

        $this->assertSame($hash, CoordinateProviderTelemetry::addressHash('315 madison st tampa fl 33602'));
        $this->assertNotSame($hash, CoordinateProviderTelemetry::addressHash('316 madison st tampa fl 33602'));
        $this->assertStringNotContainsString('madison', $hash);
    }

    // ── telemetry capture ───────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function captureTelemetry(callable $work): array
    {
        $events = [];

        Log::listen(function ($event) use (&$events) {
            if ($event->message === 'coordinate_provider') {
                $events[] = $event->context;
            }
        });

        $work();

        return $events;
    }

    /**
     * @param  list<array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private function eventWithOutcome(array $events, string $outcome): array
    {
        foreach ($events as $event) {
            if (($event['outcome'] ?? null) === $outcome) {
                return $event;
            }
        }

        $seen = implode(', ', array_map(
            static fn (array $e): string => (string) ($e['outcome'] ?? '?'),
            $events
        ));

        $this->fail("No '{$outcome}' event was recorded. Saw: [{$seen}]");
    }
}
