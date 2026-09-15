<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Http\Livewire\TenantAgentAuction;
use App\Http\Livewire\TenantAgentAuctionEdit;
use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Google\GoogleProviderAdmissionMiddleware as Admission;
use App\Support\Google\GoogleProviderFailure;
use App\Support\Google\GoogleProviderRequestRefused;
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
use Tests\TestCase;

/**
 * The Tenant address pickers degrade — they never crash — when Google Geocoding cannot answer.
 *
 * Picking an address calls Google Geocoding to fill in city / state / ZIP / county. That call
 * can now be REFUSED before it is sent (switch off, ceiling reached, no credential), and it
 * could always time out or come back 4xx/5xx. The frozen Hire Agent components caught only
 * Guzzle's `RequestException`, so a refusal — a `RuntimeException` — or a connection failure
 * escaped and crashed the Livewire request; every picker logged `$e->getMessage()`, which carries
 * the request URL and so the key.
 *
 * For every picker and every failure this proves: no exception escapes the Livewire action; the
 * address the user picked is kept as picked; nothing is fabricated for the fields Google did not
 * supply; no validation error is raised against the address (the provider being unavailable says
 * nothing about the address); and the key never reaches the log.
 *
 * ZERO live Google requests: the container client is the production stack over a fake transport,
 * and the key is a fake string.
 */
class TenantGeocodingFailureSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_KEY = 'FAKE_TEST_GOOGLE_KEY_DO_NOT_USE';
    private const PICKED   = '123 Main St, Tampa, FL, USA';

    /** @var list<RequestInterface> every request that reached the fake transport */
    private array $sent = [];

    /** How the fake transport answers: 'timeout', 'http403' or 'http500'. */
    private string $transport = 'timeout';

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
        ]);

        Cache::flush();

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
                $this->sent[] = $request;

                return match ($this->transport) {
                    // What curl actually says: the full URL, query string and key included.
                    'timeout' => Create::rejectionFor(new ConnectException(
                        'cURL error 28: Operation timed out after 10001 milliseconds for ' . $request->getUri(),
                        $request,
                    )),
                    'http403' => Create::promiseFor(new Response(403, [], '{"error_message":"denied"}')),
                    default   => Create::promiseFor(new Response(500, [], 'upstream unavailable')),
                };
            }
        ));

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event;
        });
    }

    /** @return array<string, array{0: class-string}> */
    public function components(): array
    {
        return [
            'TenantAgentAuction'     => [TenantAgentAuction::class],
            'TenantAgentAuctionEdit' => [TenantAgentAuctionEdit::class],
            'TenantOfferListing'     => [TenantOfferListing::class],
            'TenantOfferListingEdit' => [TenantOfferListingEdit::class],
        ];
    }

    /**
     * Every picker × every way Geocoding can fail to answer.
     *
     * @return array<string, array{0: class-string, 1: string, 2: int, 3: string|null}>
     *         component, scenario, requests that reach the transport, logged category
     */
    public function failures(): array
    {
        $cases = [];

        foreach ($this->components() as $name => [$class]) {
            $cases["{$name} · geocoding disabled"]   = [$class, 'disabled', 0, GoogleProviderFailure::CATEGORY_REFUSED];
            $cases["{$name} · budget exhausted"]     = [$class, 'budget_exhausted', 0, GoogleProviderFailure::CATEGORY_REFUSED];
            $cases["{$name} · timeout"]              = [$class, 'timeout', 1, GoogleProviderFailure::CATEGORY_NETWORK];
            $cases["{$name} · Google 403"]           = [$class, 'http403', 1, GoogleProviderFailure::CATEGORY_HTTP_ERROR];
            $cases["{$name} · Google 500"]           = [$class, 'http500', 1, GoogleProviderFailure::CATEGORY_HTTP_ERROR];
        }

        // The Hire Agent pickers have no credential guard of their own; the chokepoint refuses.
        // The Offer Listing pickers return before building a request (no log line at all).
        $cases['TenantAgentAuction · no credential']     = [TenantAgentAuction::class, 'no_credential', 0, GoogleProviderFailure::CATEGORY_REFUSED];
        $cases['TenantAgentAuctionEdit · no credential'] = [TenantAgentAuctionEdit::class, 'no_credential', 0, GoogleProviderFailure::CATEGORY_REFUSED];
        $cases['TenantOfferListing · no credential']     = [TenantOfferListing::class, 'no_credential', 0, null];
        $cases['TenantOfferListingEdit · no credential'] = [TenantOfferListingEdit::class, 'no_credential', 0, null];

        return $cases;
    }

    /**
     * @test
     * @dataProvider failures
     */
    public function picking_an_address_degrades_instead_of_crashing(string $class, string $scenario, int $expectedSent, ?string $expectedCategory): void
    {
        $this->arrange($scenario);

        $component = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        // The public Livewire action a user triggers. Any escaping exception fails the test.
        $component->selectAddressSuggestion(self::PICKED);

        $this->assertSame(self::PICKED, $component->address, 'the address the user picked is kept as picked');

        foreach (['newCity', 'newCounty', 'state', 'zip_code'] as $field) {
            $this->assertSame('', $component->{$field}, "{$field} is left for the user — never fabricated");
        }

        $this->assertTrue(
            $component->getErrorBag()->isEmpty(),
            'a provider that could not answer is not an invalid address'
        );

        $this->assertCount($expectedSent, $this->sent);
        $this->assertKeyNeverLogged();

        $failures = $this->failureLines();

        if ($expectedCategory === null) {
            $this->assertSame([], $failures, 'no request was built, so there is nothing to report');

            return;
        }

        $this->assertCount(1, $failures, 'one structured line per failure');
        $this->assertSame($expectedCategory, $failures[0]['category']);
        $this->assertSame('geocoding', $failures[0]['family']);
        $this->assertSame('tenant_address_details', $failures[0]['operation']);
    }

    /** The refusal is exactly what the old `catch (RequestException)` let escape. @test */
    public function a_refusal_is_not_a_request_exception(): void
    {
        $this->assertFalse(is_subclass_of(GoogleProviderRequestRefused::class, RequestException::class));
        $this->assertFalse(is_subclass_of(ConnectException::class, RequestException::class));
    }

    /**
     * @test
     * @dataProvider components
     */
    public function the_http_status_is_reported_without_the_url(string $class): void
    {
        $this->arrange('http403');

        (new ReflectionClass($class))->newInstanceWithoutConstructor()->selectAddressSuggestion(self::PICKED);

        $failure = $this->failureLines()[0];

        $this->assertSame(403, $failure['http_status']);
        $this->assertTrue(is_a($failure['exception'], RequestException::class, true));
        $this->assertKeyNeverLogged();
    }

    private function arrange(string $scenario): void
    {
        match ($scenario) {
            'disabled'         => config(['google_geocoding.enabled' => false]),
            'no_credential'    => config(['services.google.places_key' => '']),
            'budget_exhausted' => (function () {
                $budget = Admission::budgetFor(Admission::FAMILY_GEOCODING);
                for ($i = 0; $i < 25; $i++) {
                    $budget->recordRequest();
                }
            })(),
            default            => $this->transport = $scenario,
        };
    }

    /** @return list<array<string, mixed>> the context of every google_provider_failure line */
    private function failureLines(): array
    {
        return array_values(array_map(
            static fn (MessageLogged $e) => $e->context,
            array_filter($this->logged, static fn (MessageLogged $e) => $e->message === GoogleProviderFailure::EVENT),
        ));
    }

    private function assertKeyNeverLogged(): void
    {
        $everything = json_encode(array_map(
            static fn (MessageLogged $e) => [$e->level, $e->message, $e->context],
            $this->logged,
        ));

        $this->assertStringNotContainsString(self::FAKE_KEY, (string) $everything, 'the Google key reached the log');
        $this->assertStringNotContainsString('geocode/json?', (string) $everything, 'a credential-bearing URL reached the log');
    }
}
