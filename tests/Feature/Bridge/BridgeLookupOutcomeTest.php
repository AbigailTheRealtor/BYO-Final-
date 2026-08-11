<?php

namespace Tests\Feature\Bridge;

use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgeLookupResult;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Models\BridgeProperty;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * lookupByMlsNumber() — "we found nothing" vs "we could not ask".
 *
 * findByMlsNumber() returns null for both, which is why a user typing a valid
 * MLS number during a Bridge outage was told their listing does not exist. These
 * tests pin the distinction at the seam where it is decided, using real HTTP
 * fakes rather than a mocked API client, so they exercise the actual failure
 * classification in BridgeApiService rather than a stubbed version of it.
 */
class BridgeLookupOutcomeTest extends TestCase
{
    use DatabaseTransactions;

    private const CITY = 'PhpunitOutcomeCity';

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        // Credentials present by default; individual tests clear them.
        config(['bridge.dataset' => 'phpunit_dataset', 'bridge.token' => 'phpunit-token']);
    }

    private function service(): BridgeListingLookupService
    {
        return new BridgeListingLookupService(
            app(BridgeApiService::class),
            new BridgePropertyNormalizer(),
            new BridgePropertyCandidateAdapter(),
        );
    }

    private function apiRecord(string $key): array
    {
        return [
            'ListingKey'      => $key,
            'ListingId'       => $key . '-id',
            'StandardStatus'  => 'Active',
            'PropertyType'    => 'Residential',
            'ListPrice'       => 350000,
            'UnparsedAddress' => '123 Main St, ' . self::CITY . ', FL 33601',
            'City'            => self::CITY,
            'StateOrProvince' => 'FL',
            'PostalCode'      => '33601',
            'Latitude'        => 27.9506,
            'Longitude'       => -82.4572,
            'ModificationTimestamp' => '2026-08-01T12:00:00Z',
        ];
    }

    // ── Found ────────────────────────────────────────────────────────────────

    public function test_found_from_local_cache_without_any_http_call(): void
    {
        Http::fake();

        BridgeProperty::create([
            'listing_key' => 'PHPUNIT-OUTCOME-LOCAL',
            'listing_id'  => 'PHPUNIT-OUTCOME-LOCAL-id',
            'city'        => self::CITY,
            'latitude'    => 27.9506,
            'longitude'   => -82.4572,
        ]);

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-LOCAL-id');

        $this->assertTrue($result->isFound());
        $this->assertSame('PHPUNIT-OUTCOME-LOCAL', $result->candidate->listingKey);
        Http::assertNothingSent();
    }

    public function test_found_via_api_fallback(): void
    {
        Http::fake(['*' => Http::response(['value' => [$this->apiRecord('PHPUNIT-OUTCOME-API')]], 200)]);

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-API-id');

        $this->assertTrue($result->isFound());
        $this->assertSame('PHPUNIT-OUTCOME-API', $result->candidate->listingKey);
        $this->assertNull($result->failureReason);
    }

    // ── Genuinely not found ──────────────────────────────────────────────────

    public function test_successful_empty_response_is_not_found_not_unavailable(): void
    {
        Http::fake(['*' => Http::response(['value' => []], 200)]);

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-NOSUCH');

        $this->assertTrue($result->isNotFound());
        $this->assertFalse($result->isUnavailable());
        $this->assertNull($result->failureReason);
    }

    // ── Unavailable ──────────────────────────────────────────────────────────

    public function test_missing_credentials_is_unavailable_not_not_found(): void
    {
        config(['bridge.dataset' => null, 'bridge.token' => null]);
        Http::fake();

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-NOCONFIG');

        $this->assertTrue($result->isUnavailable());
        $this->assertFalse($result->isNotFound());
        $this->assertSame(BridgeApiService::FAILURE_NOT_CONFIGURED, $result->failureReason);
        Http::assertNothingSent();
    }

    /**
     * @dataProvider httpFailureProvider
     */
    public function test_http_failure_is_unavailable(int $status): void
    {
        Http::fake(['*' => Http::response('', $status)]);

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-HTTPFAIL');

        $this->assertTrue($result->isUnavailable(), "HTTP {$status} must not read as 'listing not found'");
        $this->assertSame(BridgeApiService::FAILURE_HTTP_ERROR, $result->failureReason);
    }

    public static function httpFailureProvider(): array
    {
        return [
            'unauthorized'  => [401],
            'forbidden'     => [403],
            'rate limited'  => [429],
            'server error'  => [500],
            'bad gateway'   => [502],
        ];
    }

    public function test_transport_failure_is_unavailable(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-TIMEOUT');

        $this->assertTrue($result->isUnavailable());
        $this->assertSame(BridgeApiService::FAILURE_TRANSPORT_ERROR, $result->failureReason);
    }

    // ── Malformed responses ──────────────────────────────────────────────────

    /**
     * A 200 whose body is not the expected shape is a successful call that
     * yielded nothing usable. Reporting "not found" is the honest answer: we
     * did reach the service and it told us about no listing.
     */
    public function test_malformed_but_successful_response_reads_as_not_found(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => 'shape'], 200)]);

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-MALFORMED');

        $this->assertTrue($result->isNotFound());
    }

    public function test_record_missing_listing_key_reads_as_not_found(): void
    {
        Http::fake(['*' => Http::response(['value' => [['ListingId' => 'PHPUNIT-OUTCOME-NOKEY']]], 200)]);

        $result = $this->service()->lookupByMlsNumber('PHPUNIT-OUTCOME-NOKEY');

        $this->assertTrue($result->isNotFound());
    }

    // ── Input guard ──────────────────────────────────────────────────────────

    /**
     * @dataProvider blankProvider
     */
    public function test_blank_input_is_invalid_and_sends_nothing(string $input): void
    {
        Http::fake();

        $result = $this->service()->lookupByMlsNumber($input);

        $this->assertTrue($result->isInvalidInput());
        Http::assertNothingSent();
    }

    public static function blankProvider(): array
    {
        return [
            'empty'      => [''],
            'spaces'     => ['   '],
            'tab/newline' => ["\t\n"],
        ];
    }

    // ── Failure state hygiene ────────────────────────────────────────────────

    /**
     * A failure recorded by one call must not colour the next one. Without the
     * reset at the top of fetchProperties(), a successful lookup following a
     * failed one would report itself unavailable.
     */
    public function test_a_prior_failure_does_not_leak_into_the_next_lookup(): void
    {
        $api     = app(BridgeApiService::class);
        $service = new BridgeListingLookupService(
            $api,
            new BridgePropertyNormalizer(),
            new BridgePropertyCandidateAdapter(),
        );

        // One sequence, so the second request genuinely gets the second response
        // (re-calling Http::fake() does not reliably displace an earlier stub).
        Http::fakeSequence()
            ->push('', 500)
            ->push(['value' => []], 200);

        $this->assertTrue($service->lookupByMlsNumber('PHPUNIT-OUTCOME-FIRST')->isUnavailable());
        $this->assertSame(BridgeApiService::FAILURE_HTTP_ERROR, $api->lastFailure());

        $second = $service->lookupByMlsNumber('PHPUNIT-OUTCOME-SECOND');

        $this->assertTrue($second->isNotFound(), 'stale failure state leaked into a later successful call');
        $this->assertNull($api->lastFailure());
    }

    /**
     * findByMlsNumber() is unchanged — Match Check depends on it returning a
     * plain nullable candidate, and nothing here may alter that.
     */
    public function test_find_by_mls_number_still_returns_a_nullable_candidate(): void
    {
        Http::fake(['*' => Http::response(['value' => [$this->apiRecord('PHPUNIT-OUTCOME-COMPAT')]], 200)]);

        $candidate = $this->service()->findByMlsNumber('PHPUNIT-OUTCOME-COMPAT-id');

        $this->assertNotNull($candidate);
        $this->assertNotInstanceOf(BridgeLookupResult::class, $candidate);
        $this->assertSame('PHPUNIT-OUTCOME-COMPAT', $candidate->listingKey);
    }
}
