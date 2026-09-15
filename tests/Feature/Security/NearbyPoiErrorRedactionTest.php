<?php

namespace Tests\Feature\Security;

use App\Contracts\PoiLookupAdapterInterface;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationDnaAudit;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\Providers\NearbyPoiFetcherFactory;
use App\Support\Google\GoogleHttpClientFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * A Google key is never PERSISTED in a Nearby Search error field — nor, re-checked here, in any
 * other Location DNA error field or log line.
 *
 * THE VECTOR
 * ----------
 * `GooglePlacesPoiAdapter::fetchNearby()` deliberately lets exceptions through, and the Places
 * key travels in the request's query string. Guzzle writes the whole URI into a 4xx/5xx
 * message and curl names the full URL in a timeout, so `LocationDnaPoiDistanceService` stored
 * the key in the `error` column of every failed category's POI row, and in the run's audit row
 * and output; the search-area lookup cached it for the full TTL. Each is now redacted with the
 * same {@see \App\Support\Google\GoogleProviderFailure::redact()} the logs and geocoding use.
 *
 * Nothing else about Nearby Search changes: a failed category is still an `error` row, a failed
 * run is still `failed`, a lookup error is still cached — only the stored text is sanitised.
 *
 * A fake credential — FAKE_TEST_GOOGLE_KEY_DO_NOT_USE — is the only key used. ZERO live Google
 * requests: the transport is a closure.
 */
class NearbyPoiErrorRedactionTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_KEY     = 'FAKE_TEST_GOOGLE_KEY_DO_NOT_USE';
    private const LISTING_TYPE = 'seller_agent_auction';
    private const LEAKY_URL    = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=27.95%2C-82.45&key=' . self::FAKE_KEY;

    /** How the fake transport answers: 'http403' or 'timeout'. */
    private string $transport = 'http403';

    /** @var list<RequestInterface> */
    private array $sent = [];

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google_places.enabled'           => true,
            // Headroom so a whole run's categories are sent and fail on the transport, rather
            // than being refused by the ceiling (the ceiling has its own suite).
            'google_places.hourly_limit'      => 1000,
            'google_places.daily_limit'       => 1000,
            'google_geocoding.enabled'        => true,
            'google_geocoding.hourly_limit'   => 25,
            'google_geocoding.daily_limit'    => 100,
            'services.google.places_key'      => self::FAKE_KEY,
            'location_dna.poi.tile_precision' => null,
        ]);

        Cache::flush();

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
                $this->sent[] = $request;

                return $this->transport === 'timeout'
                    ? Create::rejectionFor(new ConnectException(
                        'cURL error 28: Operation timed out after 10001 milliseconds for ' . $request->getUri(),
                        $request,
                    ))
                    : Create::promiseFor(new Response(403, [], '{"error_message":"denied"}'));
            }
        ));

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event;
        });
    }

    private function geocodedListing(int $id): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => $id,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.95,
            'geocoded_lng'   => -82.45,
        ]);
    }

    /** Per-category failures: the POI row's `error` column holds no key. @test */
    public function a_failed_nearby_request_never_persists_the_key_in_a_poi_row(): void
    {
        foreach (['http403' => 7601, 'timeout' => 7602] as $transport => $listingId) {
            $this->transport = $transport;
            $this->geocodedListing($listingId);

            (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, $listingId);

            $errors = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
                ->where('listing_id', $listingId)
                ->where('status', 'error')
                ->pluck('error');

            $this->assertNotEmpty($errors, "{$transport}: the failed categories are still recorded as error rows");

            foreach ($errors as $error) {
                $this->assertStringNotContainsString(self::FAKE_KEY, (string) $error);
                $this->assertStringContainsString('[redacted]', (string) $error, 'the URL was there, and was redacted');
            }
        }

        $this->assertNotEmpty($this->sent, 'the requests really went out — Nearby Search behaved as before');
    }

    /** A run-level failure: the output and its audit row hold no key. @test */
    public function a_run_level_failure_never_persists_the_key_in_the_output_or_audit(): void
    {
        $factory = $this->createMock(NearbyPoiFetcherFactory::class);
        $factory->method('make')->willThrowException(
            new RuntimeException('cURL error 7: Failed to connect for ' . self::LEAKY_URL)
        );
        $this->app->instance(NearbyPoiFetcherFactory::class, $factory);

        $this->geocodedListing(7603);

        $output = (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, 7603);

        $this->assertSame('failed', $output['status'], 'still a failed run');
        $this->assertStringNotContainsString(self::FAKE_KEY, (string) $output['error']);
        $this->assertStringContainsString('cURL error 7', (string) $output['error'], 'what happened survives');

        $audit = json_encode(PropertyLocationDnaAudit::where('listing_id', 7603)->get()->toArray());

        $this->assertStringContainsString('failed', (string) $audit, 'the audit row was written');
        $this->assertStringNotContainsString(self::FAKE_KEY, (string) $audit);
    }

    /** The search-area lookup still caches an adapter error — without the key. @test */
    public function the_search_area_lookup_never_caches_the_key(): void
    {
        $adapter = new class (self::LEAKY_URL) implements PoiLookupAdapterInterface {
            public function __construct(private string $url) {}

            public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array
            {
                throw new RuntimeException("Client error: `GET {$this->url}` resulted in a `403 Forbidden` response");
            }
        };

        $geometry   = ['type' => 'point', 'lat' => 27.95, 'lng' => -82.45];
        $categories = ['schools'];

        $result = (new PoiDistanceLookupService($adapter))->lookup($geometry, $categories);

        $this->assertStringStartsWith('Adapter error: ', (string) $result['error']);
        $this->assertStringNotContainsString(self::FAKE_KEY, (string) $result['error']);

        $cached = Cache::get('poi_lookup_' . sha1(serialize($geometry) . serialize($categories)));

        $this->assertNotNull($cached, 'the error is still cached, exactly as before');
        $this->assertStringNotContainsString(self::FAKE_KEY, (string) json_encode($cached));
    }

    /**
     * The key-leak audit across PERSISTED fields, not just logs: every Location DNA error
     * column and every log line, after Nearby Search AND Geocoding have failed.
     *
     * @test
     */
    public function no_persisted_error_field_or_log_line_carries_the_key(): void
    {
        foreach (['http403' => 7701, 'timeout' => 7702] as $transport => $listingId) {
            $this->transport = $transport;
            $this->geocodedListing($listingId);

            (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, $listingId);

            (new LocationDnaGeocodeService())->geocodeForListing(self::LISTING_TYPE, $listingId + 50, [
                'address' => '123 Main St',
                'city'    => 'Tampa',
                'state'   => 'FL',
            ]);
        }

        $persisted = json_encode([
            'pois'   => PropertyLocationPoi::whereIn('listing_id', [7701, 7702])->get()->toArray(),
            'dna'    => PropertyLocationDna::whereIn('listing_id', [7751, 7752])->get()->toArray(),
            'audits' => PropertyLocationDnaAudit::whereIn('listing_id', [7701, 7702, 7751, 7752])->get()->toArray(),
        ]);

        $this->assertStringContainsString('error', (string) $persisted, 'failures were persisted');
        $this->assertStringNotContainsString(self::FAKE_KEY, (string) $persisted, 'a persisted error field holds the key');

        $logs = (string) json_encode(array_map(
            static fn (MessageLogged $e) => [$e->level, $e->message, $e->context],
            $this->logged,
        ));

        $this->assertStringNotContainsString(self::FAKE_KEY, $logs, 'a log line holds the key');
        $this->assertDoesNotMatchRegularExpression('~maps/api/[a-z/]+json\?~', $logs, 'a credential-bearing URL reached the log');
    }
}
