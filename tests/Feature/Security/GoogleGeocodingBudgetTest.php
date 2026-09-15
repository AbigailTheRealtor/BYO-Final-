<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\TenantAgentAuction;
use App\Models\PropertyLocationDna;
use App\Models\User;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Google\GoogleProviderAdmissionMiddleware as Admission;
use App\Support\Google\GoogleProviderRequestRefused;
use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Google Geocoding cannot exceed its configured request ceilings, and cannot run at all
 * unless its own switch is on — the shared admission middleware on the one server-side
 * Google HTTP client, proven end to end.
 *
 * WHAT THESE PROVE, AND HOW
 * -------------------------
 * The container client is the PRODUCTION stack ({@see GoogleHttpClientFactory}) over a fake
 * transport that records every request handed to it. "Refused before sending" is asserted by
 * counting what reached that transport, never by inspecting the guard.
 *
 * Geocoding and Nearby Search are two budgets on the one {@see \App\Services\Location\Coordinates\Guards\ProviderRequestBudget};
 * the independence cases spend one to its ceiling and prove the other is untouched.
 *
 * ZERO live Google requests. The transport is a closure and the key is a fake string.
 */
class GoogleGeocodingBudgetTest extends TestCase
{
    use DatabaseTransactions;

    private const GEOCODE = 'https://maps.googleapis.com/maps/api/geocode/json';
    private const NEARBY  = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    private const LISTING_TYPE = 'seller_agent_auction';

    private const ADDRESS = [
        'address' => '123 Main St',
        'city'    => 'Tampa',
        'state'   => 'FL',
        'county'  => 'Hillsborough',
        'zip'     => '33602',
    ];

    /** @var list<RequestInterface> every request that reached the fake transport */
    private array $sent = [];

