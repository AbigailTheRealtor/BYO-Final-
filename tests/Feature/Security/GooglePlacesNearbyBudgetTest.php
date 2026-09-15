<?php

namespace Tests\Feature\Security;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Google\GoogleProviderAdmissionMiddleware as Admission;
use App\Support\Google\GoogleProviderRequestRefused;
use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Google Places Nearby Search cannot exceed its configured request ceilings — the shared
 * admission middleware on the one server-side Google HTTP client, proven end to end.
 *
 * WHAT THESE PROVE, AND HOW
 * -------------------------
 * The container client is the PRODUCTION stack ({@see GoogleHttpClientFactory}) over a fake
 * transport that records every request handed to it. "Refused before sending" is asserted by
 * counting what reached that transport, never by inspecting the guard: a ceiling that reports
 * "blocked" while the request still goes out has protected nothing.
 *
 * ZERO live Google requests. The transport is a closure, the key is a fake string, and nothing
 * here resolves Guzzle's network handler.
 */
class GooglePlacesNearbyBudgetTest extends TestCase
{
    use DatabaseTransactions;

    private const NEARBY       = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
    private const AUTOCOMPLETE = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';
    private const GEOCODE      = 'https://maps.googleapis.com/maps/api/geocode/json';

    /** @var list<RequestInterface> every request that reached the fake transport */
    private array $sent = [];

