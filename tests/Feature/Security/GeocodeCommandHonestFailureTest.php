<?php

namespace Tests\Feature\Security;

use App\Console\Commands\GeocodeSelleryLandlordListings;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 0 item 1 — "Geocoding → an honest NOT_FOUND, never a silent null."
 *
 * `GeocodeSelleryLandlordListings::geocode()` used to answer `null` for four unrelated
 * outcomes: the address is genuinely unknown; Google returned a 5xx; the body was
 * malformed; and **Google rejected our credential**. The operator saw one `FAILED` line
 * for all of them. That is how a dead API key comes to look like a street of bad
 * addresses — and it is the same conflation that let the 2026-07-05 incident run for six
 * days before anyone read the responses.
 *
 * Note especially `it_reports_a_rejected_credential_rather_than_a_missing_address`:
 * Google answers an invalid key with **HTTP 200**, so status code alone cannot tell the
 * two apart. The in-body `status` must be read (SIA-D32).
 */
class GeocodeCommandHonestFailureTest extends TestCase
{
    private function bindClientReturning(int $status, string $body): void
    {
        $stack = HandlerStack::create(
            static fn () => Create::promiseFor(new Response($status, [], $body)),
        );

        $this->app->instance(ClientInterface::class, new Client(['handler' => $stack]));
    }

    private function bindClientThrowing(): void
    {
        $stack = HandlerStack::create(static function () {
            throw new RuntimeException('connection refused');
        });

        $this->app->instance(ClientInterface::class, new Client(['handler' => $stack]));
    }

    private function geocode(string $address = '123 Main St'): array
    {
        $method = new ReflectionMethod(GeocodeSelleryLandlordListings::class, 'geocode');
        $method->setAccessible(true);

        return $method->invoke(app(GeocodeSelleryLandlordListings::class), $address, 'a-key');
    }

    /** @test */
    public function it_reports_ok_and_the_coordinates_on_a_successful_lookup(): void
    {
        $this->bindClientReturning(200, json_encode([
            'status'  => 'OK',
            'results' => [[
                'geometry'          => ['location' => ['lat' => 27.9506, 'lng' => -82.4572]],
                'place_id'          => 'ChIJ-test',
                'formatted_address' => '123 Main St, Tampa, FL 33602, USA',
            ]],
        ]));

        $outcome = $this->geocode();

        $this->assertSame('ok', $outcome['status']);
        $this->assertSame('27.9506', $outcome['data']['property_lat']);
        $this->assertSame('ChIJ-test', $outcome['data']['google_place_id']);
    }

    /** @test */
    public function it_reports_not_found_for_an_address_google_does_not_know(): void
    {
        $this->bindClientReturning(200, '{"status":"ZERO_RESULTS","results":[]}');

        $this->assertSame('not_found', $this->geocode()['status']);
    }

    /** @test */
    public function it_reports_a_rejected_credential_rather_than_a_missing_address(): void
    {
        // The load-bearing case. HTTP 200 + REQUEST_DENIED. Under the old code this was
        // indistinguishable from ZERO_RESULTS: both answered null, both printed FAILED.
        $this->bindClientReturning(200, '{"status":"REQUEST_DENIED","error_message":"The provided API key is invalid."}');

        $this->assertSame('credential_rejected', $this->geocode()['status']);
    }

    /** @test */
    public function it_reports_a_rejected_credential_when_the_quota_is_exhausted(): void
    {
        $this->bindClientReturning(200, '{"status":"OVER_QUERY_LIMIT"}');

        $this->assertSame('credential_rejected', $this->geocode()['status']);
    }

    /** @test */
    public function it_reports_an_http_error_distinctly(): void
    {
        $this->bindClientReturning(503, 'upstream unavailable');

        $this->assertSame('http_error', $this->geocode()['status']);
    }

    /** @test */
    public function it_reports_a_transport_error_distinctly(): void
    {
        $this->bindClientThrowing();

        $this->assertSame('transport_error', $this->geocode()['status']);
    }

    /** @test */
    public function every_failure_mode_is_distinguishable_from_every_other(): void
    {
        // The whole point: four failures, four names. If any two collapse, the operator
        // cannot tell a dead key from a bad address, which is the defect this closes.
        $statuses = [];

        $this->bindClientReturning(200, '{"status":"ZERO_RESULTS","results":[]}');
        $statuses[] = $this->geocode()['status'];

        $this->bindClientReturning(200, '{"status":"REQUEST_DENIED"}');
        $statuses[] = $this->geocode()['status'];

        $this->bindClientReturning(500, '');
        $statuses[] = $this->geocode()['status'];

        $this->bindClientThrowing();
        $statuses[] = $this->geocode()['status'];

        $this->assertSame($statuses, array_unique($statuses), 'Failure modes collapsed: ' . implode(', ', $statuses));
        $this->assertNotContains('ok', $statuses);
    }
}