    /** How the fake transport answers: 'ok', 'connect' or 'http500'. */
    private string $transport = 'ok';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google_geocoding.enabled'      => true,
            'google_geocoding.hourly_limit' => 25,
            'google_geocoding.daily_limit'  => 100,
            // Nearby Search, for the independence cases — and Location DNA's geocode step
            // requires this switch too, as it always has.
            'google_places.enabled'         => true,
            'google_places.hourly_limit'    => 25,
            'google_places.daily_limit'     => 100,
            'services.google.places_key'    => 'fake-test-key',
        ]);

        Cache::flush();

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
                $this->sent[] = $request;

                return match ($this->transport) {
                    'connect' => Create::rejectionFor(new ConnectException('transport down (test double)', $request)),
                    'http500' => Create::promiseFor(new Response(500, [], 'upstream unavailable')),
                    default   => Create::promiseFor(new Response(
                        200,
                        ['Content-Type' => 'application/json'],
                        (string) json_encode([
                            'status'  => 'OK',
                            'results' => [[
                                'geometry'           => ['location' => ['lat' => 27.9506, 'lng' => -82.4572]],
                                'place_id'           => 'ChIJ-test',
                                'formatted_address'  => '123 Main St, Tampa, FL 33602, USA',
                                'address_components' => [],
                            ]],
                            'predictions' => [],
                        ]),
                    )),
                };
            }
        ));
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    private function sendGeocode(): void
    {
        app(ClientInterface::class)->request('GET', self::GEOCODE, [
            'query' => ['address' => '123 Main St, Tampa, FL', 'key' => 'fake-test-key'],
        ]);
    }

    private function sendNearby(): void
    {
        app(ClientInterface::class)->request('GET', self::NEARBY, [
            'query' => ['location' => '27.95,-82.45', 'rankby' => 'distance', 'key' => 'fake-test-key'],
        ]);
    }

    /** @return array{hourly:int, daily:int} */
    private function spent(string $family = Admission::FAMILY_GEOCODING): array
    {
        return Admission::budgetFor($family)->spent();
    }

    private function preSpend(int $units, string $family = Admission::FAMILY_GEOCODING): void
    {
        $budget = Admission::budgetFor($family);

        for ($i = 0; $i < $units; $i++) {
            $budget->recordRequest();
        }
    }

    private function assertGeocodeRefusedBeforeSending(string $reasonFragment): void
    {
        $sentBefore = count($this->sent);

        try {
            $this->sendGeocode();
            $this->fail('the geocode was expected to be refused');
        } catch (GoogleProviderRequestRefused $refused) {
            $this->assertSame(Admission::FAMILY_GEOCODING, $refused->family);
            $this->assertStringContainsString($reasonFragment, $refused->reason);
        }

        $this->assertCount($sentBefore, $this->sent, 'a refused request must never reach the transport');
    }

    private function geocodeListing(int $id, array $extra = []): array
    {
        return (new LocationDnaGeocodeService())->geocodeForListing(self::LISTING_TYPE, $id, self::ADDRESS + $extra);
    }

    private function tenantAddressDetails(): mixed
    {
        $component = (new ReflectionClass(TenantAgentAuction::class))->newInstanceWithoutConstructor();
        $method    = new ReflectionMethod(TenantAgentAuction::class, 'getAddressDetailsFromApi');
        $method->setAccessible(true);

        return $method->invoke($component, '123 Main St, Tampa, FL');
    }

    /* ── A–F — switched off, unset, malformed, key without switch ───────── */

    /** A · B · C · D · E — only a real boolean true enables; everything else sends nothing. @test */
    public function a_switched_off_unset_or_malformed_value_sends_zero_geocoding_requests(): void
    {
        foreach ([false, null, 0, '0', '', 'false', 'off', 'no', 'banana', 'true', 'yes', 'on', '1', 1] as $value) {
            config(['google_geocoding.enabled' => $value]);

            $this->assertGeocodeRefusedBeforeSending(Admission::REASON_SWITCHED_OFF);
        }

        // B — the whole config absent (a config file that did not load) reads as off.
        config(['google_geocoding' => []]);
        $this->assertGeocodeRefusedBeforeSending(Admission::REASON_SWITCHED_OFF);

        $this->assertCount(0, $this->sent);
        $this->assertSame(['hourly' => 0, 'daily' => 0], $this->spent(), 'a refusal costs nothing');
    }

    /** F — a present key with the switch off: zero requests from every geocoding caller. @test */
    public function a_present_key_with_the_switch_off_sends_zero_geocoding_requests_from_any_caller(): void
    {
        config(['google_geocoding.enabled' => false]);

        $this->assertGeocodeRefusedBeforeSending(Admission::REASON_SWITCHED_OFF);

        $output = $this->geocodeListing(9101);
        $this->assertSame('skipped', $output['status']);
        $this->assertSame('non_google_geocoder_unavailable', $output['error']);

        $this->assertSame([], $this->tenantAddressDetails(), 'the tenant picker degrades to no details');

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('GOOGLE_GEOCODING_ENABLED is false. Refusing to geocode: no Google request will be made.')
            ->assertExitCode(1);

        $this->assertCount(0, $this->sent, 'no caller reached Google');
    }

    /** A missing credential is refused at the chokepoint too. @test */
    public function a_missing_credential_sends_zero_geocoding_requests(): void
    {
        config(['services.google.places_key' => '']);

        $this->assertGeocodeRefusedBeforeSending(Admission::REASON_CREDENTIAL_MISSING);
    }

    /** A zero or malformed ceiling blocks Geocoding rather than unleashing it. @test */
    public function a_zero_ceiling_blocks_geocoding(): void
    {
        config(['google_geocoding.hourly_limit' => 0]);

        $this->assertGeocodeRefusedBeforeSending('hourly_cap_reached');
    }

    /* ── G · H · I · J — exact ceilings ─────────────────────────────────── */

    /** One outbound Geocoding request costs exactly one unit, in both windows. @test */
    public function one_geocoding_request_costs_exactly_one_unit(): void
    {
        $this->sendGeocode();

        $this->assertCount(1, $this->sent);
        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent());
    }

    /** G · H — the 25th request in an hour is sent; the 26th is refused before sending. @test */
    public function the_hourly_ceiling_is_exact(): void
    {
        $this->preSpend(24);

        $this->sendGeocode();
        $this->assertCount(1, $this->sent, 'request 25 of 25 is sent');

        $this->assertGeocodeRefusedBeforeSending('hourly_cap_reached');
        $this->assertSame(25, $this->spent()['hourly'], 'charged to the ceiling and not one unit beyond');
    }

    /** I · J — the 100th request in a day is sent; the 101st is refused before sending. @test */
    public function the_daily_ceiling_is_exact(): void
    {
        config(['google_geocoding.hourly_limit' => 1000]); // only the daily ceiling may decide

        $this->preSpend(99);

        $this->sendGeocode();
        $this->assertCount(1, $this->sent, 'request 100 of 100 is sent');

        $this->assertGeocodeRefusedBeforeSending('daily_cap_reached');
        $this->assertSame(100, $this->spent()['daily']);
    }

    /** The hourly ceiling holds through the real callers, not just a raw request. @test */
    public function the_ceiling_holds_through_location_dna(): void
    {
        $this->preSpend(24);

        $this->assertSame('geocoded', $this->geocodeListing(9201)['status'], 'unit 25 is admitted');

        $refused = $this->geocodeListing(9202);

        $this->assertSame('skipped', $refused['status']);
        $this->assertCount(1, $this->sent);
        $this->assertSame(25, $this->spent()['hourly']);
    }

    /* ── K — real concurrency ───────────────────────────────────────────── */

    /**
     * K — several PHP processes, the real file cache store, the production client stack, all
     * geocoding at once against one ceiling: exactly the ceiling is sent, not one more.
     *
     * @test
     */
    public function concurrent_processes_cannot_race_past_the_geocoding_ceiling(): void
    {
        $dir = sys_get_temp_dir() . '/geocoding-admission-race-' . bin2hex(random_bytes(6));
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
    'google_geocoding' => ['enabled' => true, 'hourly_limit' => 100000, 'daily_limit' => (int) $cap],
    'services'         => ['google' => ['places_key' => 'fake-test-key']],
]));
$container->instance('cache', new Illuminate\Cache\Repository(
    new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(), $cacheDir)
));
Illuminate\Support\Facades\Facade::setFacadeApplication($container);