    private bool $transportFails = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            // Explicit opt-in: every request lands on the fake transport below.
            'google_places.enabled'      => true,
            'google_places.hourly_limit' => 25,
            'google_places.daily_limit'  => 100,
            'services.google.places_key' => 'fake-test-key',
        ]);

        Cache::flush();

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
                $this->sent[] = $request;

                if ($this->transportFails) {
                    return Create::rejectionFor(new ConnectException('transport down (test double)', $request));
                }

                return Create::promiseFor(new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    (string) json_encode(['status' => 'ZERO_RESULTS', 'results' => [], 'predictions' => []]),
                ));
            }
        ));
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    private function sendNearby(): void
    {
        app(ClientInterface::class)->request('GET', self::NEARBY, [
            'query' => ['location' => '27.7676,-82.6403', 'rankby' => 'distance', 'key' => 'fake-test-key'],
        ]);
    }

    /** @return array{hourly:int, daily:int} */
    private function spent(): array
    {
        return Admission::budgetFor(Admission::FAMILY_PLACES_NEARBY)->spent();
    }

    private function preSpend(int $units): void
    {
        $budget = Admission::budgetFor(Admission::FAMILY_PLACES_NEARBY);

        for ($i = 0; $i < $units; $i++) {
            $budget->recordRequest();
        }
    }

    private function assertRefusedBeforeSending(string $reasonFragment): void
    {
        $sentBefore = count($this->sent);

        try {
            $this->sendNearby();
            $this->fail('the request was expected to be refused');
        } catch (GoogleProviderRequestRefused $refused) {
            $this->assertSame(Admission::FAMILY_PLACES_NEARBY, $refused->family);
            $this->assertStringContainsString($reasonFragment, $refused->reason);
        }

        $this->assertCount($sentBefore, $this->sent, 'a refused request must never reach the transport');
    }

    private function geocodedListing(int $id): void
    {
        PropertyLocationDna::create([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => $id,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.95,
            'geocoded_lng'   => -82.45,
        ]);
    }

    /* ── A · B — switched off, malformed, no credential ─────────────────── */

    /** A — switched off: zero Nearby requests, at the chokepoint and through every caller. @test */
    public function places_disabled_sends_zero_nearby_requests(): void
    {
        config(['google_places.enabled' => false]);

        $this->assertRefusedBeforeSending(Admission::REASON_SWITCHED_OFF);
        $this->assertSame([], (new GooglePlacesPoiAdapter())->fetchNearby(27.95, -82.45, ['google_type' => 'school']));

        $this->geocodedListing(7101);
        $output = (new LocationDnaPoiDistanceService())->calculateForListing('seller_agent_auction', 7101);

        $this->assertSame('google_places_disabled', $output['error']);
        $this->assertCount(0, $this->sent);
    }

    /** B — only a real boolean true enables; a value that merely looks true is refused. @test */
    public function a_malformed_enable_value_is_refused(): void
    {
        foreach (['yes', 'on', '1', 1, 'true', 'banana'] as $value) {
            config(['google_places.enabled' => $value]);

            $this->assertRefusedBeforeSending(Admission::REASON_SWITCHED_OFF);
        }
    }

    /** No credential configured: zero Nearby requests. @test */
    public function a_missing_credential_sends_zero_nearby_requests(): void
    {
        config(['services.google.places_key' => '']);

        $this->assertRefusedBeforeSending(Admission::REASON_CREDENTIAL_MISSING);
    }

    /* ── D · E · F · G · H — units and exact ceilings ───────────────────── */

    /** D — one outbound Nearby request costs exactly one unit, in both windows. @test */
    public function one_nearby_request_costs_exactly_one_unit(): void
    {
        $this->sendNearby();

        $this->assertCount(1, $this->sent);
        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent());

        (new GooglePlacesPoiAdapter())->fetchNearby(27.95, -82.45, ['google_type' => 'school']);

        $this->assertCount(2, $this->sent);
        $this->assertSame(['hourly' => 2, 'daily' => 2], $this->spent());
    }

    /** E · F — the 25th request in an hour is sent; the 26th is refused before sending. @test */
    public function the_hourly_ceiling_is_exact(): void
    {
        $this->preSpend(24);

        $this->sendNearby();
        $this->assertCount(1, $this->sent, 'request 25 of 25 is sent');

        $this->assertRefusedBeforeSending('hourly_cap_reached');
        $this->assertSame(25, $this->spent()['hourly'], 'charged to the ceiling and not one unit beyond');
    }

    /** G · H — the 100th request in a day is sent; the 101st is refused before sending. @test */
    public function the_daily_ceiling_is_exact(): void
    {
        // Only the daily ceiling may decide here.
        config(['google_places.hourly_limit' => 1000]);

        $this->preSpend(99);

        $this->sendNearby();
        $this->assertCount(1, $this->sent, 'request 100 of 100 is sent');

        $this->assertRefusedBeforeSending('daily_cap_reached');
        $this->assertSame(100, $this->spent()['daily']);
    }

    /* ── I · J — retries and failures ───────────────────────────────────── */

    /** I — a retry is a new request: it needs its own admission and cannot ride the first. @test */
    public function a_retry_must_be_admitted_again(): void
    {
        $this->preSpend(24);
        $this->transportFails = true;

        try {
            $this->sendNearby();
            $this->fail('the transport was set to fail');
        } catch (ConnectException) {
            // Admitted, sent, failed — and charged.
        }

        $this->assertCount(1, $this->sent);
        $this->assertSame(25, $this->spent()['hourly']);

        $this->assertRefusedBeforeSending('hourly_cap_reached');
    }

    /** J — provider failures are charged deterministically: one unit per attempt sent. @test */
    public function provider_failures_are_charged_deterministically(): void
    {
        $this->transportFails = true;

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->sendNearby();
            } catch (ConnectException) {
                // The provider was reached; the attempt counts.
            }
        }

        $this->assertCount(3, $this->sent);
        $this->assertSame(['hourly' => 3, 'daily' => 3], $this->spent());
    }

    /* ── scope: what is and is not budgeted ─────────────────────────────── */

    /** Requests are classified by Google API family. @test */
    public function requests_are_classified_by_google_api_family(): void
    {
        $family = static fn (string $url): ?string => Admission::familyOf(new Request('GET', $url));

        $this->assertSame(Admission::FAMILY_PLACES_NEARBY, $family(self::NEARBY));
        $this->assertSame(Admission::FAMILY_PLACES_NEARBY, $family('https://places.googleapis.com/v1/places:searchNearby'));
        $this->assertSame(Admission::FAMILY_PLACES_AUTOCOMPLETE, $family(self::AUTOCOMPLETE));
        $this->assertSame(Admission::FAMILY_GEOCODING, $family(self::GEOCODE));
        $this->assertNull($family('https://api.bridgedataoutput.com/api/v2/OData/test/Property'));
    }

    /**
     * Autocomplete is identified but NOT budgeted — one request per keystroke on the listing
     * forms, so the Nearby numbers would break address entry. Geocoding IS budgeted now, on its
     * OWN allowance: an exhausted Nearby budget neither refuses it nor is charged for it
     * ({@see GoogleGeocodingBudgetTest} proves the Geocoding ceilings themselves). Pinned so
     * the Autocomplete gap stays a deliberate, visible one.
     *
     * @test
     */
    public function autocomplete_is_not_budgeted_and_geocoding_has_its_own_budget(): void
    {
        config([
            'google_places.hourly_limit'    => 1,
            'google_geocoding.enabled'      => true,
            'google_geocoding.hourly_limit' => 25,
            'google_geocoding.daily_limit'  => 100,
        ]);
        $this->preSpend(1); // the Nearby budget is exhausted

        $client = app(ClientInterface::class);
        $client->request('GET', self::AUTOCOMPLETE, ['query' => ['input' => '123 Main', 'key' => 'fake-test-key']]);
        $client->request('GET', self::GEOCODE, ['query' => ['address' => '123 Main St', 'key' => 'fake-test-key']]);

        $this->assertCount(2, $this->sent, 'an exhausted Nearby budget refuses neither');
        $this->assertSame(1, $this->spent()['hourly'], 'and neither costs the Nearby budget anything');
        $this->assertSame(
            ['hourly' => 1, 'daily' => 1],
            Admission::budgetFor(Admission::FAMILY_GEOCODING)->spent(),
            'the geocode was charged to its own budget'
        );

        $this->assertTrue(Admission::isBudgeted(Admission::FAMILY_PLACES_NEARBY));
        $this->assertTrue(Admission::isBudgeted(Admission::FAMILY_GEOCODING));
        $this->assertFalse(Admission::isBudgeted(Admission::FAMILY_PLACES_AUTOCOMPLETE));
    }

    /** A refused request is not an outbound request, so telemetry never counts one. @test */
    public function telemetry_counts_only_requests_that_were_sent(): void
    {
        $before = GoogleOutboundTelemetryMiddleware::counter();

        $this->sendNearby();
        $this->assertSame($before + 1, GoogleOutboundTelemetryMiddleware::counter());

        config(['google_places.hourly_limit' => 1]);
        $this->assertRefusedBeforeSending('hourly_cap_reached');

        $this->assertSame($before + 1, GoogleOutboundTelemetryMiddleware::counter(), 'the refused request was never sent');
    }

    /** The container binding builds this exact stack, with admission wrapping telemetry. @test */
    public function the_production_binding_puts_admission_outside_telemetry(): void
    {
        $this->app->forgetInstance(ClientInterface::class);

        $stack = app(ClientInterface::class)->getConfig('handler');

        $this->assertInstanceOf(HandlerStack::class, $stack);

        $described = (string) $stack;
        $admission = strpos($described, Admission::NAME);
        $telemetry = strpos($described, GoogleHttpClientFactory::TELEMETRY);

        $this->assertNotFalse($admission, 'the admission middleware is on the production stack');
        $this->assertNotFalse($telemetry, 'the telemetry middleware is still on the production stack');
        $this->assertLessThan($telemetry, $admission, 'admission must wrap telemetry');
    }

    /* ── callers: Location DNA and the search-area lookup ───────────────── */

    /**
     * Location DNA: exhaustion part-way through a run STOPS the run. No error rows are written
     * for categories it never reached — the next run's cache check would read them as current —
     * the run reports failure rather than completion, and nothing more is sent.
     *
     * @test
     */
    public function location_dna_stops_a_run_when_the_budget_runs_out(): void
    {
        config([
            'google_places.hourly_limit'      => 3,
            'location_dna.poi.tile_precision' => null,
        ]);

        $this->geocodedListing(7102);

        $output = (new LocationDnaPoiDistanceService())->calculateForListing('seller_agent_auction', 7102);

        $this->assertFalse($output['success']);
        $this->assertStringContainsString(Admission::FAMILY_PLACES_NEARBY, (string) $output['error']);
        $this->assertCount(3, $this->sent, 'exactly the three admitted requests went out');

        $errorRows = PropertyLocationPoi::where('listing_type', 'seller_agent_auction')
            ->where('listing_id', 7102)
            ->where('status', 'error')
            ->count();

        $this->assertSame(0, $errorRows, 'no category was recorded as an error it never reached');
    }

    /**
     * C · N — a cache hit sends nothing and costs nothing: a second listing at the same
     * coordinates is answered from the existing POI tile cache.
     *
     * @test
     */
    public function a_tile_cache_hit_costs_zero_units(): void
    {
        // The tile cache is opt-in (LOCATION_DNA_POI_TILE_PRECISION); switch it on here.
        config(['location_dna.poi.tile_precision' => 0.001]);

        $this->geocodedListing(7103);
        $this->geocodedListing(7104); // the same coordinates

        (new LocationDnaPoiDistanceService())->calculateForListing('seller_agent_auction', 7103);

        $firstRun        = count($this->sent);
        $spentAfterFirst = $this->spent();

        $this->assertGreaterThan(0, $firstRun);
        $this->assertSame($firstRun, $spentAfterFirst['hourly'], 'one unit per request sent');

        (new LocationDnaPoiDistanceService())->calculateForListing('seller_agent_auction', 7104);

        $this->assertCount($firstRun, $this->sent, 'the second listing was served from the tile cache');
        $this->assertSame($spentAfterFirst, $this->spent(), 'and charged nothing');
    }

    /**
     * The search-area lookup reports a refusal as the provider being unavailable — never as
     * "no POIs here" — and does not cache it, so the answer comes back once the window reopens.
     *
     * @test
     */
    public function the_search_area_lookup_reports_a_refusal_without_caching_it(): void
    {
        config(['google_places.hourly_limit' => 1]);
        $this->preSpend(1);

        $lookup   = new PoiDistanceLookupService(new GooglePlacesPoiAdapter());
        $geometry = ['type' => 'point', 'lat' => 27.95, 'lng' => -82.45];

        $refused = $lookup->lookup($geometry, ['schools']);

        $this->assertStringContainsString('Provider unavailable', (string) $refused['error']);
        $this->assertSame([], $refused['results']);
        $this->assertCount(0, $this->sent);

        config(['google_places.hourly_limit' => 25]); // the window reopens

        $answered = $lookup->lookup($geometry, ['schools']);

        $this->assertNull($answered['error'], 'the refusal was not cached');
        $this->assertCount(1, $this->sent);
    }

    /* ── K — real concurrency ───────────────────────────────────────────── */

    /**
     * K — the final-unit race with REAL concurrency: several PHP processes, the real file cache
     * store, the production client stack, all sending Nearby requests at once against one
     * ceiling. However they interleave, exactly the ceiling is sent — not one request more.
     *
     * @test
     */
    public function concurrent_processes_cannot_race_past_the_nearby_ceiling(): void
    {
        $dir = sys_get_temp_dir() . '/places-admission-race-' . bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);

        $script = $dir . '/racer.php';
        file_put_contents($script, <<<'PHP'
<?php
[, $base, $cacheDir, $mode, $cap, $attempts] = $argv;
require $base . '/vendor/autoload.php';

use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Google\GoogleProviderAdmissionMiddleware as Admission;
use App\Support\Google\GoogleProviderRequestRefused;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;

$container = new Illuminate\Container\Container();
Illuminate\Container\Container::setInstance($container);
$container->instance('config', new Illuminate\Config\Repository([
    'google_places' => ['enabled' => true, 'hourly_limit' => 100000, 'daily_limit' => (int) $cap],
    'services'      => ['google' => ['places_key' => 'fake-test-key']],
]));
$container->instance('cache', new Illuminate\Cache\Repository(
    new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(), $cacheDir)
));
Illuminate\Support\Facades\Facade::setFacadeApplication($container);

