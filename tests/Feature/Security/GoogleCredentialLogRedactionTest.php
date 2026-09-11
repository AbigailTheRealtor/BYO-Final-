<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Http\Livewire\TenantAgentAuction;
use App\Http\Livewire\TenantAgentAuctionEdit;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationDnaAudit;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Google\GoogleProviderFailure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * No Google credential reaches the application log — or a stored error — when a Google request
 * fails.
 *
 * THE VECTOR
 * ----------
 * Every server-side Google key travels in the request's query string, and Guzzle writes the
 * whole request URI into its exception messages (it redacts user-info, never the query). So a
 * catch that logs `$e->getMessage()` logs the key on the first 403, 500 or timeout. The first
 * test below proves the vector is real with the production client stack, so that the rest are
 * not asserting the absence of something that could never have been there.
 *
 * A fake credential — FAKE_TEST_GOOGLE_KEY_DO_NOT_USE — is the only key used. ZERO live Google
 * requests: the transport is a closure.
 */
class GoogleCredentialLogRedactionTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_KEY = 'FAKE_TEST_GOOGLE_KEY_DO_NOT_USE';
    private const GEOCODE  = 'https://maps.googleapis.com/maps/api/geocode/json';

    /** How the fake transport answers: 'http403' or 'timeout'. */
    private string $transport = 'http403';

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.places_key'    => self::FAKE_KEY,
            'google_geocoding.enabled'      => true,
            'google_geocoding.hourly_limit' => 25,
            'google_geocoding.daily_limit'  => 100,
            'google_places.enabled'         => true,
        ]);

        Cache::flush();

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
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

    /** The premise: Guzzle's own message carries the key — the leak these tests close is real. @test */
    public function guzzles_own_exception_message_carries_the_key(): void
    {
        try {
            app(ClientInterface::class)->request('GET', self::GEOCODE, [
                'query' => ['address' => '123 Main St', 'key' => self::FAKE_KEY],
            ]);
            $this->fail('a 403 is raised by the client');
        } catch (RequestException $e) {
            $this->assertStringContainsString(self::FAKE_KEY, $e->getMessage());
        }
    }

    /** @return array<string, array{0: class-string, 1: string, 2: array}> */
    public function callers(): array
    {
        $cases = [];

        foreach ([
            TenantAgentAuction::class,
            TenantAgentAuctionEdit::class,
            TenantOfferListing::class,
            TenantOfferListingEdit::class,
        ] as $class) {
            $cases[class_basename($class) . ' · geocode'] = [$class, 'getAddressDetailsFromApi', ['123 Main St, Tampa, FL']];
        }

        foreach ([
            TenantAgentAuction::class,
            TenantAgentAuctionEdit::class,
            TenantOfferListing::class,
            TenantOfferListingEdit::class,
            BuyerAgentAuction::class,
            BuyerOfferListing::class,
        ] as $class) {
            $cases[class_basename($class) . ' · autocomplete'] = [$class, 'getPlaceSuggestionsFromApi', ['123 Main', 'address']];
        }

        return $cases;
    }

    /**
     * @test
     * @dataProvider callers
     */
    public function a_failed_google_request_never_logs_the_key(string $class, string $method, array $args): void
    {
        foreach (['http403', 'timeout'] as $transport) {
            $this->transport = $transport;

            $component  = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $reflection = new ReflectionMethod($class, $method);
            $reflection->setAccessible(true);
            $reflection->invokeArgs($component, $args);
        }

        $this->assertNotEmpty($this->failureLines(), 'the failures were reported');
        $this->assertKeyNeverLogged();
    }

    /** Location DNA keeps an error message — redacted — on the row, in the audit and in its output. @test */
    public function location_dna_stores_and_returns_only_a_redacted_error(): void
    {
        foreach (['http403' => 8101, 'timeout' => 8102] as $transport => $listingId) {
            $this->transport = $transport;

            $output = (new LocationDnaGeocodeService())->geocodeForListing('seller_agent_auction', $listingId, [
                'address' => '123 Main St',
                'city'    => 'Tampa',
                'state'   => 'FL',
            ]);

            $this->assertSame('failed', $output['status']);
            $this->assertStringNotContainsString(self::FAKE_KEY, (string) $output['error']);
            $this->assertStringContainsString('[redacted]', (string) $output['error'], 'still says what happened');

            $record = PropertyLocationDna::where('listing_type', 'seller_agent_auction')->where('listing_id', $listingId)->firstOrFail();
            $this->assertStringNotContainsString(self::FAKE_KEY, (string) $record->geocode_error);
        }

        $audits = json_encode(PropertyLocationDnaAudit::whereIn('listing_id', [8101, 8102])->get()->toArray());

        $this->assertStringContainsString('failed', (string) $audits, 'the audit rows were written');
        $this->assertStringNotContainsString(self::FAKE_KEY, (string) $audits);
        $this->assertKeyNeverLogged();
    }

    /** @test */
    public function redact_strips_every_query_string_and_any_stray_key(): void
    {
        $message = 'Client error: `GET https://maps.googleapis.com/maps/api/geocode/json?address=1+Main&key='
            . self::FAKE_KEY . '` resulted in a `403 Forbidden` response; retry with key=' . self::FAKE_KEY;

        $redacted = GoogleProviderFailure::redact($message);

        $this->assertStringNotContainsString(self::FAKE_KEY, $redacted);
        $this->assertStringNotContainsString('address=1+Main', $redacted, 'the address is not kept either');
        $this->assertStringContainsString('https://maps.googleapis.com/maps/api/geocode/json?[redacted]', $redacted);
        $this->assertStringContainsString('403 Forbidden', $redacted, 'what happened survives');
    }

    /** The structured description carries no message at all. @test */
    public function the_failure_context_carries_no_message_or_url(): void
    {
        try {
            app(ClientInterface::class)->request('GET', self::GEOCODE, [
                'query' => ['address' => '123 Main St', 'key' => self::FAKE_KEY],
            ]);
        } catch (RequestException $e) {
            $context = GoogleProviderFailure::context($e, 'geocoding', 'test');

            $this->assertSame(403, $context['http_status']);
            $this->assertSame(GoogleProviderFailure::CATEGORY_HTTP_ERROR, $context['category']);
            $this->assertStringNotContainsString(self::FAKE_KEY, (string) json_encode($context));
            $this->assertStringNotContainsString('googleapis', (string) json_encode($context));
        }
    }

    /** @return list<array<string, mixed>> */
    private function failureLines(): array
    {
        return array_values(array_map(
            static fn (MessageLogged $e) => $e->context,
            array_filter($this->logged, static fn (MessageLogged $e) => $e->message === GoogleProviderFailure::EVENT),
        ));
    }

    private function assertKeyNeverLogged(): void
    {
        $everything = (string) json_encode(array_map(
            static fn (MessageLogged $e) => [$e->level, $e->message, $e->context],
            $this->logged,
        ));

        $this->assertStringNotContainsString(self::FAKE_KEY, $everything, 'the Google key reached the log');
        $this->assertDoesNotMatchRegularExpression('~maps/api/[a-z/]+json\?~', $everything, 'a credential-bearing URL reached the log');
    }
}