if ($mode === 'spent') {
    echo Admission::budgetFor(Admission::FAMILY_GEOCODING)->spent()['daily'];
    exit(0);
}

$sent   = 0;
$client = GoogleHttpClientFactory::make(static function () use (&$sent) {
    $sent++;

    return Create::promiseFor(new Response(200, [], '{"status":"ZERO_RESULTS","results":[]}'));
});

for ($i = 0; $i < (int) $attempts; $i++) {
    try {
        $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json');
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

    /* ── L · M · N — two independent budgets ────────────────────────────── */

    /** L — the two families are separate budgets on the shared ProviderRequestBudget. @test */
    public function geocoding_and_nearby_are_independent_budgets(): void
    {
        $this->sendGeocode();

        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent(Admission::FAMILY_GEOCODING));
        $this->assertSame(['hourly' => 0, 'daily' => 0], $this->spent(Admission::FAMILY_PLACES_NEARBY));

        $this->sendNearby();

        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent(Admission::FAMILY_GEOCODING));
        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent(Admission::FAMILY_PLACES_NEARBY));
    }

    /** M — an exhausted Nearby budget neither refuses Geocoding nor is charged for it. @test */
    public function nearby_exhaustion_does_not_consume_the_geocoding_budget(): void
    {
        $this->preSpend(25, Admission::FAMILY_PLACES_NEARBY);

        try {
            $this->sendNearby();
            $this->fail('Nearby Search was expected to be refused');
        } catch (GoogleProviderRequestRefused $refused) {
            $this->assertSame(Admission::FAMILY_PLACES_NEARBY, $refused->family);
        }

        $this->sendGeocode();

        $this->assertCount(1, $this->sent, 'only the geocode went out');
        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent(Admission::FAMILY_GEOCODING));
        $this->assertSame(25, $this->spent(Admission::FAMILY_PLACES_NEARBY)['hourly']);
    }

    /** N — an exhausted Geocoding budget neither refuses Nearby Search nor is charged for it. @test */
    public function geocoding_exhaustion_does_not_consume_the_nearby_budget(): void
    {
        $this->preSpend(25, Admission::FAMILY_GEOCODING);

        $this->assertGeocodeRefusedBeforeSending('hourly_cap_reached');

        $this->sendNearby();

        $this->assertCount(1, $this->sent, 'only the Nearby request went out');
        $this->assertSame(['hourly' => 1, 'daily' => 1], $this->spent(Admission::FAMILY_PLACES_NEARBY));
        $this->assertSame(25, $this->spent(Admission::FAMILY_GEOCODING)['hourly']);
    }

    /* ── O — known coordinates cost nothing ─────────────────────────────── */

    /** O — pre-supplied coordinates and an unchanged cached geocode send nothing and cost nothing. @test */
    public function known_coordinates_and_cached_geocodes_cost_zero_units(): void
    {
        $withCoordinates = $this->geocodeListing(9301, ['pre_lat' => '27.95', 'pre_lng' => '-82.45']);

        $this->assertSame('geocoded', $withCoordinates['status']);
        $this->assertSame('saved_meta', $withCoordinates['source']);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => 9302,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.95,
            'geocoded_lng'   => -82.45,
            'source_address' => self::ADDRESS['address'],
            'source_city'    => self::ADDRESS['city'],
            'source_state'   => self::ADDRESS['state'],
            'source_county'  => self::ADDRESS['county'],
            'source_zip'     => self::ADDRESS['zip'],
        ]);

        $cached = $this->geocodeListing(9302);

        $this->assertSame('geocoded', $cached['status']);
        $this->assertCount(0, $this->sent);
        $this->assertSame(['hourly' => 0, 'daily' => 0], $this->spent());
    }

    /* ── P · Q — failures and refusals ──────────────────────────────────── */

    /** P — a request that was sent and failed is charged; each attempt is one unit, deterministically. @test */
    public function provider_failures_after_sending_are_charged_deterministically(): void
    {
        $this->transport = 'connect';

        for ($i = 0; $i < 2; $i++) {
            try {
                $this->sendGeocode();
                $this->fail('the transport was set to fail');
            } catch (ConnectException) {
                // Admitted, sent, failed — and charged.
            }
        }

        $this->transport = 'http500';

        try {
            $this->sendGeocode();
            $this->fail('a 500 is raised by the client');
        } catch (RequestException) {
            // Google answered with an error — the request was sent.
        }

        $this->assertCount(3, $this->sent);
        $this->assertSame(['hourly' => 3, 'daily' => 3], $this->spent());

        $this->transport = 'connect';
        $output          = $this->geocodeListing(9401);

        $this->assertSame('failed', $output['status'], 'a sent request that failed is a failure, not a refusal');
        $this->assertSame(4, $this->spent()['hourly']);
    }

    /** A retry is a new request: at the ceiling it is refused, not ridden on the first. @test */
    public function a_retry_must_be_admitted_again(): void
    {
        $this->preSpend(24);
        $this->transport = 'connect';

        try {
            $this->sendGeocode();
        } catch (ConnectException) {
            // Unit 25 — sent and failed.
        }

        $this->assertGeocodeRefusedBeforeSending('hourly_cap_reached');
        $this->assertCount(1, $this->sent);
    }

    /**
     * Q — a refusal sends nothing, and Location DNA records it as SKIPPED — never as a lookup
     * that found nothing, never cached — so the next run, once the window reopens, geocodes.
     *
     * @test
     */
    public function a_refusal_is_skipped_not_cached_and_retried_when_the_window_reopens(): void
    {
        $this->preSpend(25);

        $refused = $this->geocodeListing(9501);

        $this->assertSame('skipped', $refused['status']);
        $this->assertFalse($refused['success']);
        $this->assertNull($refused['lat']);
        $this->assertStringStartsWith('google_geocoding_refused: ', (string) $refused['error']);
        $this->assertStringContainsString('hourly_cap_reached', (string) $refused['error']);
        $this->assertCount(0, $this->sent, 'no provider request');

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)->where('listing_id', 9501)->firstOrFail();

        $this->assertSame('skipped', $record->geocode_status);
        $this->assertNull($record->geocoded_lat);

        config(['google_geocoding.hourly_limit' => 26]); // the window reopens

        $answered = $this->geocodeListing(9501);

        $this->assertSame('geocoded', $answered['status'], 'the refusal was not cached as an answer');
        $this->assertCount(1, $this->sent);
    }

    /** A refused geocode never went out, so telemetry never counts one. @test */
    public function telemetry_counts_only_geocodes_that_were_sent(): void
    {
        $before = GoogleOutboundTelemetryMiddleware::counter();

        $this->sendGeocode();
        $this->assertSame($before + 1, GoogleOutboundTelemetryMiddleware::counter());

        config(['google_geocoding.hourly_limit' => 1]);
        $this->assertGeocodeRefusedBeforeSending('hourly_cap_reached');

        $this->assertSame($before + 1, GoogleOutboundTelemetryMiddleware::counter());
    }

    /* ── the backfill command at the ceiling ────────────────────────────── */

    /**
     * The command stops at the first refusal: it reports the budget truthfully, says how many
     * rows it never processed, exits non-zero, and does not loop through refusals.
     *
     * @test
     */
    public function the_backfill_command_stops_at_the_ceiling_and_reports_what_it_did_not_process(): void
    {
        $user = User::factory()->create();

        $ids = [];
        foreach (['1 First St, Tampa, FL', '2 Second St, Tampa, FL', '3 Third St, Tampa, FL'] as $address) {
            // DB::table, not Eloquent: no observer, so no Location DNA dispatch on insert.
            $id = DB::table('property_auctions')->insertGetId([
                'user_id'      => $user->id,
                'is_approved'  => true,
                'sold'         => false,
                'auction_type' => 'Traditional Listing',
                'title'        => 'Geocode budget fixture',
                'address'      => $address,
                'city_id'      => 1,
                'state_id'     => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('property_auction_metas')->insert([
                'property_auction_id' => $id,
                'meta_key'            => 'address',
                'meta_value'          => $address,
            ]);

            $ids[] = $id;
        }

        $this->preSpend(24); // one unit left in this hour

        // The one admitted request is sent and Google answers 500: a real, charged failure
        // (counted as `failed`, nothing written). The next row is refused before sending.
        $this->transport = 'http500';

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('  Google Geocoding request budget reached (geocoding_provider_hourly_cap_reached). Stopping: no further Google request will be made.')
            ->expectsOutput('Stopped — updated: 0, skipped: 0, failed: 1, not processed: 2')
            ->assertExitCode(1);

        $this->assertCount(1, $this->sent, 'exactly the one admitted request went out — no loop through refusals');
        $this->assertSame(25, $this->spent()['hourly'], 'and the ceiling was not exceeded');

        $geocoded = DB::table('property_auction_metas')
            ->whereIn('property_auction_id', $ids)
            ->where('meta_key', 'property_lat')
            ->count();

        $this->assertSame(0, $geocoded, 'no row is pretended to be geocoded');
    }
}