if ($mode === 'spent') {
    echo Admission::budgetFor(Admission::FAMILY_PLACES_NEARBY)->spent()['daily'];
    exit(0);
}

$sent   = 0;
$client = GoogleHttpClientFactory::make(static function () use (&$sent) {
    $sent++;

    return Create::promiseFor(new Response(200, [], '{"status":"ZERO_RESULTS","results":[]}'));
});

for ($i = 0; $i < (int) $attempts; $i++) {
    try {
        $client->request('GET', 'https://maps.googleapis.com/maps/api/place/nearbysearch/json');
    } catch (GoogleProviderRequestRefused) {
        // Refused before sending.
    }
}

echo $sent;
PHP);

        $php    = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $cap    = 20;
        $racers = [];

        try {
            // Four processes × 15 attempts = 60 attempts against a ceiling of 20.
            for ($i = 0; $i < 4; $i++) {
                $process = new Process([$php, $script, base_path(), $dir, 'race', (string) $cap, '15']);
                $process->setTimeout(120);
                $process->start();
                $racers[] = $process;
            }

            $sent = 0;

            foreach ($racers as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $sent += (int) trim($process->getOutput());
            }

            $spent = new Process([$php, $script, base_path(), $dir, 'spent', (string) $cap, '0']);
            $spent->mustRun();

            $this->assertSame($cap, $sent, 'sixty racing attempts send exactly the ceiling');
            $this->assertSame($cap, (int) trim($spent->getOutput()), 'and charge exactly the ceiling');
        } finally {
            (new Filesystem())->deleteDirectory($dir);
        }
    }
}
